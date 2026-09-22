<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Persediaan Lokal Ruangan
        Schema::create('stok_ruangan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('unit')->restrictOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->integer('jumlah')->default(0);
            $table->integer('stok_minimum')->default(0);
            $table->integer('stok_maksimum')->default(0);
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();

            $table->unique(['unit_id', 'barang_id']);
        });

        DB::statement('ALTER TABLE stok_ruangan ADD CONSTRAINT chk_stok_ruangan_non_negative CHECK (jumlah >= 0)');

        // 2. Permintaan Distribusi Ruangan (Amprah)
        Schema::create('permintaan_distribusi', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique(); // REQ-YYYYMM-XXX
            $table->foreignId('unit_id')->constrained('unit')->restrictOnDelete();
            $table->foreignId('gudang_id')->constrained('gudang')->restrictOnDelete();
            $table->date('tanggal_permintaan');
            $table->string('urgensi', 20)->default('REGULER'); // REGULER, CITO
            $table->string('status', 30)->default('DIAJUKAN'); // DIAJUKAN, DISETUJUI_KARU, DISETUJUI_GUDANG, SEDANG_DIKIRIM, SELESAI, DITOLAK
            $table->text('catatan')->nullable();
            $table->foreignId('diajukan_oleh')->constrained('pengguna')->restrictOnDelete();
            $table->foreignId('disetujui_oleh')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });

        // 3. Detail Permintaan Distribusi
        Schema::create('permintaan_distribusi_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permintaan_distribusi_id')->constrained('permintaan_distribusi')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->integer('jumlah');
            $table->integer('jumlah_disetujui')->nullable();
            $table->text('catatan')->nullable();
        });

        // 4. Pengiriman Barang (SPPB Gudang)
        Schema::create('pengiriman_barang', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique(); // SPPB-YYYYMMDD-XXXX
            $table->foreignId('permintaan_distribusi_id')->constrained('permintaan_distribusi')->restrictOnDelete();
            $table->foreignId('gudang_id')->constrained('gudang')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('unit')->restrictOnDelete();
            $table->date('tanggal_kirim');
            $table->string('status', 30)->default('DIKIRIM'); // DIKIRIM, DITERIMA, BATAL
            $table->text('catatan')->nullable();
            $table->foreignId('dikirim_oleh')->constrained('pengguna')->restrictOnDelete();
            $table->timestamp('dibuat_pada')->nullable();
        });

        // 5. Detail Pengiriman Barang (Pengalokasian Batch FEFO)
        Schema::create('pengiriman_barang_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengiriman_barang_id')->constrained('pengiriman_barang')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->foreignId('stok_batch_id')->constrained('stok_batch')->restrictOnDelete();
            $table->integer('jumlah_dikirim');
        });

        // 6. Penerimaan Distribusi Ruangan (Serah Terima Fisik)
        Schema::create('penerimaan_distribusi', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique(); // RCV-YYYYMM-XXX
            $table->foreignId('pengiriman_barang_id')->constrained('pengiriman_barang')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('unit')->restrictOnDelete();
            $table->date('tanggal_terima');
            $table->string('status', 30)->default('SELESAI'); // SELESAI, SEBAGIAN
            $table->text('catatan')->nullable();
            $table->foreignId('diterima_oleh')->constrained('pengguna')->restrictOnDelete();
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });

        // 7. Detail Penerimaan Distribusi
        Schema::create('penerimaan_distribusi_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penerimaan_distribusi_id')->constrained('penerimaan_distribusi')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->integer('jumlah_diterima');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penerimaan_distribusi_detail');
        Schema::dropIfExists('penerimaan_distribusi');
        Schema::dropIfExists('pengiriman_barang_detail');
        Schema::dropIfExists('pengiriman_barang');
        Schema::dropIfExists('permintaan_distribusi_detail');
        Schema::dropIfExists('permintaan_distribusi');
        Schema::dropIfExists('stok_ruangan');
    }
};
