<?php

use App\Models\Kategori;
use App\Models\Produk;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

// Status meja tidak boleh basi saat tidak ada yang membuka dashboard:
// sesi kedaluwarsa ditutup dan mejanya dikosongkan tiap menit.
Schedule::command('meja:bersihkan')->everyMinute()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('menu:reset-artisan', function () {
    $this->info('Mengosongkan produk dan menambahkan menu Artisan Tea...');

    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    Produk::truncate();
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');

    $kategori = Kategori::firstOrCreate(
        ['nama_kategori' => 'Artisan Tea'],
        ['deskripsi' => 'Racikan teh artisan segar dan sparkling pilihan', 'status_aktif' => true]
    );

    $items = [
        [
            'kode_produk' => 'ART-001',
            'nama_produk' => 'Blue Lemonade',
            'deskripsi' => 'Teh artisan Blue Lemonade segar dengan perpaduan citrus lemon dan daun mint',
            'harga' => 25000,
            'stok' => 50,
            'status_tersedia' => true,
            'url_gambar' => '/images/blue-lemonade.jpg',
        ],
        [
            'kode_produk' => 'ART-002',
            'nama_produk' => 'SCARLETT Tea Sparkling',
            'deskripsi' => 'Teh artisan sparkling Scarlett segar dengan sensasi soda dingin dan irisan lemon segar',
            'harga' => 28000,
            'stok' => 50,
            'status_tersedia' => true,
            'url_gambar' => '/images/scarlett-tea-sparkling.jpg',
        ],
    ];

    foreach ($items as $item) {
        Produk::create([
            'id_kategori' => $kategori->id_kategori,
            'kode_produk' => $item['kode_produk'],
            'nama_produk' => $item['nama_produk'],
            'deskripsi' => $item['deskripsi'],
            'harga' => $item['harga'],
            'stok' => $item['stok'],
            'status_tersedia' => $item['status_tersedia'],
            'url_gambar' => $item['url_gambar'],
        ]);
        $this->line("✔ Ditambahkan: {$item['nama_produk']} (Rp ".number_format($item['harga'], 0, ',', '.').')');
    }

    $this->info('Selesai! Menu berhasil dikosongkan dan diisi produk Artisan Tea.');
})->purpose('Kosongkan menu dan tambahkan produk kategori Artisan Tea');
