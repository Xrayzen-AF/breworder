<?php

namespace Database\Seeders;

use App\Models\Kategori;
use Illuminate\Database\Seeder;

class KategoriSeeder extends Seeder
{
    public function run(): void
    {
        $kategori = [
            ['nama_kategori' => 'Artisan Tea', 'deskripsi' => 'Racikan teh artisan segar dan sparkling pilihan'],
            ['nama_kategori' => 'Kopi', 'deskripsi' => 'Berbagai macam minuman kopi'],
            ['nama_kategori' => 'Non-Kopi', 'deskripsi' => 'Minuman selain kopi'],
            ['nama_kategori' => 'Pastry & Snack', 'deskripsi' => 'Camilan pendamping minuman'],
        ];

        foreach ($kategori as $item) {
            Kategori::updateOrCreate(
                ['nama_kategori' => $item['nama_kategori']],
                $item + ['status_aktif' => true]
            );
        }
    }
}
