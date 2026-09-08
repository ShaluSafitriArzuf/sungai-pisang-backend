<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Akomodasi;
use App\Models\Notifikasi;
use App\Models\PesertaReservasi;
use App\Models\Pulau;
use App\Models\Reservasi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReservasiController extends Controller
{
    // Daftar tanggal per-malam antara check-in (inklusif) dan check-out (eksklusif).
    // Contoh: check-in 1 Jan, check-out 3 Jan -> ['2025-01-01', '2025-01-02'] (2 malam).
    private function daftarTanggalMalam(string $checkin, string $checkout): array
    {
        $tanggal = [];
        $cursor = Carbon::parse($checkin);
        $akhir = Carbon::parse($checkout);

        while ($cursor->lt($akhir)) {
            $tanggal[] = $cursor->format('Y-m-d');
            $cursor->addDay();
        }

        return $tanggal;
    }

    // Reservasi VALID yang tanggal kunjungannya (atau tanggal_selesai kalau menginap) sudah
    // lewat, otomatis ditandai 'selesai' setiap kali ada yang membuka daftar reservasi — jadi
    // tidak butuh cron/scheduler terpisah yang harus dinyalakan manual di komputer lokal.
    // Begitu selesai, wisatawan dikirim notifikasi ajakan kasih ulasan (syarat ulasan: status selesai).
    private function tandaiReservasiLewatJadwalSelesai(): void
    {
        // Pengajuan pembatalan yang sudah lewat batas H-2 tapi belum sempat ditanggapi
        // Pengantar Pulau otomatis DITOLAK, supaya reservasi tidak menggantung di status
        // 'pengajuan_batal' sampai hari keberangkatan. Aturannya sama persis dengan syarat
        // pengajuan di Reservasi::bisaAjukanBatal() — begitu sisa hari ke tanggal kunjungan
        // kurang dari 2, pengajuan dianggap kedaluwarsa dan reservasi kembali VALID.
        $this->tolakOtomatisPengajuanBatalKedaluwarsa();

        // Reservasi yang tanggalnya sudah lewat tapi bukti transfernya belum sempat
        // diverifikasi -- wisatawannya dikabari, tapi statusnya TIDAK diubah otomatis.
        $this->kabariReservasiTerlewatBelumDiverifikasi();

        // Pencegahan dari hulu: ingatkan Pengantar Pulau kalau masih ada yang belum
        // diverifikasi padahal keberangkatannya besok.
        $this->ingatkanPengantarVerifikasiBesok();

        Reservasi::where('status', 'valid')
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('jenis', 'one_day_trip')->whereDate('tanggal_kunjungan', '<', now()->toDateString());
                })->orWhere(function ($q2) {
                    $q2->where('jenis', 'menginap')->whereDate('tanggal_selesai', '<', now()->toDateString());
                });
            })
            ->with('pulau')
            ->get()
            ->each(function ($r) {
                $r->update(['status' => 'selesai']);

                Notifikasi::create([
                    'user_id' => $r->user_id,
                    'reservasi_id' => $r->id,
                    'judul' => 'Perjalanan Selesai',
                    'pesan' => "Kunjunganmu ke {$r->pulau->nama} sudah selesai. Yuk beri ulasan pengalamanmu!",
                    'tipe' => 'reservasi_selesai',
                ]);
            });
    }

    // Pengajuan pembatalan kedaluwarsa (sudah masuk H-2 atau lewat) otomatis ditolak sistem.
    // batasTanggal = tanggal_kunjungan yang jaraknya tinggal < 2 hari dari hari ini, yaitu
    // semua reservasi dengan tanggal_kunjungan <= hari ini + 1 hari.
    private function tolakOtomatisPengajuanBatalKedaluwarsa(): void
    {
        $batasTanggal = now()->addDay()->toDateString();

        Reservasi::where('status', 'pengajuan_batal')
            ->whereDate('tanggal_kunjungan', '<=', $batasTanggal)
            ->with('pulau')
            ->get()
            ->each(function ($r) {
                $r->update([
                    'status' => 'valid',
                    'status_pengembalian_dana' => null,
                ]);

                Notifikasi::create([
                    'user_id' => $r->user_id,
                    'reservasi_id' => $r->id,
                    'judul' => 'Pengajuan Pembatalan Kedaluwarsa',
                    'pesan' => "Pengajuan pembatalan untuk kunjungan ke {$r->pulau->nama} otomatis ditolak karena sudah melewati batas H-2 sebelum tanggal kunjungan. Reservasi Anda tetap berjalan sesuai jadwal.",
                    'tipe' => 'pembatalan_ditolak',
                ]);
            });
    }

    // Reservasi yang masih 'menunggu_verifikasi' padahal tanggal kunjungannya sudah lewat.
    //
    // Statusnya SENGAJA tidak diubah otomatis. Uang wisatawan sudah masuk lewat transfer,
    // jadi kalau sistem membatalkannya sendiri berarti sistem ikut memutuskan soal
    // pengembalian dana tanpa ada manusia yang bertanggung jawab. Itu bertentangan dengan
    // aturan lain di sistem ini: menolak reservasi wajib mengisi catatan penolakan, dan
    // menyetujui pembatalan wajib memilih status pengembalian dana. Keputusannya tetap
    // milik Pengantar Pulau lewat tombol Valid/Tolak yang sudah ada.
    //
    // Yang dikerjakan sistem cuma satu: memberi tahu wisatawan SEKALI, lengkap dengan nomor
    // HP Pengantar Pulau, supaya dia tidak menunggu tanpa kabar dan tahu harus menghubungi
    // siapa. Dicek dari notifikasi bertipe 'reservasi_terlewat' yang sudah pernah dibuat
    // untuk reservasi tersebut, supaya tidak dikirim ulang tiap kali daftar dibuka.
    private function kabariReservasiTerlewatBelumDiverifikasi(): void
    {
        $terlewat = Reservasi::where('status', 'menunggu_verifikasi')
            ->whereDate('tanggal_kunjungan', '<', now()->toDateString())
            ->with('pulau')
            ->get();

        if ($terlewat->isEmpty()) {
            return;
        }

        $sudahDikabari = Notifikasi::where('tipe', 'reservasi_terlewat')
            ->whereIn('reservasi_id', $terlewat->pluck('id'))
            ->pluck('reservasi_id')
            ->all();

        // Nomor HP diambil dari akun Pengantar Pulau, bukan ditulis mati di sini, supaya
        // kalau nomornya berganti cukup diubah lewat halaman Edit Profil.
        $noHp = User::where('role', 'pengantar_pulau')->value('no_hp');

        $ajakanHubungi = $noHp
            ? "Silakan hubungi Pengantar Pulau di {$noHp} untuk menanyakan kelanjutan dan pengembalian dana."
            : 'Silakan hubungi Pengantar Pulau untuk menanyakan kelanjutan dan pengembalian dana.';

        foreach ($terlewat as $r) {
            if (in_array($r->id, $sudahDikabari, true)) {
                continue;
            }

            Notifikasi::create([
                'user_id' => $r->user_id,
                'reservasi_id' => $r->id,
                'judul' => 'Reservasi Terlewat, Belum Diverifikasi',
                'pesan' => "Tanggal kunjungan ke {$r->pulau->nama} sudah terlewat, tetapi bukti transfermu belum sempat diverifikasi. {$ajakanHubungi}",
                'tipe' => 'reservasi_terlewat',
            ]);
        }
    }

    // Pengingat buat Pengantar Pulau: masih ada reservasi yang belum diverifikasi padahal
    // keberangkatannya BESOK. Ini pencegahan dari hulu -- kalau verifikasi dikerjakan tepat
    // waktu, kasus "terlewat belum diverifikasi" di atas tidak akan terjadi.
    //
    // Sengaja dikirim SATU notifikasi rangkuman per hari, bukan satu per reservasi, supaya
    // lonceng notifikasi Pengantar Pulau tidak dibanjiri kalau reservasinya banyak.
    private function ingatkanPengantarVerifikasiBesok(): void
    {
        $besok = now()->addDay()->toDateString();

        $jumlah = Reservasi::where('status', 'menunggu_verifikasi')
            ->whereDate('tanggal_kunjungan', $besok)
            ->count();

        if ($jumlah === 0) {
            return;
        }

        $pengantar = User::where('role', 'pengantar_pulau')->first();

        if (! $pengantar) {
            return;
        }

        // Cukup sekali sehari. Kalau siangnya ada reservasi baru untuk besok, pengingatnya
        // baru muncul lagi besok pagi -- itu tidak masalah, karena batas pemesanan paling
        // lambat H-1 memang sudah dijaga aturan tersendiri saat reservasi dibuat.
        $sudahDikirimHariIni = Notifikasi::where('user_id', $pengantar->id)
            ->where('tipe', 'pengingat_verifikasi')
            ->whereDate('created_at', now()->toDateString())
            ->exists();

        if ($sudahDikirimHariIni) {
            return;
        }

        Notifikasi::create([
            'user_id' => $pengantar->id,
            'reservasi_id' => null,
            'judul' => 'Pengingat: Verifikasi Keberangkatan Besok',
            'pesan' => "Ada {$jumlah} reservasi untuk keberangkatan besok yang bukti transfernya belum diverifikasi. Mohon diperiksa hari ini supaya manifest keberangkatan dapat disusun tepat waktu.",
            'tipe' => 'pengingat_verifikasi',
        ]);
    }

    // GET /api/reservasi — daftar reservasi, difilter sesuai role yang login
    public function index(Request $request)
    {
        $this->tandaiReservasiLewatJadwalSelesai();

        $user = $request->user();

        if ($user->isWisatawan()) {
            $query = Reservasi::where('user_id', $user->id);
        } elseif ($user->isPengantarPulau()) {
            // Pengantar Pulau memverifikasi SEMUA reservasi dari semua pulau
            $query = Reservasi::query();
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
        } elseif ($user->isPengelolaPulau()) {
            // Pengelola Pulau melihat reservasi VALID + SELESAI (one day trip & menginap) khusus
            // pulaunya sendiri — supaya tahu siapa saja yang berkunjung, tidak cuma yang menginap.
            // "selesai" ikut disertakan supaya kunjungan yang sudah lewat tanggal (otomatis
            // ditandai selesai) tetap terhitung di Statistik & Ulasan, tidak hilang begitu saja.
            // Dashboard (yang cuma mau tampilkan reservasi yang masih berjalan) menyaring sendiri
            // status === 'valid' di sisi frontend.
            // Bisa difilter per jenis lewat query ?jenis=menginap / ?jenis=one_day_trip.
            $query = Reservasi::where('pulau_id', $user->pulau_id)->whereIn('status', ['valid', 'selesai']);

            if ($request->has('jenis')) {
                $query->where('jenis', $request->jenis);
            }
        } else {
            return response()->json(['message' => 'Role tidak dikenali.'], 403);
        }

        // per_page dulunya dihardcode 20 -- masalahnya beberapa halaman (Statistik & Ulasan,
        // Dashboard, Riwayat) cuma baca res.data.data dari HALAMAN PERTAMA saja tanpa loop ke
        // halaman berikutnya, jadi begitu total reservasi lewat 20 data lama "hilang" dari
        // tampilan (termasuk yang masih menunggu_verifikasi buat Pengantar Pulau -- bisa
        // kelewat diverifikasi). Dibolehkan override lewat ?per_page= dengan batas atas 500
        // supaya halaman-halaman itu bisa minta "semua data" tanpa perlu ubah ke infinite
        // scroll sekarang, sambil tetap ada batas wajar biar tidak disalahgunakan.
        $perPage = min(500, max(1, (int) $request->input('per_page', 20)));

        $reservasi = $query->with(['wisatawan', 'pulau', 'akomodasi'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json($reservasi);
    }

    public function show(Request $request, Reservasi $reservasi)
    {
        $user = $request->user();

        $bolehLihat =
            ($user->isWisatawan() && $reservasi->user_id === $user->id) ||
            $user->isPengantarPulau() ||
            ($user->isPengelolaPulau() && $reservasi->pulau_id === $user->pulau_id);

        if (! $bolehLihat) {
            return response()->json(['message' => 'Anda tidak berhak melihat reservasi ini.'], 403);
        }

        // BUG: 'ulasan' sebelumnya tidak ikut dimuat. Akibatnya r.ulasan di frontend SELALU
        // undefined, sehingga syarat "!r.ulasan" pada Detail Reservasi selalu benar dan tombol
        // "Beri Ulasan" tidak pernah hilang — wisatawan bisa menekannya berulang kali meskipun
        // reservasi itu sudah pernah diulas.
        $reservasi->load(['wisatawan', 'pulau', 'akomodasi', 'verifikator', 'ulasan', 'peserta']);
        // Dikirim eksplisit supaya frontend tidak perlu duplikat aturan H-2 sendiri — cukup pakai
        // hasil dari sini, dijamin selalu sinkron dengan aturan yang dipakai backend saat submit.
        $reservasi->bisa_ajukan_batal = $reservasi->bisaAjukanBatal();
        // Dikirim juga supaya halaman detail bisa MENJELASKAN kenapa tombol pembatalan
        // tidak tersedia, bukan sekadar menghilangkannya tanpa alasan.
        $reservasi->sisa_hari_kunjungan = $reservasi->sisaHariKunjungan();

        return response()->json($reservasi);
    }

    // POST /api/reservasi — buat reservasi baru (khusus wisatawan)
    // PENTING (race condition): akomodasi punya 1 angka jumlah_unit tetap (bukan kuota per
    // tanggal). Sisa unit di tanggal tertentu dihitung dinamis = jumlah_unit - jumlah reservasi
    // aktif (menunggu_verifikasi/valid) yang tanggalnya overlap. Supaya 2 wisatawan tidak bisa
    // lolos berdua untuk unit tersisa terakhir, baris Akomodasi dikunci (lockForUpdate) selama
    // transaksi supaya pengecekan + insert reservasi baru berjalan atomik.
    // JANGAN hapus/ganti bagian ini jadi pengecekan biasa tanpa lock.
    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user->isWisatawan()) {
            return response()->json(['message' => 'Hanya wisatawan yang bisa membuat reservasi.'], 403);
        }

        // Nomor HP wajib diisi dulu sebelum reservasi pertama — supaya Pengantar/Pengelola Pulau
        // punya cara menghubungi wisatawan. Ini berlaku utk SEMUA akun (bukan cuma yg dari Google),
        // karena dari awal no_hp memang opsional saat registrasi manual.
        if (! $user->no_hp) {
            return response()->json([
                'message' => 'Lengkapi nomor HP di halaman Profil dulu sebelum membuat reservasi.',
                'perlu_lengkapi_profil' => true,
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'pulau_id' => 'required|exists:pulau,id',
            'jenis' => 'required|in:one_day_trip,menginap',
            'akomodasi_id' => 'nullable|exists:akomodasi,id',
            'jumlah_unit_dipesan' => 'nullable|integer|min:1|max:20',
            'bawa_tenda_sendiri' => 'sometimes|boolean',
            // Aturan bisnis: reservasi paling cepat untuk BESOK (H-1), tidak bisa untuk hari
            // yang sama. Pengantar Pulau memerlukan waktu untuk memverifikasi bukti transfer dan
            // menyusun manifest keberangkatan. Tanpa jeda ini, reservasi yang dibuat siang atau
            // malam hari berisiko baru diverifikasi setelah jadwal keberangkatan terlewat —
            // terutama untuk One Day Trip yang berangkat dan pulang pada hari yang sama.
            'tanggal_kunjungan' => 'required|date|after:today',
            'tanggal_selesai' => 'required_if:jenis,menginap|nullable|date|after:tanggal_kunjungan',
            // Batas atas disamakan dengan batas jumlah baris peserta di bawah, supaya
            // rombongan besar ditolak dengan pesan yang jelas, bukan gagal diam-diam.
            'jumlah_orang' => 'required|integer|min:1|max:50',
            'bukti_transfer' => 'required|string',
            // Identitas setiap peserta wajib dikirim satu per satu. Jumlah barisnya harus
            // sama persis dengan jumlah_orang -- diperiksa tepat setelah blok ini.
            'peserta' => 'required|array|min:1|max:50',
            'peserta.*.nama' => 'required|string|max:255',
            'peserta.*.no_identitas' => 'nullable|string|max:32',
            'peserta.*.jenis_kelamin' => 'required|in:L,P',
            'peserta.*.usia' => 'required|integer|min:0|max:120',
            'peserta.*.no_hp' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $daftarPeserta = $request->input('peserta', []);

        // Manifest penumpang hanya sahih kalau jumlah baris identitas sama dengan jumlah orang
        // yang menyeberang. Kalau tidak sama, ada penumpang yang tidak tercatat identitasnya.
        if (count($daftarPeserta) !== (int) $request->jumlah_orang) {
            return response()->json([
                'message' => 'Jumlah data peserta ('.count($daftarPeserta).' orang) tidak sama dengan jumlah wisatawan yang diisi ('.$request->jumlah_orang.' orang).',
            ], 422);
        }

        // Nomor identitas diwajibkan bagi peserta berusia 17 tahun ke atas, karena pada usia itu
        // KTP sudah wajib dimiliki. Anak di bawahnya boleh dikosongkan.
        foreach ($daftarPeserta as $urutan => $orang) {
            if ((int) ($orang['usia'] ?? 0) >= 17 && empty($orang['no_identitas'])) {
                return response()->json([
                    'message' => 'Nomor identitas peserta ke-'.($urutan + 1).' ('.($orang['nama'] ?? 'tanpa nama').') wajib diisi karena usianya 17 tahun ke atas.',
                ], 422);
            }
        }

        $bawaTendaSendiri = $request->boolean('bawa_tenda_sendiri');

        // Satu reservasi bisa memesan beberapa unit sekaligus, misalnya rombongan 10 orang
        // memesan 3 unit cottage berkapasitas 4 orang. Untuk One Day Trip maupun "Bawa Tenda
        // Sendiri" nilainya selalu 1 karena tidak ada unit akomodasi yang dipesan.
        $jumlahUnit = ($request->jenis === 'menginap' && ! $bawaTendaSendiri)
            ? max(1, (int) $request->input('jumlah_unit_dipesan', 1))
            : 1;

        // Kalau menginap: wajib pilih salah satu — akomodasi berbayar milik pengelola, ATAU
        // centang bawa tenda sendiri (gratis, tidak masuk hitungan sisa unit akomodasi manapun).
        if ($request->jenis === 'menginap' && ! $bawaTendaSendiri && ! $request->akomodasi_id) {
            return response()->json([
                'message' => 'Pilih salah satu akomodasi, atau centang "Bawa Tenda Sendiri" kalau tidak perlu akomodasi.',
            ], 422);
        }

        $pulau = Pulau::findOrFail($request->pulau_id);

        // Tarif penyeberangan dan tiket masuk diambil per pulau (bukan tarif global lagi).
        // Tiket masuk dibedakan antara One Day Trip dan Menginap lewat Pulau::tarifTiketMasuk().
        $biayaPenyeberangan = (float) $pulau->harga_penyeberangan * $request->jumlah_orang;
        $tarifTiketMasuk = $pulau->tarifTiketMasuk($request->jenis);
        $biayaAkomodasi = 0;
        $biayaTiketMasuk = 0;
        $malamList = [];

        if ($request->jenis === 'menginap') {
            $malamList = $this->daftarTanggalMalam($request->tanggal_kunjungan, $request->tanggal_selesai);
        }

        try {
            $reservasi = DB::transaction(function () use ($request, $user, $pulau, $biayaPenyeberangan, $tarifTiketMasuk, $malamList, $bawaTendaSendiri, $jumlahUnit, $daftarPeserta, &$biayaAkomodasi, &$biayaTiketMasuk) {
                // Jumlah orang yang tiket masuknya TIDAK dihitung lagi karena sudah termasuk
                // harga akomodasi (lihat akomodasi.tiket_termasuk) — default 0 untuk One Day
                // Trip / Bawa Tenda Sendiri, karena keduanya tidak punya akomodasi berbayar.
                $orangDitanggungTiket = 0;

                if ($request->jenis === 'menginap' && ! $bawaTendaSendiri) {
                    // Kunci baris akomodasi ini supaya pengecekan sisa unit + insert reservasi
                    // berjalan atomik (2 request bersamaan tidak bisa lolos berdua di unit terakhir).
                    $akomodasi = Akomodasi::where('id', $request->akomodasi_id)->lockForUpdate()->firstOrFail();

                    // Cek sisa unit di SETIAP malam yang dipesan — kalau salah satu malam tidak
                    // cukup untuk jumlah unit yang diminta, seluruh transaksi dibatalkan (tidak ada
                    // yang perlu di-rollback manual karena belum ada yang diubah sampai titik ini).
                    foreach ($malamList as $tanggalMalam) {
                        $sisa = $akomodasi->sisaUnitPadaTanggal($tanggalMalam);
                        if ($sisa < $jumlahUnit) {
                            throw new \RuntimeException(
                                "Unit akomodasi \"{$akomodasi->nama}\" hanya tersisa {$sisa} unit pada tanggal {$tanggalMalam}, "
                                ."sedangkan Anda memesan {$jumlahUnit} unit."
                            );
                        }
                    }

                    $biayaAkomodasi = (float) $akomodasi->harga_per_malam * count($malamList) * $jumlahUnit;

                    // Akomodasi yang harganya sudah all-in (termasuk tiket masuk) membebaskan
                    // tiket masuk untuk wisatawan yang tertampung kapasitas unit yang dipesan.
                    // Kalau rombongan melebihi kapasitas unit yang dipesan, kelebihan orangnya
                    // tetap kena tiket masuk penuh seperti biasa.
                    if ($akomodasi->tiket_termasuk) {
                        $kapasitasPerUnit = $akomodasi->kapasitasAngkaMaksimal();
                        $orangDitanggungTiket = min($request->jumlah_orang, $kapasitasPerUnit * $jumlahUnit);
                    }
                }
                // Bawa tenda sendiri: tidak ada akomodasi yang dikunci/dicek, biaya akomodasi tetap 0.

                $orangBayarTiket = max(0, $request->jumlah_orang - $orangDitanggungTiket);
                $biayaTiketMasuk = $tarifTiketMasuk * $orangBayarTiket;

                $total = $biayaPenyeberangan + $biayaTiketMasuk + $biayaAkomodasi;

                $reservasiBaru = Reservasi::create([
                    'user_id' => $user->id,
                    'pulau_id' => $pulau->id,
                    'jenis' => $request->jenis,
                    'akomodasi_id' => ($request->jenis === 'menginap' && ! $bawaTendaSendiri) ? $request->akomodasi_id : null,
                    'jumlah_unit_dipesan' => $jumlahUnit,
                    'bawa_tenda_sendiri' => $request->jenis === 'menginap' ? $bawaTendaSendiri : false,
                    'tanggal_kunjungan' => $request->tanggal_kunjungan,
                    'tanggal_selesai' => $request->jenis === 'menginap' ? $request->tanggal_selesai : null,
                    'jumlah_orang' => $request->jumlah_orang,
                    'biaya_penyeberangan' => $biayaPenyeberangan,
                    'biaya_tiket_masuk' => $biayaTiketMasuk,
                    'biaya_akomodasi' => $biayaAkomodasi,
                    'total_bayar' => $total,
                    'bukti_transfer' => $request->bukti_transfer,
                    'status' => 'menunggu_verifikasi',
                ]);

                // Kode pemesanan baru bisa dibentuk setelah reservasi punya nomor urut (id).
                // Polanya SP-TTBBHH-NNNN, memakai tanggal kunjungan supaya kode itu sendiri
                // sudah memberi tahu tanggal keberangkatannya.
                $reservasiBaru->update([
                    'kode_booking' => 'SP-'
                        .\Carbon\Carbon::parse($request->tanggal_kunjungan)->format('ymd')
                        .'-'.str_pad((string) $reservasiBaru->id, 4, '0', STR_PAD_LEFT),
                ]);

                foreach ($daftarPeserta as $orang) {
                    PesertaReservasi::create([
                        'reservasi_id' => $reservasiBaru->id,
                        'nama' => $orang['nama'],
                        'no_identitas' => $orang['no_identitas'] ?? null,
                        'jenis_kelamin' => $orang['jenis_kelamin'],
                        'usia' => $orang['usia'],
                        'no_hp' => $orang['no_hp'] ?? null,
                    ]);
                }

                return $reservasiBaru;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Beri tahu semua akun Pengantar Pulau supaya reservasi baru ini segera diverifikasi.
        $jenisLabel = $reservasi->jenis === 'menginap' ? 'Menginap' : 'One Day Trip';
        User::where('role', 'pengantar_pulau')->get()->each(function ($pengantar) use ($reservasi, $jenisLabel, $pulau) {
            Notifikasi::create([
                'user_id' => $pengantar->id,
                'reservasi_id' => $reservasi->id,
                'judul' => 'Reservasi Baru Menunggu Verifikasi',
                'pesan' => "{$reservasi->wisatawan->name} mengajukan reservasi {$jenisLabel} ke {$pulau->nama} untuk tanggal {$reservasi->tanggal_kunjungan->format('d-m-Y')}.",
                'tipe' => 'reservasi_baru',
            ]);
        });

        return response()->json(['message' => 'Reservasi berhasil dibuat, menunggu verifikasi.', 'data' => $reservasi], 201);
    }

    // POST /api/reservasi/{reservasi}/upload-ulang-bukti — khusus wisatawan pemilik reservasi,
    // dipakai kalau reservasinya DITOLAK gara-gara bukti transfer kurang jelas/salah upload.
    // Daripada wisatawan harus buat reservasi baru dari nol (ribet, hitung ulang harga, dsb),
    // dia cukup unggah ulang bukti transfer untuk reservasi yang sama — status balik ke
    // menunggu_verifikasi supaya Pengantar Pulau mengecek lagi.
    public function uploadUlangBukti(Request $request, Reservasi $reservasi)
    {
        $user = $request->user();

        if (! $user->isWisatawan() || $reservasi->user_id !== $user->id) {
            return response()->json(['message' => 'Anda tidak berhak mengubah reservasi ini.'], 403);
        }

        if ($reservasi->status !== 'ditolak') {
            return response()->json(['message' => 'Upload ulang bukti transfer hanya bisa dilakukan untuk reservasi yang ditolak.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'bukti_transfer' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::transaction(function () use ($request, $reservasi) {
            $reservasi->update([
                'bukti_transfer' => $request->bukti_transfer,
                'status' => 'menunggu_verifikasi',
                'catatan_penolakan' => null,
                'verified_by' => null,
                'verified_at' => null,
            ]);

            // Beri tahu lagi semua akun Pengantar Pulau, sama seperti reservasi baru — karena
            // dari sisi mereka ini butuh diverifikasi ulang lagi.
            $jenisLabel = $reservasi->jenis === 'menginap' ? 'Menginap' : 'One Day Trip';
            User::where('role', 'pengantar_pulau')->get()->each(function ($pengantar) use ($reservasi, $jenisLabel) {
                Notifikasi::create([
                    'user_id' => $pengantar->id,
                    'reservasi_id' => $reservasi->id,
                    'judul' => 'Bukti Transfer Diunggah Ulang',
                    'pesan' => "{$reservasi->wisatawan->name} mengunggah ulang bukti transfer untuk reservasi {$jenisLabel}, mohon diverifikasi ulang.",
                    'tipe' => 'reservasi_baru',
                ]);
            });
        });

        return response()->json(['message' => 'Bukti transfer berhasil diunggah ulang, menunggu verifikasi.', 'data' => $reservasi->fresh()]);
    }

    // PATCH /api/reservasi/{reservasi}/verifikasi — khusus pengantar_pulau
    public function verifikasi(Request $request, Reservasi $reservasi)
    {
        $user = $request->user();

        if (! $user->isPengantarPulau()) {
            return response()->json(['message' => 'Hanya Pengantar Pulau yang bisa memverifikasi.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:valid,ditolak',
            'catatan_penolakan' => 'required_if:status,ditolak|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::transaction(function () use ($request, $user, $reservasi) {
            // Catatan: tidak perlu "kembalikan kuota" manual — begitu status berubah dari
            // menunggu_verifikasi, reservasi ini otomatis tidak terhitung lagi di
            // Akomodasi::sisaUnitPadaTanggal() karena hitungannya dinamis dari status reservasi.
            $reservasi->update([
                'status' => $request->status,
                'catatan_penolakan' => $request->status === 'ditolak' ? $request->catatan_penolakan : null,
                'verified_by' => $user->id,
                'verified_at' => now(),
            ]);

            Notifikasi::create([
                'user_id' => $reservasi->user_id,
                'reservasi_id' => $reservasi->id,
                'judul' => $request->status === 'valid' ? 'Reservasi Anda Valid' : 'Reservasi Anda Ditolak',
                'pesan' => $request->status === 'valid'
                    ? 'Bukti transfer Anda telah diverifikasi. Sampai jumpa di hari kunjungan!'
                    : 'Bukti transfer Anda ditolak. Alasan: '.$request->catatan_penolakan.' Ketuk notifikasi ini untuk unggah ulang bukti transfer.',
                'tipe' => $request->status === 'valid' ? 'verifikasi_valid' : 'verifikasi_ditolak',
            ]);

            // Reservasi yang baru VALID (one day trip maupun menginap) juga diberitahukan ke
            // Pengelola Pulau terkait, supaya dia tahu siapa saja yang berkunjung ke pulaunya —
            // bukan cuma yang menginap, karena dia juga yang urus tiket masuk & wahana harian.
            if ($request->status === 'valid') {
                $pengelola = User::where('role', 'pengelola_pulau')->where('pulau_id', $reservasi->pulau_id)->first();

                if ($pengelola) {
                    // "Bawa Tenda Sendiri" tidak punya akomodasi (akomodasi_id NULL) — jangan
                    // baca $reservasi->akomodasi->nama langsung, pakai label ini kalau null.
                    $labelAkomodasi = $reservasi->akomodasi->nama ?? 'Bawa Tenda Sendiri';

                    $pesan = $reservasi->jenis === 'menginap'
                        ? "{$reservasi->wisatawan->name} akan menginap di {$labelAkomodasi} mulai {$reservasi->tanggal_kunjungan->format('d-m-Y')}"
                            .($reservasi->tanggal_selesai ? " s/d {$reservasi->tanggal_selesai->format('d-m-Y')}." : '.')
                        : "{$reservasi->wisatawan->name} akan berkunjung (One Day Trip) pada {$reservasi->tanggal_kunjungan->format('d-m-Y')} — {$reservasi->jumlah_orang} orang.";

                    Notifikasi::create([
                        'user_id' => $pengelola->id,
                        'reservasi_id' => $reservasi->id,
                        'judul' => $reservasi->jenis === 'menginap' ? 'Reservasi Menginap Baru' : 'Kunjungan One Day Trip Baru',
                        'pesan' => $pesan,
                        'tipe' => $reservasi->jenis === 'menginap' ? 'reservasi_menginap_baru' : 'reservasi_one_day_baru',
                    ]);
                }
            }
        });

        return response()->json(['message' => 'Status reservasi berhasil diperbarui.', 'data' => $reservasi->fresh()]);
    }

    // POST /api/reservasi/{reservasi}/ajukan-batal — khusus wisatawan pemilik reservasi
    public function ajukanPembatalan(Request $request, Reservasi $reservasi)
    {
        $user = $request->user();

        if (! $user->isWisatawan() || $reservasi->user_id !== $user->id) {
            return response()->json(['message' => 'Anda tidak berhak mengajukan pembatalan ini.'], 403);
        }

        if (! $reservasi->bisaAjukanBatal()) {
            return response()->json([
                'message' => 'Pembatalan hanya bisa diajukan minimal H-2 sebelum tanggal kunjungan, dan reservasi harus berstatus valid.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'alasan_pembatalan' => 'required|in:cuaca_buruk,sakit_mendadak,perubahan_jadwal,lainnya',
            'keterangan_pembatalan' => 'required_if:alasan_pembatalan,lainnya|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $reservasi->update([
            'status' => 'pengajuan_batal',
            'alasan_pembatalan' => $request->alasan_pembatalan,
            'keterangan_pembatalan' => $request->keterangan_pembatalan,
            'tanggal_pengajuan_batal' => now(),
            'status_pengembalian_dana' => 'belum_diproses',
        ]);

        // BUG: sebelumnya tidak ada Notifikasi::create() sama sekali di sini -- padahal SEMUA
        // perubahan status lain (reservasi baru, verifikasi, hasil proses batal) selalu memberi
        // tahu pihak terkait. Akibatnya Pengantar Pulau tidak tahu ada pengajuan pembatalan baru
        // kecuali mengecek manual satu-satu, padahal dialah yang harus memprosesnya
        // (lihat prosesPembatalan() di bawah).
        User::where('role', 'pengantar_pulau')->get()->each(function ($pengantar) use ($reservasi) {
            Notifikasi::create([
                'user_id' => $pengantar->id,
                'reservasi_id' => $reservasi->id,
                'judul' => 'Pengajuan Pembatalan Baru',
                'pesan' => "{$reservasi->wisatawan->name} mengajukan pembatalan reservasi ke {$reservasi->pulau->nama} untuk tanggal {$reservasi->tanggal_kunjungan->format('d-m-Y')}.",
                'tipe' => 'pembatalan_diajukan',
            ]);
        });

        return response()->json(['message' => 'Pengajuan pembatalan berhasil dikirim.', 'data' => $reservasi]);
    }

    // PATCH /api/reservasi/{reservasi}/proses-batal — khusus pengantar_pulau (setuju/tolak + keputusan refund)
    public function prosesPembatalan(Request $request, Reservasi $reservasi)
    {
        $user = $request->user();

        if (! $user->isPengantarPulau()) {
            return response()->json(['message' => 'Hanya Pengantar Pulau yang bisa memproses pembatalan.'], 403);
        }

        if ($reservasi->status !== 'pengajuan_batal') {
            return response()->json(['message' => 'Reservasi ini tidak sedang dalam status pengajuan pembatalan.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'disetujui' => 'required|boolean',
            'status_pengembalian_dana' => 'required_if:disetujui,true|nullable|in:disetujui,ditolak',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::transaction(function () use ($request, $reservasi) {
            if ($request->boolean('disetujui')) {
                // Pembatalan disetujui: status berubah jadi 'dibatalkan' sehingga otomatis
                // tidak terhitung lagi di Akomodasi::sisaUnitPadaTanggal() (tidak perlu kuota manual).
                $reservasi->update([
                    'status' => 'dibatalkan',
                    'status_pengembalian_dana' => $request->status_pengembalian_dana,
                ]);
            } else {
                // Pembatalan ditolak: reservasi kembali berstatus valid seperti semula
                $reservasi->update([
                    'status' => 'valid',
                    'status_pengembalian_dana' => null,
                ]);
            }

            Notifikasi::create([
                'user_id' => $reservasi->user_id,
                'reservasi_id' => $reservasi->id,
                'judul' => $request->boolean('disetujui') ? 'Pembatalan Disetujui' : 'Pengajuan Pembatalan Ditolak',
                'pesan' => $request->boolean('disetujui')
                    ? 'Pengajuan pembatalan Anda telah disetujui.'
                    : 'Pengajuan pembatalan Anda ditolak, reservasi tetap berjalan sesuai jadwal.',
                'tipe' => $request->boolean('disetujui') ? 'pembatalan_disetujui' : 'pembatalan_ditolak',
            ]);

            // Pengelola Pulau WAJIB ikut diberi tahu kalau pembatalan disetujui. Alasannya:
            // begitu status jadi 'dibatalkan', reservasi ini langsung keluar dari daftar
            // Pengelola Pulau (index() hanya mengambil status valid & selesai), jadi barisnya
            // hilang tanpa jejak. Tanpa notifikasi ini, pengelola yang sudah menyiapkan unit
            // akomodasi untuk tamu tersebut tidak akan pernah tahu tamunya batal datang.
            //
            // Hanya dikirim saat DISETUJUI. Kalau pengajuan ditolak, reservasi kembali valid
            // dan tidak ada yang berubah bagi pengelola, jadi tidak perlu diganggu.
            if ($request->boolean('disetujui')) {
                $reservasi->loadMissing('wisatawan');

                $pengelola = User::where('role', 'pengelola_pulau')
                    ->where('pulau_id', $reservasi->pulau_id)
                    ->get();

                foreach ($pengelola as $p) {
                    Notifikasi::create([
                        'user_id' => $p->id,
                        'reservasi_id' => $reservasi->id,
                        'judul' => 'Reservasi Dibatalkan',
                        'pesan' => 'Reservasi atas nama '.$reservasi->wisatawan->name.' pada tanggal '
                            .$reservasi->tanggal_kunjungan->format('d-m-Y')
                            .' telah dibatalkan. Unit akomodasi yang sudah disiapkan dapat dilepas kembali.',
                        'tipe' => 'reservasi_dibatalkan',
                    ]);
                }
            }
        });

        return response()->json(['message' => 'Pengajuan pembatalan berhasil diproses.', 'data' => $reservasi->fresh()]);
    }
}
