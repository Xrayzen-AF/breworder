<?php

namespace Database\Seeders;

use App\Models\Promo;
use Illuminate\Database\Seeder;

class PromoSeeder extends Seeder
{
    public function run(): void
    {
        $promo = [
            [
                'kode_promo' => 'WELCOME10',
                'tipe_diskon' => 'persen',
                'nilai_diskon' => 10,
                'berlaku_mulai' => now()->startOfDay(),
                'berlaku_sampai' => now()->addMonths(3)->endOfDay(),
                'status_aktif' => true,
            ],
            [
                'kode_promo' => 'HEMAT5000',
                'tipe_diskon' => 'nominal',
                'nilai_diskon' => 5000,
                'berlaku_mulai' => now()->startOfDay(),
                'berlaku_sampai' => now()->addMonths(1)->endOfDay(),
                'status_aktif' => true,
            ],
        ];

        foreach ($promo as $item) {
            Promo::updateOrCreate(
                ['kode_promo' => $item['kode_promo']],
                $item
            );
        }
    }
}
