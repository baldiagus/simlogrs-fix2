<?php

namespace Tests\Feature\Pengadaan;

use App\Enums\Pengadaan\StatusPermintaanPengadaan;
use App\Models\Master\Barang;
use App\Models\Master\Unit;
use App\Models\Pengadaan\PermintaanPengadaan;
use App\Models\Pengadaan\PermintaanPengadaanDetail;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PengadaanApprovalSecurityTest extends TestCase
{
    use DatabaseTransactions;

    protected Unit $unitA;
    protected Unit $unitB;
    protected Barang $barang;
    protected User $creator;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $units = Unit::where('status', 'AKTIF')->orderBy('id')->take(2)->get();
        if ($units->count() < 2) {
            $this->markTestSkipped('Butuh minimal dua unit AKTIF pada database dev.');
        }
        $this->unitA = $units[0];
        $this->unitB = $units[1];

        $this->barang = Barang::where('status', 'AKTIF')->firstOrFail();
        $this->creator = User::where('email', 'superadmin@simlogrs.local')->firstOrFail();
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    private function makeUser(array $roles = [], array $permissions = [], ?int $unitId = null, ?int $gudangId = null): User
    {
        $suffix = uniqid();
        $user = User::create([
            'nama' => 'Uji Approval ' . $suffix,
            'username' => 'uji_approval_' . $suffix,
            'email' => "uji_approval_{$suffix}@simlog.test",
            'password' => bcrypt('password'),
            'unit_id' => $unitId,
            'gudang_id' => $gudangId,
            'status' => 'AKTIF',
        ]);

        foreach ($permissions as $name) {
            $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }

        foreach ($roles as $role) {
            Role::findOrCreate($role, 'web');
        }
        if ($roles) {
            $user->assignRole($roles);
        }

        return $user->fresh();
    }

    private function makePr(?int $unitId, string $status = StatusPermintaanPengadaan::Diajukan->value): PermintaanPengadaan
    {
        $pr = PermintaanPengadaan::create([
            'kode' => CodeGenerator::generatePr(),
            'unit_id' => $unitId,
            'diajukan_oleh' => $this->creator->id,
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi' => 'REGULER',
            'status' => $status,
        ]);

        PermintaanPengadaanDetail::create([
            'permintaan_pengadaan_id' => $pr->id,
            'barang_id' => $this->barang->id,
            'jumlah' => 3,
        ]);

        return $pr;
    }

    public function test_role_pengadaan_can_reject_pr_with_status_diteruskan_ke_pengadaan(): void
    {
        $userPengadaan = $this->makeUser(['pengadaan'], unitId: $this->unitA->id);
        $pr = $this->makePr($this->unitB->id, StatusPermintaanPengadaan::DiteruskanKePengadaan->value);

        $response = $this->actingAs($userPengadaan)
            ->postJson("/api/v1/approval/pr/{$pr->id}", [
                'aksi' => 'tolak',
                'catatan' => 'Spesifikasi belanja belum memenuhi kriteria',
            ]);

        $response->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('data.status', StatusPermintaanPengadaan::Ditolak->value);

        $prFresh = $pr->fresh();
        $this->assertSame(StatusPermintaanPengadaan::Ditolak->value, $prFresh->status);
        $this->assertSame('[Ditolak Pengadaan]: Spesifikasi belanja belum memenuhi kriteria', $prFresh->catatan);
        $this->assertSame($userPengadaan->id, $prFresh->disetujui_oleh);
    }

    public function test_role_pengadaan_cannot_reject_pr_with_status_diajukan(): void
    {
        $userPengadaan = $this->makeUser(['pengadaan']);
        $pr = $this->makePr($this->unitA->id, StatusPermintaanPengadaan::Diajukan->value);

        $response = $this->actingAs($userPengadaan)
            ->postJson("/api/v1/approval/pr/{$pr->id}", [
                'aksi' => 'tolak',
                'catatan' => 'Ditolak awal',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('sukses', false);

        $this->assertStringContainsString(
            'Bagian Pengadaan hanya berhak menolak PR yang telah diteruskan ke pengadaan',
            $response->json('pesan')
        );
        $this->assertSame(StatusPermintaanPengadaan::Diajukan->value, $pr->fresh()->status);
    }

    public function test_role_pengadaan_cannot_approve_pr(): void
    {
        $userPengadaan = $this->makeUser(['pengadaan']);
        $pr = $this->makePr($this->unitA->id, StatusPermintaanPengadaan::Diajukan->value);

        $response = $this->actingAs($userPengadaan)
            ->postJson("/api/v1/approval/pr/{$pr->id}", [
                'aksi' => 'setujui',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('sukses', false);

        $this->assertStringContainsString(
            'Bagian Pengadaan tidak memiliki wewenang untuk menyetujui pengajuan PR ruangan',
            $response->json('pesan')
        );
        $this->assertSame(StatusPermintaanPengadaan::Diajukan->value, $pr->fresh()->status);
    }

    public function test_unauthorized_user_cannot_access_pr_approval(): void
    {
        $userUnauthorized = $this->makeUser(['petugas_unit']);
        $pr = $this->makePr($this->unitA->id, StatusPermintaanPengadaan::Diajukan->value);

        $response = $this->actingAs($userUnauthorized)
            ->postJson("/api/v1/approval/pr/{$pr->id}", [
                'aksi' => 'tolak',
                'catatan' => 'Coba tolak tanpa wewenang',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('sukses', false)
            ->assertJsonPath('pesan', 'Anda tidak memiliki wewenang untuk memproses permohonan pengadaan ini.');

        $this->assertSame(StatusPermintaanPengadaan::Diajukan->value, $pr->fresh()->status);
    }
}
