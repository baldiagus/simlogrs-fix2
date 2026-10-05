<?php

namespace Tests\Feature\Distribusi;

use App\Livewire\Distribusi\ApprovalPermintaanIndex;
use App\Livewire\Distribusi\PermintaanBarangIndex;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\KategoriBarang;
use App\Models\LokasiGudang;
use App\Models\PengirimanBarang;
use App\Models\PengirimanBarangDetail;
use App\Models\PermintaanDistribusi;
use App\Models\PermintaanDistribusiDetail;
use App\Models\SatuanBarang;
use App\Models\StokBatch;
use App\Models\StokRuangan;
use App\Models\Unit;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class DistribusiLifecycleAuditTest extends TestCase
{
    use DatabaseTransactions;

    protected User $userPetugas;
    protected User $userKaru;
    protected User $userGudang;
    protected Unit $unit;
    protected Gudang $gudang;
    protected Barang $barang;
    protected LokasiGudang $lokasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = Unit::where('status', 'AKTIF')->first();
        $this->gudang = Gudang::where('status', 'AKTIF')->first();

        $this->userPetugas = User::create([
            'nama' => 'Petugas Ruangan Audit Test',
            'username' => 'petugasaudit',
            'email' => 'petugasaudit@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $this->unit->id,
            'status' => 'AKTIF',
        ]);
        $this->userPetugas->assignRole('petugas_unit');
        $this->userPetugas->givePermissionTo([
            'create_layanan_unit::permintaan::barang',
            'view_any_layanan_unit::permintaan::barang',
            'view_layanan_unit::permintaan::barang',
            'view_any_layanan_unit::penerimaan::barang',
            'update_layanan_unit::penerimaan::barang',
        ]);

        $this->userKaru = User::create([
            'nama' => 'Karu Audit Test',
            'username' => 'karuaudit',
            'email' => 'karuaudit@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $this->unit->id,
            'status' => 'AKTIF',
        ]);
        $this->userKaru->assignRole('kepala_unit');
        $this->userKaru->givePermissionTo([
            'view_any_layanan_unit::approval::karu',
            'update_layanan_unit::approval::karu',
        ]);

        $this->userGudang = User::create([
            'nama' => 'Petugas Gudang Audit Test',
            'username' => 'gudangaudit',
            'email' => 'gudangaudit@simlog.test',
            'password' => bcrypt('password'),
            'gudang_id' => $this->gudang->id,
            'status' => 'AKTIF',
        ]);
        $this->userGudang->assignRole('petugas_gudang');
        $this->userGudang->givePermissionTo([
            'create_gudang_stok::picking::distribusi',
            'view_any_gudang_stok::picking::distribusi',
            'update_gudang_stok::distribusi::approval',
            'view_any_gudang_stok::distribusi::approval',
        ]);

        $kategori = KategoriBarang::firstOrCreate(['kode' => 'KAT-AUDIT'], ['nama' => 'Kategori Audit', 'status' => 'AKTIF']);
        $satuan = SatuanBarang::firstOrCreate(['kode' => 'PCS-AUDIT'], ['nama' => 'Pcs', 'status' => 'AKTIF']);

        $this->barang = Barang::firstOrCreate(
            ['kode' => 'BRG-AUDIT-01'],
            [
                'nama' => 'Komoditas Audit Distribusi',
                'kategori_id' => $kategori->id,
                'satuan_id' => $satuan->id,
                'tipe' => 'HABIS_PAKAI',
                'status' => 'AKTIF',
            ]
        );

        $this->lokasi = LokasiGudang::firstOrCreate(
            ['kode' => 'RAK-AUDIT-01', 'gudang_id' => $this->gudang->id],
            ['nama' => 'Rak Audit 01', 'status' => 'AKTIF']
        );
    }

    public function test_room_can_submit_amprah_request_even_when_warehouse_stock_is_zero(): void
    {
        $this->actingAs($this->userPetugas);

        // Pastikan stok di gudang 0 unit
        StokBatch::where('barang_id', $this->barang->id)->delete();

        // Staf ruangan mengajukan 10 unit kassa medis
        Livewire::test(PermintaanBarangIndex::class)
            ->call('create')
            ->set('unit_id', $this->unit->id)
            ->set('gudang_id', $this->gudang->id)
            ->set('tanggal_permintaan', now()->toDateString())
            ->set('urgensi', 'REGULER')
            ->set('items', [
                ['barang_id' => $this->barang->id, 'jumlah' => 10, 'catatan' => 'Kebutuhan darurat stok kosong'],
            ])
            ->call('save')
            ->assertHasNoErrors();

        // Permintaan harus berhasil dibuat di database untuk memicu alur backorder
        $this->assertDatabaseHas('permintaan_distribusi', [
            'unit_id' => $this->unit->id,
            'gudang_id' => $this->gudang->id,
            'status' => 'DIAJUKAN',
        ]);
    }

    public function test_api_picking_queue_and_execution_includes_partially_sent_requests(): void
    {
        // Setup permintaan berstatus DIKIRIM_SEBAGIAN
        $req = PermintaanDistribusi::create([
            'kode' => CodeGenerator::generate('REQ', PermintaanDistribusi::class, 'kode', 'Ym', 3),
            'unit_id' => $this->unit->id,
            'gudang_id' => $this->gudang->id,
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi' => 'REGULER',
            'status' => 'DIKIRIM_SEBAGIAN',
            'diajukan_oleh' => $this->userPetugas->id,
            'disetujui_oleh' => $this->userKaru->id,
        ]);
        PermintaanDistribusiDetail::create([
            'permintaan_distribusi_id' => $req->id,
            'barang_id' => $this->barang->id,
            'jumlah' => 5,
            'jumlah_disetujui' => 5,
        ]);

        // Beri stok baru di gudang
        StokBatch::create([
            'barang_id' => $this->barang->id,
            'gudang_id' => $this->gudang->id,
            'lokasi_gudang_id' => $this->lokasi->id,
            'nomor_batch' => 'BATCH-AUDIT-RESTOCK',
            'tanggal_kedaluwarsa' => now()->addYear()->toDateString(),
            'jumlah' => 10,
            'status' => 'AKTIF',
        ]);

        $this->actingAs($this->userGudang);

        // 1. Antrean picking API HARUS memuat permohonan DIKIRIM_SEBAGIAN
        $resQueue = $this->getJson('/api/v1/distribusi/antrean-picking');
        $resQueue->assertOk();
        $this->assertTrue(
            collect($resQueue->json('data'))->contains('kode', $req->kode),
            'Permintaan dengan status DIKIRIM_SEBAGIAN tidak muncul di antrean picking API'
        );
        // 2. Eksekusi picking API HARUS berhasil memproses permintaan DIKIRIM_SEBAGIAN
        $resPick = $this->postJson("/api/v1/distribusi/picking", [
            'permintaan_id' => $req->id,
        ]);
        $resPick->assertCreated();
    }

    public function test_api_receipt_confirmation_keeps_request_partially_sent_if_not_fully_received(): void
    {
        $this->actingAs($this->userPetugas);

        $req = PermintaanDistribusi::create([
            'kode' => CodeGenerator::generate('REQ', PermintaanDistribusi::class, 'kode', 'Ym', 3),
            'unit_id' => $this->unit->id,
            'gudang_id' => $this->gudang->id,
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi' => 'REGULER',
            'status' => 'DIKIRIM_SEBAGIAN',
            'diajukan_oleh' => $this->userPetugas->id,
            'disetujui_oleh' => $this->userKaru->id,
        ]);
        PermintaanDistribusiDetail::create([
            'permintaan_distribusi_id' => $req->id,
            'barang_id' => $this->barang->id,
            'jumlah' => 10,
            'jumlah_disetujui' => 10,
        ]);

        $batch = StokBatch::create([
            'barang_id' => $this->barang->id,
            'gudang_id' => $this->gudang->id,
            'lokasi_gudang_id' => $this->lokasi->id,
            'nomor_batch' => 'BATCH-TEST-PARTIAL-RCV',
            'jumlah' => 0,
            'status' => 'HABIS',
        ]);

        $sppb = PengirimanBarang::create([
            'kode' => CodeGenerator::generate('SPPB', PengirimanBarang::class, 'kode', 'Ymd', 4),
            'permintaan_distribusi_id' => $req->id,
            'gudang_id' => $this->gudang->id,
            'unit_id' => $this->unit->id,
            'tanggal_kirim' => now()->toDateString(),
            'status' => 'DIKIRIM',
            'dikirim_oleh' => $this->userGudang->id,
        ]);
        PengirimanBarangDetail::create([
            'pengiriman_barang_id' => $sppb->id,
            'barang_id' => $this->barang->id,
            'stok_batch_id' => $batch->id,
            'jumlah_dikirim' => 4, // Baru 4 dari 10 unit
        ]);
        $response = $this->postJson("/api/v1/distribusi/sppb/{$sppb->id}/terima");
        $response->assertCreated();
        $req->refresh();
        // Permintaan TIDAK BOLEH menjadi SELESAI jika baru 4 dari 10 yang diterima
        $this->assertEquals('DIKIRIM_SEBAGIAN', $req->status);
    }

    public function test_room_can_cancel_or_close_remaining_backorder_on_partial_request(): void
    {
        $this->actingAs($this->userPetugas);

        $req = PermintaanDistribusi::create([
            'kode' => CodeGenerator::generate('REQ', PermintaanDistribusi::class, 'kode', 'Ym', 3),
            'unit_id' => $this->unit->id,
            'gudang_id' => $this->gudang->id,
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi' => 'REGULER',
            'status' => 'DIKIRIM_SEBAGIAN',
            'diajukan_oleh' => $this->userPetugas->id,
            'disetujui_oleh' => $this->userKaru->id,
        ]);
        PermintaanDistribusiDetail::create([
            'permintaan_distribusi_id' => $req->id,
            'barang_id' => $this->barang->id,
            'jumlah' => 10,
            'jumlah_disetujui' => 10,
        ]);

        Livewire::test(PermintaanBarangIndex::class)
            ->call('tutupSisaHutang', $req->id);

        $req->refresh();
        $this->assertEquals('SELESAI', $req->status);
        $this->assertStringContainsString('Sisa hutang ditutup oleh pemohon', $req->catatan);
    }
}
