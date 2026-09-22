<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_maintenance', function (Blueprint $table) {
            $table->foreignId('teknisi_id')->nullable()->after('aset_id')->constrained('pengguna')->nullOnDelete();
            $table->integer('interval_nilai')->default(1)->after('frekuensi');
            $table->string('interval_tipe', 20)->default('BULAN')->after('interval_nilai'); // HARI, MINGGU, BULAN
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_maintenance', function (Blueprint $table) {
            $table->dropForeign(['teknisi_id']);
            $table->dropColumn(['teknisi_id', 'interval_nilai', 'interval_tipe']);
        });
    }
};
