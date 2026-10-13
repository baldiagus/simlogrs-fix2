<?php

namespace Tests\Feature\Distribusi;

use App\Livewire\Distribusi\StokRuanganIndex;
use App\Models\Aset\Aset;
use App\Models\Master\Barang;
use App\Models\Master\Unit;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class StokRuanganTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Unit $unit;
    protected Barang $barang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::where('email', 'superadmin@simlogrs.local')->first();
        $this->unit = Unit::where('status', 'AKTIF')->first();
        $this->barang = Barang::where('status', 'AKTIF')->where('tipe', 'ASET')->first()
            ?: Barang::where('status', 'AKTIF')->first();
    }

    public function test_cannot_change_operational_status_of_borrowed_or_maintenance_assets(): void
    {
        $this->actingAs($this->user);

        // 1. Buat aset dengan status DIPINJAM
        $asetDipinjam = Aset::create([
            'barang_id' => $this->barang->id,
            'unit_id' => $this->unit->id,
            'gudang_id' => null,
            'kode_aset' => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri' => 'SN-PINJAM-001',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 5000000,
            'kondisi' => 'BAIK',
            'status' => 'DIPINJAM',
        ]);

        // Coba buka modal ubah status aset yang DIPINJAM -> HARUS DIGAGALKAN (403 atau error toast)
        Livewire::test(StokRuanganIndex::class)
            ->call('openUbahStatusAset', $asetDipinjam->id)
            ->assertForbidden();

        // Pastikan status aset tetap DIPINJAM
        $asetDipinjam->refresh();
        $this->assertEquals('DIPINJAM', $asetDipinjam->status);

        // 2. Buat aset dengan status DALAM_MAINTENANCE
        $asetMaintenance = Aset::create([
            'barang_id' => $this->barang->id,
            'unit_id' => $this->unit->id,
            'gudang_id' => null,
            'kode_aset' => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri' => 'SN-MNT-001',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 5000000,
            'kondisi' => 'RUSAK_BERAT',
            'status' => 'DALAM_MAINTENANCE',
        ]);

        // Coba buka modal ubah status aset yang DALAM_MAINTENANCE -> HARUS DIGAGALKAN (403 atau error toast)
        Livewire::test(StokRuanganIndex::class)
            ->call('openUbahStatusAset', $asetMaintenance->id)
            ->assertForbidden();

        // Pastikan status aset tetap DALAM_MAINTENANCE
        $asetMaintenance->refresh();
        $this->assertEquals('DALAM_MAINTENANCE', $asetMaintenance->status);
    }
    public function test_room_stock_consumption_records_audit_trail_with_keterangan(): void
    {
        $this->actingAs($this->user);

        $barangBhp = Barang::where('status', 'AKTIF')->where('tipe', 'HABIS_PAKAI')->first()
            ?: Barang::first();

        $stok = \App\Models\Distribusi\StokRuangan::updateOrCreate(
            ['unit_id' => $this->unit->id, 'barang_id' => $barangBhp->id],
            ['jumlah' => 10, 'stok_minimum' => 2, 'stok_maksimum' => 20]
        );

        $catatanPemakaian = 'Pasien Darurat Tindakan Bedah IGD Ref #9921';

        Livewire::test(StokRuanganIndex::class)
            ->call('openCatatPemakaian', $stok->id)
            ->set('jumlah_pakai', 3)
            ->set('keterangan', $catatanPemakaian)
            ->call('simpanPemakaian');

        // 1. Stok lemari berkurang menjadi 7
        $stok->refresh();
        $this->assertEquals(7, $stok->jumlah);

        // 2. Keterangan pemakaian HARUS tercatat di log_audit
        $auditLog = \App\Models\Audit\LogAudit::where('referensi_id', $stok->id)
            ->where('aksi', 'PEMAKAIAN')
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog, 'LogAudit dengan aksi PEMAKAIAN tidak ditemukan.');
        $this->assertEquals($catatanPemakaian, $auditLog->data_sesudah['keterangan'] ?? null);
        $this->assertEquals(3, $auditLog->data_sesudah['jumlah_pakai'] ?? null);
    }

    public function test_can_open_service_report_modal_with_preselected_asset(): void
    {
        $this->actingAs($this->user);

        $aset = Aset::create([
            'barang_id' => $this->barang->id,
            'unit_id' => $this->unit->id,
            'gudang_id' => null,
            'kode_aset' => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri' => 'SN-SRV-001',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 7500000,
            'kondisi' => 'BAIK',
            'status' => 'DIGUNAKAN',
        ]);

        Livewire::test(StokRuanganIndex::class)
            ->call('openLaporServis', $aset->id)
            ->assertSet('showLaporServisModal', true)
            ->assertSet('servisAsetId', $aset->id);
    }

    public function test_submitting_service_report_from_room_inventory_creates_maintenance_ticket(): void
    {
        $this->actingAs($this->user);

        $aset = Aset::create([
            'barang_id' => $this->barang->id,
            'unit_id' => $this->unit->id,
            'gudang_id' => null,
            'kode_aset' => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri' => 'SN-SRV-002',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 12000000,
            'kondisi' => 'BAIK',
            'status' => 'DIGUNAKAN',
        ]);

        Livewire::test(StokRuanganIndex::class)
            ->call('openLaporServis', $aset->id)
            ->set('servisPrioritas', 'CITO')
            ->set('servisDeskripsi', 'Kabel daya korsleting dan monitor padam total saat digunakan')
            ->call('simpanLaporServis')
            ->assertHasNoErrors()
            ->assertSet('showLaporServisModal', false);

        $this->assertDatabaseHas('permintaan_maintenance', [
            'aset_id' => $aset->id,
            'prioritas' => 'CITO',
            'status' => 'DILAPORKAN',
        ]);

        $aset->refresh();
        $this->assertEquals('DALAM_MAINTENANCE', $aset->status);
        $this->assertEquals('RUSAK_BERAT', $aset->kondisi);
    }

    public function test_reporting_service_with_standard_priority_locks_asset_to_maintenance(): void
    {
        $this->actingAs($this->user);

        $aset = Aset::create([
            'barang_id' => $this->barang->id,
            'unit_id' => $this->unit->id,
            'gudang_id' => null,
            'kode_aset' => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri' => 'SN-SRV-SEDANG',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 5000000,
            'kondisi' => 'BAIK',
            'status' => 'DIGUNAKAN',
        ]);

        Livewire::test(StokRuanganIndex::class)
            ->call('openLaporServis', $aset->id)
            ->set('servisPrioritas', 'SEDANG')
            ->set('servisDeskripsi', 'Kertas sering macet dan roller berderit keras')
            ->call('simpanLaporServis')
            ->assertHasNoErrors();

        $aset->refresh();
        $this->assertEquals('DALAM_MAINTENANCE', $aset->status);
        $this->assertEquals('RUSAK_RINGAN', $aset->kondisi);
    }

    public function test_cannot_report_service_again_for_asset_already_in_maintenance(): void
    {
        $this->actingAs($this->user);

        $aset = Aset::create([
            'barang_id' => $this->barang->id,
            'unit_id' => $this->unit->id,
            'gudang_id' => null,
            'kode_aset' => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri' => 'SN-SRV-LOCKED',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 5000000,
            'kondisi' => 'RUSAK_RINGAN',
            'status' => 'DALAM_MAINTENANCE',
        ]);

        Livewire::test(StokRuanganIndex::class)
            ->call('openLaporServis', $aset->id)
            ->assertSet('showLaporServisModal', false);
    }

    public function test_warehouse_user_can_view_their_warehouse_assets_in_asset_tab(): void
    {
        $gudang = \App\Models\Master\Gudang::where('status', 'AKTIF')->first();
        $userGudang = User::create([
            'nama' => 'Petugas Gudang Persediaan',
            'username' => 'petugas_gdg_stk',
            'email' => 'gdg_stk@simlog.test',
            'password' => bcrypt('password'),
            'gudang_id' => $gudang->id,
            'status' => 'AKTIF',
        ]);
        $userGudang->givePermissionTo([
            'view_any_layanan_unit::stok::ruangan',
            'view_layanan_unit::stok::ruangan',
            'page_StokRuanganIndex',
        ]);

        $asetGudang = Aset::create([
            'barang_id' => $this->barang->id,
            'unit_id' => null,
            'gudang_id' => $gudang->id,
            'kode_aset' => 'AST-GDG-' . uniqid(),
            'nomor_seri' => 'SN-GDG-101',
            'tanggal_perolehan' => now()->toDateString(),
            'status' => 'TERSEDIA',
            'kondisi' => 'BAIK',
        ]);

        $this->actingAs($userGudang);

        Livewire::test(StokRuanganIndex::class)
            ->call('setTab', 'aset')
            ->assertSee($asetGudang->kode_aset)
            ->assertSee($this->barang->nama);
    }

    public function test_warehouse_user_can_view_their_warehouse_inventory_in_goods_tab(): void
    {
        $gudang = \App\Models\Master\Gudang::where('status', 'AKTIF')->first();
        $userGudang = User::create([
            'nama' => 'Petugas Gudang Persediaan BHP',
            'username' => 'petugas_gdg_bhp',
            'email' => 'gdg_bhp@simlog.test',
            'password' => bcrypt('password'),
            'gudang_id' => $gudang->id,
            'status' => 'AKTIF',
        ]);
        $userGudang->givePermissionTo([
            'view_any_layanan_unit::stok::ruangan',
            'view_layanan_unit::stok::ruangan',
            'page_StokRuanganIndex',
        ]);

        $satuanId = \App\Models\Master\SatuanBarang::firstOrCreate(['kode' => 'UNT-STK-GDG'], ['nama' => 'Unit', 'status' => 'AKTIF'])->id;
        $kategoriId = \App\Models\Master\KategoriBarang::firstOrCreate(['kode' => 'KAT-STK-GDG'], ['nama' => 'Kategori BHP', 'status' => 'AKTIF'])->id;
        $barangBhp = Barang::firstOrCreate(
            ['kode' => 'BRG-BHP-GDG-01'],
            [
                'nama' => 'Sarung Tangan Karet Medis',
                'tipe' => 'HABIS_PAKAI',
                'satuan_id' => $satuanId,
                'kategori_id' => $kategoriId,
                'status' => 'AKTIF',
            ]
        );

        \App\Models\Persediaan\StokBatch::create([
            'barang_id' => $barangBhp->id,
            'gudang_id' => $gudang->id,
            'nomor_batch' => 'BATCH-TEST-GDG-01',
            'tanggal_kedaluwarsa' => now()->addYear()->toDateString(),
            'jumlah' => 45,
            'status' => 'AKTIF',
        ]);

        $this->actingAs($userGudang);

        Livewire::test(StokRuanganIndex::class)
            ->call('setTab', 'bhp')
            ->assertSee($barangBhp->nama)
            ->assertSee('45');
    }

    public function test_warehouse_user_can_manage_status_and_report_service_for_warehouse_asset(): void
    {
        $gudang = \App\Models\Master\Gudang::where('status', 'AKTIF')->first();
        $userGudang = User::create([
            'nama' => 'Petugas Gudang Aset Manage',
            'username' => 'petugas_gdg_mng',
            'email' => 'gdg_mng@simlog.test',
            'password' => bcrypt('password'),
            'gudang_id' => $gudang->id,
            'status' => 'AKTIF',
        ]);
        $userGudang->givePermissionTo([
            'view_any_layanan_unit::stok::ruangan',
            'update_layanan_unit::stok::ruangan',
            'create_layanan_unit::lapor::servis',
            'page_StokRuanganIndex',
        ]);

        $asetGudang = Aset::create([
            'barang_id' => $this->barang->id,
            'unit_id' => null,
            'gudang_id' => $gudang->id,
            'kode_aset' => 'AST-GDG-MANAGE-' . uniqid(),
            'nomor_seri' => 'SN-GDG-202',
            'tanggal_perolehan' => now()->toDateString(),
            'status' => 'TERSEDIA',
            'kondisi' => 'BAIK',
        ]);

        $this->actingAs($userGudang);

        Livewire::test(StokRuanganIndex::class)
            ->call('openUbahStatusAset', $asetGudang->id)
            ->assertSet('showUbahStatusAsetModal', true)
            ->call('openLaporServis', $asetGudang->id)
            ->assertSet('showLaporServisModal', true);
    }

    public function test_room_user_only_sees_assets_from_their_own_unit_excluding_disposed(): void
    {
        $unit1 = Unit::where('status', 'AKTIF')->first();
        $unit2 = Unit::where('status', 'AKTIF')->where('id', '!=', $unit1->id)->first();

        $userRoom1 = User::create([
            'nama' => 'Petugas Ruang Unit 1',
            'username' => 'petugas_u1_' . uniqid(),
            'email' => 'u1_' . uniqid() . '@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $unit1->id,
            'status' => 'AKTIF',
        ]);
        $userRoom1->givePermissionTo([
            'view_any_layanan_unit::stok::ruangan',
            'view_layanan_unit::stok::ruangan',
            'page_StokRuanganIndex',
        ]);

        $asetUnit1 = Aset::create([
            'barang_id' => $this->barang->id,
            'unit_id' => $unit1->id,
            'gudang_id' => null,
            'kode_aset' => 'AST-U1-ACTIVE-' . uniqid(),
            'nomor_seri' => 'SN-U1-1',
            'tanggal_perolehan' => now()->toDateString(),
            'status' => 'TERSEDIA',
            'kondisi' => 'BAIK',
        ]);

        $asetUnit1Dihapuskan = Aset::create([
            'barang_id' => $this->barang->id,
            'unit_id' => $unit1->id,
            'gudang_id' => null,
            'kode_aset' => 'AST-U1-DISPOSED-' . uniqid(),
            'nomor_seri' => 'SN-U1-DISPOSED',
            'tanggal_perolehan' => now()->toDateString(),
            'status' => 'DIHAPUSKAN',
            'kondisi' => 'DIHAPUSKAN',
        ]);

        $asetUnit2 = Aset::create([
            'barang_id' => $this->barang->id,
            'unit_id' => $unit2->id,
            'gudang_id' => null,
            'kode_aset' => 'AST-U2-OTHER-' . uniqid(),
            'nomor_seri' => 'SN-U2-1',
            'tanggal_perolehan' => now()->toDateString(),
            'status' => 'TERSEDIA',
            'kondisi' => 'BAIK',
        ]);

        $this->actingAs($userRoom1);

        Livewire::test(StokRuanganIndex::class)
            ->call('setTab', 'aset')
            ->assertSee($asetUnit1->kode_aset)
            ->assertDontSee($asetUnit1Dihapuskan->kode_aset)
            ->assertDontSee($asetUnit2->kode_aset);
    }

    public function test_admin_filtering_by_unit_only_shows_assets_of_selected_unit(): void
    {
        $unit1 = Unit::where('status', 'AKTIF')->first();
        $unit2 = Unit::where('status', 'AKTIF')->where('id', '!=', $unit1->id)->first();

        $asetUnit1 = Aset::create([
            'barang_id' => $this->barang->id,
            'unit_id' => $unit1->id,
            'gudang_id' => null,
            'kode_aset' => 'AST-ADM-U1-' . uniqid(),
            'nomor_seri' => 'SN-ADM-U1',
            'tanggal_perolehan' => now()->toDateString(),
            'status' => 'TERSEDIA',
            'kondisi' => 'BAIK',
        ]);

        $asetUnit2 = Aset::create([
            'barang_id' => $this->barang->id,
            'unit_id' => $unit2->id,
            'gudang_id' => null,
            'kode_aset' => 'AST-ADM-U2-' . uniqid(),
            'nomor_seri' => 'SN-ADM-U2',
            'tanggal_perolehan' => now()->toDateString(),
            'status' => 'TERSEDIA',
            'kondisi' => 'BAIK',
        ]);

        $this->actingAs($this->user);

        Livewire::test(StokRuanganIndex::class)
            ->call('setTab', 'aset')
            ->set('filterUnitId', $unit1->id)
            ->assertSee($asetUnit1->kode_aset)
            ->assertDontSee($asetUnit2->kode_aset);
    }
}
