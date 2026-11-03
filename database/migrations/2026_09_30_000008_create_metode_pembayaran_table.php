<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metode_pembayaran', function (Blueprint $table) {
            $table->id('id_metode');
            $table->string('kode_metode')->unique(); // CASH atau TRANSFER
            $table->string('nama_metode'); // Tunai / Transfer Bank
            $table->boolean('ada_kembalian')->default(false); // true jika CASH, false jika TRANSFER
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metode_pembayaran');
    }
};
