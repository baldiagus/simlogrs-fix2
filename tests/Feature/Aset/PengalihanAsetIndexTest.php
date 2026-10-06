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
}
