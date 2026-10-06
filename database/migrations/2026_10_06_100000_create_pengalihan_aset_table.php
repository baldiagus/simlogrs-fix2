<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Pengalihan Aset
        Schema::create('pengalihan_aset', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique(); // TRF-YYYYMM-XXX
            $table->string('tipe_asal', 20)->default('RUANGAN'); // RUANGAN, GUDANG
            $table->foreignId('unit_asal_id')->nullable()->constrained('unit')->restrictOnDelete();
            $table->foreignId('gudang_asal_id')->nullable()->constrained('gudang')->restrictOnDelete();
            $table->string('tipe_tujuan', 20)->default('RUANGAN'); // RUANGAN, GUDANG
            $table->foreignId('unit_tujuan_id')->nullable()->constrained('unit')->restrictOnDelete();
            $table->foreignId('gudang_tujuan_id')->nullable()->constrained('gudang')->restrictOnDelete();
            $table->foreignId('lokasi_gudang_tujuan_id')->nullable()->constrained('lokasi_gudang')->nullOnDelete();
            $table->date('tanggal_pengalihan');
            $table->text('alasan');
            $table->string('status', 30)->default('DIAJUKAN'); // DIAJUKAN, DISETUJUI, SELESAI, DITOLAK, DIBATALKAN
            $table->foreignId('diajukan_oleh')->constrained('pengguna')->restrictOnDelete();
            $table->foreignId('disetujui_oleh')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->foreignId('diterima_oleh')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('tanggal_serah_terima')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });

        // Constraint XOR asal: wajib dari Unit Ruangan ATAU Gudang
        DB::statement("ALTER TABLE pengalihan_aset ADD CONSTRAINT chk_pengalihan_asal_xor CHECK ((tipe_asal = 'RUANGAN' AND unit_asal_id IS NOT NULL AND gudang_asal_id IS NULL) OR (tipe_asal = 'GUDANG' AND unit_asal_id IS NULL AND gudang_asal_id IS NOT NULL))");

        // Constraint XOR tujuan: wajib ke Unit Ruangan ATAU Gudang
        DB::statement("ALTER TABLE pengalihan_aset ADD CONSTRAINT chk_pengalihan_tujuan_xor CHECK ((tipe_tujuan = 'RUANGAN' AND unit_tujuan_id IS NOT NULL AND gudang_tujuan_id IS NULL) OR (tipe_tujuan = 'GUDANG' AND unit_tujuan_id IS NULL AND gudang_tujuan_id IS NOT NULL))");

        // 2. Tabel Detail Pengalihan Aset
        Schema::create('pengalihan_aset_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengalihan_aset_id')->constrained('pengalihan_aset')->cascadeOnDelete();
            $table->foreignId('aset_id')->constrained('aset')->restrictOnDelete();
            $table->string('kondisi_sebelum', 30)->default('BAIK');
            $table->string('kondisi_saat_diterima', 30)->nullable();
            $table->text('catatan')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengalihan_aset_detail');
        Schema::dropIfExists('pengalihan_aset');
    }
};
