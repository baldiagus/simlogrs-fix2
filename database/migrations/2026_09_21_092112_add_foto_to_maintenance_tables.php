<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permintaan_maintenance', function (Blueprint $table) {
            $table->string('foto_kerusakan', 255)->nullable()->after('deskripsi_masalah');
        });

        Schema::table('log_maintenance', function (Blueprint $table) {
            $table->string('foto_pengerjaan', 255)->nullable()->after('catatan');
        });
    }

    public function down(): void
    {
        Schema::table('log_maintenance', function (Blueprint $table) {
            $table->dropColumn('foto_pengerjaan');
        });

        Schema::table('permintaan_maintenance', function (Blueprint $table) {
            $table->dropColumn('foto_kerusakan');
        });
    }
};
