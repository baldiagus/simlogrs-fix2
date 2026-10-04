<?php

namespace Tests\Feature\Ipsrs;

use App\Livewire\Ipsrs\LaporServisIndex;
use App\Models\Aset;
use App\Models\Barang;
use App\Models\PermintaanMaintenance;
use App\Models\Unit;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class LaporServisTest extends TestCase
{
    use DatabaseTransactions;

    protected User $userRuangan;
    protected Unit $unit;
    protected Barang $barangAset;
    protected Aset $aset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = Unit::where('status', 'AKTIF')->first();

        $this->userRuangan = User::create([
            'nama' => 'Perawat Lapor Servis Test',
            'username' => 'perawatlaporservis',
            'email' => 'perawatlapor@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $this->unit->id,
            'status' => 'AKTIF',
        ]);
        $this->userRuangan->givePermissionTo([
            'create_layanan_unit::lapor::servis',
            'view_any_layanan_unit::lapor::servis',
            'view_layanan_unit::lapor::servis',
            'update_layanan_unit::lapor::servis',
        ]);

        $this->barangAset = Barang::where('status', 'AKTIF')->where('tipe', 'ASET')->first()
            ?: Barang::where('status', 'AKTIF')->first();

        $this->aset = Aset::create([
            'barang_id' => $this->barangAset->id,
            'unit_id' => $this->unit->id,
            'kode_aset' => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri' => 'SN-LAPOR-TEST-001',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 12000000,
            'kondisi' => 'BAIK',
            'status' => 'DIGUNAKAN',
        ]);
    }

    public function test_cancelling_cito_service_report_restores_asset_status(): void
    {
        $this->actingAs($this->userRuangan);

        // 1. Ruangan melaporkan kerusakan darurat CITO
        Livewire::test(LaporServisIndex::class)
            ->call('create')
            ->set('aset_id', $this->aset->id)
            ->set('prioritas', 'CITO')
            ->set('deskripsi_masalah', 'Layar monitor mati total mendadak saat tindakan')
            ->call('save');

        // Pastikan tiket terbuat
        $tiket = PermintaanMaintenance::where('aset_id', $this->aset->id)->latest('id')->first();
        $this->assertNotNull($tiket);
        $this->assertEquals('DILAPORKAN', $tiket->status);

        // Aset otomatis masuk ke status DALAM_MAINTENANCE dan RUSAK_BERAT
        $this->aset->refresh();
        $this->assertEquals('DALAM_MAINTENANCE', $this->aset->status);
        $this->assertEquals('RUSAK_BERAT', $this->aset->kondisi);

        // 2. Ruangan membatalkan laporan (misal: kabel hanya terlepas / false alarm)
        Livewire::test(LaporServisIndex::class)
            ->call('batal', $tiket->id);

        $tiket->refresh();
        $this->assertEquals('DIBATALKAN', $tiket->status);

        // 3. Aset HARUS dipulihkan statusnya dari DALAM_MAINTENANCE
        $this->aset->refresh();
        $this->assertNotEquals('DALAM_MAINTENANCE', $this->aset->status, 'Status aset tidak boleh tertinggal dalam DALAM_MAINTENANCE setelah tiket dibatalkan.');
        $this->assertEquals('DIGUNAKAN', $this->aset->status);
        $this->assertEquals('BAIK', $this->aset->kondisi);
    }
    public function test_user_cannot_cancel_service_ticket_from_another_unit(): void
    {
        // 1. Buat unit lain dan pelapor dari unit tersebut
        $unitLain = Unit::where('status', 'AKTIF')->where('id', '!=', $this->unit->id)->first();
        $userLain = User::create([
            'nama' => 'Staf Unit Lain Test',
            'username' => 'stafunitlaintest',
            'email' => 'unitlain@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $unitLain->id,
            'status' => 'AKTIF',
        ]);

        $tiketUnitLain = PermintaanMaintenance::create([
            'kode' => CodeGenerator::generateMnt(),
            'aset_id' => $this->aset->id,
            'unit_id' => $unitLain->id,
            'dilaporkan_oleh' => $userLain->id,
            'deskripsi_masalah' => 'Kerusakan sah dilaporkan unit lain',
            'prioritas' => 'SEDANG',
            'status' => 'DILAPORKAN',
        ]);

        // 2. User ruangan kita (yang bukan pemilik tiket) mencoba membatalkan tiket unit lain -> HARUS 403
        $this->actingAs($this->userRuangan);

        Livewire::test(LaporServisIndex::class)
            ->call('batal', $tiketUnitLain->id)
            ->assertForbidden();

        $tiketUnitLain->refresh();
        $this->assertEquals('DILAPORKAN', $tiketUnitLain->status);
    }
}
