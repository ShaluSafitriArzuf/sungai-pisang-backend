<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pulau;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PetaController extends Controller
{
    // Publik: seluruh data spasial untuk halaman Peta Interaktif.
    //
    // Yang dikirim bukan sekadar titik pulau, melainkan lapisan-lapisan data yang
    // dipakai peta untuk menggambar: posisi (titik), batas wilayah (poligon),
    // titik kumpul Pengantar Pulau, serta jumlah kunjungan yang menjadi dasar
    // lapisan tematik kepadatan.
    public function index()
    {
        $pulau = Pulau::select(
            'id', 'nama', 'foto_utama', 'luas', 'deskripsi', 'badge', 'fasilitas',
            'latitude', 'longitude', 'batas_koordinat',
            'harga_tiket_masuk_one_day', 'harga_tiket_masuk_menginap', 'harga_penyeberangan'
        )
            ->withCount('ulasan')
            // Jumlah wisatawan (bukan jumlah transaksi) yang benar-benar berkunjung.
            // Dipakai lapisan "Kepadatan Kunjungan": ukuran lingkaran pada tiap pulau
            // sebanding dengan banyaknya orang, bukan banyaknya pesanan.
            ->withSum([
                'reservasi as jumlah_wisatawan' => function ($q) {
                    $q->whereIn('status', ['valid', 'selesai']);
                },
            ], 'jumlah_orang')
            ->get()
            ->map(function ($p) {
                $p->rating_rata_rata = $p->ratingRataRata();
                $p->jumlah_wisatawan = (int) ($p->jumlah_wisatawan ?? 0);
                return $p;
            });

        $pengantarPulau = User::where('role', 'pengantar_pulau')
            ->whereNotNull('latitude')
            ->select('id', 'name', 'no_hp', 'latitude', 'longitude', 'foto')
            ->get();

        return response()->json([
            'pulau' => $pulau,
            'pengantar_pulau' => $pengantarPulau,
        ]);
    }

    // Khusus pengantar_pulau: update lokasi dermaga/rumahnya sendiri
    public function updateLokasiSaya(Request $request)
    {
        $user = $request->user();

        if (! $user->isPengantarPulau()) {
            return response()->json(['message' => 'Hanya Pengantar Pulau yang bisa mengatur lokasi ini.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'foto' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = [
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
        ];

        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('pengantar', 'public');
            $data['foto'] = asset('storage/'.$path);
        }

        $user->update($data);

        return response()->json(['message' => 'Lokasi berhasil disimpan.', 'data' => $user]);
    }
}
