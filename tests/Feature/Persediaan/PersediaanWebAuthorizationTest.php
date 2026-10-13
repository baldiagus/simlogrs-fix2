<?php

namespace Tests\Feature\Persediaan;

use App\Livewire\Persediaan\OtorisasiPenyesuaianIndex;
use App\Livewire\Persediaan\PengalihanBarangIndex;
use App\Models\Master\Gudang;
use App\Models\Persediaan\PenyesuaianStok;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * ADR 0001: otorisasi web modul Persediaan (otorisasi penyesuaian opname & pengalihan barang).
 */
class PersediaanWebAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected Gudang $gudangA;
    protected Gudang $gudangB;
    protected PenyesuaianStok $penyesuaianA;

    protected function setUp(): void
    {
        parent::setUp();

        $gudang = Gudang::where('status', 'AKTIF')->orderBy('id')->take(2)->get();
        $this->gudangA = $gudang[0];
        $this->gudangB = $gudang[1];

        $pengaju = $this->makeUser('pengaju_adj', $this->gudangA->id);

        $this->penyesuaianA = PenyesuaianStok::forceCreate([
            'kode' => 'ADJ-RBAC-' . uniqid(),
            'gudang_id' => $this->gudangA->id,
            'tanggal_penyesuaian' => now()->toDateString(),
            'alasan' => 'Selisih hitung fisik uji RBAC',
            'status' => 'MENUNGGU',
            'diajukan_oleh' => $pengaju->id,
        ]);
    }

    private function makeUser(string $username, ?int $gudangId = null, ?int $unitId = null): User
    {
        return User::create([
            'nama' => 'User ' . $username,
            'username' => $username . '_' . uniqid(),
            'email' => $username . uniqid() . '@simlog.test',
            'password' => bcrypt('password'),
            'gudang_id' => $gudangId,
            'unit_id' => $unitId,
            'status' => 'AKTIF',
        ]);
    }

    public function test_page_requires_view_permission(): void
    {
        $user = $this->makeUser('tanpa_view', $this->gudangA->id);
        $user->givePermissionTo(['page_Dashboard', 'page_KepalaGudangDashboard']);

        $this->actingAs($user);
        Livewire::test(OtorisasiPenyesuaianIndex::class)->assertForbidden();
    }

    public function test_kepala_gudang_role_without_action_permission_is_denied_even_with_dashboard_permissions(): void
    {
        Role::findByName('kepala_gudang', 'web')->revokePermissionTo('update_gudang_stok::otorisasi::penyesuaian');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = $this->makeUser('kagud_tanpa_izin', $this->gudangA->id);
        $user->assignRole('kepala_gudang');
        $user->givePermissionTo(['page_Dashboard', 'page_KepalaGudangDashboard']);

        $this->actingAs($user);
        Livewire::test(OtorisasiPenyesuaianIndex::class)
            ->call('approvePenyesuaian', $this->penyesuaianA->id)
            ->assertForbidden();

        $this->assertSame('MENUNGGU', $this->penyesuaianA->fresh()->status);
    }

    public function test_kepala_gudang_with_permission_can_approve_own_warehouse_adjustment(): void
    {
        $user = $this->makeUser('kagud_a', $this->gudangA->id);
        $user->assignRole('kepala_gudang');

        $this->actingAs($user);
        Livewire::test(OtorisasiPenyesuaianIndex::class)
            ->assertSee($this->penyesuaianA->kode)
            ->call('approvePenyesuaian', $this->penyesuaianA->id)
            ->assertOk();

        $this->assertSame('DISETUJUI', $this->penyesuaianA->fresh()->status);
    }

    public function test_permission_without_kepala_gudang_role_cannot_approve(): void
    {
        $user = $this->makeUser('petugas_berizin', $this->gudangA->id);
        $user->givePermissionTo([
            'view_any_gudang_stok::otorisasi::penyesuaian',
            'update_gudang_stok::otorisasi::penyesuaian',
        ]);

        $this->actingAs($user);
        Livewire::test(OtorisasiPenyesuaianIndex::class)
            ->assertDontSee('Sahkan Penyesuaian')
            ->call('rejectPenyesuaian', $this->penyesuaianA->id)
            ->assertForbidden();

        $this->assertSame('MENUNGGU', $this->penyesuaianA->fresh()->status);
    }

    public function test_kepala_gudang_of_other_warehouse_cannot_read_or_approve(): void
    {
        $user = $this->makeUser('kagud_b', $this->gudangB->id);
        $user->assignRole('kepala_gudang');
        $user->givePermissionTo('page_Dashboard');

        $this->actingAs($user);
        Livewire::test(OtorisasiPenyesuaianIndex::class)
            ->assertDontSee($this->penyesuaianA->kode);

        Livewire::test(OtorisasiPenyesuaianIndex::class)
            ->call('showDetail', $this->penyesuaianA->id)
            ->assertForbidden();

        Livewire::test(OtorisasiPenyesuaianIndex::class)
            ->call('approvePenyesuaian', $this->penyesuaianA->id)
            ->assertForbidden();

        $this->assertSame('MENUNGGU', $this->penyesuaianA->fresh()->status);
    }

    public function test_pengalihan_barang_dashboard_permission_does_not_unlock_global_scope(): void
    {
        $user = $this->makeUser('gudang_b_dashboard', $this->gudangB->id);
        $user->givePermissionTo([
            'page_Dashboard',
            'view_any_gudang_stok::pengalihan::barang',
            'create_gudang_stok::pengalihan::barang',
        ]);

        $this->actingAs($user);
        Livewire::test(PengalihanBarangIndex::class)
            ->assertSet('tipe_tujuan', 'GUDANG')
            ->assertSet('gudang_tujuan_id', $this->gudangB->id)
            ->call('openCreateModal')
            ->assertSet('gudang_tujuan_id', $this->gudangB->id);
    }
}
