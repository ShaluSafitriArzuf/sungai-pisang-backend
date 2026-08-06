<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WahanaKegiatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WahanaController extends Controller
{
    // Publik: dipakai di halaman Detail Pulau (sudah include via PulauController@show),
    // endpoint ini untuk fetch terpisah jika dibutuhkan frontend.
    public function index(Request $request)
    {
        $query = WahanaKegiatan::query();

        if ($request->has('pulau_id')) {
            $query->where('pulau_id', $request->pulau_id);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user->isPengelolaPulau()) {
            return response()->json(['message' => 'Hanya pengelola pulau yang bisa menambah wahana.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'berbayar_lokasi' => 'sometimes|boolean',
            'foto' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $fotoUrl = null;
        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('wahana', 'public');
            $fotoUrl = asset('storage/'.$path);
        }

        $wahana = WahanaKegiatan::create([
            'pulau_id' => $user->pulau_id,
            'nama' => $request->nama,
            'deskripsi' => $request->deskripsi,
            'foto' => $fotoUrl,
            'berbayar_lokasi' => $request->boolean('berbayar_lokasi'),
            'aktif' => true,
        ]);

        return response()->json(['message' => 'Wahana berhasil ditambahkan.', 'data' => $wahana], 201);
    }

    public function update(Request $request, WahanaKegiatan $wahana)
    {
        $user = $request->user();

        if (! $user->isPengelolaPulau() || $user->pulau_id !== $wahana->pulau_id) {
            return response()->json(['message' => 'Anda tidak berhak mengubah wahana ini.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'nama' => 'sometimes|string|max:255',
            'deskripsi' => 'nullable|string',
            'aktif' => 'sometimes|boolean',
            'berbayar_lokasi' => 'sometimes|boolean',
            'foto' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('wahana', 'public');
            $data['foto'] = asset('storage/'.$path);
        } else {
            unset($data['foto']);
        }

        $wahana->update($data);

        return response()->json(['message' => 'Wahana berhasil diperbarui.', 'data' => $wahana]);
    }

    public function destroy(Request $request, WahanaKegiatan $wahana)
    {
        $user = $request->user();

        if (! $user->isPengelolaPulau() || $user->pulau_id !== $wahana->pulau_id) {
            return response()->json(['message' => 'Anda tidak berhak menghapus wahana ini.'], 403);
        }

        $wahana->delete();

        return response()->json(['message' => 'Wahana berhasil dihapus.']);
    }
}
