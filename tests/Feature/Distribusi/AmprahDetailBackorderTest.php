<?php

namespace Tests\Feature\Distribusi;

use App\Livewire\Distribusi\ApprovalPermintaanIndex;
use App\Livewire\Distribusi\DistribusiApprovalIndex;
use App\Livewire\Distribusi\PermintaanBarangIndex;
use App\Models\Master\Barang;
use App\Models\Master\Gudang;
use App\Models\Master\KategoriBarang;
use App\Models\Master\LokasiGudang;
use App\Models\Distribusi\PengirimanBarang;
use App\Models\Distribusi\PengirimanBarangDetail;
use App\Models\Distribusi\PermintaanDistribusi;
use App\Models\Distribusi\PermintaanDistribusiDetail;
use App\Models\Master\SatuanBarang;
use App\Models\Persediaan\StokBatch;
use App\Models\Master\Unit;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class AmprahDetailBackorderTest extends TestCase
{
    use DatabaseTransactions;

    protected User $userAdmin;
    protected Gudang $gudang;
    protected Unit $unit;
    protected Barang $barang;
    protected LokasiGudang $lokasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userAdmin = User::where('email', 'superadmin@simlogrs.local')->first();
        $this->gudang = Gudang::where('status', 'AKTIF')->first();
        $this->unit = Unit::where('status', 'AKTIF')->first();

        $this->lokasi = LokasiGudang::firstOrCreate(
            ['kode' => 'RAK-DTL-BO', 'gudang_id' => $this->gudang->id],
            ['nama' => 'Rak Detail Backorder', 'status' => 'AKTIF']
        );

        $kategori = KategoriBarang::firstOrCreate(['kode' => 'KAT-DTL-BO'], ['nama' => 'Kategori Detail BO', 'status' => 'AKTIF']);
        $satuan = SatuanBarang::firstOrCreate(['kode' => 'PCS-DTL-BO'], ['nama' => 'Pcs', 'status' => 'AKTIF']);

        $this->barang = Barang::firstOrCreate(
            ['kode' => 'BRG-DTL-BO-01'],
            [
                'nama' => 'Kassa Steril Backorder Modal Test',
                'kategori_id' => $kategori->id,
                'satuan_id' => $satuan->id,
                'tipe' => 'HABIS_PAKAI',
                'status' => 'AKTIF',
            ]
        );
    }

    public function test_detail_modal_in_permintaan_barang_displays_accurate_shipped_and_backorder_quantities(): void
    {
        $this->actingAs($this->userAdmin);

        // 1. Permintaan 10 unit, disetujui 10 unit, status DIKIRIM_SEBAGIAN
        $req = PermintaanDistribusi::create([
            'kode' => CodeGenerator::generate('REQ', PermintaanDistribusi::class, 'kode', 'Ym', 3),
            'unit_id' => $this->unit->id,
            'gudang_id' => $this->gudang->id,
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi' => 'REGULER',
            'status' => 'DIKIRIM_SEBAGIAN',
            'diajukan_oleh' => $this->userAdmin->id,
            'disetujui_oleh' => $this->userAdmin->id,
        ]);
        $detail = PermintaanDistribusiDetail::create([
            'permintaan_distribusi_id' => $req->id,
            'barang_id' => $this->barang->id,
            'jumlah' => 10,
            'jumlah_disetujui' => 10,
        ]);

        // SPPB 1 sudah terbit dan mengirim 4 unit
        $sppb = PengirimanBarang::create([
            'kode' => CodeGenerator::generate('SPPB', PengirimanBarang::class, 'kode', 'Ymd', 4),
            'permintaan_distribusi_id' => $req->id,
            'gudang_id' => $this->gudang->id,
            'unit_id' => $this->unit->id,
            'tanggal_kirim' => now()->toDateString(),
            'status' => 'DIKIRIM',
            'dikirim_oleh' => $this->userAdmin->id,
        ]);
        $batch = StokBatch::create([
            'barang_id' => $this->barang->id,
            'gudang_id' => $this->gudang->id,
            'lokasi_gudang_id' => $this->lokasi->id,
            'nomor_batch' => 'BATCH-SPPB-4UNIT',
            'jumlah' => 0,
            'status' => 'HABIS',
        ]);
        PengirimanBarangDetail::create([
            'pengiriman_barang_id' => $sppb->id,
            'barang_id' => $this->barang->id,
            'stok_batch_id' => $batch->id,
            'jumlah_dikirim' => 4,
        ]);

        // 2. Buka modal detail di PermintaanBarangIndex
        $test = Livewire::test(PermintaanBarangIndex::class)
            ->call('showDetail', $req->id);

        $test->assertSet('showDetailModal', true);
        $test->assertSee('Sisa Hutang');
        $test->assertSee('Terkirim');
        $test->assertSee('6'); // Sisa hutang = 10 - 4 = 6
    }

    public function test_detail_modal_in_approval_permintaan_and_distribusi_approval_displays_backorder_column(): void
    {
        $this->actingAs($this->userAdmin);

        $req = PermintaanDistribusi::create([
            'kode' => CodeGenerator::generate('REQ', PermintaanDistribusi::class, 'kode', 'Ym', 3),
            'unit_id' => $this->unit->id,
            'gudang_id' => $this->gudang->id,
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi' => 'REGULER',
            'status' => 'DIKIRIM_SEBAGIAN',
            'diajukan_oleh' => $this->userAdmin->id,
            'disetujui_oleh' => $this->userAdmin->id,
        ]);
        PermintaanDistribusiDetail::create([
            'permintaan_distribusi_id' => $req->id,
            'barang_id' => $this->barang->id,
            'jumlah' => 8,
            'jumlah_disetujui' => 8,
        ]);

        // ApprovalPermintaanIndex
        $testKaru = Livewire::test(ApprovalPermintaanIndex::class)
            ->call('showDetail', $req->id);
        $testKaru->assertSet('showDetailModal', true);
        $testKaru->assertSee('Sisa Hutang');

        // DistribusiApprovalIndex
        $testLogistik = Livewire::test(DistribusiApprovalIndex::class)
            ->call('openDetail', $req->id);
        $testLogistik->assertSet('showDetailModal', true);
        $testLogistik->assertSee('Sisa Hutang');
    }
}
