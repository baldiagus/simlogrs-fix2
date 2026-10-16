<?php

namespace Tests\Feature\Pengadaan;

use App\Enums\Pengadaan\StatusPermintaanPengadaan;
use App\Models\Master\Barang;
use App\Models\Master\Unit;
use App\Models\Pengadaan\PermintaanPengadaan;
use App\Models\Pengadaan\PermintaanPengadaanDetail;
use App\Models\User;
use App\Services\Pengadaan\PengajuanPrService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * POST /api/v1/pengadaan/pr harus mengikuti aturan web PermintaanPengadaanIndex::save
 * (scope pemohon, auto-otorisasi kepala, status awal ditentukan server).
 */
class PengadaanPrApiTest extends TestCase
{
    use DatabaseTransactions;

    private const CREATE = 'create_pengadaan::permintaan::pengadaan';
    private const UPDATE = 'update_pengadaan::permintaan::pengadaan';

    protected Unit $unitSendiri;
    protected Unit $unitLain;
    protected Barang $barang;

    protected function setUp(): void
    {
        parent::setUp();

        $units = Unit::where('status', 'AKTIF')->orderBy('id')->take(2)->get();
        if ($units->count() < 2) {
            $this->markTestSkipped('Butuh minimal dua unit AKTIF pada database dev.');
        }
        [$this->unitSendiri, $this->unitLain] = [$units[0], $units[1]];
        $this->barang = Barang::where('status', 'AKTIF')->firstOrFail();
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    private function makeUser(array $permissions = [], array $roles = [], ?int $unitId = null, ?int $gudangId = null): User
    {
        $suffix = uniqid();
        $user = User::create([
            'nama' => 'Uji PR API ' . $suffix,
            'username' => 'uji_pr_api_' . $suffix,
            'email' => "uji_pr_api_{$suffix}@simlog.test",
            'password' => bcrypt('password'),
            'unit_id' => $unitId,
            'gudang_id' => $gudangId,
            'status' => 'AKTIF',
        ]);

        foreach ($permissions as $name) {
            $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
        if ($roles) {
            $user->assignRole($roles);
        }

        return $user->fresh();
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'urgensi' => 'REGULER',
            'catatan' => 'Uji API PR',
            'items' => [
                ['barang_id' => $this->barang->id, 'jumlah' => 7, 'catatan' => 'baris uji'],
            ],
        ], $extra);
    }

    public function test_non_global_user_cannot_submit_for_other_unit(): void
    {
        $user = $this->makeUser([self::CREATE], [], $this->unitSendiri->id);
        $jumlahSebelum = PermintaanPengadaan::count();

        $this->actingAs($user)
            ->postJson('/api/v1/pengadaan/pr', $this->payload(['unit_id' => $this->unitLain->id]))
            ->assertStatus(403)
            ->assertJsonPath('sukses', false)
            ->assertJsonPath('pesan', PengajuanPrService::PESAN_DI_LUAR_SCOPE);

        $this->assertSame($jumlahSebelum, PermintaanPengadaan::count());
        $this->assertDatabaseMissing('permintaan_pengadaan', [
            'unit_id' => $this->unitLain->id,
            'diajukan_oleh' => $user->id,
        ]);
    }

    public function test_non_leader_own_unit_is_diajukan(): void
    {
        $user = $this->makeUser([self::CREATE], [], $this->unitSendiri->id);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/pengadaan/pr', $this->payload(['unit_id' => $this->unitSendiri->id]))
            ->assertStatus(201)
            ->assertJsonPath('data.status', StatusPermintaanPengadaan::Diajukan->value)
            ->assertJsonPath('data.urgensi', 'REGULER');

        $this->assertNotEmpty($response->json('data.kode'));
        $pr = PermintaanPengadaan::findOrFail($response->json('data.id'));
        $this->assertSame((int) $this->unitSendiri->id, (int) $pr->unit_id);
        $this->assertNull($pr->gudang_id);
        $this->assertNull($pr->disetujui_oleh);

        $detail = PermintaanPengadaanDetail::where('permintaan_pengadaan_id', $pr->id)->sole();
        $this->assertNull($detail->jumlah_disetujui);
        $this->assertSame('baris uji', $detail->catatan);
    }

    public function test_non_leader_defaults_to_own_unit_when_unit_omitted(): void
    {
        $user = $this->makeUser([self::CREATE], [], $this->unitSendiri->id);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/pengadaan/pr', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('data.status', StatusPermintaanPengadaan::Diajukan->value);

        $this->assertSame((int) $this->unitSendiri->id, (int) PermintaanPengadaan::findOrFail($response->json('data.id'))->unit_id);
    }

    public function test_kepala_unit_with_update_permission_is_forwarded_to_pengadaan(): void
    {
        $user = $this->makeUser([self::CREATE, self::UPDATE], ['kepala_unit'], $this->unitSendiri->id);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/pengadaan/pr', $this->payload(['unit_id' => $this->unitSendiri->id]))
            ->assertStatus(201)
            ->assertJsonPath('data.status', StatusPermintaanPengadaan::DiteruskanKePengadaan->value);

        $pr = PermintaanPengadaan::findOrFail($response->json('data.id'));
        $this->assertSame((int) $user->id, (int) $pr->disetujui_oleh);

        $detail = PermintaanPengadaanDetail::where('permintaan_pengadaan_id', $pr->id)->sole();
        $this->assertSame((int) $detail->jumlah, (int) $detail->jumlah_disetujui);
        $this->assertSame(7, (int) $detail->jumlah_disetujui);
    }

    public function test_client_sent_status_and_auto_approve_are_ignored(): void
    {
        $user = $this->makeUser([self::CREATE], [], $this->unitSendiri->id);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/pengadaan/pr', $this->payload([
                'unit_id' => $this->unitSendiri->id,
                'status' => 'DISETUJUI',
                'auto_approve' => true,
                'disetujui_oleh' => $user->id,
            ]))
            ->assertStatus(201)
            ->assertJsonPath('data.status', StatusPermintaanPengadaan::Diajukan->value);

        $pr = PermintaanPengadaan::findOrFail($response->json('data.id'));
        $this->assertSame(StatusPermintaanPengadaan::Diajukan->value, $pr->status);
        $this->assertNull($pr->disetujui_oleh);
    }

    public function test_admin_logistik_can_submit_for_any_unit(): void
    {
        $user = $this->makeUser([self::CREATE], ['admin_logistik'], $this->unitSendiri->id);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/pengadaan/pr', $this->payload(['unit_id' => $this->unitLain->id]))
            ->assertStatus(201);

        $this->assertSame((int) $this->unitLain->id, (int) PermintaanPengadaan::findOrFail($response->json('data.id'))->unit_id);
    }

    public function test_global_role_without_create_permission_is_forbidden_like_web(): void
    {
        Role::findByName('admin_logistik', 'web')->revokePermissionTo(self::CREATE);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $this->makeUser([], ['admin_logistik'], $this->unitSendiri->id);
        $jumlahSebelum = PermintaanPengadaan::count();

        $this->actingAs($user)
            ->postJson('/api/v1/pengadaan/pr', $this->payload(['unit_id' => $this->unitSendiri->id]))
            ->assertStatus(403);

        $this->assertSame($jumlahSebelum, PermintaanPengadaan::count());
    }
}
