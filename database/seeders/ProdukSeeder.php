<?php

namespace Database\Seeders;

use App\Models\Kategori;
use App\Models\Produk;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProdukSeeder extends Seeder
{
    public function run(): void
    {
        // Hanya MySQL/MariaDB yang mengenal SET FOREIGN_KEY_CHECKS; SQLite (dipakai
        // saat pengujian) akan gagal bila perintah ini dikirim apa adanya.
        $mysql = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);

        if ($mysql) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }

        Produk::truncate();

        if ($mysql) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        // Menu demo memakai gambar yang tersedia di public/images agar tampilan
        // halaman pelanggan tidak kosong setelah migrate --seed.
        $menu = [
            'Kopi' => [
                ['CF-001', 'Mont Blanc', 'Espresso dengan susu creamy dan topping krim manis', 25000, 'mont blanc.jfif'],
                ['CF-002', 'Magic Coffee', 'Kopi susu pekat khas Tejas dengan rasa kuat', 28000, 'magic coffee.jfif'],
                ['CF-003', 'Caffè Latte', 'Espresso dengan steamed milk lembut', 20000, 'coffe latte.jfif'],
                ['CF-004', 'Kopi Susu Tejas', 'Kopi susu gula aren favorit pelanggan', 18000, 'kopi susu tejas.jfif'],
                ['CF-005', 'Butterscotch', 'Latte dengan sirup butterscotch dan aroma karamel', 21000, 'butterscotch.jfif'],
                ['CF-006', 'Salty Sweet Coffee', 'Kopi susu dengan sentuhan garam laut', 25000, 'salty sweet coffee.jfif'],
                ['CF-007', 'Choco Latte', 'Perpaduan cokelat pekat dan espresso', 23000, 'choco latte.jfif'],
                ['CF-008', 'Affogato', 'Es krim vanila disiram espresso panas', 27000, 'affogato.avif'],
            ],
            'Non-Kopi' => [
                ['NK-001', 'Matcha Latte', 'Matcha Jepang dengan susu segar', 25000, 'matca latte.jfif'],
                ['NK-002', 'Matcha Cheese', 'Matcha latte dengan foam cheese gurih', 28000, 'matcha cheese.jfif'],
                ['NK-003', 'Strawberry Smoothie', 'Smoothie stroberi segar dengan yogurt', 26000, 'strawbery smoothie.jfif'],
                ['NK-004', 'Mineral Water', 'Air mineral kemasan dingin', 8000, 'minerall water.jfif'],
            ],
            'Artisan Tea' => [
                ['ART-001', 'Blue Lemonade', 'Teh artisan Blue Lemonade segar dengan perpaduan citrus lemon dan daun mint', 25000, 'blue-lemonade.jpg'],
                ['ART-002', 'SCARLETT Tea Sparkling', 'Teh artisan sparkling Scarlett segar dengan sensasi soda dingin dan irisan lemon segar', 28000, 'scarlett-tea-sparkling.jpg'],
                ['ART-003', 'Lychee Tea', 'Teh leci dingin dengan buah leci utuh', 22000, 'lecy tea.jfif'],
                ['ART-004', 'Lemon Tea', 'Teh lemon klasik, disajikan dingin', 20000, 'lemon teaa.jfif'],
            ],
            'Pastry & Snack' => [
                ['PS-001', 'Risol Mayo', 'Risol goreng isi mayones dan smoked beef', 13000, 'risol mayo.jfif'],
                ['PS-002', 'Risol Matcha', 'Risol manis isi krim matcha', 12000, 'risol matcha.jfif'],
                ['PS-003', 'Risol Cokelat', 'Risol manis isi cokelat lumer', 12000, 'risol coklatt.jfif'],
                ['PS-004', 'Risol Pedas', 'Risol isi ayam pedas', 13000, 'risol pedas.jfif'],
                ['PS-005', 'Cireng Rujak', 'Cireng kenyal dengan saus rujak', 18000, 'cireng rujak.jfif'],
                ['PS-006', 'Tahu Lada Garam', 'Tahu goreng crispy dengan bumbu lada garam', 15000, 'tahu lada garam.jfif'],
                ['PS-007', 'Long Potato', 'Kentang goreng panjang renyah', 15000, 'long pottato.jfif'],
                ['PS-008', 'Mini Burger', 'Burger mini isi beef patty dan keju', 30000, 'BURGER MINI.jfif'],
                ['PS-009', 'Mix Platter', 'Aneka camilan dalam satu piring untuk berbagi', 35000, 'mix platter.jfif'],
                ['PS-010', 'Ice Cream Cone', 'Es krim vanila dalam cone renyah', 15000, 'ice cream cone.jfif'],
            ],
        ];

        $deskripsiKategori = [
            'Kopi' => 'Berbagai macam minuman kopi',
            'Non-Kopi' => 'Minuman selain kopi',
            'Artisan Tea' => 'Racikan teh artisan segar dan sparkling pilihan',
            'Pastry & Snack' => 'Camilan pendamping minuman',
        ];

        foreach ($menu as $namaKategori => $items) {
            $kategori = Kategori::firstOrCreate(
                ['nama_kategori' => $namaKategori],
                ['deskripsi' => $deskripsiKategori[$namaKategori], 'status_aktif' => true]
            );

            foreach ($items as [$kode, $nama, $deskripsi, $harga, $gambar]) {
                Produk::create([
                    'id_kategori' => $kategori->id_kategori,
                    'kode_produk' => $kode,
                    'nama_produk' => $nama,
                    'deskripsi' => $deskripsi,
                    'harga' => $harga,
                    'stok' => 50,
                    'status_tersedia' => true,
                    'url_gambar' => '/images/'.$gambar,
                ]);
            }
        }
    }
}
