<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Log Forensik Digital Audit Trail
        Schema::create('log_audit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->foreignId('peran_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('referensi_jenis', 100); // e.g. Barang, PesananPembelian, PermintaanDistribusi
            $table->unsignedBigInteger('referensi_id');
            $table->string('aksi', 30); // TAMBAH, UBAH, HAPUS, APPROVE, REJECT, ADJUSTMENT
            $table->jsonb('data_sebelum')->nullable();
            $table->jsonb('data_sesudah')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('device')->nullable();
            $table->timestamp('dibuat_pada')->nullable();

            $table->index(['referensi_jenis', 'referensi_id', 'dibuat_pada'], 'idx_log_audit_lookup');
        });

        // 2. Tabel Register Temuan Audit & CAPA (Corrective and Preventive Action)
        Schema::create('temuan_audit', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique(); // AUD-YYYYMM-XXX
            $table->date('tanggal_audit');
            $table->string('objek_audit', 150);
            $table->text('ringkasan');
            $table->string('tingkat_risiko', 20)->default('Moderat'); // Mayor, Moderat, Minor
            $table->date('due_date_capa');
            $table->string('status_capa', 20)->default('In Progress'); // In Progress, In Review, Closed
            $table->text('analisis_penyebab')->nullable();
            $table->text('tindakan_perbaikan')->nullable();
            $table->string('bukti_perbaikan', 255)->nullable();
            $table->text('catatan_evaluasi')->nullable();
            $table->foreignId('auditor_id')->constrained('pengguna')->restrictOnDelete();
            $table->foreignId('dievaluasi_oleh')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('unit')->nullOnDelete();
            $table->foreignId('gudang_id')->nullable()->constrained('gudang')->nullOnDelete();
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });

        // 3. Tabel Notifikasi Lonceng Internal
        Schema::create('notifikasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengguna_id')->constrained('pengguna')->cascadeOnDelete();
            $table->string('judul', 150);
            $table->text('pesan');
            $table->string('tautan', 255)->nullable();
            $table->boolean('dibaca')->default(false);
            $table->timestamp('dibuat_pada')->nullable();
        });

        // 4. Tabel Pengaturan Parameter Sistem
        Schema::create('pengaturan_sistem', function (Blueprint $table) {
            $table->id();
            $table->string('kunci', 100)->unique();
            $table->text('nilai')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_sistem');
        Schema::dropIfExists('notifikasi');
        Schema::dropIfExists('temuan_audit');
        Schema::dropIfExists('log_audit');
    }
};
