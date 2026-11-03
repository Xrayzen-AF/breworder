# Freebuff POS - Role-Based Pages Summary

Generated for AI-assisted page creation / Stitch AI workflow.

---

## 👑 ADMIN (`peran = 'admin'`)

| Halaman | Fungsi | URL |
| ------- | ------ | --- |
| **Dashboard** | Ringkasan total users, kasir, barista, pelanggan | `/admin/dashboard` (`admin.dashboard`) |
| **CRUD Pegawai** | Kelola akun Kasir & Barista (hanya admin yang bisa) | `/admin/employees/*` (`employees.*`) |
| **Sales & Revenue** | Lihat data penjualan (hanya baca) | `/admin/sales` (`admin.sales.index`) |
| **Inventory & Roastery** | Kelola stok & po reorder | `/admin/inventory/*` (`admin.inventory.*`) |
| **Staff & Roster** | Toggle aktif/nonaktif staff | `/admin/staff/*` (`admin.staff.*`) |
| **Customer CRM & Loyalty** | Kelola pelanggan & poin | `/admin/customers/*` (`admin.customers.*`) |
| **Store Settings** | Edit konfigurasi toko (JSON file) | `/admin/settings/*` (`admin.settings.*`) |
| **Master Data** | Full CRUD Kategori, Produk, Meja, Promo | `/admin/master/*` (`admin.master.*`) |
| **Pesanan (Tabel)** | Kelola status transaksi, batalkan | `/admin/orders/*` (`admin.orders.*`) |

---

## 💰 KASIR (`peran = 'kasir'`)

| Halaman | Fungsi | URL |
| ------- | ------ | --- |
| **Dashboard** | Antarmuka kasir/barista | `/kasir/dashboard` (`kasir.dashboard`) |
| **Ticket POS** | Tambah/ubah item di session, buat pesanan (Charge) | `/kasir/ticket/*` (`kasir.ticket.*`) |
| **Status Pembayaran** | Patch status pesanan (dari web form) | `/pesanan/{pesanan}/status` (`pesanan.status`) |
| **Tandai Lunas** | Tandai lunas + berikan poin loyalti | `/pesanan/{pesanan}/lunas` (`pesanan.lunas`) |
| **Snap Token** | Generate Midtrans Snap token | `/pesanan/{pesanan}/snap-token` (`pesanan.snap`) |

---

## 🧹 BARISTA (`peran = 'barista'`)

| Halaman | Fungsi | URL |
| ------- | ------ | --- |
| **Dashboard** | Antarmuka barista | `/barista/dashboard` (`barista.dashboard`) |
| **KDS Control** | Bump (sematkan ke atas) & Dismiss (sembunyikan) | `/barista/bump/{pesanan}` (`bump`), `/barista/dismiss/{pesanan}` (`dismiss`) |

---

## 👤 PELAngGAN (`peran = 'pelanggan'`)

| Halaman | Fungsi | URL |
| ------- | ------ | --- |
| **Dashboard** | Halaman pelanggan | `/pelanggan/dashboard` (`pelanggan.dashboard`) |
| **Menu & Keranjang** | Lihat menu, kustom, tambah/kurangi keranjang | `/pelanggan/keranjang/*`, `/pelanggan/menu/*` (`menu.*`, `keranjang.*`) |
| **Checkout** | Simpan pesanan, apply voucher/poin | `/pelanggan/checkout` (`checkout.simpan`) |
| **Status Pesanan** | Cek status pesanan | `/pelanggan/pesanan/{nomorPesanan}` (`status`) |
| **Batal Pesanan** | Batalkan pesanan | `/pelanggan/pesanan/{pesanan}/batal` (`pesanan.batal`) |
| **Profil Member** | Lihat & edit data akun | `/pelanggan/profil` (`profil`, `profil.update`) |

---

## 🔐 GUEST (Tidak Login)

| Halaman | Fungsi | URL |
| ------- | ------ | --- |
| **Login Form** | Form login sentral | `/login` (`login`) |
| **Register** | Registrasi Pelanggan saja | `/register` (`register`) |
| **Halaman Utama** | Redirect ke login | `/` |

---

## 🌐 API Routes (biasanya butuh `auth` middleware)

| Endpoint | Fungsi |
| -------- | ------ |
| `GET /categories` | Daftar kategori (publik) |
| `GET /products` | Daftar produk (publik) |
| `GET /products/{kode_produk}` | Detail produk (publik) |
| `POST /orders` | Buat pesanan (butuh `auth`) |
| `GET /orders/{nomorPesanan}` | Tampilkan pesanan (butuh `auth`) |
| `POST /midtrans/callback` | Webhook Midtrans (aman dari CSRF) |

---

## 🔄 Alur Utama (Flow)

### 1. **Pelanggan Flow** (checkout & bayar)
```
Login → Menu/Kustom → Keranjang → Checkout → Simpan Pesanan
      → Generate Midtrans Snap Token → Pembayaran (QRIS/Transfer/Cash)
      → Midtrans Webhook → Update Status Pesanan + Kurangi Stok
      → Member dapat poin loyalti (1 poin / Rp 10.000)
```

### 2. **Kasir/Barista Flow** (operasional toko)
```
Login → Dashboard → Tambah Item ke Session (ticket) → Buat Pesanan (Charge)
      → Update Status Pesanan → Kelola Status (lunas/gagal/cancel)
      → KDS: Bump/Dismiss antrian
```

### 3. **Admin Flow** (kepegawaian & laporan)
```
Login → Dashboard → CRUD Users, Inventory, Customers, Settings
      → Master Data: Kategori, Produk, Meja, Promo
      → Kelola Pesanan Tabel (status, destroy)
```

### 4. **Auth & RBAC Flow**
```
User Login → Cek `peran` di tabel `pengguna` → 
Middleware `role:X` membandingkan `user->peran` → 
Jika match → Izinkan akses → Jika tidak → abort(403)
```

### 5. **Payment Flow (Midtrans)**
```
1. Kontroler baca config: `config('services.midtrans.*')`
   - Bisa dari `.env` atau dari tabel `midtrans_settings` (baru)
2. Set `Config::$serverKey` dan `Config::$isProduction`
3. Verifikasi signature webhook dari Midtrans
4. Update status pesanan & transaksi di database
5. Generate Midtrans Snap Token untuk client
```

---

## 📝 Catatan untuk Membuat Halaman dengan Stitch AI

1. **Role-based access** gunakan middleware `role:admin|kasir|barista|pelanggan`
2. **URL naming** ikuti pola yang sudah ada: `.name('prefix.x')`
3. **Route prefix** gunakan `prefix('admin')->name('admin.')` atau peran lainnya
4. **Hanya Pelanggan** yang butuh flow checkout, keranjang, profil
5. **Hanya Admin/Kasir** yang butuh CRUD master data, inventory, laporan
6. **Midtrans integration** sudah ada di `MidtransNotificationController` - baca dari `config/services.php` atau tabel `midtrans_settings` yang baru dibuat

Semua kode sudah siap digunakan - tinggal buat halaman baru dengan merujuk pada route names dan controller methods yang sudah ada di atas.