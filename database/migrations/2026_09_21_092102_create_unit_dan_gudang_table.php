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
        Schema::create('unit', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama', 150);
            $table->string('jenis', 50)->default('PELAYANAN_MEDIS'); // PELAYANAN_MEDIS, PENUNJANG_MEDIS, ADMINISTRASI, OPERASIONAL
            $table->string('penanggung_jawab', 150)->nullable();
            $table->string('lokasi_gedung', 100)->nullable();
            $table->string('status', 20)->default('AKTIF'); // AKTIF, NONAKTIF
            $table->unsignedBigInteger('kepala_unit_id')->nullable(); // FK pengguna, diisi seeder
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });

        Schema::create('gudang', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama', 150);
            $table->string('jenis', 50)->default('SENTRAL'); // SENTRAL, TRANSIT, UNIT, DEPO
            $table->string('penanggung_jawab', 150)->nullable();
            $table->string('lokasi_gedung', 100)->nullable();
            $table->string('status', 20)->default('AKTIF'); // AKTIF, NONAKTIF
            $table->unsignedBigInteger('kepala_gudang_id')->nullable(); // FK pengguna, diisi seeder
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gudang');
        Schema::dropIfExists('unit');
    }
};
