<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stok_biji', function (Blueprint $table) {
            // Jenis bahan: Biji Kopi, Susu & Krimer, Sirup & Gula, Kemasan, Lainnya.
            $table->string('kategori')->default('Biji Kopi')->after('nama_biji');
            // Satuan tampilan: kg, liter, pcs, pack, botol.
            $table->string('satuan', 20)->default('kg')->after('kategori');
        });
    }

    public function down(): void
    {
        Schema::table('stok_biji', function (Blueprint $table) {
            $table->dropColumn(['kategori', 'satuan']);
        });
    }
};
