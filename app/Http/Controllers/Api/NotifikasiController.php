<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    public function index(Request $request)
    {
        // per_page dulunya dihardcode 20 -- sama seperti bug yang sudah dibetulkan di
        // ReservasiController: halaman Notifikasi & badge jumlah belum dibaca (lihat
        // useJumlahNotifBelumDibaca.js) cuma baca HALAMAN PERTAMA tanpa loop ke halaman
        // berikutnya, jadi notifikasi lama "hilang" dari daftar begitu totalnya lewat 20 --
        // dan parahnya, TIDAK ADA cara buka/tandai notifikasi lama itu sama sekali dari UI.
        $perPage = min(500, max(1, (int) $request->input('per_page', 20)));

        $notifikasi = Notifikasi::where('user_id', $request->user()->id)
            ->latest()
            ->paginate($perPage);

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
