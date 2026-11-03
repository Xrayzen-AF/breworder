<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Token acak pada QR fisik meja. Bukan id berurutan supaya URL meja
        // tidak bisa ditebak/diulang dari meja lain.
        Schema::table('meja', function (Blueprint $table) {
            $table->string('qr_token', 64)->nullable()->unique()->after('status');
        });

        $tokens = [];
        foreach (DB::table('meja')->select('id_meja')->get() as $row) {
            do {
                $token = Str::random(40);
            } while (in_array($token, $tokens, true));

            $tokens[] = $token;
            DB::table('meja')->where('id_meja', $row->id_meja)->update(['qr_token' => $token]);
        }

        Schema::create('reservasi_meja', function (Blueprint $table) {
            $table->id('id_reservasi');
            $table->foreignId('id_meja')
                ->constrained('meja', 'id_meja')
                ->cascadeOnDelete();
            $table->foreignId('id_pelanggan')
                ->nullable()
                ->constrained('pelanggan', 'id_pelanggan')
                ->nullOnDelete(); // reservasi tamu tanpa akun boleh tanpa pelanggan
            $table->string('kode_booking', 12)->unique();
            $table->enum('status', ['aktif', 'selesai', 'kedaluwarsa', 'dibatalkan'])->default('aktif');
            $table->datetime('dicek_in_pada')->nullable();
            $table->datetime('berakhir_pada');
            $table->timestamp('dibuat_pada')->useCurrent();

            $table->index(['id_meja', 'status']);
            $table->index(['status', 'berakhir_pada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservasi_meja');

        Schema::table('meja', function (Blueprint $table) {
            $table->dropUnique(['qr_token']);
            $table->dropColumn('qr_token');
        });
    }
};
