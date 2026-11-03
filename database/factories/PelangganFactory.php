<?php

namespace Database\Factories;

use App\Models\Pelanggan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pelanggan>
 */
class PelangganFactory extends Factory
{
    protected $model = Pelanggan::class;

    public function definition(): array
    {
        return [
            'nama_pelanggan' => fake()->name(),
            'nomor_telepon' => fake()->unique()->numerify('08##########'),
            'poin_loyalitas' => 0,
        ];
    }
}
