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
        Schema::create('kategori_barang', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama', 100);
            $table->text('deskripsi')->nullable();
            $table->string('status', 20)->default('AKTIF'); // AKTIF, NONAKTIF
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });

        Schema::create('satuan_barang', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->string('nama', 50);
            $table->string('simbol', 20)->nullable();
            $table->string('status', 20)->default('AKTIF'); // AKTIF, NONAKTIF
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });

        Schema::create('merek_barang', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama', 100);
            $table->string('status', 20)->default('AKTIF'); // AKTIF, NONAKTIF
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });

        Schema::create('lokasi_gudang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gudang_id')->constrained('gudang')->restrictOnDelete();
            $table->string('kode', 50); // misal: RAK-ATK-01
            $table->string('nama', 100);
            $table->string('baris', 20)->nullable();
            $table->string('kolom', 20)->nullable();
            $table->string('level', 20)->nullable();
            $table->string('status', 20)->default('AKTIF'); // AKTIF, NONAKTIF
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();

            $table->unique(['gudang_id', 'kode'], 'uk_lokasi_gudang_kode');
        });

        Schema::create('pemasok', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama', 150);
            $table->string('npwp', 50)->nullable();
            $table->text('alamat')->nullable();
            $table->string('kota', 100)->nullable();
            $table->string('no_telepon', 50)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('nama_kontak', 100)->nullable(); // PIC Vendor
            $table->string('status', 20)->default('AKTIF'); // AKTIF, NONAKTIF
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pemasok');
        Schema::dropIfExists('lokasi_gudang');
        Schema::dropIfExists('merek_barang');
        Schema::dropIfExists('satuan_barang');
        Schema::dropIfExists('kategori_barang');
    }
};
