# Tejas Coffee POS — Sistem Pemesanan Coffee Shop

Aplikasi pemesanan coffee shop berbasis **Laravel 12** + **MySQL** dengan empat peran pengguna
(admin, kasir, barista, pelanggan), pemesanan mandiri lewat **QR meja**, pembayaran **Midtrans**
(QRIS & virtual account), serta **KDS** (kitchen/bar display) untuk barista.

## Tumpukan Teknologi

| Komponen | Versi / Keterangan |
|---|---|
| PHP | ^8.2 |
| Laravel | ^12.0 |
| Database | MySQL (nama DB: `coffee_shop_order`) |
| Frontend | Blade + Tailwind CSS 4 + Vite 7 + Lucide icons |
| Pembayaran | `midtrans/midtrans-php` (Core API: QRIS, VA bank) |
| QR meja | `bacon/bacon-qr-code` |
| Test | PHPUnit 11 (`php artisan test`) |

## Fitur per Peran

### Admin (`/admin/dashboard`)
- Ringkasan toko: jumlah pengguna per peran, penjualan, stok kritis.
- CRUD akun pegawai (khusus peran kasir & barista; admin tidak bisa menghapus dirinya sendiri).
- Sales & Revenue, Inventory & Roastery (termasuk *reorder* biji kopi), Staff & Roster.
- Customer CRM & Loyalty (tambah pelanggan, penyesuaian poin, hapus).
- Master data: kategori, produk, meja (+ cetak lembar QR), promo.
- Store Settings: profil toko, biaya layanan, poin per Rp 10.000, metode pembayaran,
  dan kredensial Midtrans (disimpan di tabel `midtrans_settings`, bukan `.env`).
- Kelola status pesanan dan pembatalan pesanan.

### Kasir (`/kasir/dashboard`)
- POS: tambah/ubah item pada draf tiket (session), lalu **Charge** menjadi pesanan.
- Papan status meja real-time (dipolling).
- Pembayaran tunai (dengan kalkulator kembalian), QRIS dinamis, dan virtual account.
- Cetak struk 80 mm, tandai lunas (poin pelanggan otomatis bertambah).

### Barista (`/barista/dashboard`)
- KDS: daftar pesanan aktif, **bump** (sematkan ke atas) dan **dismiss** (sembunyikan).
- Ubah status meja (kosong / terisi / dipesan).

### Pelanggan (`/pelanggan/dashboard`)
- Registrasi mandiri (hanya peran pelanggan), login via email **atau** nomor HP.
- Menu + filter kategori, kustomisasi item, keranjang, voucher, dan tukar poin.
- Check-in meja lewat scan QR (atau kode booking bila kamera tidak tersedia).
- Checkout (dine-in / takeaway / delivery), status pesanan real-time, riwayat, profil.
- Registrasi, login, dan scan QR meja juga tersedia untuk tamu tanpa akun.

## Struktur Database

| Tabel | Keterangan |
|---|---|
| `pengguna` | Akun login semua peran (`peran`: admin, kasir, barista, pelanggan) |
| `pelanggan` | Data member + `poin_loyalitas` |
| `kategori` | Kategori menu |
| `produk` | Menu: kode unik, harga, stok, status tersedia, gambar |
| `meja` | Nomor meja, kapasitas, `status`, `qr_token` acak untuk QR fisik |
| `reservasi_meja` | Sesi meja: `kode_booking`, status, waktu berakhir (90 menit) |
| `promo` | Voucher persen/nominal dengan masa berlaku |
| `pesanan` | Header pesanan: tipe, status, total, diskon, biaya layanan |
| `detail_pesanan` | Rincian item (snapshot harga satuan & subtotal) |
| `metode_pembayaran` | Tunai / QRIS / Transfer, penanda kembalian |
| `transaksi` | Pembayaran 1:1 dengan pesanan, status & referensi |
| `stok_biji`, `log_reorder` | Inventori biji kopi + riwayat dispatch reorder |
| `midtrans_settings` | Kredensial aktif Midtrans (diatur dari halaman Settings) |

Alur status pesanan: `baru → diproses → selesai`, atau `dibatalkan`.
Status pembayaran: `menunggu → lunas` / `gagal`.

## Instalasi

1. Pastikan MySQL berjalan (XAMPP/Laragon), lalu buat database:

   ```sql
   CREATE DATABASE IF NOT EXISTS coffee_shop_order
     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. Salin `.env.example` menjadi `.env`, sesuaikan koneksi:

   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=coffee_shop_order
   DB_USERNAME=root
   DB_PASSWORD=
   ```

3. Install dependensi, generate key, migrasi + seed, dan build aset:

   ```bash
   composer install
   npm install
   php artisan key:generate
   php artisan migrate --seed
   npm run build
   ```

