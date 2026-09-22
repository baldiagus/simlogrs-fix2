<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Penugasan Maintenance / Surat Perintah Kerja (SPK)
        Schema::create('penugasan_maintenance', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique(); // SPK-YYYYMM-XXX
            $table->foreignId('permintaan_maintenance_id')->constrained('permintaan_maintenance')->restrictOnDelete();
            $table->foreignId('teknisi_id')->constrained('pengguna')->restrictOnDelete();
            $table->foreignId('ditugaskan_oleh')->constrained('pengguna')->restrictOnDelete();
            $table->date('tanggal_penugasan');
            $table->date('target_selesai')->nullable();
            $table->text('instruksi_khusus')->nullable();
            $table->string('status', 30)->default('DITUGASKAN'); // DITUGASKAN, DITERIMA, SELESAI, DIBATALKAN
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });

        // 2. Log Maintenance / Lembar Kerja Pengerjaan Teknisi
        Schema::create('log_maintenance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permintaan_maintenance_id')->constrained('permintaan_maintenance')->restrictOnDelete();
            $table->foreignId('penugasan_maintenance_id')->nullable()->constrained('penugasan_maintenance')->nullOnDelete();
            $table->foreignId('dikerjakan_oleh')->constrained('pengguna')->restrictOnDelete();
            $table->dateTime('tanggal_pengerjaan');
            $table->string('jam_mulai', 10)->nullable();
            $table->string('jam_selesai', 10)->nullable();
            $table->text('diagnosa')->nullable();
            $table->text('tindakan');
            $table->string('hasil', 30)->default('BERHASIL'); // BERHASIL, PENDING, GAGAL
            $table->text('catatan')->nullable();
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });

        // 3. Suku Cadang Maintenance (Konsumsi Sparepart FEFO)
        Schema::create('suku_cadang_maintenance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permintaan_maintenance_id')->constrained('permintaan_maintenance')->restrictOnDelete();
            $table->foreignId('log_maintenance_id')->nullable()->constrained('log_maintenance')->nullOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->foreignId('stok_batch_id')->nullable()->constrained('stok_batch')->nullOnDelete();
            $table->foreignId('pergerakan_stok_id')->nullable()->constrained('pergerakan_stok')->nullOnDelete();
            $table->integer('jumlah_digunakan');
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->foreignId('dicatat_oleh')->constrained('pengguna')->restrictOnDelete();
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });

        // 4. Jadwal Maintenance Preventif Periodik
        Schema::create('jadwal_maintenance', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique(); // PRV-YYYYMM-XXX
            $table->foreignId('aset_id')->constrained('aset')->restrictOnDelete();
            $table->string('frekuensi', 30)->default('BULANAN'); // MINGGUAN, BULANAN, TRIWULAN, SEMESTER, TAHUNAN
            $table->string('jenis_maintenance', 150);
            $table->date('tanggal_terakhir')->nullable();
            $table->date('tanggal_berikutnya');
            $table->text('catatan')->nullable();
            $table->string('status', 20)->default('AKTIF'); // AKTIF, NONAKTIF
            $table->foreignId('dibuat_oleh')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_maintenance');
        Schema::dropIfExists('suku_cadang_maintenance');
        Schema::dropIfExists('log_maintenance');
        Schema::dropIfExists('penugasan_maintenance');
    }
};
