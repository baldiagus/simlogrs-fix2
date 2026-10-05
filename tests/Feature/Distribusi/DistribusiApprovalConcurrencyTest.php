<?php

namespace Tests\Feature\Distribusi;

use App\Livewire\Distribusi\DistribusiApprovalIndex;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\KategoriBarang;
use App\Models\LokasiGudang;
use App\Models\PermintaanDistribusi;
use App\Models\PermintaanDistribusiDetail;
use App\Models\SatuanBarang;
use App\Models\StokBatch;
use App\Models\Unit;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class DistribusiApprovalConcurrencyTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Gudang $gudang;
    protected Unit $unit1;
    protected Unit $unit2;
    protected Barang $barang;
    protected LokasiGudang $lokasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::where('email', 'superadmin@simlogrs.local')->first();
        $this->gudang = Gudang::where('status', 'AKTIF')->first();
        $units = Unit::where('status', 'AKTIF')->take(2)->get();
        $this->unit1 = $units[0];
        $this->unit2 = $units[1];

        $kategori = KategoriBarang::firstOrCreate(['kode' => 'KAT-BHP-TEST'], ['nama' => 'BHP Test', 'status' => 'AKTIF']);
        $satuan = SatuanBarang::firstOrCreate(['kode' => 'RIM-TEST'], ['nama' => 'Rim', 'status' => 'AKTIF']);

        $this->barang = Barang::firstOrCreate(
            ['kode' => 'BRG-AMPRAH-CONC-01'],
            [
                'nama' => 'Kertas A4 Amprah Test',
                'kategori_id' => $kategori->id,
                'satuan_id' => $satuan->id,
                'tipe' => 'HABIS_PAKAI',
                'status' => 'AKTIF',
            ]
        );

        $this->lokasi = LokasiGudang::firstOrCreate(
            ['kode' => 'RAK-CONC-01', 'gudang_id' => $this->gudang->id],
            ['nama' => 'Rak Conc 01', 'status' => 'AKTIF']
        );
    }

    public function test_approving_first_requisition_limits_available_stock_for_second_requisition(): void
    {
        $this->actingAs($this->user);

        // 1. Stok gudang hanya ada 10 unit
        StokBatch::where('barang_id', $this->barang->id)->delete();
        StokBatch::create([
            'barang_id' => $this->barang->id,
            'gudang_id' => $this->gudang->id,
            'lokasi_gudang_id' => $this->lokasi->id,
            'nomor_batch' => 'BATCH-CONC-10',
            'tanggal_kedaluwarsa' => now()->addMonths(6)->toDateString(),
            'jumlah' => 10,
            'harga_satuan' => 50000,
            'status' => 'AKTIF',
        ]);

        // 2. Permintaan pertama (Unit 1): minta 8 unit, SUDAH DISETUJUI GUDANG 8 unit (antre picking)
        $req1 = PermintaanDistribusi::create([
            'kode' => CodeGenerator::generate('REQ', PermintaanDistribusi::class, 'kode', 'Ym', 3),
            'unit_id' => $this->unit1->id,
            'gudang_id' => $this->gudang->id,
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi' => 'REGULER',
            'status' => 'DISETUJUI_GUDANG',
            'diajukan_oleh' => $this->user->id,
            'disetujui_oleh' => $this->user->id,
        ]);
        PermintaanDistribusiDetail::create([
            'permintaan_distribusi_id' => $req1->id,
            'barang_id' => $this->barang->id,
            'jumlah' => 8,
            'jumlah_disetujui' => 8,
        ]);

        // 3. Permintaan kedua (Unit 2): minta 8 unit, masih berstatus DISETUJUI_KARU (menunggu otorisasi logistik)
        $req2 = PermintaanDistribusi::create([
            'kode' => CodeGenerator::generate('REQ', PermintaanDistribusi::class, 'kode', 'Ym', 3),
            'unit_id' => $this->unit2->id,
            'gudang_id' => $this->gudang->id,
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi' => 'REGULER',
            'status' => 'DISETUJUI_KARU',
            'diajukan_oleh' => $this->user->id,
            'disetujui_oleh' => $this->user->id,
        ]);
        $detail2 = PermintaanDistribusiDetail::create([
            'permintaan_distribusi_id' => $req2->id,
            'barang_id' => $this->barang->id,
            'jumlah' => 8,
            'jumlah_disetujui' => null,
        ]);

        // 4. Buka review permohonan kedua
        $test = Livewire::test(DistribusiApprovalIndex::class)
            ->call('openReview', $req2->id);

        // ASSERTION: Sisa stok gudang tersedia untuk permintaan kedua HARUS 2 (10 fisik - 8 komitmen req1), BUKAN 10
        $this->assertEquals(2, $test->get("itemQtys.{$detail2->id}.stok_gudang"));
        $this->assertEquals(2, $test->get("itemQtys.{$detail2->id}.jumlah_disetujui"));

        // Memaksa persetujuan 5 unit (> 2 unit sisa) HARUS DITOLAK
        $test->set("itemQtys.{$detail2->id}.jumlah_disetujui", 5)
            ->call('confirmApproveGudang')
            ->assertHasErrors(["itemQtys.{$detail2->id}.jumlah_disetujui"]);
    }
}
