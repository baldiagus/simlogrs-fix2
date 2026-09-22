<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Sesi Stock Opname
        Schema::create('stok_opname', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique(); // OPN-YYYYMM-XXX
            $table->foreignId('gudang_id')->constrained('gudang')->restrictOnDelete();
            $table->date('tanggal_opname');
            $table->string('status', 30)->default('DRAFT'); // DRAFT, BERJALAN, MENUNGGU_APPROVAL, DISETUJUI, DITOLAK
            $table->text('catatan')->nullable();
            $table->foreignId('dibuat_oleh')->constrained('pengguna')->restrictOnDelete();
            $table->foreignId('disetujui_oleh')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });

        // 2. Tabel Rincian Hitung Fisik Opname (Blind Count)
        Schema::create('stok_opname_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stok_opname_id')->constrained('stok_opname')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->foreignId('stok_batch_id')->nullable()->constrained('stok_batch')->nullOnDelete();
            $table->integer('jumlah_sistem')->default(0);
            $table->integer('jumlah_fisik')->default(0);
            $table->integer('selisih')->default(0); // jumlah_fisik - jumlah_sistem
            $table->text('catatan')->nullable();
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });

        // 3. Tabel Dokumen Pengajuan Penyesuaian Stok
        Schema::create('penyesuaian_stok', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique(); // ADJ-YYYYMM-XXX
            $table->foreignId('stok_opname_id')->nullable()->constrained('stok_opname')->nullOnDelete();
            $table->foreignId('gudang_id')->constrained('gudang')->restrictOnDelete();
            $table->date('tanggal_penyesuaian');
            $table->text('alasan');
            $table->string('status', 30)->default('MENUNGGU'); // MENUNGGU, DISETUJUI, DITOLAK
            $table->foreignId('diajukan_oleh')->constrained('pengguna')->restrictOnDelete();
            $table->foreignId('disetujui_oleh')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });

        // 4. Tabel Detail Penyesuaian Saldo Batch
        Schema::create('penyesuaian_stok_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penyesuaian_stok_id')->constrained('penyesuaian_stok')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->foreignId('stok_batch_id')->constrained('stok_batch')->restrictOnDelete();
            $table->integer('jumlah_penyesuaian'); // nilai mutlak
            $table->string('jenis_penyesuaian', 20); // TAMBAH, KURANG
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->decimal('subtotal_nilai', 15, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penyesuaian_stok_detail');
        Schema::dropIfExists('penyesuaian_stok');
        Schema::dropIfExists('stok_opname_detail');
        Schema::dropIfExists('stok_opname');
    }
};
