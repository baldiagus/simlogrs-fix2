<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pengguna', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->nullable()->constrained('unit')->nullOnDelete();
            $table->foreignId('gudang_id')->nullable()->constrained('gudang')->nullOnDelete();
            $table->string('nama');
            $table->string('nip', 30)->nullable();
            $table->string('username', 100)->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('no_telepon', 50)->nullable();
            $table->string('status', 20)->default('AKTIF'); // AKTIF, NONAKTIF
            $table->timestamp('terakhir_login')->nullable();
            $table->string('ip_terakhir', 45)->nullable();
            $table->rememberToken();
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });

        Schema::create('peran', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama', 100);
            $table->text('deskripsi')->nullable();
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });

        Schema::create('pengguna_peran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengguna_id')->constrained('pengguna')->cascadeOnDelete();
            $table->foreignId('peran_id')->constrained('peran')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('unit')->nullOnDelete();
            $table->foreignId('gudang_id')->nullable()->constrained('gudang')->nullOnDelete();
            $table->timestamp('dibuat_pada')->nullable();

            $table->unique(['pengguna_id', 'peran_id', 'unit_id', 'gudang_id'], 'uk_pengguna_peran_binding');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengguna_peran');
        Schema::dropIfExists('peran');
        Schema::dropIfExists('pengguna');
    }
};
