<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo', function (Blueprint $table) {
            $table->id('id_promo');
            $table->string('kode_promo')->unique();
            $table->enum('tipe_diskon', ['persen', 'nominal']);
            $table->decimal('nilai_diskon', 10, 2);
            $table->datetime('berlaku_mulai')->nullable();
            $table->datetime('berlaku_sampai')->nullable();
            $table->boolean('status_aktif')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo');
    }
};
