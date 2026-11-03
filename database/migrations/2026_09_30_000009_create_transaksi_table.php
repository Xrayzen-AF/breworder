<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi', function (Blueprint $table) {
            $table->id('id_transaksi');
            $table->foreignId('id_pesanan')
                ->unique() // 1:1 — satu pesanan dibayar melalui satu transaksi
                ->constrained('pesanan', 'id_pesanan')
                ->cascadeOnDelete();
            $table->foreignId('id_metode')
                ->constrained('metode_pembayaran', 'id_metode')
                ->restrictOnDelete();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total_bayar', 12, 2);
            $table->decimal('kembalian', 12, 2)->default(0);
            $table->string('bank_atau_ewallet')->nullable(); // diisi jika TRANSFER
            $table->string('nomor_referensi')->nullable(); // bukti transfer, diisi jika TRANSFER
            $table->enum('status_pembayaran', ['menunggu', 'lunas', 'gagal'])->default('menunggu');
            $table->datetime('waktu_pembayaran')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi');
    }
};
