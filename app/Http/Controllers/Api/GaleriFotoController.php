<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GaleriFotoPulau;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class GaleriFotoController extends Controller
{
    // GET /api/galeri-foto?pulau_id= — khusus pengelola_pulau, dipakai halaman Kelola Galeri
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user->isPengelolaPulau()) {
            return response()->json(['message' => 'Hanya pengelola pulau yang bisa melihat galeri ini.'], 403);
        }

        return response()->json(
            GaleriFotoPulau::where('pulau_id', $user->pulau_id)->latest()->get()
        );
    }

    // POST /api/galeri-foto — menerima file asli (foto atau video) lewat multipart/form-data,
    // disimpan ke storage/app/public/galeri (bukan base64) supaya video besar tetap ringan di DB.
    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user->isPengelolaPulau()) {
            return response()->json(['message' => 'Hanya pengelola pulau yang bisa menambah foto.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'foto' => 'required|file|mimes:jpeg,jpg,png,webp,mp4,mov,avi,webm|max:102400', // maks 100MB
            'tipe' => 'sometimes|in:foto,video',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $file = $request->file('foto');
        $path = $file->store('galeri', 'public');
        $tipe = $request->input('tipe') ?: (str_starts_with($file->getMimeType(), 'video/') ? 'video' : 'foto');

        $foto = GaleriFotoPulau::create([
            'pulau_id' => $user->pulau_id,
            'foto' => asset('storage/'.$path),
            'tipe' => $tipe,
        ]);

        return response()->json(['message' => 'Berhasil ditambahkan ke galeri.', 'data' => $foto], 201);
    }

    public function destroy(Request $request, GaleriFotoPulau $galeriFoto)
    {
        $user = $request->user();

        if (! $user->isPengelolaPulau() || $user->pulau_id !== $galeriFoto->pulau_id) {
            return response()->json(['message' => 'Anda tidak berhak menghapus foto ini.'], 403);
        }

        // Kalau filenya tersimpan di storage lokal kita, hapus juga file fisiknya biar tidak numpuk.
        $prefix = asset('storage').'/';
        if (str_starts_with($galeriFoto->foto, $prefix)) {
            Storage::disk('public')->delete(str_replace($prefix, '', $galeriFoto->foto));
        }

        $galeriFoto->delete();

        return response()->json(['message' => 'Foto berhasil dihapus.']);
    }
}
