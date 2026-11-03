<?php

use App\Http\Controllers\Admin\AdminEmployeeController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\MasterKategoriController;
use App\Http\Controllers\Admin\MasterMejaController;
use App\Http\Controllers\Admin\MasterProdukController;
use App\Http\Controllers\Admin\MasterPromoController;
use App\Http\Controllers\Admin\OrderManagementController;
use App\Http\Controllers\Admin\OverviewController;
use App\Http\Controllers\Admin\SalesController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MejaQrController;
use App\Http\Controllers\MidtransPaymentController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\StrukController;
use App\Models\Meja;
use Illuminate\Support\Facades\Route;

// Halaman utama diarahkan ke login sentral
Route::get('/', function () {
    return redirect()->route('login');
});

// ============================================================
// AUTH (guest) — satu login sentral untuk semua role
// ============================================================
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');

    // Registrasi MANDIRI khusus PELANGGAN saja
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:10,1');
});

// Logout (harus login)
Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// ============================================================
// SCAN QR MEJA (publik) — tamu tetap bisa melihat status meja dan
// memverifikasi kode booking saat tidak bisa login.
// ============================================================
Route::get('/meja/{meja:qr_token}', [MejaQrController::class, 'show'])->name('meja.scan');
Route::post('/meja/{meja:qr_token}/verifikasi', [MejaQrController::class, 'verifikasi'])
    ->middleware('throttle:6,1')
    ->name('meja.verifikasi');

// ============================================================
// DASHBOARD PER ROLE — dilindungi middleware 'role'
// pelanggan yang membuka /admin atau /kasir akan ditolak (403), dst.
// ============================================================
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [OverviewController::class, 'index'])->name('dashboard');

    // CRUD akun pegawai (Kasir & Barista) — STRICT RULE: admin only
    Route::resource('employees', AdminEmployeeController::class)
        ->except(['show'])
        ->names('employees');

    // === 5 fitur sidebar Ops Console ===
    // Sales & Revenue (baca saja)
    Route::get('/sales', [SalesController::class, 'index'])->name('sales.index');

    // Inventory & Roastery
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
    Route::put('/inventory/{inventory}', [InventoryController::class, 'update'])->name('inventory.update');
    Route::delete('/inventory/{inventory}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
    Route::post('/inventory/{inventory}/reorder', [InventoryController::class, 'reorder'])->name('inventory.reorder');

    // Staff & Roster (ringkasan + toggle aktif)
    Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
    Route::patch('/staff/{staff}/toggle', [StaffController::class, 'toggle'])->name('staff.toggle');

    // Customer CRM & Loyalty
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::patch('/customers/{customer}/poin', [CustomerController::class, 'adjustPoin'])->name('customers.poin');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');

    // Store Settings (JSON file, tanpa migrasi)
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('/settings/metode', [SettingController::class, 'storeMetode'])->name('settings.metode');
    Route::put('/settings/midtrans', [SettingController::class, 'updateMidtrans'])->name('settings.midtrans');

    // === Master data (CRUD penuh oleh admin) ===
    Route::prefix('master')->name('master.')->group(function () {
        Route::get('/kategori', [MasterKategoriController::class, 'index'])->name('kategori.index');
        Route::post('/kategori', [MasterKategoriController::class, 'store'])->name('kategori.store');
        Route::put('/kategori/{kategori}', [MasterKategoriController::class, 'update'])->name('kategori.update');
        Route::delete('/kategori/{kategori}', [MasterKategoriController::class, 'destroy'])->name('kategori.destroy');

        Route::get('/produk', [MasterProdukController::class, 'index'])->name('produk.index');
        Route::post('/produk', [MasterProdukController::class, 'store'])->name('produk.store');
        Route::put('/produk/{produk}', [MasterProdukController::class, 'update'])->name('produk.update');
        Route::delete('/produk/{produk}', [MasterProdukController::class, 'destroy'])->name('produk.destroy');

        Route::get('/meja', [MasterMejaController::class, 'index'])->name('meja.index');
        Route::get('/meja/qr', [MasterMejaController::class, 'qr'])->name('meja.qr');
        Route::post('/meja', [MasterMejaController::class, 'store'])->name('meja.store');
        Route::put('/meja/{meja}', [MasterMejaController::class, 'update'])->name('meja.update');
        Route::delete('/meja/{meja}', [MasterMejaController::class, 'destroy'])->name('meja.destroy');

        Route::get('/promo', [MasterPromoController::class, 'index'])->name('promo.index');
        Route::post('/promo', [MasterPromoController::class, 'store'])->name('promo.store');
        Route::put('/promo/{promo}', [MasterPromoController::class, 'update'])->name('promo.update');
        Route::delete('/promo/{promo}', [MasterPromoController::class, 'destroy'])->name('promo.destroy');
    });

    // === Pesanan (tabel transaksi — kelola status, bukan edit isi) ===
    Route::get('/orders', [OrderManagementController::class, 'index'])->name('orders.index');
    Route::post('/orders/{pesanan}/status', [OrderManagementController::class, 'updateStatus'])->name('orders.status');
    Route::delete('/orders/{pesanan}', [OrderManagementController::class, 'destroy'])->name('orders.destroy');
});

