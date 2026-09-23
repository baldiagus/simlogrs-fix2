<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anggaran_pengadaan', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('id')->constrained('unit')->nullOnDelete();
            $table->string('tipe', 30)->default('UNIT')->after('unit_id'); // UNIT, SPAREPART_IPSRS, UMUM
        });
    }

    public function down(): void
    {
        Schema::table('anggaran_pengadaan', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn(['unit_id', 'tipe']);
        });
    }
};
