# Sistem Inventaris Aset

Aplikasi pencatatan aset IT: pendaftaran aset, serah terima ke karyawan, servis dan upgrade, mutasi antar cabang, pelepasan aset, lampiran berkas, serta laporan yang bisa dicetak dan diekspor.

Dibangun dengan Laravel 13 dan Filament 5. Seluruh antarmuka berada di panel admin — tidak ada halaman publik.

## Kebutuhan Sistem

| Kebutuhan | Versi | Catatan |
|---|---|---|
| PHP | 8.3 atau lebih baru | ekstensi standar Laravel: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd` |
| Composer | 2.x | |
| MySQL / MariaDB | MySQL 8.x | |
| Node.js | 20 atau lebih baru | hanya untuk membangun aset front-end |

Ekstensi `gd` dipakai DomPDF saat menghasilkan berkas PDF laporan. Ekstensi `fileinfo` dipakai untuk mendeteksi tipe berkas lampiran.

Pengguna Laragon, XAMPP, atau Herd sudah mendapat PHP, MySQL, dan Composer sekaligus.

## Langkah Pemasangan

### 1. Ambil kode dan pasang dependensi

```bash
git clone <url-repositori> inventaris
cd inventaris
composer install
npm install
```

### 2. Siapkan berkas konfigurasi

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Arahkan ke MySQL

`.env.example` bawaan Laravel memakai SQLite. Aplikasi ini memakai MySQL, jadi ubah bagian database di `.env` menjadi:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=inventaris
DB_USERNAME=root
DB_PASSWORD=
```

Sesuaikan `DB_USERNAME` dan `DB_PASSWORD` dengan MySQL di komputer kamu.

Sekalian sesuaikan identitas aplikasi:

```dotenv
APP_NAME="Inventaris Aset"
APP_URL=http://localhost:8000
APP_LOCALE=id
```

`APP_LOCALE=id` membuat pesan validasi dan format tanggal Filament tampil dalam bahasa Indonesia.

### 4. Buat database

Buat database kosong bernama `inventaris` lewat phpMyAdmin, HeidiSQL, atau baris perintah:

```bash
mysql -u root -e "CREATE DATABASE inventaris CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 5. Jalankan migrasi dan isi data master

```bash
php artisan migrate
php artisan db:seed --class=MasterSeeder
```

`MasterSeeder` wajib dijalankan. Isinya data acuan yang dipakai seluruh aplikasi: status aset, kategori, kondisi, alasan pelepasan, jenis servis, hasil servis, jenis lampiran, dan dua cabang awal (Jakarta dan Batam). Tanpa ini form aset tidak punya pilihan apa pun.

### 6. Bangun aset front-end

```bash
npm run build
```

CSS panel admin (`public/css/`) sudah ikut tersimpan di repositori, jadi panel tetap tampil rapi walau langkah ini dilewati. Membangun aset tetap disarankan agar konsisten.

## Mengisi Data Simulasi

Opsional, tapi sangat membantu untuk mencoba aplikasi dan melihat laporan terisi.

```bash
php artisan db:seed --class=DemoDataSeeder
```

Seeder ini menolak berjalan bila `APP_ENV=production` atau bila tabel aset sudah berisi data.

Isinya 21 aset, 12 karyawan, 8 surat serah terima, 28 transaksi, 4 catatan servis, dan 4 lampiran — dibuat lewat service aplikasi yang sama dengan yang dipakai antarmuka, sehingga perhitungan stok dan riwayat transaksinya konsisten.

Skenario yang sengaja disiapkan agar laporan tidak kosong:

- 2 karyawan nonaktif yang masih memegang aset
- 1 servis yang lewat estimasi dan 1 servis yang masih berjalan
- 1 pengiriman antar cabang yang belum diterima
- 1 aset rusak yang menunggu penjadwalan servis
- pelepasan aset dengan alasan dibuang dan hilang
- 1 surat serah terima yang masih berstatus draf
- lampiran berkas, salah satunya tertaut ke catatan servis

Seeder ini juga membuat dua akun:

| Email | Peran | Kata sandi |
|---|---|---|
| `admin@indosurta.test` | Admin Pusat | `password` |
| `batam@indosurta.test` | Admin Cabang Batam | `password` |

Akun cabang berguna untuk mencoba pembatasan data per cabang: pengguna cabang hanya melihat aset milik cabangnya.

Kata sandi di atas lemah dan hanya untuk pengembangan lokal. Jangan pakai seeder ini di server produksi.

### Membangun ulang dari nol

```bash
php artisan migrate:fresh
php artisan db:seed --class=MasterSeeder
php artisan db:seed --class=DemoDataSeeder
```

`php artisan migrate:fresh --seed` **tidak** cukup — perintah itu hanya menjalankan `DatabaseSeeder`, yang isinya sekadar satu akun uji berperan peninjau.

## Membuat Akun Pertama Tanpa Data Simulasi

Kalau kamu memulai dengan database bersih dan melewati `DemoDataSeeder`, buat akun admin sendiri:

```bash
php artisan make:filament-user
```

Perintah itu membuat akun berperan `viewer`. Naikkan menjadi admin pusat lewat Tinker:

```bash
php artisan tinker --execute="App\Models\User::where('email','emailkamu@contoh.test')->update(['role' => 'admin_pusat']);"
```

Peran yang tersedia: `admin_pusat` (semua cabang), `admin_cabang` (satu cabang, wajib mengisi `branch_id`), dan `viewer` (hanya membaca).

## Menjalankan Aplikasi

```bash
php artisan serve
```

Buka `http://localhost:8000`. Halaman depan langsung mengalihkan ke `/admin`.

