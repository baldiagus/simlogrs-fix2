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

    public function test_origin_is_locked_to_authenticated_user_room_and_only_shows_room_assets(): void
    {
        $userUnit = User::create([
            'nama' => 'Perawat Ruangan 1',
            'username' => 'perawat_r1',
            'email' => 'perawat_r1@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $this->unit1->id,
            'status' => 'AKTIF',
        ]);
        $userUnit->givePermissionTo(['view_any_layanan_unit::pengalihan::aset', 'create_layanan_unit::pengalihan::aset']);

        $this->actingAs($userUnit);

        // Aset di unit lain (unit2) tidak boleh muncul
        $asetUnitLain = Aset::create([
            'barang_id' => $this->aset->barang_id,
            'unit_id' => $this->unit2->id,
            'kode_aset' => 'AST-U2-' . uniqid(),
            'tanggal_perolehan' => now()->toDateString(),
            'status' => 'TERSEDIA',
            'kondisi' => 'BAIK',
        ]);

        Livewire::test(PengalihanAsetIndex::class)
            ->call('openCreateModal')
            ->assertSet('tipe_asal', 'RUANGAN')
            ->assertSet('unit_asal_id', $this->unit1->id)
            ->assertSee($this->aset->kode_aset)
            ->assertDontSee($asetUnitLain->kode_aset)
            ->assertSee('Terkunci Otomatis');
    }

    public function test_user_cannot_tamper_origin_location_on_submit(): void
    {
        $userUnit = User::create([
            'nama' => 'Perawat Ruangan 1 Tamper',
            'username' => 'perawat_r1_tamper',
            'email' => 'tamper@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $this->unit1->id,
            'status' => 'AKTIF',
        ]);
        $userUnit->givePermissionTo(['view_any_layanan_unit::pengalihan::aset', 'create_layanan_unit::pengalihan::aset']);

        $this->actingAs($userUnit);

        Livewire::test(PengalihanAsetIndex::class)
            ->call('openCreateModal')
            ->set('unit_asal_id', $this->unit2->id) // Percobaan manipulasi unit asal
            ->set('tipe_tujuan', 'RUANGAN')
            ->set('unit_tujuan_id', $this->unit2->id)
            ->set('selected_aset_ids', [$this->aset->id])
            ->set('alasan', 'Percobaan pengalihan manipulasi unit')
            ->call('submitPengalihan')
            ->assertHasNoErrors();

        // Dokumen yang terbit di DB HARUS tetap unit_asal_id milik akun login (unit1)
        $this->assertDatabaseHas('pengalihan_aset', [
            'unit_asal_id' => $this->unit1->id,
            'diajukan_oleh' => $userUnit->id,
        ]);
    }

    public function test_modal_asset_search_filters_displayed_assets(): void
    {
        $this->actingAs($this->userAdmin);

        $asetKedua = Aset::create([
            'barang_id' => $this->aset->barang_id,
            'unit_id' => $this->unit1->id,
            'kode_aset' => 'AST-SEARCH-TARGET-99',
            'nomor_seri' => 'SN-UNIQUE-TARGET',
            'tanggal_perolehan' => now()->toDateString(),
            'status' => 'TERSEDIA',
            'kondisi' => 'BAIK',
        ]);

        Livewire::test(PengalihanAsetIndex::class)
            ->call('openCreateModal')
            ->set('tipe_asal', 'RUANGAN')
            ->set('unit_asal_id', $this->unit1->id)
            ->set('searchAsetModal', 'TARGET-99')
            ->assertSee('AST-SEARCH-TARGET-99')
            ->assertDontSee($this->aset->kode_aset);
    }
}
