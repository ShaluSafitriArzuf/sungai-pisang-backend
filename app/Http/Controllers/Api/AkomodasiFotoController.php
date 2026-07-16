<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Akomodasi;
use App\Models\FotoAkomodasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AkomodasiFotoController extends Controller
{
    // POST /api/akomodasi/{akomodasi}/foto — pengelola pulau, tambah 1 foto tambahan (bukan cover)
    public function store(Request $request, Akomodasi $akomodasi)
    {
        $user = $request->user();

        if (! $user->isPengelolaPulau() || $user->pulau_id !== $akomodasi->pulau_id) {
            return response()->json(['message' => 'Anda tidak berhak mengubah akomodasi ini.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'foto' => 'required|file|mimes:jpeg,jpg,png,webp|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $path = $request->file('foto')->store('akomodasi-tambahan', 'public');

        $foto = FotoAkomodasi::create([
            'akomodasi_id' => $akomodasi->id,
            'foto' => asset('storage/'.$path),
            'urutan' => $akomodasi->fotoTambahan()->count(),
        ]);

        return response()->json(['message' => 'Foto berhasil ditambahkan.', 'data' => $foto], 201);
    }

    // DELETE /api/akomodasi/{akomodasi}/foto/{fotoAkomodasi}
    public function destroy(Request $request, Akomodasi $akomodasi, FotoAkomodasi $fotoAkomodasi)
    {
        $user = $request->user();

        if (! $user->isPengelolaPulau() || $user->pulau_id !== $akomodasi->pulau_id || $fotoAkomodasi->akomodasi_id !== $akomodasi->id) {
            return response()->json(['message' => 'Anda tidak berhak menghapus foto ini.'], 403);
        }

        $prefix = asset('storage').'/';
        if (str_starts_with($fotoAkomodasi->foto, $prefix)) {
            Storage::disk('public')->delete(str_replace($prefix, '', $fotoAkomodasi->foto));
        }

        $fotoAkomodasi->delete();

        return response()->json(['message' => 'Foto berhasil dihapus.']);
    }
}
