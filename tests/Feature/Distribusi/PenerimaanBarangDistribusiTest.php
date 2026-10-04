<?php

namespace Tests\Feature\Distribusi;

use App\Livewire\Distribusi\PenerimaanBarangIndex;
use App\Models\Aset;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\LokasiGudang;
use App\Models\PengirimanBarang;
use App\Models\PengirimanBarangDetail;
use App\Models\PermintaanDistribusi;
use App\Models\PermintaanDistribusiDetail;
use App\Models\StokBatch;
use App\Models\Unit;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class PenerimaanBarangDistribusiTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Unit $unit;
    protected Gudang $gudang;
    protected Barang $barangAset;
    protected LokasiGudang $lokasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::where('email', 'superadmin@simlogrs.local')->first();
        $this->unit = Unit::where('status', 'AKTIF')->first();
        $this->gudang = Gudang::where('status', 'AKTIF')->first();
        $this->lokasi = LokasiGudang::where('gudang_id', $this->gudang->id)->where('status', 'AKTIF')->first();

        // Cari atau buat komoditas aset
        $this->barangAset = Barang::where('status', 'AKTIF')->where('tipe', 'ASET')->first();
        if (! $this->barangAset) {
            $this->barangAset = Barang::create([
                'kode' => 'BRG-AST-TEST',
                'nama' => 'Tensimeter Digital Uji Seri',
                'tipe' => 'ASET',
                'satuan_id' => 1,
                'status' => 'AKTIF',
            ]);
        }
    }

    public function test_bulk_asset_receipt_assigns_unique_serial_numbers_per_unit(): void
    {
        $this->actingAs($this->user);

        // Batch persediaan aset di gudang
        $batch = StokBatch::create([
            'barang_id' => $this->barangAset->id,
            'gudang_id' => $this->gudang->id,
            'lokasi_gudang_id' => $this->lokasi->id,
            'nomor_batch' => 'BATCH-AST-TEST-99',
            'tanggal_kedaluwarsa' => null,
            'jumlah' => 10,
            'harga_satuan' => 1500000,
            'status' => 'AKTIF',
        ]);

        // Permintaan amprah untuk 3 unit aset
        $permintaan = PermintaanDistribusi::create([
            'kode' => CodeGenerator::generate('REQ', PermintaanDistribusi::class, 'kode', 'Ym', 3),
            'unit_id' => $this->unit->id,
            'gudang_id' => $this->gudang->id,
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi' => 'REGULER',
            'status' => 'SEDANG_DIKIRIM',
            'diajukan_oleh' => $this->user->id,
        ]);

        PermintaanDistribusiDetail::create([
            'permintaan_distribusi_id' => $permintaan->id,
            'barang_id' => $this->barangAset->id,
            'jumlah' => 3,
            'jumlah_disetujui' => 3,
        ]);

        // SPPB Pengiriman 3 unit aset
        $sppb = PengirimanBarang::create([
            'kode' => CodeGenerator::generate('SPPB', PengirimanBarang::class, 'kode', 'Ymd', 4),
            'permintaan_distribusi_id' => $permintaan->id,
            'gudang_id' => $this->gudang->id,
            'unit_id' => $this->unit->id,
            'tanggal_kirim' => now()->toDateString(),
            'status' => 'DIKIRIM',
            'dikirim_oleh' => $this->user->id,
        ]);

        PengirimanBarangDetail::create([
            'pengiriman_barang_id' => $sppb->id,
            'barang_id' => $this->barangAset->id,
            'stok_batch_id' => $batch->id,
            'jumlah_dikirim' => 3,
        ]);

        // Ruangan menerima fisik 3 unit aset
        Livewire::test(PenerimaanBarangIndex::class)
            ->call('terima', $sppb->id);

        // Ambil 3 aset yang terdaftar untuk SPPB ini
        $assets = Aset::where('barang_id', $this->barangAset->id)
            ->where('unit_id', $this->unit->id)
            ->latest('id')
            ->take(3)
            ->get();

        $this->assertCount(3, $assets);

        // Seluruh 3 unit fisik HARUS memiliki nomor seri yang BERBEDA (unik)
        $serialNumbers = $assets->pluck('nomor_seri')->all();
        $this->assertCount(3, array_unique($serialNumbers), 'Nomor seri aset terduplikasi pada penerimaan massal: ' . json_encode($serialNumbers));

        // Seluruh 3 unit fisik HARUS memiliki kode register yang BERBEDA (unik)
        $assetCodes = $assets->pluck('kode_aset')->all();
        $this->assertCount(3, array_unique($assetCodes), 'Kode register aset terduplikasi: ' . json_encode($assetCodes));
    }
}
