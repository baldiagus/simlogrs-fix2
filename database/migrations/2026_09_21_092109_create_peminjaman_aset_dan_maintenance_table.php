<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Permintaan Peminjaman Aset
        Schema::create('permintaan_peminjaman', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique(); // PINJ-YYYY-XXX
            $table->string('tipe_penyedia', 20)->default('RUANGAN'); // RUANGAN, GUDANG
            $table->foreignId('unit_penyedia_id')->nullable()->constrained('unit')->restrictOnDelete();
            $table->foreignId('gudang_penyedia_id')->nullable()->constrained('gudang')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('unit')->restrictOnDelete(); // Unit peminjam
            $table->foreignId('diajukan_oleh')->constrained('pengguna')->restrictOnDelete();
            $table->date('tanggal_pinjam');
            $table->date('tanggal_kembali_rencana');
            $table->text('keperluan')->nullable();
            $table->string('status', 30)->default('DIAJUKAN'); // DIAJUKAN, DISETUJUI, DIPINJAM, SELESAI, DITOLAK
            $table->foreignId('disetujui_oleh')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });

        // Constraint XOR penyedia aset: wajib dari Unit Ruangan ATAU Gudang
        DB::statement("ALTER TABLE permintaan_peminjaman ADD CONSTRAINT chk_pinjam_penyedia_xor CHECK ((tipe_penyedia = 'RUANGAN' AND unit_penyedia_id IS NOT NULL AND gudang_penyedia_id IS NULL) OR (tipe_penyedia = 'GUDANG' AND unit_penyedia_id IS NULL AND gudang_penyedia_id IS NOT NULL))");

        // 2. Detail Permintaan Peminjaman
        Schema::create('permintaan_peminjaman_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permintaan_peminjaman_id')->constrained('permintaan_peminjaman')->cascadeOnDelete();
            $table->foreignId('aset_id')->constrained('aset')->restrictOnDelete();
            $table->string('kondisi_saat_pinjam', 30)->default('BAIK');
            $table->text('catatan')->nullable();
        });

        // 3. Pengembalian Peminjaman
        Schema::create('pengembalian_peminjaman', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->nullable()->unique();
            $table->foreignId('permintaan_peminjaman_id')->constrained('permintaan_peminjaman')->restrictOnDelete();
            $table->date('tanggal_kembali_aktual');
            $table->foreignId('diperiksa_oleh')->constrained('pengguna')->restrictOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamp('dibuat_pada')->nullable();
        });

        // 4. Detail Pengembalian Peminjaman (Inspeksi Fisik)
        Schema::create('pengembalian_peminjaman_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengembalian_peminjaman_id')->constrained('pengembalian_peminjaman')->cascadeOnDelete();
            $table->foreignId('permintaan_peminjaman_detail_id')->constrained('permintaan_peminjaman_detail')->restrictOnDelete();
            $table->foreignId('aset_id')->constrained('aset')->restrictOnDelete();
            $table->string('kondisi_saat_kembali', 30)->default('BAIK'); // BAIK, RUSAK_RINGAN, RUSAK_BERAT
            $table->text('catatan')->nullable();
        });

        // 5. Permintaan Maintenance IPSRS (Auto-trigger saat pengembalian aset rusak)
        Schema::create('permintaan_maintenance', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique(); // MNT-YYYYMM-XXX
            $table->foreignId('aset_id')->constrained('aset')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('unit')->nullOnDelete();
            $table->foreignId('pengembalian_peminjaman_detail_id')->nullable()->constrained('pengembalian_peminjaman_detail')->nullOnDelete();
            $table->foreignId('dilaporkan_oleh')->constrained('pengguna')->restrictOnDelete();
            $table->text('deskripsi_masalah');
            $table->string('prioritas', 20)->default('SEDANG'); // RENDAH, SEDANG, TINGGI, CITO
            $table->string('status', 30)->default('DILAPORKAN'); // DILAPORKAN, DITUGASKAN, SEDANG_DIKERJAKAN, MENUNGGU_SPAREPART, UJI_FUNGSI, SELESAI, DIBATALKAN
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permintaan_maintenance');
        Schema::dropIfExists('pengembalian_peminjaman_detail');
        Schema::dropIfExists('pengembalian_peminjaman');
        Schema::dropIfExists('permintaan_peminjaman_detail');
        Schema::dropIfExists('permintaan_peminjaman');
    }
};
