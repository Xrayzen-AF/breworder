<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('midtrans_settings', function (Blueprint $table) {
            $table->id();
            $table->string('server_key')->nullable();
            $table->string('client_key')->nullable();
            $table->boolean('is_production')->default(false);
            $table->string('webhook_verified')->default('pending');
            $table->timestamps();

            $table->unique(['server_key', 'is_production']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('midtrans_settings');
    }
};