4. Jalankan server:

   ```bash
   php artisan serve
   ```

   Untuk pengembangan dengan Vite HMR + log + queue sekaligus:

   ```bash
   composer dev
   ```

## Akun Demo (hasil seeder)

Semua akun memakai password **`password`**. Login bisa memakai email atau nomor HP.

| Peran | Email | Nomor HP |
|---|---|---|
| Admin | `admin@kopi.dev` | `081100000001` |
| Kasir | `kasir@kopi.dev` | `081200000001` |
| Barista | `barista@kopi.dev` | `081300000001` |
| Pelanggan | `pelanggan@kopi.dev` | `081234567890` |

Seeder juga menambahkan kategori, produk, meja (lengkap dengan `qr_token`), promo
(`WELCOME10`, `HEMAT5000`), metode pembayaran, dan data pelanggan contoh.

## Pembayaran Midtrans

Kredensial dapat diatur di dua tempat (yang paling baru menang):

1. Halaman **Admin → Store Settings → Midtrans** (disimpan di tabel `midtrans_settings`), atau
2. `.env`:

   ```env
   MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxxxxx
   MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxxxxxx
   MIDTRANS_IS_PRODUCTION=false
   ```

Isi **Payment Notification URL** di dashboard Midtrans dengan:

```
{APP_URL}/api/midtrans/callback
```

Webhook (`POST /api/midtrans/callback`, bebas CSRF) memverifikasi signature, memperbarui
status transaksi/pesanan, dan menambah poin pelanggan. Karena pengembangan biasanya berjalan
di localhost, status juga disinkronkan lewat polling dari halaman kasir/status pesanan.

## Penjadwalan

Sesi meja yang kedaluwarsa ditutup oleh command berikut dan dijadwalkan tiap menit
(`routes/console.php`):

```bash
php artisan meja:bersihkan
```

Saat produksi, jalankan scheduler lewat cron:

```
* * * * * cd /path/ke/proyek && php artisan schedule:run >> /dev/null 2>&1
```

Untuk development, `php artisan schedule:work` sudah cukup.

## Pengujian

```bash
php artisan test
```

Test berjalan di SQLite in-memory (lihat `phpunit.xml`), sehingga tidak menyentuh database
MySQL aplikasi. Cakupan mencakup autentikasi multi-role, alur pesanan pelanggan, QR meja,
status meja barista/pelanggan, POS kasir + Midtrans, inventori, dan endpoint Ops Agent.

Perapian format kode memakai Laravel Pint:

```bash
./vendor/bin/pint
```

## API

Semua endpoint berada di prefix `/api`. Endpoint pesanan wajib login (session) dan
harga selalu dihitung ulang di server.

| Method | Endpoint | Akses | Deskripsi |
|---|---|---|---|
| `GET` | `/categories` | publik | Daftar kategori |
| `GET` | `/products?category=&search=` | publik | Daftar menu + filter |
| `GET` | `/products/{kode_produk}` | publik | Detail menu |
| `POST` | `/orders` | login | Buat pesanan |
| `GET` | `/orders/{nomor_pesanan}` | login | Detail pesanan |
| `GET` | `/orders?status=` | kasir/barista | Daftar pesanan |
| `PATCH` | `/orders/{id}/status` | kasir/barista | Ubah status |
| `PATCH` | `/orders/{id}/cancel` | kasir/barista | Batalkan |
| `POST` | `/midtrans/callback` | Midtrans | Webhook pembayaran |
| `GET` | `/v1/ops/financial-summary` | — | Ringkasan keuangan (Ops Console) |
| `GET` | `/v1/ops/traffic-telemetry` | — | Telemetri trafik |
| `GET` | `/v1/inventory/alerts` | — | Biji kopi di bawah ambang |
| `POST` | `/v1/inventory/reorder` | — | Dispatch reorder ke roastery |

## Struktur Direktori Penting

```
app/Http/Controllers/Admin/     Ops Console (sales, inventory, staff, CRM, master data)
app/Http/Controllers/Auth/      Login & registrasi terpusat (email/nomor HP)
app/Http/Controllers/           Alur pelanggan, kasir, barista, QR meja, struk, Midtrans
app/Services/                   MidtransService, MejaQrService, Agents (sales/staffing/inventory)
app/Console/Commands/           meja:bersihkan (scheduler)
resources/views/                Blade per peran (admin, kasir, barista, pelanggan)
tests/Feature/                  Test integrasi lintas peran
screenshots/                    Tangkapan layar dokumentasi UI
```

Dokumentasi ringkas peta halaman per peran tersedia di
[`ROLE_BASED_PAGES_SUMMARY.md`](ROLE_BASED_PAGES_SUMMARY.md).
