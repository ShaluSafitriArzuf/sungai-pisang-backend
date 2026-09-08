<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservasi;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Penerbitan dokumen resmi sebuah reservasi: invoice, kuitansi, dan tiket kunjungan.
 *
 * Dipisahkan dari ReservasiController karena ketiganya bukan bagian dari alur pemesanan,
 * melainkan hasil cetak dari data reservasi yang sudah ada.
 *
 * Ketiganya terbit pada tahap yang berbeda dan itu disengaja:
 *   invoice  -> terbit sejak reservasi dibuat, karena berfungsi sebagai TAGIHAN
 *   kuitansi -> terbit setelah pembayaran diverifikasi, karena berfungsi sebagai
 *               BUKTI UANG DITERIMA
 *   tiket    -> terbit setelah pembayaran diverifikasi, karena berfungsi sebagai
 *               BUKTI HAK KEBERANGKATAN
 */
class DokumenController extends Controller
{
    /**
     * Invoice diterbitkan sejak reservasi dibuat, karena fungsinya adalah bukti tagihan
     * beserta rincian biayanya — termasuk ketika pembayarannya masih menunggu verifikasi.
     */
    public function invoice(Request $request, Reservasi $reservasi)
    {
        if (! $this->boleh($request->user(), $reservasi)) {
            return response()->json(['message' => 'Anda tidak berhak melihat dokumen reservasi ini.'], 403);
        }

        $reservasi->load(['wisatawan', 'pulau', 'akomodasi', 'peserta']);

        $pdf = Pdf::loadView('dokumen.invoice', ['reservasi' => $reservasi]);

        return $pdf->download('Invoice-'.($reservasi->kode_booking ?: $reservasi->id).'.pdf');
    }

    /**
     * Kuitansi adalah bukti bahwa uangnya sudah diterima, sehingga baru boleh terbit setelah
     * Pengantar Pulau memverifikasi bukti transfer. Kedudukannya berbeda dengan invoice yang
     * hanya menyatakan besarnya tagihan.
     */
    public function kuitansi(Request $request, Reservasi $reservasi)
    {
        if (! $this->boleh($request->user(), $reservasi)) {
            return response()->json(['message' => 'Anda tidak berhak melihat dokumen reservasi ini.'], 403);
        }

        if (! in_array($reservasi->status, ['valid', 'selesai'], true)) {
            return response()->json([
                'message' => 'Kuitansi baru dapat diunduh setelah bukti pembayaran diverifikasi oleh Pengantar Pulau.',
            ], 409);
        }

        $reservasi->load(['wisatawan', 'pulau', 'akomodasi', 'verifikator']);

        $pdf = Pdf::loadView('dokumen.kuitansi', ['reservasi' => $reservasi]);

        return $pdf->download('Kuitansi-'.($reservasi->kode_booking ?: $reservasi->id).'.pdf');
    }

    /**
     * Tiket hanya terbit setelah pembayaran diverifikasi. Sebelum itu belum ada hak
     * keberangkatan yang bisa dicetak, sehingga permintaannya ditolak dengan penjelasan.
     */
    public function tiket(Request $request, Reservasi $reservasi)
    {
        if (! $this->boleh($request->user(), $reservasi)) {
            return response()->json(['message' => 'Anda tidak berhak melihat dokumen reservasi ini.'], 403);
        }

        if (! in_array($reservasi->status, ['valid', 'selesai'], true)) {
            return response()->json([
                'message' => 'Tiket baru dapat diunduh setelah bukti pembayaran diverifikasi oleh Pengantar Pulau.',
            ], 409);
        }

        $reservasi->load(['wisatawan', 'pulau', 'akomodasi', 'peserta']);

        // Kontak dan titik kumpul Pengantar Pulau dicantumkan pada tiket supaya wisatawan
        // tetap punya cara menghubungi walaupun sedang tidak membuka aplikasi.
        $pengantar = User::where('role', 'pengantar_pulau')->first();

        $pdf = Pdf::loadView('dokumen.tiket', [
            'reservasi' => $reservasi,
            'pengantar' => $pengantar,
        ]);

        return $pdf->download('Tiket-'.($reservasi->kode_booking ?: $reservasi->id).'.pdf');
    }

    /**
     * Aturan akses sama dengan halaman Detail Reservasi: pemilik reservasi, Pengantar Pulau,
     * dan Pengelola Pulau yang bersangkutan.
     */
    private function boleh(User $user, Reservasi $reservasi): bool
    {
        return ($user->isWisatawan() && $reservasi->user_id === $user->id)
            || $user->isPengantarPulau()
            || ($user->isPengelolaPulau() && $reservasi->pulau_id === $user->pulau_id);
    }
}
