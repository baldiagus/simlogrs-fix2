<?php

namespace Tests\Feature\Distribusi;

use App\Livewire\Distribusi\PickingDistribusiIndex;
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

class PickingDetailActionTest extends TestCase
{
    use DatabaseTransactions;

    protected User $userGudang;
    protected Gudang $gudang;
    protected Unit $unit;
    protected Barang $barang;
    protected LokasiGudang $lokasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = Unit::where('status', 'AKTIF')->first();
        $this->gudang = Gudang::where('status', 'AKTIF')->first();

        $this->userGudang = User::create([
            'nama' => 'Petugas Gudang Detail Test',
            'username' => 'gudangdetailtest',
            'email' => 'gudangdetail@simlog.test',
            'password' => bcrypt('password'),
            'gudang_id' => $this->gudang->id,
            'status' => 'AKTIF',
        ]);
        $this->userGudang->givePermissionTo([
            'view_any_gudang_stok::picking::distribusi',
            'create_gudang_stok::picking::distribusi',
            'update_gudang_stok::picking::distribusi',
            'page_PickingDistribusiIndex',
        ]);

        $kategori = KategoriBarang::firstOrCreate(['kode' => 'KAT-DTL-TEST'], ['nama' => 'Kategori Detail', 'status' => 'AKTIF']);
        $satuan = SatuanBarang::firstOrCreate(['kode' => 'PCS-DTL'], ['nama' => 'Pcs', 'status' => 'AKTIF']);

        $this->barang = Barang::firstOrCreate(
            ['kode' => 'BRG-DTL-01'],
            [
                'nama' => 'Spuit 5cc Detail Picking Test',
                'kategori_id' => $kategori->id,
                'satuan_id' => $satuan->id,
                'tipe' => 'HABIS_PAKAI',
                'status' => 'AKTIF',
            ]
        );

        $this->lokasi = LokasiGudang::firstOrCreate(
            ['kode' => 'RAK-DTL-01', 'gudang_id' => $this->gudang->id],
            ['nama' => 'Rak Detail 01', 'status' => 'AKTIF']
        );
    }

    public function test_can_open_picking_queue_item_detail_modal(): void
    {
        $this->actingAs($this->userGudang);

        $req = PermintaanDistribusi::create([
            'kode' => CodeGenerator::generate('REQ', PermintaanDistribusi::class, 'kode', 'Ym', 3),
            'unit_id' => $this->unit->id,
            'gudang_id' => $this->gudang->id,
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi' => 'REGULER',
            'status' => 'DISETUJUI_GUDANG',
            'diajukan_oleh' => $this->userGudang->id,
            'disetujui_oleh' => $this->userGudang->id,
            'catatan' => 'Catatan khusus amprah tindakan',
        ]);

        PermintaanDistribusiDetail::create([
            'permintaan_distribusi_id' => $req->id,
            'barang_id' => $this->barang->id,
            'jumlah' => 15,
            'jumlah_disetujui' => 15,
            'catatan' => 'Untuk cadangan shift malam',
        ]);

        $component = Livewire::test(PickingDistribusiIndex::class)
            ->call('showPermintaanDetail', $req->id);

        $component->assertSet('showPermintaanDetailModal', true);
        $component->assertSee($req->kode);
        $component->assertSee('Spuit 5cc Detail Picking Test');
        $component->assertSee('15');
        $component->assertSee('Catatan khusus amprah tindakan');
        $component->assertSee('Untuk cadangan shift malam');
    }

    public function test_picking_detail_modal_shows_accurate_delivered_and_owed_quantities(): void
    {
        $this->actingAs($this->userGudang);

        $req = PermintaanDistribusi::create([
            'kode' => CodeGenerator::generate('REQ', PermintaanDistribusi::class, 'kode', 'Ym', 3),
            'unit_id' => $this->unit->id,
            'gudang_id' => $this->gudang->id,
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi' => 'REGULER',
            'status' => 'DIKIRIM_SEBAGIAN',
            'diajukan_oleh' => $this->userGudang->id,
            'disetujui_oleh' => $this->userGudang->id,
        ]);

        $detail = PermintaanDistribusiDetail::create([
            'permintaan_distribusi_id' => $req->id,
            'barang_id' => $this->barang->id,
            'jumlah' => 10,
            'jumlah_disetujui' => 10,
        ]);

        // SPPB 1 sudah terbit sebesar 4 unit
        $sppb = PengirimanBarang::create([
            'kode' => CodeGenerator::generate('SPPB', PengirimanBarang::class, 'kode', 'Ymd', 4),
            'permintaan_distribusi_id' => $req->id,
            'gudang_id' => $this->gudang->id,
            'unit_id' => $this->unit->id,
            'tanggal_kirim' => now()->toDateString(),
            'status' => 'DIKIRIM',
            'dikirim_oleh' => $this->userGudang->id,
        ]);
        $batch = StokBatch::create([
            'barang_id' => $this->barang->id,
            'gudang_id' => $this->gudang->id,
            'lokasi_gudang_id' => $this->lokasi->id,
            'nomor_batch' => 'BATCH-TEST-SPPB1',
            'jumlah' => 0,
            'status' => 'HABIS',
        ]);
        PengirimanBarangDetail::create([
            'pengiriman_barang_id' => $sppb->id,
            'barang_id' => $this->barang->id,
            'stok_batch_id' => $batch->id,
            'jumlah_dikirim' => 4,
        ]);

        // Cek modal detail
        $component = Livewire::test(PickingDistribusiIndex::class)
            ->call('showPermintaanDetail', $req->id);

        $component->assertSet('showPermintaanDetailModal', true);
        // Harus menampilkan informasi kuantitas terkirim dan sisa yang mau dipicking
        $ringkasan = $component->get('ringkasanPermintaanDetail');
        $this->assertNotEmpty($ringkasan);
        $this->assertEquals(4, $ringkasan[$detail->id]['terkirim']);
        $this->assertEquals(6, $ringkasan[$detail->id]['sisa_picking']);
    }
}
