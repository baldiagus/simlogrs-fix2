<?php

namespace Tests\Feature\Ui;

use App\Models\Master\Barang;
use App\Models\Master\Gudang;
use App\Models\Master\KategoriBarang;
use App\Models\Master\Pemasok;
use App\Models\Distribusi\PengirimanBarang;
use App\Models\Distribusi\PermintaanDistribusi;
use App\Models\Ipsrs\PermintaanMaintenance;
use App\Models\Pengadaan\PesananPembelian;
use App\Models\Master\SatuanBarang;
use App\Models\Master\Unit;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ActionDropdownStandardizationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Unit $unit;
    protected Gudang $gudang;
    protected Barang $barang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::where('email', 'superadmin@simlogrs.local')->first();
        $this->unit = Unit::where('status', 'AKTIF')->first();
        $this->gudang = Gudang::where('status', 'AKTIF')->first();

        $kat = KategoriBarang::firstOrCreate(['kode' => 'KAT-UI-TEST'], ['nama' => 'Kat UI Test', 'status' => 'AKTIF']);
        $sat = SatuanBarang::firstOrCreate(['kode' => 'UNIT-UI'], ['nama' => 'Unit', 'status' => 'AKTIF']);
        $this->barang = Barang::firstOrCreate(
            ['kode' => 'BRG-UI-TEST-01'],
            ['nama' => 'Barang UI Test', 'kategori_id' => $kat->id, 'satuan_id' => $sat->id, 'tipe' => 'HABIS_PAKAI', 'status' => 'AKTIF']
        );
    }

    public function test_distribusi_pages_contain_standard_action_dropdown(): void
    {
        $this->actingAs($this->user);

        // 1. Approval Permintaan
        $req1 = PermintaanDistribusi::create([
            'kode' => CodeGenerator::generate('REQ', PermintaanDistribusi::class, 'kode', 'Ym', 3),
            'unit_id' => $this->unit->id,
            'gudang_id' => $this->gudang->id,
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi' => 'REGULER',
            'status' => 'DIAJUKAN',
            'diajukan_oleh' => $this->user->id,
        ]);
        $resApproval = $this->get('/super-admin/approval-permintaan');
        $resApproval->assertOk();
        $resApproval->assertSee('title="Menu Opsi"', false);

        // 2. Distribusi Approval
        $req2 = PermintaanDistribusi::create([
            'kode' => CodeGenerator::generate('REQ', PermintaanDistribusi::class, 'kode', 'Ym', 3),
            'unit_id' => $this->unit->id,
            'gudang_id' => $this->gudang->id,
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi' => 'REGULER',
            'status' => 'DISETUJUI_KARU',
            'diajukan_oleh' => $this->user->id,
            'disetujui_oleh' => $this->user->id,
        ]);
        $resDist = $this->get('/super-admin/distribusi-approval');
        $resDist->assertOk();
        $resDist->assertSee('title="Menu Opsi"', false);

        // 3. Picking Distribusi
        $req3 = PermintaanDistribusi::create([
            'kode' => CodeGenerator::generate('REQ', PermintaanDistribusi::class, 'kode', 'Ym', 3),
            'unit_id' => $this->unit->id,
            'gudang_id' => $this->gudang->id,
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi' => 'REGULER',
            'status' => 'DISETUJUI_GUDANG',
            'diajukan_oleh' => $this->user->id,
            'disetujui_oleh' => $this->user->id,
        ]);
        $resPick = $this->get('/super-admin/picking-distribusi');
        $resPick->assertOk();
        $resPick->assertSee('title="Menu Opsi"', false);

        // 4. Penerimaan Barang
        $sppb = PengirimanBarang::create([
            'kode' => CodeGenerator::generate('SPPB', PengirimanBarang::class, 'kode', 'Ymd', 4),
            'permintaan_distribusi_id' => $req3->id,
            'gudang_id' => $this->gudang->id,
            'unit_id' => $this->unit->id,
            'tanggal_kirim' => now()->toDateString(),
            'status' => 'DIKIRIM',
            'dikirim_oleh' => $this->user->id,
        ]);
        $resRcv = $this->get('/super-admin/penerimaan-barang');
        $resRcv->assertOk();
        $resRcv->assertSee('title="Menu Opsi"', false);
    }

    public function test_pengadaan_and_ipsrs_pages_contain_standard_action_dropdown(): void
    {
        $this->actingAs($this->user);

        $pemasok = Pemasok::firstOrCreate(['kode' => 'VND-UI-01'], ['nama' => 'Vendor UI Test', 'status' => 'AKTIF']);

        // 1. Pesanan Pembelian (PO)
        $po = PesananPembelian::create([
            'kode' => CodeGenerator::generatePo(),
            'pemasok_id' => $pemasok->id,
            'tanggal_po' => now()->toDateString(),
            'total_biaya' => 1000000,
            'syarat_pembayaran' => 'TEMPO_30_HARI',
            'status' => 'DITERBITKAN',
            'dibuat_oleh' => $this->user->id,
        ]);
        $resPo = $this->get('/super-admin/pesanan-pembelian');
        $resPo->assertOk();
        $resPo->assertSee('title="Menu Opsi"', false);

        // 2. Penerimaan PO (Inbound)
        $po->update(['status' => 'DIKIRIM_VENDOR']);
        $resPoRcv = $this->get('/super-admin/penerimaan-po');
        $resPoRcv->assertOk();
        $resPoRcv->assertSee('title="Menu Opsi"', false);

        // 3. Lapor Servis
        $aset = \App\Models\Aset\Aset::create([
            'barang_id' => $this->barang->id,
            'unit_id' => $this->unit->id,
            'kode_aset' => CodeGenerator::generate('AST', \App\Models\Aset\Aset::class, 'kode_aset'),
            'nomor_seri' => 'SN-UI-001',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 5000000,
            'kondisi' => 'BAIK',
            'status' => 'DIGUNAKAN',
        ]);
        PermintaanMaintenance::create([
            'kode' => CodeGenerator::generateMnt(),
            'aset_id' => $aset->id,
            'unit_id' => $this->unit->id,
            'dilaporkan_oleh' => $this->user->id,
            'deskripsi_masalah' => 'Uji tombol aksi dropdown',
            'prioritas' => 'SEDANG',
            'status' => 'DILAPORKAN',
        ]);
        $resServis = $this->get('/super-admin/lapor-servis');
        $resServis->assertOk();
        $resServis->assertSee('title="Menu Opsi"', false);

        // 4. SPK
        $resSpk = $this->get('/super-admin/spk');
        $resSpk->assertOk();
        $resSpk->assertSee('title="Menu Opsi"', false);
    }
}
