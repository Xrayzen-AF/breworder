<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pesanan', function (Blueprint $table) {
            $table->id('id_pesanan');
            $table->string('nomor_pesanan')->unique();
            $table->foreignId('id_pengguna')
                ->constrained('pengguna', 'id_pengguna')
                ->restrictOnDelete(); // kasir yang menangani
            $table->foreignId('id_pelanggan')
                ->nullable()
                ->constrained('pelanggan', 'id_pelanggan')
                ->nullOnDelete(); // boleh NULL untuk non-member
            $table->foreignId('id_meja')
                ->nullable()
                ->constrained('meja', 'id_meja')
                ->nullOnDelete(); // boleh NULL untuk takeaway/delivery
            $table->foreignId('id_promo')
                ->nullable()
                ->constrained('promo', 'id_promo')
                ->nullOnDelete(); // boleh NULL jika tanpa promo
            $table->enum('tipe_pesanan', ['dine-in', 'takeaway', 'delivery'])->default('dine-in');
            $table->enum('status_pesanan', ['baru', 'diproses', 'selesai', 'dibatalkan'])->default('baru');
            $table->decimal('total_harga', 12, 2)->default(0);
            $table->decimal('diskon', 12, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->datetime('waktu_pesan')->useCurrent();

            $table->index(['status_pesanan', 'waktu_pesan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pesanan');
    }
};
