<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pesanan', function (Blueprint $table) {
            if (! Schema::hasColumn('pesanan', 'poin_dipakai')) {
                $table->unsignedInteger('poin_dipakai')->default(0)->after('diskon');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pesanan', function (Blueprint $table) {
            $table->dropColumn('poin_dipakai');
        });
    }
};
