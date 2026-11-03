<?php

namespace Database\Seeders;

use App\Models\Pengguna;
use Illuminate\Database\Seeder;

/**
 * Seeder banyak akun agar bisa login sesuai role masing-masing.
 * Semua password: password (bisa juga login via nomor_telepon).
 *
 * ADMIN:     admin@kopi.dev, admin2@kopi.dev
 * KASIR:     kasir@kopi.dev, kasir2@kopi.dev, kasir3@kopi.dev
 * BARISTA:   barista@kopi.dev, barista2@kopi.dev, barista3@kopi.dev
 * PELANGGAN: pelanggan@kopi.dev, pelanggan2@kopi.dev, dst.
 */
class PenggunaSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // --- Admin (2) ---
            ['nama_lengkap' => 'Administrator', 'email' => 'admin@kopi.dev', 'nomor_telepon' => '081100000001', 'peran' => 'admin'],
            ['nama_lengkap' => 'Admin Dua', 'email' => 'admin2@kopi.dev', 'nomor_telepon' => '081100000002', 'peran' => 'admin'],

            // --- Kasir (3) ---
            ['nama_lengkap' => 'Kasir Satu', 'email' => 'kasir@kopi.dev', 'nomor_telepon' => '081200000001', 'peran' => 'kasir'],
            ['nama_lengkap' => 'Kasir Dua', 'email' => 'kasir2@kopi.dev', 'nomor_telepon' => '081200000002', 'peran' => 'kasir'],
            ['nama_lengkap' => 'Kasir Tiga', 'email' => 'kasir3@kopi.dev', 'nomor_telepon' => '081200000003', 'peran' => 'kasir'],

            // --- Barista (3) ---
            ['nama_lengkap' => 'Barista Satu', 'email' => 'barista@kopi.dev', 'nomor_telepon' => '081300000001', 'peran' => 'barista'],
            ['nama_lengkap' => 'Barista Dua', 'email' => 'barista2@kopi.dev', 'nomor_telepon' => '081300000002', 'peran' => 'barista'],
            ['nama_lengkap' => 'Barista Tiga', 'email' => 'barista3@kopi.dev', 'nomor_telepon' => '081300000003', 'peran' => 'barista'],

            // --- Pelanggan (5, bisa daftar mandiri juga) ---
            ['nama_lengkap' => 'Pelanggan Satu', 'email' => 'pelanggan@kopi.dev', 'nomor_telepon' => '081234567890', 'peran' => 'pelanggan'],
            ['nama_lengkap' => 'Pelanggan Dua', 'email' => 'pelanggan2@kopi.dev', 'nomor_telepon' => '081234567891', 'peran' => 'pelanggan'],
            ['nama_lengkap' => 'Budi Santoso', 'email' => 'budi@mail.com', 'nomor_telepon' => '081234567892', 'peran' => 'pelanggan'],
            ['nama_lengkap' => 'Siti Rahma', 'email' => 'siti@mail.com', 'nomor_telepon' => '082198765432', 'peran' => 'pelanggan'],
            ['nama_lengkap' => 'Andi Wijaya', 'email' => 'andi@mail.com', 'nomor_telepon' => '085612345678', 'peran' => 'pelanggan'],
        ];

        foreach ($accounts as $account) {
            Pengguna::firstOrCreate(
                ['email' => $account['email']],
                [
                    'nama_lengkap' => $account['nama_lengkap'],
                    'nomor_telepon' => $account['nomor_telepon'],
                    // Plain password — cast `hashed` di model yang meng-hash.
                    'kata_sandi' => 'password',
                    'peran' => $account['peran'],
                    'status_aktif' => true,
                ]
            );
        }
    }
}