Route::middleware(['auth', 'role:kasir'])->prefix('kasir')->name('kasir.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'kasir'])->name('dashboard');
    // Draf tiket POS: tambah/ubah item di session, lalu buat pesanan (Charge).
    Route::post('/ticket/tambah/{produk}', [DashboardController::class, 'ticketTambah'])->name('ticket.tambah');
    Route::post('/ticket/update/{idProduk}', [DashboardController::class, 'ticketUpdate'])->name('ticket.update');
    Route::post('/ticket/buat', [DashboardController::class, 'ticketBuat'])->name('ticket.buat');
    // Status meja realtime (dipolling oleh papan pemilihan meja di POS).
    Route::get('/meja/status', [DashboardController::class, 'mejaStatus'])->name('meja.status');
});

Route::middleware(['auth', 'role:barista'])->prefix('barista')->name('barista.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'barista'])->name('dashboard');
    // Bump (sematkan ke atas papan) & Dismiss (sembunyikan dari dispatch).
    Route::post('/bump/{pesanan}', [DashboardController::class, 'kdsPin'])->name('bump');
    // Papan status meja: barista bisa menandai meja kosong/terisi/dipesan.
    Route::post('/meja/{meja}/status', [DashboardController::class, 'updateMejaStatus'])->name('meja.status.update');
    Route::post('/dismiss/{pesanan}', [DashboardController::class, 'kdsDismiss'])->name('dismiss');
});

Route::middleware(['auth', 'role:pelanggan'])->prefix('pelanggan')->name('pelanggan.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'pelanggan'])->name('dashboard');
    // Riwayat pesanan punya halaman sendiri, tidak lagi menumpang di beranda.
    Route::get('/riwayat', [DashboardController::class, 'riwayat'])->name('riwayat');
    // Status meja realtime (dipolling oleh panel "Status Meja" di beranda).
    Route::get('/meja/status', [DashboardController::class, 'mejaStatusPelanggan'])->name('meja.status');

    // Check-in & lepas meja tetap wajib akun pelanggan.
    // URL lama (/pelanggan/meja/...) diarahkan ke halaman scan publik.
    Route::get('/meja/{meja:qr_token}', fn (Meja $meja) => redirect()
        ->route('meja.scan', ['meja' => $meja->qr_token]))->name('meja.scan');
    Route::post('/meja/{meja:qr_token}/checkin', [MejaQrController::class, 'checkin'])->name('meja.checkin');
    Route::post('/meja/{meja:qr_token}/lepas', [MejaQrController::class, 'lepas'])->name('meja.lepas');

    // Alur pemesanan: menu → kustom → keranjang → checkout → status
    Route::get('/menu', [CartController::class, 'menu'])->name('menu');
    Route::get('/menu/{produk}/kustom', [CartController::class, 'kustom'])->name('menu.kustom');
    Route::post('/keranjang/tambah/{produk}', [CartController::class, 'tambah'])->name('keranjang.tambah');
    Route::post('/keranjang/update/{key}', [CartController::class, 'update'])->name('keranjang.update');
    Route::delete('/keranjang/hapus/{key}', [CartController::class, 'hapus'])->name('keranjang.hapus');
    Route::get('/keranjang', [CartController::class, 'index'])->name('keranjang.index');
    Route::post('/keranjang/voucher', [CartController::class, 'voucher'])->name('keranjang.voucher');
    Route::post('/keranjang/poin', [CartController::class, 'poinToggle'])->name('keranjang.poin');
    Route::post('/keranjang/tipe', [CartController::class, 'tipeSimpan'])->name('keranjang.tipe');
    Route::post('/keranjang/batal', [CartController::class, 'batalKeranjang'])->name('keranjang.batal');
    Route::get('/checkout', [CartController::class, 'checkout'])->name('checkout');
    Route::post('/checkout', [CartController::class, 'simpanPesanan'])->name('checkout.simpan');
    Route::get('/pesanan/{nomorPesanan}', [CartController::class, 'status'])->name('status');
    // Polling status untuk progres real-time (hanya milik sendiri).
    Route::get('/pesanan/{pesanan}/status-live', [CartController::class, 'statusLive'])->name('pesanan.status-live');
    Route::patch('/pesanan/{pesanan}/batal', [CartController::class, 'batalkan'])->name('pesanan.batal');

    // Profil member + edit data akun.
    Route::get('/profil', [ProfilController::class, 'show'])->name('profil');
    Route::put('/profil', [ProfilController::class, 'update'])->name('profil.update');
});

// Aksi status pesanan & pembayaran dari dashboard kasir/barista (web form, RBAC)
Route::middleware(['auth'])->group(function () {
    // Struk pembayaran 80mm — kasir/admin dan pelanggan pemilik pesanan.
    Route::get('/struk/{pesanan}', [StrukController::class, 'show'])->name('struk.show');

    Route::patch('/pesanan/{pesanan}/status', [DashboardController::class, 'updateStatus'])
        ->name('pesanan.status');
    Route::patch('/pesanan/{pesanan}/lunas', [DashboardController::class, 'tandaiLunas'])
        ->name('pesanan.lunas');
    Route::post('/pesanan/{pesanan}/snap-token', [DashboardController::class, 'snapToken'])
        ->name('pesanan.snap');

    // Midtrans non-tunai: QRIS dinamis & virtual account transfer.
    Route::post('/pesanan/{pesanan}/midtrans/qris', [MidtransPaymentController::class, 'qris'])
        ->name('pesanan.midtrans.qris');
    Route::post('/pesanan/{pesanan}/midtrans/transfer', [MidtransPaymentController::class, 'transfer'])
        ->name('pesanan.midtrans.transfer');
    Route::get('/pesanan/{pesanan}/midtrans/status', [MidtransPaymentController::class, 'status'])
        ->name('pesanan.midtrans.status');
});
