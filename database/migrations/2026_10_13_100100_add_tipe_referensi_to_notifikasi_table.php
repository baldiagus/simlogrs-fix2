<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifikasi', function (Blueprint $table) {
            $table->string('tipe', 40)->nullable()->after('pesan');
            $table->string('referensi_id', 40)->nullable()->after('tipe');
            $table->index(['pengguna_id', 'dibaca'], 'notifikasi_pengguna_dibaca_index');
        });
    }

    public function down(): void
    {
        Schema::table('notifikasi', function (Blueprint $table) {
            $table->dropIndex('notifikasi_pengguna_dibaca_index');
            $table->dropColumn(['tipe', 'referensi_id']);
        });
    }
};
