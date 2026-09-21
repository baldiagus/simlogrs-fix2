<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('barang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kategori_id')->constrained('kategori_barang')->restrictOnDelete();
            $table->foreignId('satuan_id')->constrained('satuan_barang')->restrictOnDelete();
            $table->foreignId('merek_id')->nullable()->constrained('merek_barang')->nullOnDelete();
            $table->foreignId('pemasok_default_id')->nullable()->constrained('pemasok')->nullOnDelete();
            $table->string('kode', 50)->unique();
            $table->string('nama', 255);
            $table->string('tipe', 30)->default('HABIS_PAKAI'); // HABIS_PAKAI, ASET, SPAREPART
            $table->integer('stok_minimum')->default(0);
            $table->integer('stok_maksimum')->default(0);
            $table->integer('titik_pesan')->default(0); // Reorder Point (ROP)
            $table->decimal('harga_beli_terakhir', 15, 2)->default(0);
            $table->decimal('harga_estimasi', 15, 2)->default(0);
            $table->string('status', 20)->default('AKTIF'); // AKTIF, NONAKTIF
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });

        // Ekstensi Trigram dan GIN Index untuk pencarian instan nama barang (< 10ms)
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm;');
        DB::statement('CREATE INDEX idx_barang_nama_trgm ON barang USING gin (nama gin_trgm_ops);');

        Schema::create('aset', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('unit')->nullOnDelete();
            $table->foreignId('gudang_id')->nullable()->constrained('gudang')->nullOnDelete();
            $table->foreignId('lokasi_gudang_id')->nullable()->constrained('lokasi_gudang')->nullOnDelete();
            $table->string('kode_aset', 50)->unique(); // AST-YYYY-XXX
            $table->string('nomor_seri', 100)->nullable();
            $table->date('tanggal_perolehan');
            $table->decimal('nilai_perolehan', 15, 2)->default(0);
            $table->string('kondisi', 30)->default('BAIK'); // BAIK, RUSAK_RINGAN, RUSAK_BERAT
            $table->string('status', 30)->default('TERSEDIA'); // TERSEDIA, DIGUNAKAN, DIPINJAM, DALAM_MAINTENANCE, DIHAPUSKAN
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });

        // Check Constraint XOR: Aset fisik wajib berada di Ruangan ATAU di Gudang
        DB::statement('ALTER TABLE aset ADD CONSTRAINT chk_aset_lokasi_xor CHECK ((unit_id IS NOT NULL AND gudang_id IS NULL AND lokasi_gudang_id IS NULL) OR (unit_id IS NULL AND gudang_id IS NOT NULL));');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aset');
        DB::statement('DROP INDEX IF EXISTS idx_barang_nama_trgm;');
        Schema::dropIfExists('barang');
    }
};
