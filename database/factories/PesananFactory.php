<?php

namespace Database\Factories;

use App\Models\Pengguna;
use App\Models\Pesanan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pesanan>
 */
class PesananFactory extends Factory
{
    protected $model = Pesanan::class;

    public function definition(): array
    {
        return [
            'nomor_pesanan' => Pesanan::generateNomorPesanan(),
            'id_pengguna' => Pengguna::factory()->create(['peran' => 'kasir'])->id_pengguna,
            'tipe_pesanan' => fake()->randomElement(['dine-in', 'takeaway']),
            'status_pesanan' => 'baru',
            'total_harga' => 0,
            'diskon' => 0,
        ];
    }
}
