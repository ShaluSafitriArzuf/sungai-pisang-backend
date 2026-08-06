<?php

namespace Database\Seeders;

use App\Models\Akomodasi;
use App\Models\FotoAkomodasi;
use App\Models\Pulau;
use Illuminate\Database\Seeder;

/**
 * Seeder AMAN untuk memperbarui data akomodasi tanpa menghapus data lain.
 *
 * Berbeda dengan DatabaseSeeder yang hanya cocok dijalankan di database kosong, seeder ini:
 *   - MEMPERBARUI akomodasi yang sudah ada (dicocokkan berdasarkan nama), tidak membuat ganda
 *   - MEMBUAT akomodasi yang belum ada
 *   - MENGHAPUS akomodasi bertipe tenda (digantikan opsi "Bawa Tenda Sendiri" saat reservasi)
 *   - TIDAK menyentuh tabel users, reservasi, ulasan, notifikasi, maupun manifest
 *
 * Cara menjalankan:
 *     php artisan db:seed --class=AkomodasiSeeder
 */
class AkomodasiSeeder extends Seeder
{
    public function run(): void
    {
        $pulauSemua = Pulau::orderBy('id')->get();

        if ($pulauSemua->isEmpty()) {
            $this->command->error('Tabel pulau masih kosong.');
            return;
        }

        $diperbarui = 0;
        $dibuat = 0;
        $dihapus = 0;

        foreach ($pulauSemua as $pulau) {
            // Akomodasi bertipe tenda dihapus: wisatawan yang ingin berkemah cukup memilih
            // "Bawa Tenda Sendiri" pada form reservasi, tanpa biaya akomodasi.
            $tenda = Akomodasi::where('pulau_id', $pulau->id)->where('tipe', 'tenda')->get();
            foreach ($tenda as $t) {
                // Hanya dihapus kalau belum pernah dipakai reservasi, supaya riwayat tidak rusak.
                if ($t->reservasi()->count() === 0) {
                    $t->delete();
                    $dihapus++;
                } else {
                    $t->update(['aktif' => false]);
                    $this->command->warn("  \"{$t->nama}\" dinonaktifkan (sudah dipakai reservasi, tidak dihapus).");
                }
            }

            foreach ($this->daftarAkomodasi($pulau->nama) as $item) {
                $fotoTambahan = $item['foto_tambahan'];
                unset($item['foto_tambahan']);

                $akomodasi = Akomodasi::where('pulau_id', $pulau->id)
                    ->where('nama', $item['nama'])
                    ->first();

                if ($akomodasi) {
                    $akomodasi->update($item);
                    $diperbarui++;
                } else {
                    $akomodasi = Akomodasi::create(array_merge($item, [
                        'pulau_id' => $pulau->id,
                        'aktif' => true,
                    ]));
                    $dibuat++;
                }

                // Foto tambahan ditulis ulang supaya tidak menumpuk kalau seeder dijalankan
                // lebih dari sekali.
                FotoAkomodasi::where('akomodasi_id', $akomodasi->id)->delete();
                foreach ($fotoTambahan as $urutan => $foto) {
                    FotoAkomodasi::create([
                        'akomodasi_id' => $akomodasi->id,
                        'foto' => $foto,
                        'urutan' => $urutan + 1,
                    ]);
                }
            }
        }

        $this->command->info("Selesai. {$diperbarui} akomodasi diperbarui, {$dibuat} dibuat baru, {$dihapus} tenda dihapus.");
        $this->command->info('Data akun, reservasi, ulasan, dan notifikasi tidak diubah sama sekali.');
    }

    private function daftarAkomodasi(string $namaPulau): array
    {
        return [
            [
                'nama' => 'Gazebo '.$namaPulau,
                'tipe' => 'gazebo',
                'harga_per_malam' => 150000,
                'jumlah_unit' => 12,
                'kapasitas' => 6,
                'fasilitas' => ['matras', 'listrik'],
                'deskripsi' => 'Pondok terbuka di tepi pantai beralas karpet, tersedia colokan listrik. Cocok untuk bersantai bersama rombongan kecil.',
                'foto' => 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=700&q=80',
                'foto_tambahan' => [
                    'https://images.unsplash.com/photo-1499696010180-025ef29a4f38?w=700&q=80',
                    'https://images.unsplash.com/photo-1439066615861-d1af74d74000?w=700&q=80',
                    'https://images.unsplash.com/photo-1528127269322-539801943592?w=700&q=80',
                ],
            ],
            [
                'nama' => 'Gazebo Keluarga '.$namaPulau,
                'tipe' => 'gazebo',
                'harga_per_malam' => 200000,
                'jumlah_unit' => 8,
                'kapasitas' => 8,
                'fasilitas' => ['matras', 'listrik'],
                'deskripsi' => 'Pondok terbuka ukuran besar beralas karpet, muat hingga 8 orang, dekat area kuliner pulau.',
                'foto' => 'https://images.unsplash.com/photo-1533105079780-92b9be482077?w=700&q=80',
                'foto_tambahan' => [
                    'https://images.unsplash.com/photo-1470770841072-f978cf4d019e?w=700&q=80',
                    'https://images.unsplash.com/photo-1504280390367-361c6d9f38f4?w=700&q=80',
                    'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?w=700&q=80',
                ],
            ],
            [
                'nama' => 'Cottage '.$namaPulau,
                'tipe' => 'cottage',
                'harga_per_malam' => 250000,
                'jumlah_unit' => 10,
                'kapasitas' => 4,
                'fasilitas' => ['kipas_angin', 'kamar_mandi_dalam', 'kasur', 'listrik', 'teras'],
                'deskripsi' => 'Cottage tertutup dengan kipas angin dan kamar mandi dalam, kapasitas 2-4 orang.',
                'foto' => 'https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?w=700&q=80',
                'foto_tambahan' => [
                    'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?w=700&q=80',
                    'https://images.unsplash.com/photo-1584132967334-10e028bd69f7?w=700&q=80',
                    'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?w=700&q=80',
                ],
            ],
            [
                'nama' => 'Cottage Premium '.$namaPulau,
                'tipe' => 'cottage',
                'harga_per_malam' => 450000,
                'jumlah_unit' => 5,
                'kapasitas' => 6,
                'fasilitas' => ['ac', 'kamar_mandi_dalam', 'kasur', 'listrik', 'teras'],
                'deskripsi' => 'Cottage lebih luas dan ber-AC dengan pemandangan terbaik, kapasitas hingga 6 orang.',
                'foto' => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=700&q=80',
                'foto_tambahan' => [
                    'https://images.unsplash.com/photo-1590490360182-c33d57733427?w=700&q=80',
                    'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=700&q=80',
                    'https://images.unsplash.com/photo-1618773928121-c32242e63f39?w=700&q=80',
                ],
            ],
        ];
    }
}
