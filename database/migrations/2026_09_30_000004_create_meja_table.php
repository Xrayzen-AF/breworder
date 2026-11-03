<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meja', function (Blueprint $table) {
            $table->id('id_meja');
            $table->string('nomor_meja')->unique();
            $table->unsignedInteger('kapasitas');
            $table->enum('status', ['kosong', 'terisi', 'dipesan'])->default('kosong');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meja');
    }
};
