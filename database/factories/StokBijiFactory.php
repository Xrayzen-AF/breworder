<?php

namespace Database\Factories;

use App\Models\StokBiji;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StokBiji>
 */
class StokBijiFactory extends Factory
{
    protected $model = StokBiji::class;

    public function definition(): array
    {
        return [
            'nama_biji' => fake()->unique()->randomElement([
                'Ethiopia Yirgacheffe Beans',
                'House Blend Beans',
                'Colombia Supremo Beans',
            ]),
            'deskripsi' => fake()->sentence(),
            'sisa_kg' => fake()->randomFloat(2, 3, 20),
            'threshold_kg' => 5.0,
            'safety_cap_kg' => 25.0,
            'laju_konsumsi_kg_per_jam' => fake()->randomFloat(4, 0.1, 1.5),
        ];
    }

    /** State kritis: sisa di bawah threshold (untuk test alert). */
    public function kritis(): static
    {
        return $this->state(fn () => [
            'nama_biji' => 'Ethiopia Yirgacheffe Beans',
            'sisa_kg' => 2.4,
            'threshold_kg' => 5.0,
            'safety_cap_kg' => 25.0,
            'laju_konsumsi_kg_per_jam' => 0.4,
        ]);
    }
}
