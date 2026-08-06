<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Manifest;
use App\Models\Reservasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class ManifestController extends Controller
{
    // GET /api/manifest?tanggal=YYYY-MM-DD — khusus pengantar_pulau
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user->isPengantarPulau()) {
            return response()->json(['message' => 'Hanya Pengantar Pulau yang bisa mengakses manifest.'], 403);
        }

        $tanggal = $request->query('tanggal', now()->format('Y-m-d'));

        $reservasi = Reservasi::whereDate('tanggal_kunjungan', $tanggal)
            ->whereIn('status', ['valid', 'selesai'])
            ->with(['wisatawan', 'pulau', 'akomodasi'])
            ->get();

        return response()->json([
            'tanggal' => $tanggal,
            'jumlah_wisatawan' => $reservasi->sum('jumlah_orang'),
            // Jumlah unit akomodasi yang harus disiapkan pada tanggal ini. Satu reservasi
            // dapat memesan lebih dari satu unit, sehingga tidak cukup menghitung jumlah
            // baris reservasi. Reservasi One Day Trip dan "Bawa Tenda Sendiri" tidak
            // memakai unit akomodasi sehingga tidak ikut dijumlahkan.
            'jumlah_unit_akomodasi' => $reservasi->whereNotNull('akomodasi_id')->sum('jumlah_unit_dipesan'),
            'reservasi' => $reservasi,
        ]);
    }

    // POST /api/manifest/export — generate & simpan PDF manifest untuk 1 tanggal
    public function export(Request $request)
    {
        $user = $request->user();

        if (! $user->isPengantarPulau()) {
            return response()->json(['message' => 'Hanya Pengantar Pulau yang bisa ekspor manifest.'], 403);
        }

        $tanggal = $request->input('tanggal', now()->format('Y-m-d'));

        $reservasi = Reservasi::whereDate('tanggal_kunjungan', $tanggal)
            ->whereIn('status', ['valid', 'selesai'])
            ->with(['wisatawan', 'pulau', 'akomodasi'])
            ->get();

        $pdf = Pdf::loadView('manifest.pdf', [
            'tanggal' => $tanggal,
            'reservasi' => $reservasi,
        ]);

        $fileName = 'manifest/manifest-'.$tanggal.'-'.time().'.pdf';
        Storage::disk('public')->put($fileName, $pdf->output());

        $manifest = Manifest::create([
            'tanggal' => $tanggal,
            'dibuat_oleh' => $user->id,
            'jumlah_wisatawan' => $reservasi->sum('jumlah_orang'),
            'file_pdf' => $fileName,
            'status' => 'exported',
            'exported_at' => now(),
        ]);

        return response()->json([
            'message' => 'Manifest berhasil diekspor.',
            'data' => $manifest,
            'url' => Storage::disk('public')->url($fileName),
        ]);
    }
}
