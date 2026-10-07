<?php

namespace Tests\Feature\Pengadaan;

use App\Livewire\Pengadaan\RealisasiAnggaranIndex;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class ScreeningPengeluaranUnitTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::where('email', 'superadmin@simlogrs.local')->first();
    }

    public function test_screening_table_renders_hospital_units_even_when_anggaran_pengadaan_table_is_empty(): void
    {
        $this->actingAs($this->user);

        $igd = Unit::where('kode', 'IGD')->firstOrFail();

        Livewire::test(RealisasiAnggaranIndex::class)
            ->assertSee($igd->nama)
            ->assertSee($igd->kode)
            ->assertDontSee('Tidak ada data unit kerja yang sesuai dengan kata kunci pencarian pada TA 2026.');
    }

    public function test_screening_table_displays_accurate_distribution_and_sparepart_expenses(): void
    {
        $this->actingAs($this->user);

        Livewire::test(RealisasiAnggaranIndex::class)
            ->assertSee('99.000.000')
            ->assertSee('1.870.000');
    }

    public function test_screening_table_search_filters_units_by_name_or_code(): void
    {
        $this->actingAs($this->user);

        Livewire::test(RealisasiAnggaranIndex::class)
            ->set('search', 'IGD')
            ->assertSee('Instalasi Gawat Darurat')
            ->assertDontSee('Laboratorium Patologi Klinik');
    }

    public function test_drilldown_modal_opens_for_selected_unit(): void
    {
        $this->actingAs($this->user);

        $igd = Unit::where('kode', 'IGD')->firstOrFail();

        Livewire::test(RealisasiAnggaranIndex::class)
            ->call('openDetail', $igd->id)
            ->assertSet('selectedUnitId', $igd->id)
            ->assertSet('showDetailModal', true)
            ->assertSee($igd->nama);
    }
}
