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
            'regulasi' => 'nullable|string',
            'fasilitas' => 'nullable|array',
            'fasilitas.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

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
