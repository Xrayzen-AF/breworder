<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_pesanan', function (Blueprint $table) {
            $table->id('id_detail');
            $table->foreignId('id_pesanan')
                ->constrained('pesanan', 'id_pesanan')
                ->cascadeOnDelete();
            $table->foreignId('id_produk')
                ->constrained('produk', 'id_produk')
                ->restrictOnDelete();
            $table->unsignedInteger('jumlah')->default(1);
            $table->decimal('harga_satuan', 10, 2);
            $table->decimal('subtotal', 12, 2);
            $table->text('catatan_item')->nullable(); // misal "less sugar"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_pesanan');
    }
};
