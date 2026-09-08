<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pulau;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class PulauController extends Controller
{
    // Publik: list semua pulau untuk halaman Beranda
    public function index()
    {
        $pulau = Pulau::withCount('ulasan')->get()->map(function ($p) {
            $p->rating_rata_rata = $p->ratingRataRata();
            return $p;
        });

        return response()->json($pulau);
    }

    // Publik: detail 1 pulau untuk halaman Detail Pulau
    public function show(Pulau $pulau)
    {
        $pulau->load(['galeriFoto', 'wahanaKegiatan' => function ($q) {
            $q->where('aktif', true);
        }, 'akomodasi' => function ($q) {
            $q->where('aktif', true);
        }, 'akomodasi.fotoTambahan', 'ulasan.wisatawan']);

        $pulau->rating_rata_rata = $pulau->ratingRataRata();

        return response()->json($pulau);
    }

    // Khusus pengelola_pulau: update profil pulau miliknya sendiri (CMS)
    public function update(Request $request, Pulau $pulau)
    {
        $user = $request->user();

        if (! $user->isPengelolaPulau() || $user->pulau_id !== $pulau->id) {
            return response()->json(['message' => 'Anda hanya bisa mengelola pulau Anda sendiri.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'foto_utama' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:10240',
            'luas' => 'nullable|numeric',
            'deskripsi' => 'nullable|string',
            'badge' => 'nullable|string|max:255',
            'harga_tiket_masuk_one_day' => 'nullable|numeric|min:0',
            'harga_tiket_masuk_menginap' => 'nullable|numeric|min:0',
            'harga_penyeberangan' => 'nullable|numeric|min:0',
            'jam_operasional_genset' => 'nullable|string|max:255',
            'genset_24_jam' => 'sometimes|boolean',
            'regulasi' => 'nullable|string',
            'fasilitas' => 'nullable|array',
            'fasilitas.*' => 'string',
            // Batas wilayah pulau hasil digitasi: larik titik [lintang, bujur].
            // Dibatasi 200 titik supaya poligon tetap ringan digambar di peta.
            'batas_koordinat' => 'nullable|array|max:200',
            'batas_koordinat.*' => 'array|size:2',
            'batas_koordinat.*.*' => 'numeric',
            'hapus_batas' => 'nullable|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Formulir dikirim sebagai FormData, sehingga angka koordinat tiba sebagai teks.
        // Dijadikan bilangan dulu supaya yang tersimpan di basis data benar-benar angka,
        // bukan string yang nanti merepotkan saat digambar ulang di peta.
        if (isset($data['batas_koordinat'])) {
            $data['batas_koordinat'] = array_values(array_map(
                fn ($titik) => [(float) $titik[0], (float) $titik[1]],
                $data['batas_koordinat']
            ));
        }

        // Pengelola boleh menghapus batas yang pernah digambar. FormData tidak bisa mengirim
        // larik kosong, jadi penghapusan dikabarkan lewat penanda tersendiri.
        unset($data['hapus_batas']);
        if ($request->input('hapus_batas') === '1') {
            $data['batas_koordinat'] = null;
        }
        // Konsisten sama tiket_termasuk di akomodasi: kalau genset_24_jam dinyalain, jadwal
        // manual di jam_operasional_genset ga relevan lagi -- dikosongin biar ga nyisa data lama.
        if ($request->boolean('genset_24_jam')) {
            $data['jam_operasional_genset'] = null;
        }

        if ($request->hasFile('foto_utama')) {
            $path = $request->file('foto_utama')->store('pulau', 'public');
            $data['foto_utama'] = asset('storage/'.$path);
        } else {
            unset($data['foto_utama']);
        }

        $pulau->update($data);

        return response()->json(['message' => 'Profil pulau berhasil diperbarui.', 'pulau' => $pulau]);
    }
}
