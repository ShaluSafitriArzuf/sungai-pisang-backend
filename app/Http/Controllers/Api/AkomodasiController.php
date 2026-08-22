<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Akomodasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class AkomodasiController extends Controller
{
    public function index(Request $request)
    {
        $query = Akomodasi::where('aktif', true)->with('fotoTambahan');

        if ($request->has('pulau_id')) {
            $query->where('pulau_id', $request->pulau_id);
        }

        return response()->json($query->get());
    }

    public function show(Akomodasi $akomodasi)
    {
        return response()->json($akomodasi);
    }

    // GET /akomodasi/kelola/semua — khusus Pengelola Pulau, tampilkan SEMUA akomodasi
    // miliknya (aktif maupun nonaktif) untuk halaman Kelola Akomodasi. Beda dari index()
    // di atas yang cuma menampilkan aktif=true (dipakai halaman publik Detail Pulau/Form
    // Reservasi) — supaya akomodasi yang baru dinonaktifkan TIDAK hilang dari daftar kelola.
    public function kelola(Request $request)
    {
        $user = $request->user();

        if (! $user->isPengelolaPulau()) {
            return response()->json(['message' => 'Hanya pengelola pulau yang bisa mengakses ini.'], 403);
        }

        $akomodasi = Akomodasi::where('pulau_id', $user->pulau_id)->with('fotoTambahan')->get();

        return response()->json($akomodasi);
    }

    // GET /api/akomodasi/{akomodasi}/ketersediaan?checkin=&checkout= — publik, dipakai
    // form reservasi supaya wisatawan bisa lihat sisa unit SEBELUM kirim reservasi.
    public function ketersediaan(Request $request, Akomodasi $akomodasi)
    {
        $validator = Validator::make($request->all(), [
            'checkin' => 'required|date',
            'checkout' => 'required|date|after:checkin',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $sisa = $akomodasi->sisaUnitUntukRentang($request->checkin, $request->checkout);

        return response()->json([
            'jumlah_unit' => $akomodasi->jumlah_unit,
            'sisa_unit' => $sisa,
            'tersedia' => $sisa > 0,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user->isPengelolaPulau()) {
            return response()->json(['message' => 'Hanya pengelola pulau yang bisa menambah akomodasi.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:255',
            'tipe' => 'required|in:cottage,gazebo,tenda,backpacker',
            'harga_per_malam' => 'required|numeric|min:0',
            'jumlah_unit' => 'required|integer|min:1',
            'kapasitas' => 'nullable|string|max:50',
            'foto' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:10240',
            'deskripsi' => 'nullable|string',
            'fasilitas' => 'nullable|array',
            'fasilitas.*' => 'string',
            'tiket_termasuk' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $fotoUrl = null;
        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('akomodasi', 'public');
            $fotoUrl = asset('storage/'.$path);
        }

        $akomodasi = Akomodasi::create([
            'pulau_id' => $user->pulau_id,
            'nama' => $request->nama,
            'tipe' => $request->tipe,
            'harga_per_malam' => $request->harga_per_malam,
            'jumlah_unit' => $request->jumlah_unit,
            'kapasitas' => $request->kapasitas,
            'foto' => $fotoUrl,
            'deskripsi' => $request->deskripsi,
            'fasilitas' => $request->input('fasilitas', []),
            'tiket_termasuk' => $request->boolean('tiket_termasuk'),
            'aktif' => true,
        ]);

        return response()->json(['message' => 'Akomodasi berhasil ditambahkan.', 'data' => $akomodasi], 201);
    }

    public function update(Request $request, Akomodasi $akomodasi)
    {
        $user = $request->user();

        if (! $user->isPengelolaPulau() || $user->pulau_id !== $akomodasi->pulau_id) {
            return response()->json(['message' => 'Anda tidak berhak mengubah akomodasi ini.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'nama' => 'sometimes|string|max:255',
            'tipe' => 'sometimes|in:cottage,gazebo,tenda,backpacker',
            'harga_per_malam' => 'sometimes|numeric|min:0',
            'jumlah_unit' => 'sometimes|integer|min:1',
            'kapasitas' => 'nullable|string|max:50',
            'foto' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:10240',
            'deskripsi' => 'nullable|string',
            'fasilitas' => 'nullable|array',
            'fasilitas.*' => 'string',
            'aktif' => 'sometimes|boolean',
            'tiket_termasuk' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('akomodasi', 'public');
            $data['foto'] = asset('storage/'.$path);
        } else {
            unset($data['foto']);
        }

        $akomodasi->update($data);

        return response()->json(['message' => 'Akomodasi berhasil diperbarui.', 'data' => $akomodasi]);
    }

    public function destroy(Request $request, Akomodasi $akomodasi)
    {
        $user = $request->user();

        if (! $user->isPengelolaPulau() || $user->pulau_id !== $akomodasi->pulau_id) {
            return response()->json(['message' => 'Anda tidak berhak menghapus akomodasi ini.'], 403);
        }

        $akomodasi->delete();

        return response()->json(['message' => 'Akomodasi berhasil dihapus.']);
    }
}
