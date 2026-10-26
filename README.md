# Meera Coffee — Laravel

Konversi proyek `merracoffe` (Vercel + Node + Drizzle) ke **Laravel 13**. Tampilan pembeli dan dashboard admin tetap sama; backend dipindah ke Laravel dengan kontrak API yang identik (`/api/orders`, `/api/orders/{id}`, `/api/deploys`, `/api/reset`), sehingga `script.js` dan `admin.js` hampir tidak berubah.

## Menjalankan lokal

Butuh PHP ≥ 8.3 dan Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite      # database lokal (SQLite)
php artisan migrate --seed
php artisan serve                   # http://localhost:8000
php artisan schedule:work           # terminal lain: reset harian 00:00 WIB
```

- Pembeli: `http://localhost:8000/`
- Admin: `http://localhost:8000/admin` (tanpa login, seperti versi sebelumnya)

## Pemetaan dari versi lama

| Versi Vercel | Laravel |
| --- | --- |
| `public/index.html` | `resources/views/home.blade.php` |
| `public/admin/index.html` | `resources/views/admin/index.blade.php` |
| `public/styles.css`, `script.js` | `public/assets/` |
| `public/admin/admin.css`, `admin.js` | `public/assets/admin/` |
| `api/orders.ts` | `app/Http/Controllers/OrderController.php` |
| `api/deploys.ts` | `app/Http/Controllers/DeployController.php` |
| `api/reset.ts` + Vercel Cron | `orders:reset` di `routes/console.php` (scheduler) + `/api/reset` |
| `db/schema.ts`, `drizzle/` | `database/migrations/`, `app/Models/` |
| `db/daily.ts` (nomor harian, reset) | `Order::nextNumber()` dan `Order::purgeStale()` |
| `db/ensure.ts` (data awal) | `database/seeders/DatabaseSeeder.php` |
| `MENU_CATALOG` di `api/orders.ts` | `config/menu.php` |

## Catatan penting

- **Harga menu**: ubah di `config/menu.php` **dan** `resources/views/home.blade.php` (harus sama). Server hanya memakai harga dari `config/menu.php`.
- **Reset harian**: berjalan lewat scheduler Laravel, pembersihan otomatis pada request pertama tiap hari, dan endpoint `/api/reset`. Di server produksi, tambahkan cron: `* * * * * cd /path/ke/proyek && php artisan schedule:run >> /dev/null 2>&1`.
- **Zona waktu**: `APP_TIMEZONE` (default `Asia/Jakarta`) menentukan batas "hari ini" dan jadwal reset.
- **Database lain**: untuk Postgres/Neon, isi `DB_CONNECTION=pgsql` dan `DB_URL` di `.env`, lalu `php artisan migrate --seed`.
- **Menu Deploys**: pencatatan otomatis deployment Vercel dihapus (tidak relevan di Laravel). Riwayat dicatat manual lewat tombol *Catat Deploy*; kartu "Deployment Aktif" menampilkan versi PHP/Laravel.
- **Keamanan**: `/admin` dan `/api/orders` tetap terbuka tanpa login, sama seperti aslinya. Sebaiknya tambahkan autentikasi sebelum dipakai publik.

## Deploy ke Railway / Render (Docker)

1. Push folder proyek ke GitHub (`vendor/` dan `.env` sudah diabaikan lewat `.gitignore`).
2. Buat proyek dari repo itu. Dockerfile terdeteksi otomatis (di Render pilih runtime **Docker**).
3. Tambahkan database **PostgreSQL** di platform yang sama. Jangan pakai SQLite di sana: filesystem container tidak permanen.
4. Isi Variables pada service web:
   - `APP_KEY` = hasil `php artisan key:generate --show`
   - `APP_URL` = alamat https layanan Anda (isi setelah domain bawaan dibuat)
   - `APP_TIMEZONE` = `Asia/Jakarta`
   - `DB_CONNECTION` = `pgsql`
   - `DB_URL` = connection string Postgres dari platform
5. Buat domain bawaan platform, buka alamatnya, lalu cek `/admin`.

Saat container start, `docker/start.sh` otomatis menjalankan migrasi + seeder, menyalakan scheduler, lalu Apache. Reset harian tetap berjalan walau layanan sempat tidur, karena pembersihan juga dilakukan pada request pertama tiap hari.
