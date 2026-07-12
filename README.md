# Backend Laravel — Sistem Reservasi & Peta Wisata Bahari Sungai Pisang

Skema final: 10 tabel, multi-tenant Pengelola Pulau (5 akun terpisah), fitur pengajuan
pembatalan reservasi (H-2), race-condition-safe pada kuota akomodasi.

Jalankan semua command di bawah dari Terminal/CMD di komputer kamu (bukan di sini),
di folder tempat kamu mau simpan project.

## 1. Buat project Laravel baru

```bash
composer create-project laravel/laravel backend
cd backend
```

## 2. Install package tambahan

```bash
composer require laravel/sanctum
composer require barryvdh/laravel-dompdf
```

## 3. Copy file-file custom dari folder ini

Salin (timpa/tambahkan) folder & file berikut dari paket yang saya berikan ke dalam folder `backend` project kamu:

```
database/migrations/2024_01_01_*.php   → backend/database/migrations/
database/seeders/DatabaseSeeder.php    → backend/database/seeders/ (timpa file default)
app/Models/*.php                       → backend/app/Models/ (timpa User.php default)
app/Http/Controllers/Api/*.php         → backend/app/Http/Controllers/Api/ (buat folder Api dulu)
app/Http/Middleware/CheckRole.php      → backend/app/Http/Middleware/
routes/api.php                         → backend/routes/ (timpa file default; jika belum ada, lihat langkah 4)
resources/views/manifest/pdf.blade.php → backend/resources/views/manifest/
```

## 4. Aktifkan routes/api.php (Laravel 11 default tidak mengaktifkannya otomatis)

Buka `backend/bootstrap/app.php`, ubah bagian `withRouting` jadi seperti ini:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

Lalu daftarkan middleware alias `role` di bagian `withMiddleware` pada file yang sama:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role' => \App\Http\Middleware\CheckRole::class,
    ]);
})
```

> Catatan: jika kamu pakai Laravel 10 ke bawah, daftarkan alias itu di `app/Http/Kernel.php`
> pada array `$middlewareAliases` sebagai gantinya.

## 5. Tambahkan konfigurasi tarif penyeberangan

Tarif penyeberangan bersifat global (bukan per-pulau), jadi disimpan sebagai config, bukan tabel baru.
Tambahkan ini di `backend/config/services.php`:

```php
'penyeberangan' => [
    'tarif_per_orang' => env('TARIF_PENYEBERANGAN_PER_ORANG', 50000),
],
```

Lalu tambahkan baris ini di `backend/.env`:

```
TARIF_PENYEBERANGAN_PER_ORANG=50000
```

## 6. Konfigurasi database (.env)

Buka `backend/.env`, sesuaikan (contoh pakai MySQL dari XAMPP/Laragon):

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sungai_pisang
DB_USERNAME=root
DB_PASSWORD=
```

Buat database `sungai_pisang` kosong dulu lewat phpMyAdmin/HeidiSQL.

## 7. Migrasi & isi data contoh

```bash
php artisan storage:link
php artisan migrate
php artisan db:seed
```

## 8. Jalankan server

```bash
php artisan serve
```

Backend jalan di `http://localhost:8000`, API bisa diakses di `http://localhost:8000/api/...`.

## 9. Akun contoh untuk testing (semua password: `password`)

| Role | Email |
|---|---|
| Wisatawan | wisatawan@test.com |
| Pengantar Pulau | pengantar@test.com |
| Pengelola — Pasumpahan | pengelola.pasumpahan@test.com |
| Pengelola — Sirandah | pengelola.sirandah@test.com |
| Pengelola — Ujung Kapuri | pengelola.ujung-kapuri@test.com |
| Pengelola — Pagang | pengelola.pagang@test.com |
| Pengelola — Pamutusan | pengelola.pamutusan@test.com |

## 10. Test cepat pakai curl/Postman

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"wisatawan@test.com","password":"password"}'
```

Copy `token` dari response, lalu pakai di header setiap request lain:

```
Authorization: Bearer <token>
```

## Catatan penting (jangan dihapus)

- **Race condition kuota akomodasi**: `ReservasiController@store` sudah pakai
  `DB::transaction()` + `lockForUpdate()` supaya 2 wisatawan tidak bisa lolos berdua
  untuk kuota tersisa terakhir. Jangan diganti jadi pengecekan biasa tanpa lock.
- **Pengelola Pulau = 5 akun terpisah.** Setiap akun hanya bisa CRUD data pulau miliknya
  sendiri (dicek lewat `$user->pulau_id` di semua controller terkait).
- File upload (foto, bukti transfer) di controller ini menerima **string path** yang
  sudah diupload lebih dulu — kamu perlu tambahkan endpoint upload file terpisah
  (pakai `Storage::disk('public')->put(...)`) kalau mau terima file langsung dari form,
  atau proses upload dari sisi React sebelum submit ke endpoint reservasi/galeri/akomodasi.
