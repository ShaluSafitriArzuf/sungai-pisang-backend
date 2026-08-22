<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ulasan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UlasanController extends Controller
{
    public function index(Request $request)
    {
        $query = Ulasan::query();

        if ($request->has('pulau_id')) {
            $query->where('pulau_id', $request->pulau_id);
        }

        // per_page dulunya dihardcode 20 -- ini yang dipakai halaman "Statistik & Ulasan"
        // Pengelola Pulau (baca HALAMAN PERTAMA saja tanpa loop halaman berikutnya). Begitu
        // sebuah pulau punya lebih dari 20 ulasan, ulasan lama "hilang" dari statistik rating
        // DAN tidak bisa dihapus lagi lewat moderasi karena tidak pernah termuat di frontend.
        $perPage = min(500, max(1, (int) $request->input('per_page', 20)));

        return response()->json($query->with('wisatawan')->latest()->paginate($perPage));
    }

    // Wisatawan hanya bisa mengulas reservasi miliknya sendiri yang sudah 'selesai'
    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user->isWisatawan()) {
            return response()->json(['message' => 'Hanya wisatawan yang bisa memberi ulasan.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'reservasi_id' => 'required|exists:reservasi,id',
            'rating' => 'required|integer|min:1|max:5',
            'komentar' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $reservasi = \App\Models\Reservasi::findOrFail($request->reservasi_id);

        if ($reservasi->user_id !== $user->id || $reservasi->status !== 'selesai') {
            return response()->json(['message' => 'Anda hanya bisa mengulas reservasi milik sendiri yang sudah selesai.'], 403);
        }

        // Satu reservasi hanya boleh punya SATU ulasan. Sebelumnya dipakai updateOrCreate,
        // jadi pengiriman berulang diam-diam MENIMPA ulasan lama tanpa pemberitahuan apa pun --
        // wisatawan bisa mengganti penilaiannya berkali-kali, dan Pengelola Pulau tidak pernah
        // tahu ulasan yang sudah ia baca ternyata berubah isinya. Sekarang ditolak terang-terangan.
        if (Ulasan::where('reservasi_id', $reservasi->id)->exists()) {
            return response()->json([
                'message' => 'Reservasi ini sudah pernah diulas. Satu reservasi hanya bisa diberi satu ulasan.',
            ], 409);
        }

        $ulasan = Ulasan::create([
            'reservasi_id' => $reservasi->id,
            'user_id' => $user->id,
            'pulau_id' => $reservasi->pulau_id,
            'rating' => $request->rating,
            'komentar' => $request->komentar,
        ]);

        return response()->json(['message' => 'Ulasan berhasil dikirim.', 'data' => $ulasan], 201);
    }

    // Khusus pengelola_pulau: hapus ulasan yang masuk ke pulau miliknya sendiri (moderasi
    // dasar — mis. ulasan spam/tidak pantas). Wisatawan penulis aslinya tidak diberi tahu
    // otomatis; ini murni tindakan moderasi oleh pengelola.
    public function destroy(Request $request, Ulasan $ulasan)
    {
        $user = $request->user();

        if (! $user->isPengelolaPulau() || $user->pulau_id !== $ulasan->pulau_id) {
            return response()->json(['message' => 'Anda tidak berhak menghapus ulasan ini.'], 403);
        }

        $ulasan->delete();

        return response()->json(['message' => 'Ulasan berhasil dihapus.']);
    }
}
