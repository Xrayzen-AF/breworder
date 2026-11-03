<?php

namespace Database\Seeders;

use App\Models\MetodePembayaran;
use Illuminate\Database\Seeder;

class MetodePembayaranSeeder extends Seeder
{
    public function run(): void
    {
        $defaultMethods = [
            ['kode_metode' => 'CASH', 'nama_metode' => 'Tunai', 'ada_kembalian' => true],
            ['kode_metode' => 'QRIS', 'nama_metode' => 'QRIS / Midtrans', 'ada_kembalian' => false],
            ['kode_metode' => 'TRANSFER', 'nama_metode' => 'Transfer Bank', 'ada_kembalian' => false],
        ];

        foreach ($defaultMethods as $method) {
            MetodePembayaran::firstOrCreate(
                ['kode_metode' => $method['kode_metode']],
                $method
            );
        }
    }
}
