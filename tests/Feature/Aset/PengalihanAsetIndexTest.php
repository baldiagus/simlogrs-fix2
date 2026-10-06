<?php

namespace Tests\Feature\Aset;

use App\Livewire\Aset\PengalihanAsetIndex;
use App\Models\Aset;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\PengalihanAset;
use App\Models\Unit;
use App\Models\User;
use App\Services\Aset\PengalihanAsetService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class PengalihanAsetIndexTest extends TestCase
{
    use DatabaseTransactions;

    protected User $userAdmin;
    protected Unit $unit1;
    protected Unit $unit2;
    protected Aset $aset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userAdmin = User::where('email', 'superadmin@simlogrs.local')->first();

        $units = Unit::where('status', 'AKTIF')->take(2)->get();
        $this->unit1 = $units[0];
        $this->unit2 = $units[1];

        $satuanId = \App\Models\SatuanBarang::firstOrCreate(['kode' => 'UNT-TRF'], ['nama' => 'Unit', 'status' => 'AKTIF'])->id;
        $kategoriId = \App\Models\KategoriBarang::firstOrCreate(['kode' => 'KAT-TRF'], ['nama' => 'Kategori TRF', 'status' => 'AKTIF'])->id;
        $barang = Barang::firstOrCreate(
            ['kode' => 'BRG-TRF-002'],
            ['nama' => 'EKG Mesin Portable', 'tipe' => 'ASET', 'satuan_id' => $satuanId, 'kategori_id' => $kategoriId, 'status' => 'AKTIF']
        );

        $this->aset = Aset::create([
            'barang_id' => $barang->id,
            'unit_id' => $this->unit1->id,
            'gudang_id' => null,
            'lokasi_gudang_id' => null,
            'kode_aset' => 'AST-EKG-' . uniqid(),
            'nomor_seri' => 'SN-EKG-101',
            'tanggal_perolehan' => now()->toDateString(),
            'kondisi' => 'BAIK',
            'status' => 'TERSEDIA',
        ]);
    }

    public function test_page_renders_with_kpi_metrics_and_active_tab(): void
    {
        $this->actingAs($this->userAdmin);

        Livewire::test(PengalihanAsetIndex::class)
            ->assertSee('Pengalihan')
            ->assertSee('Mutasi Aset')
            ->assertSee('Siap Serah Terima')
            ->assertSee('Mutasi Bulan Ini')
            ->assertSee('Total Aset Dimutasi');
    }

    public function test_user_can_submit_transfer_via_livewire_component(): void
    {
        $this->actingAs($this->userAdmin);

        Livewire::test(PengalihanAsetIndex::class)
            ->call('openCreateModal')
            ->set('tipe_asal', 'RUANGAN')
            ->set('unit_asal_id', $this->unit1->id)
            ->set('tipe_tujuan', 'RUANGAN')
            ->set('unit_tujuan_id', $this->unit2->id)
            ->set('selected_aset_ids', [$this->aset->id])
            ->set('alasan', 'Reposisi alat ke poli spesialis')
            ->call('submitPengalihan')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pengalihan_aset', [
            'unit_asal_id' => $this->unit1->id,
            'unit_tujuan_id' => $this->unit2->id,
            'status' => 'DIAJUKAN',
        ]);

        $this->aset->refresh();
        $this->assertEquals('DALAM_PENGALIHAN', $this->aset->status);
    }

    public function test_switching_to_history_tab_renders_completed_transfers(): void
    {
        $this->actingAs($this->userAdmin);

        // Buat pengalihan selesai
        $service = app(PengalihanAsetService::class);
        $transfer = $service->ajukanPengalihan([
            'tipe_asal' => 'RUANGAN',
            'unit_asal_id' => $this->unit1->id,
            'tipe_tujuan' => 'RUANGAN',
            'unit_tujuan_id' => $this->unit2->id,
            'tanggal_pengalihan' => now()->toDateString(),
            'alasan' => 'Mutasi selesai test',
            'diajukan_oleh' => $this->userAdmin->id,
            'aset_ids' => [$this->aset->id],
        ]);
        $service->setujuiPengalihan($transfer->id, $this->userAdmin->id);
        $service->eksekusiSerahTerima($transfer->id, $this->userAdmin->id);

        Livewire::test(PengalihanAsetIndex::class)
            ->call('setTab', 'riwayat')
            ->assertSee($transfer->kode)
            ->assertSee('SELESAI');
    }

    public function test_modal_lists_available_asset_commodities_and_shows_provider_cards(): void
    {
        $userPenerima = User::create([
            'nama' => 'Perawat Penerima Unit 2',
            'username' => 'perawat_u2',
            'email' => 'perawat_u2@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $this->unit2->id,
            'status' => 'AKTIF',
        ]);
        $userPenerima->givePermissionTo(['view_any_layanan_unit::pengalihan::aset', 'create_layanan_unit::pengalihan::aset']);

        $this->actingAs($userPenerima);

        Livewire::test(PengalihanAsetIndex::class)
            ->call('openCreateModal')
            ->set('selected_barang_id', $this->aset->barang_id)
            ->assertSee($this->unit1->nama) // Unit 1 memiliki aset ini
            ->assertSee($this->aset->kode_aset)
            ->assertDontSee('Terkunci Otomatis');
    }

    public function test_submitting_transfer_from_selected_provider_without_return_date(): void
    {
        $userPenerima = User::create([
            'nama' => 'Perawat Penerima Unit 2 Submit',
            'username' => 'perawat_u2_sub',
            'email' => 'perawat_u2_sub@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $this->unit2->id,
            'status' => 'AKTIF',
        ]);
        $userPenerima->givePermissionTo(['view_any_layanan_unit::pengalihan::aset', 'create_layanan_unit::pengalihan::aset']);

        $this->actingAs($userPenerima);

        Livewire::test(PengalihanAsetIndex::class)
            ->call('openCreateModal')
            ->set('selected_barang_id', $this->aset->barang_id)
            ->set('selected_penyedia_key', "RUANGAN_{$this->unit1->id}")
            ->set('alasan', 'Permohonan pengalihan alat monitor ke ruang ICU')
            ->call('submitPengalihan')
            ->assertHasNoErrors();

        // Asal adalah Unit 1 (penyedia alat), Tujuan adalah Unit 2 (unit pemohon)
        $this->assertDatabaseHas('pengalihan_aset', [
            'unit_asal_id' => $this->unit1->id,
            'unit_tujuan_id' => $this->unit2->id,
            'status' => 'DIAJUKAN',
            'diajukan_oleh' => $userPenerima->id,
        ]);

        $this->aset->refresh();
        $this->assertEquals('DALAM_PENGALIHAN', $this->aset->status);
    }

    public function test_modal_ui_does_not_render_terkunci_otomatis_badge(): void
    {
        $this->actingAs($this->userAdmin);

        Livewire::test(PengalihanAsetIndex::class)
            ->call('openCreateModal')
            ->assertDontSee('Terkunci Otomatis');
    }

    public function test_user_can_transfer_multiple_asset_quantities_from_provider(): void
    {
        $userPenerima = User::create([
            'nama' => 'Perawat Penerima Multi Qty',
            'username' => 'perawat_multi_qty',
            'email' => 'multi_qty@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $this->unit2->id,
            'status' => 'AKTIF',
        ]);
        $userPenerima->givePermissionTo(['view_any_layanan_unit::pengalihan::aset', 'create_layanan_unit::pengalihan::aset']);

        // Buat 2 aset lagi di Unit 1
        Aset::create([
            'barang_id' => $this->aset->barang_id,
            'unit_id' => $this->unit1->id,
            'kode_aset' => 'AST-QTY-TRF-2',
            'tanggal_perolehan' => now()->toDateString(),
            'status' => 'TERSEDIA',
            'kondisi' => 'BAIK',
        ]);

        $this->actingAs($userPenerima);

        Livewire::test(PengalihanAsetIndex::class)
            ->call('openCreateModal')
            ->set('selected_barang_id', $this->aset->barang_id)
            ->set('selected_penyedia_key', "RUANGAN_{$this->unit1->id}")
            ->set('quantity', 2)
            ->set('alasan', 'Permohonan mutasi 2 unit monitor')
            ->call('submitPengalihan')
            ->assertHasNoErrors();

        $latestTrf = PengalihanAset::where('unit_tujuan_id', $this->unit2->id)->latest('id')->first();
        $this->assertNotNull($latestTrf);
        $this->assertEquals(2, $latestTrf->detail()->count());
    }

    public function test_searching_provider_in_transfer_modal_filters_displayed_cards(): void
    {
        $this->actingAs($this->userAdmin);

        Livewire::test(PengalihanAsetIndex::class)
            ->call('openCreateModal')
            ->set('selected_barang_id', $this->aset->barang_id)
            ->set('searchPenyedia', substr($this->unit1->nama, 0, 4))
            ->assertSee($this->aset->kode_aset)
            ->set('searchPenyedia', 'NAMA_TIDAK_ADA_XYZ')
            ->assertDontSee($this->aset->kode_aset);
    }

    public function test_transfer_quantity_is_clamped_to_selected_provider_stock(): void
    {
        $this->actingAs($this->userAdmin);

        $test = Livewire::test(PengalihanAsetIndex::class)
            ->call('openCreateModal')
            ->set('selected_barang_id', $this->aset->barang_id)
            ->set('selected_penyedia_key', "RUANGAN_{$this->unit1->id}")
            ->set('quantity', 50); // stok hanya 1

        $this->assertLessThanOrEqual(5, $test->get('quantity'));
    }

    public function test_active_tab_only_displays_asset_transfers_requested_by_user_facility(): void
    {
        $userUnit = User::create([
            'nama' => 'Perawat Ruangan 2 Tab Test',
            'username' => 'perawat_u2_tab',
            'email' => 'perawat_u2_tab@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $this->unit2->id,
            'status' => 'AKTIF',
        ]);
        $userUnit->givePermissionTo(['view_any_layanan_unit::pengalihan::aset', 'create_layanan_unit::pengalihan::aset']);

        $this->actingAs($userUnit);

        // Transfer antara unit 1 ke gudang (tidak melibatkan unit 2)
        $gudang = Gudang::where('status', 'AKTIF')->first();
        $service = app(PengalihanAsetService::class);
        $transferLain = $service->ajukanPengalihan([
            'tipe_asal' => 'RUANGAN',
            'unit_asal_id' => $this->unit1->id,
            'tipe_tujuan' => 'GUDANG',
            'gudang_tujuan_id' => $gudang->id,
            'tanggal_pengalihan' => now()->toDateString(),
            'alasan' => 'Pengalihan aset fasilitas lain',
            'diajukan_oleh' => $this->userAdmin->id,
            'aset_ids' => [$this->aset->id],
        ]);

        Livewire::test(PengalihanAsetIndex::class)
            ->set('activeTab', 'aktif')
            ->assertDontSee($transferLain->kode)
            ->assertSet('kpiBerjalan', 0);
    }

    public function test_authorization_tab_displays_inbound_asset_requests_from_other_facilities(): void
    {
        // Aset berada di Unit 1
        $userUnit1 = User::create([
            'nama' => 'Perawat Ruangan 1 Pemilik',
            'username' => 'perawat_u1_owner',
            'email' => 'perawat_u1_owner@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $this->unit1->id,
            'status' => 'AKTIF',
        ]);
        $userUnit1->givePermissionTo(['view_any_layanan_unit::pengalihan::aset', 'update_layanan_unit::pengalihan::aset']);

        // Unit 2 meminta aset dari Unit 1
        $service = app(PengalihanAsetService::class);
        $transferInbound = $service->ajukanPengalihan([
            'tipe_asal' => 'RUANGAN',
            'unit_asal_id' => $this->unit1->id,
            'tipe_tujuan' => 'RUANGAN',
            'unit_tujuan_id' => $this->unit2->id,
            'tanggal_pengalihan' => now()->toDateString(),
            'alasan' => 'Permintaan unit 2 meminjam permanen',
            'diajukan_oleh' => $this->userAdmin->id,
            'aset_ids' => [$this->aset->id],
        ]);

        $this->actingAs($userUnit1);

        Livewire::test(PengalihanAsetIndex::class)
            ->set('activeTab', 'aktif')
            ->assertDontSee($transferInbound->kode) // Tidak boleh ada di tab pengajuan aktif
            ->call('setTab', 'otorisasi')
            ->assertSee($transferInbound->kode); // Harus ada di tab otorisasi
    }
}