Untuk pengembangan dengan pemuatan ulang otomatis, jalankan server, antrean, dan Vite sekaligus:

```bash
composer dev
```

Pengguna Laragon cukup mengarahkan virtual host ke folder `public/` dan mengakses `http://inventaris.test/admin`.

## Penyimpanan Lampiran

Lampiran aset disimpan di disk privat `local`, yaitu `storage/app/private/asset-attachments/`. Berkas tidak dapat diakses langsung lewat URL; Filament menyajikannya lewat tautan bertanda tangan yang kedaluwarsa.

Karena itu `php artisan storage:link` **tidak diperlukan** untuk fitur lampiran. Jalankan hanya bila kamu menambahkan fitur yang butuh berkas publik.

Pastikan folder `storage/` dan `bootstrap/cache/` dapat ditulis oleh proses web server.

## Perintah Harian

```bash
php artisan test              # menjalankan pengujian
vendor/bin/pint               # merapikan gaya penulisan kode
php artisan optimize:clear    # membersihkan cache konfigurasi, rute, dan tampilan
php artisan filament:optimize-clear   # membersihkan cache komponen Filament
```

Bersihkan cache Filament setiap kali kamu mengubah `AppServiceProvider` atau berkas skema form dan tabel, lalu muat ulang browser dengan `Ctrl+Shift+R`.

## Struktur Singkat

| Lokasi | Isi |
|---|---|
| `app/Filament/Resources/` | Modul CRUD: aset, servis, transaksi, serah terima, karyawan, dan data master |
| `app/Filament/Pages/Reports/` | Halaman laporan yang bisa dicetak dan diekspor |
| `app/Services/` | Aturan bisnis: serah terima, servis, mutasi cabang, perhitungan stok |
| `app/Filament/Support/AssetSelect.php` | Dropdown pemilih aset yang dipakai bersama beberapa form |
| `database/seeders/MasterSeeder.php` | Data acuan, wajib dijalankan |
| `database/seeders/DemoDataSeeder.php` | Data simulasi, opsional |

Seluruh pergerakan aset ditulis lewat kelas di `app/Services/`, bukan lewat penyimpanan model langsung, supaya kolom stok (`qty_out`, `qty_in`, `qty_writeoff`, `qty_available`) dan riwayat transaksi selalu sejalan.

## Masalah Umum

**`SQLSTATE[HY000] [2002] No connection could be made`** — MySQL belum berjalan. Nyalakan lewat Laragon atau XAMPP.

**Form aset kosong tanpa pilihan kategori, kondisi, atau cabang** — `MasterSeeder` belum dijalankan.

**`DemoDataSeeder` berhenti dengan pesan tabel aset sudah berisi data** — memang disengaja agar data yang ada tidak tertimpa. Bangun ulang dengan `migrate:fresh` bila memang ingin mengganti isinya.

**Perubahan pada form atau tabel tidak muncul** — jalankan `php artisan filament:optimize-clear`, lalu muat ulang browser dengan `Ctrl+Shift+R`.

**`Tests\Feature\ExampleTest` gagal dengan status 302** — pengujian bawaan Laravel itu mengharapkan halaman depan membalas 200, padahal aplikasi ini mengalihkan `/` ke `/admin`. Bukan tanda pemasangan gagal; tiga pengujian lainnya lulus.
