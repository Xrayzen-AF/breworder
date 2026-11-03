<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use App\Models\StokBiji;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryBahanTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Pengguna
    {
        return Pengguna::factory()->create(['peran' => 'admin']);
    }

    public function test_admin_bisa_menambah_bahan_selain_biji_kopi(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.inventory.store'), [
                'nama_biji' => 'Susu UHT Full Cream',
                'kategori' => 'Susu & Krimer',
                'satuan' => 'liter',
                'deskripsi' => '1 liter per karton',
                'sisa_kg' => 12,
                'threshold_kg' => 4,
                'safety_cap_kg' => 20,
                'laju_konsumsi_kg_per_jam' => 0.5,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('stok_biji', [
            'nama_biji' => 'Susu UHT Full Cream',
            'kategori' => 'Susu & Krimer',
            'satuan' => 'liter',
        ]);
    }

    public function test_halaman_stok_bahan_menampilkan_semua_kategori_dan_satuan(): void
    {
        StokBiji::create([
            'nama_biji' => 'Cup 12 oz',
            'kategori' => 'Kemasan',
            'satuan' => 'pcs',
            'sisa_kg' => 150,
            'threshold_kg' => 50,
            'safety_cap_kg' => 300,
            'laju_konsumsi_kg_per_jam' => 4,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.inventory.index'))
            ->assertOk()
            ->assertSee('Stok Bahan')
            ->assertSee('Cup 12 oz')
            ->assertSee('Kemasan')
            ->assertSee('pcs');
    }

    public function test_kategori_dan_satuan_tidak_dikenal_ditolak(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.inventory.store'), [
                'nama_biji' => 'Bahan Aneh',
                'kategori' => 'Bumbu Dapur',
                'satuan' => 'karung',
                'sisa_kg' => 1,
                'threshold_kg' => 1,
                'safety_cap_kg' => 2,
                'laju_konsumsi_kg_per_jam' => 0,
            ])
            ->assertSessionHasErrors(['kategori', 'satuan']);

        $this->assertDatabaseMissing('stok_biji', ['nama_biji' => 'Bahan Aneh']);
    }
}
