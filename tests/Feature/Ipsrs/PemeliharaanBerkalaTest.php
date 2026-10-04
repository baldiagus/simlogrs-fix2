<?php

namespace Tests\Feature\Ipsrs;

use App\Livewire\Ipsrs\PemeliharaanBerkalaIndex;
use App\Models\Aset;
use App\Models\Barang;
use App\Models\JadwalMaintenance;
use App\Models\PenugasanMaintenance;
use App\Models\PermintaanMaintenance;
use App\Models\Unit;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class PemeliharaanBerkalaTest extends TestCase
{
    use DatabaseTransactions;

    protected User $userKoordinator;
    protected User $userTeknisi;
    protected Unit $unit;
    protected Barang $barangAset;
    protected Aset $aset;
    protected JadwalMaintenance $jadwal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = Unit::where('status', 'AKTIF')->first();

        $this->userKoordinator = User::create([
            'nama' => 'Koordinator Preventif Test',
            'username' => 'koorpreventif',
            'email' => 'koorpreventif@simlog.test',
            'password' => bcrypt('password'),
            'status' => 'AKTIF',
        ]);
        $this->userKoordinator->givePermissionTo([
            'create_pemeliharaan::spk::perbaikan',
            'create_pemeliharaan::pemeliharaan::berkala',
            'update_pemeliharaan::pemeliharaan::berkala',
            'view_any_pemeliharaan::pemeliharaan::berkala',
            'view_pemeliharaan::pemeliharaan::berkala',
        ]);

        $this->userTeknisi = User::create([
            'nama' => 'Teknisi Preventif Test',
            'username' => 'teknisipreventif',
            'email' => 'teknisipreventif@simlog.test',
            'password' => bcrypt('password'),
            'status' => 'AKTIF',
        ]);

        $this->barangAset = Barang::where('status', 'AKTIF')->where('tipe', 'ASET')->first()
            ?: Barang::where('status', 'AKTIF')->first();

        $this->aset = Aset::create([
            'barang_id' => $this->barangAset->id,
            'unit_id' => $this->unit->id,
            'kode_aset' => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri' => 'SN-PRV-TEST-01',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 20000000,
            'kondisi' => 'BAIK',
            'status' => 'DIGUNAKAN',
        ]);

        $this->jadwal = JadwalMaintenance::create([
            'kode' => CodeGenerator::generatePrv(),
            'aset_id' => $this->aset->id,
            'jenis_maintenance' => 'Kalibrasi Berkala 3 Bulanan',
            'frekuensi' => '3_BULAN',
            'interval_nilai' => 3,
            'interval_tipe' => 'BULAN',
            'tanggal_terakhir' => now()->subMonths(3)->toDateString(),
            'tanggal_berikutnya' => now()->toDateString(),
            'teknisi_id' => $this->userTeknisi->id,
            'status' => 'AKTIF',
            'dibuat_oleh' => $this->userKoordinator->id,
        ]);
    }

    public function test_executing_preventive_schedule_locks_asset_status_to_dalam_maintenance(): void
    {
        $this->actingAs($this->userKoordinator);

        // Eksekusi penerbitan SPK preventif sekarang
        Livewire::test(PemeliharaanBerkalaIndex::class)
            ->call('eksekusiServis', $this->jadwal->id);

        // Pastikan tiket dan SPK terbuat
        $spk = PenugasanMaintenance::where('teknisi_id', $this->userTeknisi->id)->latest('id')->first();
        $this->assertNotNull($spk);

        // ASET HARUS DIKUNCI KE STATUS DALAM_MAINTENANCE!
        $this->aset->refresh();
        $this->assertEquals('DALAM_MAINTENANCE', $this->aset->status, 'Aset harus dikunci ke status DALAM_MAINTENANCE saat SPK servis preventif diterbitkan.');
    }
}
