<?php

namespace Tests\Feature\Ipsrs;

use App\Livewire\Ipsrs\RekamMedisAsetIndex;
use App\Models\Aset\Aset;
use App\Models\Master\Barang;
use App\Models\Master\Unit;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class RekamMedisAsetTest extends TestCase
{
    use DatabaseTransactions;

    protected User $userTeknisi;
    protected Unit $unit;
    protected Barang $barangAset;
    protected Aset $aset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = Unit::where('status', 'AKTIF')->first();

        $this->userTeknisi = User::create([
            'nama' => 'Teknisi Rekam Medis Test',
            'username' => 'teknisirekammedis',
            'email' => 'teknisirm@simlog.test',
            'password' => bcrypt('password'),
            'status' => 'AKTIF',
        ]);
        $this->userTeknisi->givePermissionTo([
            'view_any_pemeliharaan::rekam::medis::aset',
            'view_pemeliharaan::rekam::medis::aset',
        ]);

        $this->barangAset = Barang::where('status', 'AKTIF')->where('tipe', 'ASET')->first()
            ?: Barang::where('status', 'AKTIF')->first();

        $this->aset = Aset::create([
            'barang_id' => $this->barangAset->id,
            'unit_id' => $this->unit->id,
            'kode_aset' => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri' => 'SN-RM-TEST-01',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 15000000,
            'kondisi' => 'BAIK',
            'status' => 'TERSEDIA',
        ]);
    }

    public function test_calling_open_rekam_medis_and_show_buku_riwayat_loads_selected_aset(): void
    {
        $this->actingAs($this->userTeknisi);

        // Test memanggil method yang tertera pada baris nama barang di Blade template
        $component = Livewire::test(RekamMedisAsetIndex::class)
            ->call('openRekamMedis', $this->aset->id);

        $component->assertSet('showDetailModal', true);
        $this->assertEquals($this->aset->id, $component->get('selectedAset')->id);
    }
}
