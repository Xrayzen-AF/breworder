<?php

namespace Database\Seeders;

use App\Models\Meja;
use Illuminate\Database\Seeder;

class MejaSeeder extends Seeder
{
    public function run(): void
    {
        $meja = [
            ['nomor_meja' => 'M01', 'kapasitas' => 2],
            ['nomor_meja' => 'M02', 'kapasitas' => 2],
            ['nomor_meja' => 'M03', 'kapasitas' => 4],
            ['nomor_meja' => 'M04', 'kapasitas' => 4],
            ['nomor_meja' => 'M05', 'kapasitas' => 6],
            ['nomor_meja' => 'M06', 'kapasitas' => 8],
        ];

        foreach ($meja as $item) {
            Meja::firstOrCreate(
                ['nomor_meja' => $item['nomor_meja']],
                $item + ['status' => 'kosong']
            );
        }
    }
}
