<?php

namespace Tests\Feature\Ipsrs;

use App\Livewire\Ipsrs\DaftarSpkPerbaikanIndex;
use App\Models\Aset;
use App\Models\Barang;
use App\Models\PenugasanMaintenance;
use App\Models\PermintaanMaintenance;
use App\Models\Unit;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class DaftarSpkTest extends TestCase
{
    use DatabaseTransactions;

    protected User $userKoordinator;
    protected User $userTeknisi;
    protected Unit $unit;
    protected Barang $barangAset;
    protected Aset $aset;
    protected PermintaanMaintenance $tiket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = Unit::where('status', 'AKTIF')->first();

        $this->userKoordinator = User::create([
            'nama' => 'Koordinator IPSRS Test',
            'username' => 'koordinatoripsrs',
            'email' => 'koordinator@simlog.test',
            'password' => bcrypt('password'),
            'status' => 'AKTIF',
        ]);
        $this->userKoordinator->givePermissionTo([
            'create_pemeliharaan::spk::perbaikan',
            'view_any_pemeliharaan::spk::perbaikan',
            'view_pemeliharaan::spk::perbaikan',
            'update_pemeliharaan::spk::perbaikan',
        ]);

        $this->userTeknisi = User::create([
            'nama' => 'Teknisi Elektromedik Test',
            'username' => 'teknisielmedtest',
            'email' => 'teknisielmed@simlog.test',
            'password' => bcrypt('password'),
            'status' => 'AKTIF',
        ]);

        $this->barangAset = Barang::where('status', 'AKTIF')->where('tipe', 'ASET')->first()
            ?: Barang::where('status', 'AKTIF')->first();

        $this->aset = Aset::create([
            'barang_id' => $this->barangAset->id,
            'unit_id' => $this->unit->id,
            'kode_aset' => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri' => 'SN-SPK-TEST-001',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 15000000,
            'kondisi' => 'RUSAK_RINGAN',
            'status' => 'DALAM_MAINTENANCE',
        ]);

        $this->tiket = PermintaanMaintenance::create([
            'kode' => CodeGenerator::generateMnt(),
            'aset_id' => $this->aset->id,
            'unit_id' => $this->unit->id,
            'dilaporkan_oleh' => $this->userKoordinator->id,
            'deskripsi_masalah' => 'Sensor suhu inkubator error code E04',
            'prioritas' => 'TINGGI',
            'status' => 'DILAPORKAN',
        ]);
    }

    public function test_concurrent_or_duplicate_spk_issuance_on_same_ticket_is_prevented(): void
    {
        $this->actingAs($this->userKoordinator);

        // 1. Terbitkan SPK pertama
        $component1 = Livewire::test(DaftarSpkPerbaikanIndex::class)
            ->call('openTerbitkanSpk', $this->tiket->id)
            ->set('teknisi_id', $this->userTeknisi->id)
            ->set('tanggal_penugasan', now()->toDateString())
            ->set('target_selesai', now()->addDays(2)->toDateString())
            ->call('saveSpk');

        $this->tiket->refresh();
        $this->assertEquals('DITUGASKAN', $this->tiket->status);

        // SPK pertama berhasil tersimpan
        $this->assertEquals(1, PenugasanMaintenance::where('permintaan_maintenance_id', $this->tiket->id)->count());

        // 2. Coba terbitkan SPK kedua untuk tiket yang sama
        $component2 = Livewire::test(DaftarSpkPerbaikanIndex::class)
            ->set('selected_tiket_id', $this->tiket->id)
            ->set('teknisi_id', $this->userTeknisi->id)
            ->set('tanggal_penugasan', now()->toDateString())
            ->set('target_selesai', now()->addDays(2)->toDateString())
            ->call('saveSpk');

        // SPK kedua HARUS DITOLAK: jumlah SPK di DB untuk tiket ini TETAP 1!
        $this->assertEquals(1, PenugasanMaintenance::where('permintaan_maintenance_id', $this->tiket->id)->count(), 'Tidak boleh menerbitkan SPK ganda untuk tiket yang sama.');
    }
}
