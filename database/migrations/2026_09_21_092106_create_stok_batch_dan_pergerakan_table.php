<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_batch', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->foreignId('gudang_id')->constrained('gudang')->restrictOnDelete();
            $table->foreignId('lokasi_gudang_id')->nullable()->constrained('lokasi_gudang')->nullOnDelete();
            $table->string('nomor_batch', 100);
            $table->date('tanggal_kedaluwarsa')->nullable()->index();
            $table->integer('jumlah')->default(0);
            $table->integer('jumlah_dipesan')->default(0);
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->string('status', 30)->default('AKTIF'); // AKTIF, HAMPIR_KEDALUWARSA, KEDALUWARSA, HABIS
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();

            $table->index(['barang_id', 'status', 'tanggal_kedaluwarsa'], 'idx_stok_batch_fefo');
        });

        // CHECK constraint: saldo stok tidak boleh negatif
        DB::statement('ALTER TABLE stok_batch ADD CONSTRAINT chk_stok_batch_non_negative CHECK (jumlah >= 0 AND jumlah_dipesan >= 0)');

        Schema::create('pergerakan_stok', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->foreignId('stok_batch_id')->constrained('stok_batch')->restrictOnDelete();
            $table->foreignId('gudang_id')->constrained('gudang')->restrictOnDelete();
            $table->foreignId('lokasi_gudang_id')->nullable()->constrained('lokasi_gudang')->nullOnDelete();
            $table->string('jenis', 30); // MASUK, KELUAR, PENYESUAIAN, TRANSFER
            $table->integer('jumlah');
            $table->integer('saldo_sebelum');
            $table->integer('saldo_sesudah');
            $table->string('referensi_jenis', 50); // PENERIMAAN_PO, PENGIRIMAN_DISTRIBUSI, STOCK_OPNAME, MAINTENANCE
            $table->unsignedBigInteger('referensi_id');
            $table->text('keterangan')->nullable();
            $table->foreignId('dibuat_oleh')->constrained('pengguna')->restrictOnDelete();
            $table->timestamp('dibuat_pada')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pergerakan_stok');
        Schema::dropIfExists('stok_batch');
    }
};
