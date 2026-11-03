<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PenggunaSeeder::class,
            KategoriSeeder::class,
            ProdukSeeder::class,
            MejaSeeder::class,
            PromoSeeder::class,
            MetodePembayaranSeeder::class,
            PelangganSeeder::class,
            MidtransSettingsSeeder::class,
        ]);
    }
}
