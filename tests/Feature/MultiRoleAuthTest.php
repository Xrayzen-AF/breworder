<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiRoleAuthTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $peran): Pengguna
    {
        return Pengguna::factory()->create(['peran' => $peran]);
    }

    // ==================================================
    // Halaman login & registrasi
    // ==================================================

    public function test_halaman_login_tampil_untuk_guest(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Masuk Akun')
            ->assertSee('Daftar');
    }

    public function test_halaman_registrasi_tampil_untuk_guest(): void
    {
        $this->get('/register')->assertOk()->assertSee('Buat akun Pelanggan');
    }

    public function test_user_yang_sudah_login_diarahkan_dari_halaman_login(): void
    {
        $this->actingAs($this->makeUser('admin'))
            ->get('/login')
            ->assertRedirect('/admin/dashboard');
    }

    // ==================================================
    // Registrasi = selalu PELANGGAN
    // ==================================================

    public function test_registrasi_membuat_akun_dengan_peran_pelanggan(): void
    {
        $response = $this->post('/register', [
            'nama_lengkap' => 'Pelanggan Baru',
            'email' => 'baru@mail.com',
            'nomor_telepon' => '081234567891',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('pengguna', [
            'email' => 'baru@mail.com',
            'peran' => 'pelanggan',
        ]);
        $this->assertDatabaseHas('pelanggan', [
            'nomor_telepon' => '081234567891',
        ]);
        $response->assertRedirect(route('pelanggan.dashboard'));
    }

    public function test_registrasi_wajib_nomor_hp(): void
    {
        $this->post('/register', [
            'nama_lengkap' => 'Tanpa HP',
            'email' => 'tanpahp@mail.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('nomor_telepon');

        $this->assertGuest();
    }

    // ==================================================
    // Redirect ke dashboard sesuai role
    // ==================================================

    public function test_login_redirect_ke_dashboard_sesuai_role(): void
    {
        $expected = [
            'admin' => '/admin/dashboard',
            'kasir' => '/kasir/dashboard',
            'barista' => '/barista/dashboard',
            'pelanggan' => '/pelanggan/dashboard',
        ];

        foreach ($expected as $peran => $dashboard) {
            $user = $this->makeUser($peran);

            $this->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ])->assertRedirect($dashboard);

            $this->assertAuthenticatedAs($user);
            $this->post('/logout');
        }
    }

    // ==================================================
    // Proteksi route antar-role
    // ==================================================

    public function test_logout_mengarahkan_kembali_ke_halaman_login(): void
    {
        $user = $this->makeUser('admin');

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect(route('login'))
            ->assertSessionHas('success', 'Anda telah berhasil keluar. Silakan login kembali.');

        $this->assertGuest();

        // Setelah logout, akses dashboard harus dilempar ke login lagi.
        $this->get('/admin/dashboard')->assertRedirect('/login');
    }

    public function test_tamu_dilarang_mengakses_dashboard(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->get('/kasir/dashboard')->assertRedirect('/login');
        $this->get('/barista/dashboard')->assertRedirect('/login');
        $this->get('/pelanggan/dashboard')->assertRedirect('/login');
    }

    public function test_role_tidak_bisa_mengakses_dashboard_role_lain(): void
    {
        $dashboards = [
            'admin' => '/admin/dashboard',
            'kasir' => '/kasir/dashboard',
            'barista' => '/barista/dashboard',
            'pelanggan' => '/pelanggan/dashboard',
        ];

        foreach ($dashboards as $peran => $dashboard) {
            foreach ($dashboards as $other => $otherDashboard) {
                if ($peran === $other) {
                    continue;
                }

                $this->actingAs($this->makeUser($peran))
                    ->get($otherDashboard)
                    ->assertForbidden();
            }
        }
    }

    // ==================================================
    // STRICT RULE: akun pegawai hanya lewat Admin
    // ==================================================

    public function test_admin_bisa_membuat_akun_kasir_dan_barista(): void
    {
        foreach (['kasir', 'barista'] as $peran) {
            $this->actingAs($this->makeUser('admin'))
                ->post('/admin/employees', [
                    'nama_lengkap' => "Pegawai {$peran}",
                    'email' => "{$peran}@kopi.dev",
                    'peran' => $peran,
                    'password' => 'password',
                    'password_confirmation' => 'password',
                ])
                ->assertRedirect('/admin/employees');

            $this->assertDatabaseHas('pengguna', [
                'email' => "{$peran}@kopi.dev",
                'peran' => $peran,
            ]);
        }
    }

    public function test_form_pegawai_dilarang_membuat_peran_admin_dan_pelanggan(): void
    {
        foreach (['admin', 'pelanggan'] as $peran) {
            $this->actingAs($this->makeUser('admin'))
                ->from('/admin/employees/create')
                ->post('/admin/employees', [
                    'nama_lengkap' => "Coba {$peran}",
                    'email' => "coba-{$peran}@kopi.dev",
                    'peran' => $peran,
                    'password' => 'password',
                    'password_confirmation' => 'password',
                ])
                ->assertSessionHasErrors('peran');

            $this->assertDatabaseMissing('pengguna', ['email' => "coba-{$peran}@kopi.dev"]);
        }
    }

    public function test_non_admin_dilarang_mengelola_akun_pegawai(): void
    {
        foreach (['kasir', 'barista', 'pelanggan'] as $peran) {
            $this->actingAs($this->makeUser($peran))
                ->get('/admin/employees')
                ->assertForbidden();

            $this->actingAs($this->makeUser($peran))
                ->post('/admin/employees', [
                    'nama_lengkap' => 'Peretas',
                    'email' => 'peretas@kopi.dev',
                    'peran' => 'kasir',
                    'password' => 'password',
                    'password_confirmation' => 'password',
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('pengguna', ['email' => 'peretas@kopi.dev']);
    }

    public function test_admin_bisa_mengedit_akun_pegawai(): void
    {
        $admin = $this->makeUser('admin');
        $kasir = $this->makeUser('kasir');

        $this->actingAs($admin)
            ->put("/admin/employees/{$kasir->id_pengguna}", [
                'nama_lengkap' => 'Kasir Diubah',
                'email' => $kasir->email,
                'peran' => 'barista',
            ])
            ->assertRedirect('/admin/employees');

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $kasir->id_pengguna,
            'nama_lengkap' => 'Kasir Diubah',
            'peran' => 'barista',
        ]);
    }

    public function test_admin_bisa_menghapus_akun_pegawai(): void
    {
        $admin = $this->makeUser('admin');
        $barista = $this->makeUser('barista');

        $this->actingAs($admin)
            ->delete("/admin/employees/{$barista->id_pengguna}")
            ->assertRedirect('/admin/employees');

        $this->assertDatabaseMissing('pengguna', ['id_pengguna' => $barista->id_pengguna]);
    }

    public function test_admin_dilarang_menghapus_dirinya_lewat_form_pegawai(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)
            ->delete("/admin/employees/{$admin->id_pengguna}")
            ->assertNotFound();

        $this->assertDatabaseHas('pengguna', ['id_pengguna' => $admin->id_pengguna]);
    }

    public function test_admin_tidak_bisa_mengedit_akun_non_pegawai_lewat_form_pegawai(): void
    {
        $admin = $this->makeUser('admin');
        $pelanggan = $this->makeUser('pelanggan');

        $this->actingAs($admin)
            ->get("/admin/employees/{$pelanggan->id_pengguna}/edit")
            ->assertNotFound();

        $this->actingAs($admin)
            ->delete("/admin/employees/{$pelanggan->id_pengguna}")
            ->assertNotFound();

        $this->assertDatabaseHas('pengguna', ['id_pengguna' => $pelanggan->id_pengguna]);
    }
}
