<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produk', function (Blueprint $table) {
            $table->id('id_produk');
            $table->foreignId('id_kategori')
                ->constrained('kategori', 'id_kategori')
                ->cascadeOnDelete();
            $table->string('kode_produk')->unique();
            $table->string('nama_produk');
            $table->text('deskripsi')->nullable();
            $table->decimal('harga', 10, 2);
            $table->unsignedInteger('stok')->default(0);
            $table->boolean('status_tersedia')->default(true);
            $table->string('url_gambar')->nullable();

            $table->index(['id_kategori', 'status_tersedia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produk');
    }
};
