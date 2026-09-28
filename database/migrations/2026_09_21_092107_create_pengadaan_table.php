<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Anggaran Pengadaan
        Schema::create('anggaran_pengadaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->nullable()->constrained('unit')->nullOnDelete();
            $table->string('tipe', 30)->default('UNIT'); // UNIT, SPAREPART_IPSRS, UMUM
            $table->string('kode', 50)->unique();
            $table->string('pos_anggaran', 200);
            $table->decimal('pagu', 15, 2)->default(0);
            $table->decimal('realisasi', 15, 2)->default(0);
            $table->decimal('sisa', 15, 2)->default(0);
            $table->decimal('serapan', 5, 2)->default(0); // persentase
            $table->string('tahun', 10)->default('2026');
            $table->string('status', 50)->default('Normal');
            $table->json('po_terkait')->nullable();
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });

        // 2. Permintaan Pengadaan (PR)
        Schema::create('permintaan_pengadaan', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique(); // PR-YYYY-XXX
            $table->foreignId('unit_id')->nullable()->constrained('unit')->nullOnDelete();
            $table->foreignId('gudang_id')->nullable()->constrained('gudang')->nullOnDelete();
            $table->foreignId('diajukan_oleh')->constrained('pengguna')->restrictOnDelete();
            $table->date('tanggal_permintaan');
            $table->string('urgensi', 20)->default('REGULER'); // REGULER, MENDESAK, CITO
            $table->string('status', 30)->default('DIAJUKAN'); // DIAJUKAN, DISETUJUI, DITERUSKAN_KE_PENGADAAN, DITOLAK
            $table->text('catatan')->nullable();
            $table->foreignId('disetujui_oleh')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });

        // Check Constraint XOR: Pemohon PR wajib berasal dari Unit ATAU Gudang (salah satu, eksklusif)
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE permintaan_pengadaan ADD CONSTRAINT chk_pr_pemohon_xor CHECK ((unit_id IS NOT NULL AND gudang_id IS NULL) OR (unit_id IS NULL AND gudang_id IS NOT NULL));');

        // 3. Permintaan Pengadaan Detail
        Schema::create('permintaan_pengadaan_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permintaan_pengadaan_id')->constrained('permintaan_pengadaan')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->integer('jumlah');
            $table->integer('jumlah_disetujui')->nullable();
            $table->text('catatan')->nullable();
        });

        // 4. Pesanan Pembelian (PO)
        Schema::create('pesanan_pembelian', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique(); // PO-YYYY-XXX
            $table->foreignId('pemasok_id')->constrained('pemasok')->restrictOnDelete();
            $table->foreignId('permintaan_pengadaan_id')->nullable()->constrained('permintaan_pengadaan')->nullOnDelete();
            $table->date('tanggal_po');
            $table->date('estimasi_tanggal_kirim')->nullable();
            $table->decimal('total_biaya', 15, 2)->default(0);
            $table->string('syarat_pembayaran', 50)->default('TEMPO_30_HARI');
            $table->string('status', 30)->default('DITERBITKAN'); // DITERBITKAN, DIKIRIM_VENDOR, DITERIMA_SEBAGIAN, DITERIMA_LENGKAP, DIBATALKAN
            $table->text('catatan')->nullable();
            $table->foreignId('dibuat_oleh')->constrained('pengguna')->restrictOnDelete();
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });

        // 5. Pesanan Pembelian Detail
        Schema::create('pesanan_pembelian_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesanan_pembelian_id')->constrained('pesanan_pembelian')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->integer('jumlah_dipesan');
            $table->integer('jumlah_diterima')->default(0);
            $table->decimal('harga_satuan', 15, 2);
            $table->decimal('subtotal', 15, 2);
        });

        // 6. Penerimaan Barang (Inbound)
        Schema::create('penerimaan_barang', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique(); // RCV-YYYYMM-XXX
            $table->foreignId('pesanan_pembelian_id')->constrained('pesanan_pembelian')->restrictOnDelete();
            $table->foreignId('gudang_id')->constrained('gudang')->restrictOnDelete();
            $table->string('nomor_surat_jalan', 100);
            $table->date('tanggal_terima');
            $table->string('status', 30)->default('LENGKAP'); // LENGKAP, SEBAGIAN, DITOLAK
            $table->text('catatan')->nullable();
            $table->foreignId('diterima_oleh')->constrained('pengguna')->restrictOnDelete();
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });

        // 7. Penerimaan Barang Detail
        Schema::create('penerimaan_barang_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penerimaan_barang_id')->constrained('penerimaan_barang')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->foreignId('lokasi_gudang_id')->constrained('lokasi_gudang')->restrictOnDelete();
            $table->string('nomor_batch', 100);
            $table->date('tanggal_kedaluwarsa')->nullable();
            $table->integer('jumlah_diterima');
            $table->integer('jumlah_rusak')->default(0);
            $table->decimal('harga_satuan', 15, 2);
        });
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE permintaan_pengadaan DROP CONSTRAINT IF EXISTS chk_pr_pemohon_xor;');
        Schema::dropIfExists('penerimaan_barang_detail');
        Schema::dropIfExists('penerimaan_barang');
        Schema::dropIfExists('pesanan_pembelian_detail');
        Schema::dropIfExists('pesanan_pembelian');
        Schema::dropIfExists('permintaan_pengadaan_detail');
        Schema::dropIfExists('permintaan_pengadaan');
        Schema::dropIfExists('anggaran_pengadaan');
    }
};
