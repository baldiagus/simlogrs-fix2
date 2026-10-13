<?php

namespace Tests\Feature\Aset;

use App\Models\Aset\PengalihanAset;
use App\Models\Master\Unit;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PengalihanAsetApiAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    private Unit $unitA;
    private Unit $unitB;
    private User $pengaju;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->unitA, $this->unitB] = Unit::where('status', 'AKTIF')->take(2)->get()->all();
        $this->pengaju = $this->user('pengaju', $this->unitB->id);
    }

    public function test_role_only_without_permission_cannot_authorize(): void
    {
        // Seeder memberi kepala_unit permission lewat role; cabut agar yang diuji murni role tanpa permission.
        Role::findByName('kepala_unit', 'web')->revokePermissionTo('update_layanan_unit::pengalihan::aset');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $karu = $this->user('karu-role-only', $this->unitA->id);
        $karu->assignRole('kepala_unit');

        $this->actingAs($karu)
            ->postJson("/api/v1/aset/pengalihan/{$this->dokumen()->id}/otorisasi", ['aksi' => 'APPROVE'])
            ->assertForbidden();
    }

    public function test_permission_without_workflow_role_cannot_authorize(): void
    {
        $user = $this->user('perm-only', $this->unitA->id);
        $user->givePermissionTo('update_layanan_unit::pengalihan::aset');

        $this->actingAs($user)
            ->postJson("/api/v1/aset/pengalihan/{$this->dokumen()->id}/otorisasi", ['aksi' => 'APPROVE'])
            ->assertForbidden();
    }

    public function test_origin_kepala_unit_with_permission_can_authorize(): void
    {
        $karu = $this->user('karu-asal', $this->unitA->id);
        $karu->assignRole('kepala_unit');
        $karu->givePermissionTo('update_layanan_unit::pengalihan::aset');
        $dokumen = $this->dokumen();

        $this->actingAs($karu)
            ->postJson("/api/v1/aset/pengalihan/{$dokumen->id}/otorisasi", ['aksi' => 'APPROVE'])
            ->assertOk();

        $this->assertSame('DISETUJUI', $dokumen->fresh()->status);
    }

    public function test_kepala_unit_of_other_unit_cannot_authorize(): void
    {
        $karu = $this->user('karu-lain', $this->unitB->id);
        $karu->assignRole('kepala_unit');
        $karu->givePermissionTo('update_layanan_unit::pengalihan::aset');

        $this->actingAs($karu)
            ->postJson("/api/v1/aset/pengalihan/{$this->dokumen()->id}/otorisasi", ['aksi' => 'APPROVE'])
            ->assertForbidden();
    }

    public function test_user_without_unit_cannot_authorize_even_with_role_and_permission(): void
    {
        $karu = $this->user('karu-tanpa-unit', null);
        $karu->assignRole('kepala_unit');
        $karu->givePermissionTo('update_layanan_unit::pengalihan::aset');

        $this->actingAs($karu)
            ->postJson("/api/v1/aset/pengalihan/{$this->dokumen()->id}/otorisasi", ['aksi' => 'APPROVE'])
            ->assertForbidden();
    }

    public function test_other_user_cannot_cancel_but_requester_with_permission_can(): void
    {
        $dokumen = $this->dokumen();
        $lain = $this->user('bukan-pengaju', $this->unitB->id);
        $lain->givePermissionTo('delete_layanan_unit::pengalihan::aset');

        $this->actingAs($lain)
            ->postJson("/api/v1/aset/pengalihan/{$dokumen->id}/batal")
            ->assertForbidden();

        $this->pengaju->givePermissionTo('delete_layanan_unit::pengalihan::aset');
        $this->actingAs($this->pengaju)
            ->postJson("/api/v1/aset/pengalihan/{$dokumen->id}/batal")
            ->assertOk();
    }

    public function test_receipt_requires_destination_scope(): void
    {
        $dokumen = $this->dokumen(['status' => 'DISETUJUI']);
        $asal = $this->user('petugas-asal', $this->unitA->id);
        $asal->givePermissionTo('update_layanan_unit::pengalihan::aset');

        $this->actingAs($asal)
            ->postJson("/api/v1/aset/pengalihan/{$dokumen->id}/serah-terima")
            ->assertForbidden();
    }

    public function test_admin_logistik_bypasses_scope(): void
    {
        $admin = $this->user('admin-log', null);
        $admin->assignRole('admin_logistik');

        $this->actingAs($admin)
            ->postJson("/api/v1/aset/pengalihan/{$this->dokumen()->id}/otorisasi", ['aksi' => 'APPROVE'])
            ->assertOk();
    }

    private function user(string $username, ?int $unitId): User
    {
        return User::create([
            'nama' => $username,
            'username' => $username . '-api-trf',
            'email' => $username . '-api-trf@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $unitId,
            'status' => 'AKTIF',
        ]);
    }

    /** Dokumen: asal unitA -> tujuan unitB, diajukan $this->pengaju. */
    private function dokumen(array $override = []): PengalihanAset
    {
        return PengalihanAset::create(array_merge([
            'kode' => 'TRF-API-' . uniqid(),
            'tipe_asal' => 'RUANGAN',
            'unit_asal_id' => $this->unitA->id,
            'tipe_tujuan' => 'RUANGAN',
            'unit_tujuan_id' => $this->unitB->id,
            'tanggal_pengalihan' => now()->toDateString(),
            'alasan' => 'Tes otorisasi API',
            'status' => 'DIAJUKAN',
            'diajukan_oleh' => $this->pengaju->id,
        ], $override));
    }
}
