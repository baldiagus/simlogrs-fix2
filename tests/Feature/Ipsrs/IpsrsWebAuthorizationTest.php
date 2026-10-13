<?php

namespace Tests\Feature\Ipsrs;

use App\Livewire\Ipsrs\DaftarSpkPerbaikanIndex;
use App\Livewire\Ipsrs\LaporServisIndex;
use App\Livewire\Ipsrs\LembarKerjaPengerjaanIndex;
use App\Livewire\Master\AsetIndex;
use App\Models\Aset\Aset;
use App\Models\Master\Barang;
use App\Models\Ipsrs\PenugasanMaintenance;
use App\Models\Ipsrs\PermintaanMaintenance;
use App\Models\Master\Unit;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * ADR 0001: otorisasi web modul IPSRS (lapor servis, SPK, lembar kerja) dan register Master Aset.
 */
class IpsrsWebAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected Unit $unitA;
    protected Unit $unitB;
    protected Aset $asetA;
    protected User $pelaporA;
    protected PermintaanMaintenance $tiketA;

    protected function setUp(): void
    {
        parent::setUp();

        $units = Unit::where('status', 'AKTIF')->orderBy('id')->take(2)->get();
        $this->unitA = $units[0];
        $this->unitB = $units[1];

        $barang = Barang::where('status', 'AKTIF')->where('tipe', 'ASET')->first()
            ?: Barang::where('status', 'AKTIF')->first();

        $this->asetA = Aset::create([
            'barang_id' => $barang->id,
            'unit_id' => $this->unitA->id,
            'kode_aset' => 'AST-IPSRS-RBAC-' . uniqid(),
            'nomor_seri' => 'SN-IPSRS-RBAC',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 1000000,
            'kondisi' => 'RUSAK_RINGAN',
            'status' => 'DALAM_MAINTENANCE',
        ]);

        $this->pelaporA = $this->makeUser('pelapor_a', $this->unitA->id);
        $this->pelaporA->assignRole('petugas_unit');

        $this->tiketA = PermintaanMaintenance::create([
            'kode' => CodeGenerator::generateMnt(),
            'aset_id' => $this->asetA->id,
            'unit_id' => $this->unitA->id,
            'dilaporkan_oleh' => $this->pelaporA->id,
            'deskripsi_masalah' => 'Uji RBAC: alat tidak menyala',
            'prioritas' => 'SEDANG',
            'status' => 'DILAPORKAN',
        ]);
    }

    private function makeUser(string $username, ?int $unitId = null, ?int $gudangId = null): User
    {
        return User::create([
            'nama' => 'User ' . $username,
            'username' => $username . '_' . uniqid(),
            'email' => $username . uniqid() . '@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $unitId,
            'gudang_id' => $gudangId,
            'status' => 'AKTIF',
        ]);
    }

    private function makeSpk(User $teknisi, User $penugas): PenugasanMaintenance
    {
        return PenugasanMaintenance::create([
            'kode' => CodeGenerator::generateSpk(),
            'permintaan_maintenance_id' => $this->tiketA->id,
            'teknisi_id' => $teknisi->id,
            'ditugaskan_oleh' => $penugas->id,
            'tanggal_penugasan' => now()->toDateString(),
            'target_selesai' => now()->addDay()->toDateString(),
            'status' => 'DITUGASKAN',
        ]);
    }

    // --- SPK: tahap workflow Koordinator IPSRS (role + permission)

    public function test_kepala_teknisi_role_without_spk_permission_is_denied(): void
    {
        Role::findByName('kepala_teknisi', 'web')->revokePermissionTo('create_pemeliharaan::spk::perbaikan');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = $this->makeUser('katek_tanpa_izin');
        $user->assignRole('kepala_teknisi');
        $user->givePermissionTo(['page_Dashboard', 'page_TeknisiDashboard']);

        $this->actingAs($user);
        Livewire::test(DaftarSpkPerbaikanIndex::class)
            ->call('openTerbitkanSpk', $this->tiketA->id)
            ->assertForbidden();
    }

    public function test_kepala_teknisi_with_permission_can_issue_spk(): void
    {
        $koordinator = $this->makeUser('katek');
        $koordinator->assignRole('kepala_teknisi');
        $teknisi = $this->makeUser('teknisi_pelaksana');
        $teknisi->assignRole('teknisi');

        $this->actingAs($koordinator);
        Livewire::test(DaftarSpkPerbaikanIndex::class)
            ->call('openTerbitkanSpk', $this->tiketA->id)
            ->set('teknisi_id', $teknisi->id)
            ->set('tanggal_penugasan', now()->toDateString())
            ->set('target_selesai', now()->addDays(2)->toDateString())
            ->call('saveSpk')
            ->assertHasNoErrors();

        $this->assertSame('DITUGASKAN', $this->tiketA->fresh()->status);
    }

    public function test_teknisi_with_create_permission_but_no_coordinator_role_cannot_issue_spk(): void
    {
        $teknisi = $this->makeUser('teknisi_biasa');
        $teknisi->assignRole('teknisi');
        $this->assertTrue($teknisi->can('create_pemeliharaan::spk::perbaikan'));

        $this->actingAs($teknisi);
        Livewire::test(DaftarSpkPerbaikanIndex::class)
            ->call('openTerbitkanSpk', $this->tiketA->id)
            ->assertForbidden();

        $this->assertSame('DILAPORKAN', $this->tiketA->fresh()->status);
    }

    // --- Lembar kerja: teknisi hanya SPK miliknya

    public function test_teknisi_only_sees_and_opens_own_spk(): void
    {
        $koordinator = $this->makeUser('katek_lk');
        $koordinator->assignRole('kepala_teknisi');
        $teknisiA = $this->makeUser('teknisi_a');
        $teknisiA->assignRole('teknisi');
        $teknisiB = $this->makeUser('teknisi_b');
        $teknisiB->assignRole('teknisi');
        $teknisiB->givePermissionTo('page_AdminLogistikDashboard');

        $spkA = $this->makeSpk($teknisiA, $koordinator);

        $this->actingAs($teknisiB);
        Livewire::test(LembarKerjaPengerjaanIndex::class)
            ->assertDontSee($spkA->kode);
        Livewire::test(LembarKerjaPengerjaanIndex::class)
            ->call('showDetail', $spkA->id)
            ->assertForbidden();
        Livewire::test(LembarKerjaPengerjaanIndex::class)
            ->call('openLembarKerja', $spkA->id)
            ->assertForbidden();
        Livewire::test(DaftarSpkPerbaikanIndex::class)
            ->set('activeTab', 'spk')
            ->assertDontSee($spkA->kode);

        $this->actingAs($teknisiA);
        Livewire::test(LembarKerjaPengerjaanIndex::class)
            ->assertSee($spkA->kode)
            ->call('openLembarKerja', $spkA->id)
            ->assertSet('showModal', true);

        $this->actingAs($koordinator);
        Livewire::test(LembarKerjaPengerjaanIndex::class)
            ->assertSee($spkA->kode);
    }

    // --- Lapor servis: scope unit dan pembatalan

    public function test_unit_user_cannot_read_or_cancel_other_unit_ticket(): void
    {
        $userB = $this->makeUser('kaunit_b', $this->unitB->id);
        $userB->assignRole('kepala_unit');
        $userB->givePermissionTo('page_Dashboard');

        $this->actingAs($userB);
        Livewire::test(LaporServisIndex::class)
            ->assertDontSee($this->tiketA->kode);
        Livewire::test(LaporServisIndex::class)
            ->call('showDetail', $this->tiketA->id)
            ->assertForbidden();
        Livewire::test(LaporServisIndex::class)
            ->call('batal', $this->tiketA->id)
            ->assertForbidden();

        $this->assertSame('DILAPORKAN', $this->tiketA->fresh()->status);
    }

    public function test_reporter_can_cancel_own_ticket(): void
    {
        $this->actingAs($this->pelaporA);
        Livewire::test(LaporServisIndex::class)
            ->assertSee($this->tiketA->kode)
            ->call('batal', $this->tiketA->id);

        $this->assertSame('DIBATALKAN', $this->tiketA->fresh()->status);
    }

    public function test_auditor_reads_globally_but_cannot_cancel(): void
    {
        $auditor = $this->makeUser('auditor_ipsrs');
        $auditor->assignRole('auditor');

        $this->actingAs($auditor);
        Livewire::test(LaporServisIndex::class)
            ->assertSee($this->tiketA->kode);
        Livewire::test(LaporServisIndex::class)
            ->call('batal', $this->tiketA->id)
            ->assertForbidden();

        $this->assertSame('DILAPORKAN', $this->tiketA->fresh()->status);
    }

    public function test_lapor_servis_page_requires_view_permission(): void
    {
        $user = $this->makeUser('tanpa_view', $this->unitA->id);
        $user->givePermissionTo(['page_Dashboard', 'page_UnitDashboard']);

        $this->actingAs($user);
        Livewire::test(LaporServisIndex::class)->assertForbidden();
    }

    // --- Master aset: scope unit, dashboard bukan bypass

    public function test_master_aset_is_scoped_to_own_unit(): void
    {
        $userB = $this->makeUser('petugas_unit_b', $this->unitB->id);
        $userB->assignRole('petugas_unit');
        $userB->givePermissionTo(['page_AdminLogistikDashboard', 'update_master::aset', 'delete_master::aset']);

        $this->actingAs($userB);
        Livewire::test(AsetIndex::class)
            ->assertDontSee($this->asetA->kode_aset);
        Livewire::test(AsetIndex::class)
            ->call('showDetail', $this->asetA->id)
            ->assertForbidden();
        Livewire::test(AsetIndex::class)
            ->call('edit', $this->asetA->id)
            ->assertForbidden();
        Livewire::test(AsetIndex::class)
            ->call('delete', $this->asetA->id)
            ->assertForbidden();

        $this->assertNotNull(Aset::find($this->asetA->id));
    }

    public function test_master_aset_edit_requires_permission_not_role(): void
    {
        $this->actingAs($this->pelaporA);
        Livewire::test(AsetIndex::class)
            ->assertSee($this->asetA->kode_aset)
            ->call('edit', $this->asetA->id)
            ->assertForbidden();

        $this->pelaporA->givePermissionTo('update_master::aset');
        Livewire::test(AsetIndex::class)
            ->call('edit', $this->asetA->id)
            ->assertSet('showModal', true);
    }
}
