<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    public function index(Request $request)
    {
        $notifikasi = Notifikasi::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json($notifikasi);
    }

    public function markAsRead(Request $request, Notifikasi $notifikasi)
    {
        if ($notifikasi->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Anda tidak berhak mengubah notifikasi ini.'], 403);
        }

        $notifikasi->update(['is_read' => true]);

        return response()->json(['message' => 'Notifikasi ditandai sudah dibaca.']);
    }
}
