<?php

namespace Database\Seeders;

use App\Models\Akomodasi;
use App\Models\FotoAkomodasi;
use App\Models\GaleriFotoPulau;
use App\Models\Pulau;
use App\Models\User;
use App\Models\WahanaKegiatan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── 5 Pulau ──────────────────────────────────
        $namaPulau = [
            [
                'nama' => 'Pulau Pasumpahan', 'harga_one_day' => 25000, 'harga_menginap' => 35000, 'harga_penyeberangan' => 35000, 'luas' => 5, 'badge' => 'Snorkeling',
                'latitude' => -1.1188946237082842, 'longitude' => 100.36792745083592,
                'deskripsi' => 'Pulau Pasumpahan dikenal sebagai "Maldives-nya Sumatera" berkat pasir putih yang membentang halus dan air laut sebening kristal. Pulau ini sangat cocok untuk snorkeling santai maupun sekadar menikmati matahari terbenam di tepi pantai bersama keluarga atau teman.',
                'fasilitas' => ['toilet', 'mushola', 'warung_makan', 'gazebo_umum', 'dermaga', 'spot_foto'],
            ],
            [
                'nama' => 'Pulau Sirandah', 'harga_one_day' => 30000, 'harga_menginap' => 60000, 'harga_penyeberangan' => 50000, 'luas' => 3, 'badge' => 'Camping',
                'latitude' => -1.121956886117603, 'longitude' => 100.34015294010803,
                'deskripsi' => 'Pulau Sirandah merupakan permata tersembunyi di pesisir Padang. Dikenal dengan julukan "Pulau Kucing", destinasi ini menawarkan pasir putih yang halus dan air laut yang tenang, sangat ideal untuk berenang, camping semalam, dan bersantai bersama keluarga.',
                'fasilitas' => ['toilet', 'gazebo_umum', 'dermaga', 'tempat_sampah'],
            ],
            [
                'nama' => 'Pulau Ujung Kapuri', 'harga_one_day' => 30000, 'harga_menginap' => 40000, 'harga_penyeberangan' => 35000, 'luas' => 2, 'badge' => 'Memancing',
                'latitude' => -1.1225551284474338, 'longitude' => 100.36857490315879,
                'deskripsi' => 'Pulau Ujung Kapuri menawarkan suasana yang lebih tenang dan asri, cocok bagi wisatawan yang ingin memancing atau sekadar menikmati ketenangan jauh dari keramaian. Perairan di sekitarnya kaya akan ikan karang dan jernih sepanjang tahun.',
                'fasilitas' => ['dermaga', 'tempat_sampah'],
            ],
            [
                'nama' => 'Pulau Pagang', 'harga_one_day' => 30000, 'harga_menginap' => 50000, 'harga_penyeberangan' => 50000, 'luas' => 6, 'badge' => 'Diving',
                'latitude' => -1.1570946176856034, 'longitude' => 100.35000497904498,
                'deskripsi' => 'Pulau Pagang menyimpan keindahan bawah laut yang memukau dengan terumbu karang yang masih terjaga, menjadikannya destinasi favorit para penyelam. Pemandangan lautnya yang biru jernih menambah pesona pulau ini, cocok untuk wisata diving maupun snorkeling.',
                'fasilitas' => ['toilet', 'mushola', 'warung_makan', 'dermaga', 'area_parkir', 'spot_foto'],
            ],
            [
                'nama' => 'Pulau Pamutusan', 'harga_one_day' => 30000, 'harga_menginap' => 50000, 'harga_penyeberangan' => 50000, 'luas' => 4, 'badge' => 'Snorkeling',
                'latitude' => -1.1615901728999332, 'longitude' => 100.35137213604911,
                'deskripsi' => 'Pulau Pamutusan memiliki garis pantai yang landai dan tenang dengan hamparan pasir putih bersih, sempurna untuk snorkeling maupun bersantai menikmati suasana pantai yang masih alami dan jauh dari keramaian.',
                'fasilitas' => ['toilet', 'gazebo_umum', 'dermaga', 'spot_foto', 'tempat_sampah'],
            ],
        ];

        $pulauList = [];
        foreach ($namaPulau as $data) {
            $pulauList[] = Pulau::create([
                'nama' => $data['nama'],
                'deskripsi' => $data['deskripsi'],
                'badge' => $data['badge'],
                'luas' => $data['luas'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'harga_tiket_masuk_one_day' => $data['harga_one_day'],
                'harga_tiket_masuk_menginap' => $data['harga_menginap'],
                'harga_penyeberangan' => $data['harga_penyeberangan'],
                'jam_operasional_genset' => '18.00 - 06.00 WIB',
                'regulasi' => 'Dilarang membuang sampah sembarangan. Jaga kebersihan & kelestarian pulau.',
                'fasilitas' => $data['fasilitas'],
            ]);
        }

        // ── 1 Akun Wisatawan ─────────────────────────
        // email_verified_at diisi langsung — akun seeder/demo ini dibuat manual oleh kita
        // sendiri (bukan lewat form Registrasi publik), jadi tidak perlu ikut alur verifikasi
        // email. Kalau tidak diisi, akun ini malah tidak akan bisa dipakai login sama sekali
        // sejak fitur verifikasi email aktif.
        User::create([
            'name' => 'Wisatawan Contoh',
            'email' => 'wisatawan@test.com',
            'password' => Hash::make('password'),
            'role' => 'wisatawan',
            'no_hp' => '081234567890',
            'email_verified_at' => now(),
        ]);

        // ── 1 Akun Pengantar Pulau ───────────────────
        User::create([
            'name' => 'Pengantar Pulau',
            'email' => 'pengantar@test.com',
            'password' => Hash::make('password'),
            'role' => 'pengantar_pulau',
            'no_hp' => '081234567891',
            'latitude' => -1.11664604555515,
            'longitude' => 100.3868464247046,
            'email_verified_at' => now(),
        ]);

        // ── 5 Akun Pengelola Pulau (1 akun = 1 pulau, sesuai syarat dosen) ──
        foreach ($pulauList as $i => $pulau) {
            $slug = strtolower(str_replace(['Pulau ', ' '], ['', '-'], $pulau->nama));
            User::create([
                'name' => 'Pengelola '.$pulau->nama,
                'email' => 'pengelola.'.$slug.'@test.com',
                'password' => Hash::make('password'),
                'role' => 'pengelola_pulau',
                'no_hp' => '08123456780'.$i,
                'pulau_id' => $pulau->id,
                'email_verified_at' => now(),
            ]);

            // Contoh akomodasi (cottage biasa, cottage premium, dan gazebo) + wahana per pulau.
            // Tidak ada akomodasi bertipe tenda: wisatawan yang ingin berkemah memilih opsi
            // "Bawa Tenda Sendiri" pada form reservasi, yang tidak dikenakan biaya akomodasi.
            //
            // Tiap akomodasi diberi 1 foto utama (kolom akomodasi.foto, tampil di kartu daftar)
            // dan 3 foto tambahan (tabel foto_akomodasi) supaya carousel di halaman Detail Pulau
            // langsung bisa digeser tanpa perlu diunggah manual satu per satu.
            $daftarAkomodasi = [
                [
                    'nama' => 'Gazebo '.$pulau->nama,
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
                    'nama' => 'Gazebo Keluarga '.$pulau->nama,
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
                    'nama' => 'Cottage '.$pulau->nama,
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
                    'nama' => 'Cottage Premium '.$pulau->nama,
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

            foreach ($daftarAkomodasi as $item) {
                $fotoTambahan = $item['foto_tambahan'];
                unset($item['foto_tambahan']);

                $akomodasi = Akomodasi::create(array_merge($item, ['pulau_id' => $pulau->id]));

                foreach ($fotoTambahan as $urutan => $foto) {
                    FotoAkomodasi::create([
                        'akomodasi_id' => $akomodasi->id,
                        'foto' => $foto,
                        'urutan' => $urutan + 1,
                    ]);
                }
            }

            // Wahana & Aktivitas: murni info daya tarik di pulau ini, TIDAK berbayar &
            // TIDAK bisa ditambahkan sebagai biaya di form reservasi.
            WahanaKegiatan::create([
                'pulau_id' => $pulau->id,
                'nama' => 'Snorkeling',
                'deskripsi' => 'Area terumbu karang dangkal dekat pantai, cocok untuk pemula. Bawa alat sendiri atau sewa di lokasi.',
            ]);

            WahanaKegiatan::create([
                'pulau_id' => $pulau->id,
                'nama' => 'Banana Boat',
                'deskripsi' => 'Tersedia di pantai utama saat ramai pengunjung, minimal 5 orang per sesi.',
                'berbayar_lokasi' => true,
            ]);

            WahanaKegiatan::create([
                'pulau_id' => $pulau->id,
                'nama' => 'Donat Boat',
                'deskripsi' => 'Wahana pelampung bentuk donat ditarik speedboat, seru dinaiki rame-rame bareng teman/keluarga.',
                'berbayar_lokasi' => true,
            ]);

            if ($pulau->nama === 'Pulau Pasumpahan') {
                WahanaKegiatan::create([
                    'pulau_id' => $pulau->id,
                    'nama' => 'Puncak Pasumpahan',
                    'deskripsi' => 'Trekking santai ke atas bukit, pemandangan seluruh pulau & laut lepas dari puncak — spot favorit untuk foto.',
                ]);
            }

            // Galeri Foto & Video: dokumentasi keseruan di pulau ini (contoh/placeholder —
            // pengelola bisa ganti/tambah lewat halaman "Kelola Galeri").
            $fotoGaleri = [
                'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=700&q=80',
                'https://images.unsplash.com/photo-1519046904884-53103b34b206?w=700&q=80',
                'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=700&q=80',
                'https://images.unsplash.com/photo-1471922694854-ff1b63b20054?w=700&q=80',
            ];
            foreach ($fotoGaleri as $foto) {
                GaleriFotoPulau::create(['pulau_id' => $pulau->id, 'foto' => $foto, 'tipe' => 'foto']);
            }
            GaleriFotoPulau::create([
                'pulau_id' => $pulau->id,
                'foto' => 'https://storage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
                'tipe' => 'video',
            ]);
        }
    }
}
