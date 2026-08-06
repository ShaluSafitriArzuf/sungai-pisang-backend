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

        return response()->json($query->with('wisatawan')->latest()->paginate(20));
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

        $ulasan = Ulasan::updateOrCreate(
            ['reservasi_id' => $reservasi->id],
            [
                'user_id' => $user->id,
                'pulau_id' => $reservasi->pulau_id,
                'rating' => $request->rating,
                'komentar' => $request->komentar,
            ]
        );

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
