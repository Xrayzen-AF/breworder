<?php

namespace Database\Seeders;

use App\Models\Pelanggan;
use Illuminate\Database\Seeder;

class PelangganSeeder extends Seeder
{
    public function run(): void
    {
        $pelanggan = [
            ['nama_pelanggan' => 'Budi Santoso', 'nomor_telepon' => '081234567890', 'poin_loyalitas' => 120],
            ['nama_pelanggan' => 'Siti Rahma', 'nomor_telepon' => '082198765432', 'poin_loyalitas' => 80],
            ['nama_pelanggan' => 'Andi Wijaya', 'nomor_telepon' => '085612345678', 'poin_loyalitas' => 25],
        ];

        foreach ($pelanggan as $item) {
            Pelanggan::firstOrCreate(
                ['nama_pelanggan' => $item['nama_pelanggan'], 'nomor_telepon' => $item['nomor_telepon']],
                ['poin_loyalitas' => $item['poin_loyalitas']]
            );
        }
    }
}
