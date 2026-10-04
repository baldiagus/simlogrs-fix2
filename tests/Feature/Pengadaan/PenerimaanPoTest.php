<?php

namespace Tests\Feature\Pengadaan;

use App\Livewire\Pengadaan\PenerimaanPoIndex;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\LokasiGudang;
use App\Models\Pemasok;
use App\Models\PermintaanPengadaan;
use App\Models\PesananPembelian;
use App\Models\PesananPembelianDetail;
use App\Models\StokBatch;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class PenerimaanPoTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Pemasok $pemasok;
    protected Barang $barang;
    protected Gudang $gudang;
    protected LokasiGudang $lokasiGudang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::where('email', 'superadmin@simlogrs.local')->first();
        $this->pemasok = Pemasok::first();
        $this->barang = Barang::first();
        $this->gudang = Gudang::where('status', 'AKTIF')->first();
        $this->lokasiGudang = LokasiGudang::where('gudang_id', $this->gudang->id)->where('status', 'AKTIF')->first();
    }

    public function test_receiving_po_with_damaged_goods_keeps_po_partially_received(): void
    {
        $this->actingAs($this->user);

        // 1. Buat PR & PO dengan jumlah 10 unit
        $pr = PermintaanPengadaan::create([
            'kode' => CodeGenerator::generatePr(),
            'unit_id' => 1,
            'diajukan_oleh' => $this->user->id,
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi' => 'REGULER',
            'status' => PermintaanPengadaan::STATUS_DIPROSES,
        ]);

        $po = PesananPembelian::create([
            'kode' => CodeGenerator::generatePo(),
            'pemasok_id' => $this->pemasok->id,
            'permintaan_pengadaan_id' => $pr->id,
            'tanggal_po' => now()->toDateString(),
            'total_biaya' => 500000,
            'syarat_pembayaran' => 'TEMPO_30_HARI',
            'status' => 'DIKIRIM_VENDOR',
            'dibuat_oleh' => $this->user->id,
        ]);

        $poDetail = PesananPembelianDetail::create([
            'pesanan_pembelian_id' => $po->id,
            'barang_id' => $this->barang->id,
            'jumlah_dipesan' => 10,
            'jumlah_diterima' => 0,
            'harga_satuan' => 50000,
            'subtotal' => 500000,
        ]);

        // 2. Petugas gudang menerima 10 unit fisik, namun 4 unit rusak/pecah
        Livewire::test(PenerimaanPoIndex::class)
            ->call('terima', $po->id)
            ->set('nomor_surat_jalan', 'SJ-VENDOR-999')
            ->set('gudang_id', $this->gudang->id)
            ->set('itemRows.0.jumlah_diterima', 10)
            ->set('itemRows.0.jumlah_rusak', 4)
            ->set('itemRows.0.nomor_batch', 'BATCH-TEST-001')
            ->set('itemRows.0.lokasi_gudang_id', $this->lokasiGudang->id)
            ->call('savePenerimaan');

        // 3. Verifikasi:
        // - Stok batch yang masuk harus netReceived = 6 unit
        $batch = StokBatch::where('nomor_batch', 'BATCH-TEST-001')->first();
        $this->assertNotNull($batch);
        $this->assertEquals(6, $batch->jumlah);

        // - PO Detail jumlah_diterima harus hanya bertambah 6 unit (barang layak)
        $poDetail->refresh();
        $this->assertEquals(6, $poDetail->jumlah_diterima);

        // - Status PO HARUS TETAP DITERIMA_SEBAGIAN (karena 4 unit rusak belum terpenuhi)
        $po->refresh();
        $this->assertEquals('DITERIMA_SEBAGIAN', $po->status);

        // - Status PR induk tidak boleh SELESAI
        $pr->refresh();
        $this->assertNotEquals('SELESAI', $pr->status);
    }
    public function test_inbound_receiving_locks_price_to_po_contract_and_ignores_tampering(): void
    {
        $this->actingAs($this->user);

        $po = PesananPembelian::create([
            'kode' => CodeGenerator::generatePo(),
            'pemasok_id' => $this->pemasok->id,
            'tanggal_po' => now()->toDateString(),
            'total_biaya' => 500000,
            'syarat_pembayaran' => 'TEMPO_30_HARI',
            'status' => 'DIKIRIM_VENDOR',
            'dibuat_oleh' => $this->user->id,
        ]);

        $poDetail = PesananPembelianDetail::create([
            'pesanan_pembelian_id' => $po->id,
            'barang_id' => $this->barang->id,
            'jumlah_dipesan' => 10,
            'jumlah_diterima' => 0,
            'harga_satuan' => 50000, // Harga kontrak resmi di database
            'subtotal' => 500000,
        ]);

        // Tamper harga_satuan di payload client menjadi 1000
        Livewire::test(PenerimaanPoIndex::class)
            ->call('terima', $po->id)
            ->set('nomor_surat_jalan', 'SJ-VENDOR-111')
            ->set('gudang_id', $this->gudang->id)
            ->set('itemRows.0.jumlah_diterima', 10)
            ->set('itemRows.0.jumlah_rusak', 0)
            ->set('itemRows.0.nomor_batch', 'BATCH-PRICE-LOCK')
            ->set('itemRows.0.harga_satuan', 1000) // Tampered payload!
            ->set('itemRows.0.lokasi_gudang_id', $this->lokasiGudang->id)
            ->call('savePenerimaan');

        // Batch stok HARUS memiliki harga_satuan 50000 (dari kontrak PO resmi), BUKAN 1000
        $batch = StokBatch::where('nomor_batch', 'BATCH-PRICE-LOCK')->first();
        $this->assertNotNull($batch);
        $this->assertEquals(50000, (float) $batch->harga_satuan);

        // Harga beli terakhir master barang HARUS terkunci di 50000, BUKAN 1000
        $this->barang->refresh();
        $this->assertEquals(50000, (float) $this->barang->harga_beli_terakhir);
    }
}
