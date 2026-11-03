<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pelanggan', function (Blueprint $table) {
            $table->id('id_pelanggan');
            $table->string('nama_pelanggan');
            $table->string('nomor_telepon', 20)->nullable();
            $table->unsignedInteger('poin_loyalitas')->default(0);
            $table->timestamp('terdaftar_pada')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pelanggan');
    }
};
