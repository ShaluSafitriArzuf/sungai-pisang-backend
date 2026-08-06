# Catatan Perubahan — Backend

Ringkasan perubahan pada sistem informasi reservasi wisata bahari Sungai Pisang.
Berkas ini dipakai sebagai rujukan saat memperbarui laporan Tugas Akhir.

---

## 8 Agustus 2026

### Perubahan struktur basis data

**Migration baru:**

| Berkas | Perubahan |
|---|---|
| `2026_08_08_000001_split_harga_tiket_and_add_penyeberangan_to_pulau_table.php` | Kolom `harga_tiket_masuk` diganti nama menjadi `harga_tiket_masuk_one_day`; ditambah `harga_tiket_masuk_menginap` dan `harga_penyeberangan` |
| `2026_08_08_000002_add_kapasitas_and_fasilitas_to_akomodasi_table.php` | Ditambah kolom `kapasitas` dan `fasilitas` (JSON) |
| `2026_08_08_000003_add_jumlah_unit_dipesan_to_reservasi_table.php` | Ditambah kolom `jumlah_unit_dipesan` |

Jumlah tabel tetap **10**, yang berubah hanya kamus data.

### Perubahan aturan bisnis

1. **Tarif per pulau.** Tarif penyeberangan tidak lagi berupa nilai global pada
   `config/services.php`, melainkan disimpan per pulau. Tarif tiket masuk juga dibedakan
   antara One Day Trip dan Menginap.

   Formula biaya menjadi:
   ```
   biaya_penyeberangan = harga_penyeberangan × jumlah_orang
   biaya_tiket_masuk   = harga_tiket_masuk_(one_day|menginap) × jumlah_orang
   biaya_akomodasi     = harga_per_malam × jumlah_malam × jumlah_unit_dipesan
   total_bayar         = penyeberangan + tiket_masuk + akomodasi
   ```

2. **Pemesanan beberapa unit akomodasi.** Satu reservasi kini dapat memesan lebih dari
   satu unit, sehingga rombongan yang melebihi kapasitas satu unit tetap dapat dilayani.
   Perhitungan sisa unit pada `Akomodasi::unitTerpakaiPadaTanggal()` diubah dari
   `count()` menjadi `sum('jumlah_unit_dipesan')`.

3. **Reservasi minimal H-1.** Validasi `tanggal_kunjungan` diubah dari
   `after_or_equal:today` menjadi `after:today`. Pengantar Pulau memerlukan waktu untuk
   memverifikasi bukti transfer dan menyusun manifest sebelum hari keberangkatan.

4. **Pembatalan kedaluwarsa ditolak otomatis.** Pengajuan pembatalan yang belum ditanggapi
   sampai melewati batas H-2 otomatis ditolak sistem, status reservasi kembali menjadi
   `valid`, dan wisatawan menerima notifikasi.

5. **Zona waktu aplikasi.** `config/app.php` diubah dari `UTC` menjadi `Asia/Jakarta`.
   Dengan UTC, seluruh aturan tanggal (batas H-2, penandaan selesai, tanggal manifest)
   tertinggal 7 jam dari waktu setempat.

### Perubahan lain

- **Manifest PDF** dilengkapi nomor HP wisatawan, jumlah unit akomodasi, keterangan
  "Bawa Tenda Sendiri", waktu cetak, ringkasan total, dan kolom tanda tangan.
- **Endpoint manifest** mengembalikan `jumlah_unit_akomodasi`.
- **`config/cors.php`** ditambahkan; domain yang diizinkan dibatasi pada `FRONTEND_URL`.
- **`.env.production.example`** ditambahkan sebagai panduan konfigurasi hosting.
- **Seeder:** akomodasi bertipe tenda dihapus (digantikan opsi "Bawa Tenda Sendiri" saat
  reservasi); tiap akomodasi diberi kapasitas, fasilitas, satu foto utama, dan tiga foto
  tambahan.
- **`AkomodasiSeeder`** ditambahkan — memperbarui data akomodasi tanpa menghapus data lain.
- **`ReservasiDemoSeeder`** ditambahkan — membuat reservasi contoh pada seluruh pulau
  dengan semua status, beserta ulasan, notifikasi, dan manifest.

---

## Perintah setelah menarik perubahan ini

```bash
composer install
php artisan migrate

# Opsional, mengisi data contoh akomodasi tanpa menghapus data lain
php artisan db:seed --class=AkomodasiSeeder
```

## Perintah saat deploy ke hosting

```bash
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
```

Salin `.env.production.example` menjadi `.env`, lalu isi seluruh nilai bertanda GANTI.
