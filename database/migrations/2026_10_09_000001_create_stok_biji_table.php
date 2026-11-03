<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Inventori biji kopi (roast) untuk InventoryAgent
        Schema::create('stok_biji', function (Blueprint $table) {
            $table->id('id_stok_biji');
            $table->string('nama_biji');
            $table->string('deskripsi')->nullable();
            $table->decimal('sisa_kg', 8, 2)->default(0);
            $table->decimal('threshold_kg', 8, 2)->default(5.0);
            $table->decimal('safety_cap_kg', 8, 2)->default(25.0);
            // Laju konsumsi kg/jam untuk proyeksi deplesi (InventoryAgent)
            $table->decimal('laju_konsumsi_kg_per_jam', 8, 4)->default(0);
            $table->timestamps();
        });

        // Log dispatch reorder ke roastery (InventoryAgent -> webhooks)
        Schema::create('log_reorder', function (Blueprint $table) {
            $table->id('id_log_reorder');
            $table->foreignId('id_stok_biji')
                ->constrained('stok_biji', 'id_stok_biji')
                ->cascadeOnDelete();
            $table->string('destination');
            $table->decimal('order_quantity_kg', 8, 2);
            $table->enum('status', ['terkirim', 'gagal'])->default('terkirim');
            $table->string('response_reference')->nullable();
            $table->datetime('waktu_dispatch');

            $table->index(['id_stok_biji', 'waktu_dispatch']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_reorder');
        Schema::dropIfExists('stok_biji');
    }
};
