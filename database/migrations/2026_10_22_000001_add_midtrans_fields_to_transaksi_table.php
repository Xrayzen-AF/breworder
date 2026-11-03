<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->string('midtrans_order_id', 80)->nullable()->unique()->after('nomor_referensi');
            $table->string('midtrans_payment_type', 40)->nullable()->after('midtrans_order_id');
            $table->json('midtrans_payload')->nullable()->after('midtrans_payment_type');
            $table->datetime('midtrans_expires_at')->nullable()->after('midtrans_payload');
        });
    }

    public function down(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->dropUnique(['midtrans_order_id']);
            $table->dropColumn([
                'midtrans_order_id',
                'midtrans_payment_type',
                'midtrans_payload',
                'midtrans_expires_at',
            ]);
        });
    }
};
