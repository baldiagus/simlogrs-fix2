<?php

namespace Tests\Feature\Distribusi;

use App\Enums\Distribusi\StatusPermintaanDistribusi;
use App\Models\Distribusi\PengirimanBarang;
use App\Models\Distribusi\PermintaanDistribusi;
use App\Models\Distribusi\PermintaanDistribusiDetail;
use App\Models\Master\Barang;
use App\Models\Master\Gudang;
use App\Models\Master\LokasiGudang;
use App\Models\Master\Unit;
use App\Models\Persediaan\StokBatch;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DistribusiAmprahWebParityTest extends TestCase
{
    use DatabaseTransactions;

    private const PERM_AMPRAH_CREATE = 'create_layanan_unit::permintaan::barang';
    private const PERM_AMPRAH_UPDATE = 'update_layanan_unit::permintaan::barang';
    private const PERM_AMPRAH_VIEW   = 'view_any_layanan_unit::permintaan::barang';
    private const PERM_MEJA2_UPDATE  = 'update_gudang_stok::distribusi::approval';

    protected Gudang $gudang1;
    protected Gudang $gudang2;
    protected Unit $unitA;
    protected Unit $unitB;
    protected Barang $barang;

    protected function setUp(): void
    {
        parent::setUp();

        $gudangs = Gudang::where('status', 'AKTIF')->orderBy('id')->take(2)->get();
        if ($gudangs->count() < 2) {
            $this->markTestSkipped('Dibutuhkan minimal 2 gudang aktif.');
        }
        $units = Unit::where('status', 'AKTIF')->orderBy('id')->take(2)->get();
        if ($units->count() < 2) {
            $this->markTestSkipped('Dibutuhkan minimal 2 unit aktif.');
        }

        [$this->gudang1, $this->gudang2] = [$gudangs[0], $gudangs[1]];
        [$this->unitA, $this->unitB] = [$units[0], $units[1]];
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
            'nama'      => 'Uji Parity ' . $suffix,
            'username'  => 'uji_par_' . $suffix,
            'email'     => "uji_par_{$suffix}@simlog.test",
            'password'  => bcrypt('password'),
            'unit_id'   => $unitId,
            'gudang_id' => $gudangId,
            'status'    => 'AKTIF',
        ]);

        foreach ($permissions as $name) {
            $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
        if ($roles) {
            $user->assignRole($roles);
        }

        return $user->fresh();
    }

    private function createPermintaan(string $status, ?int $gudangId = null, ?int $unitId = null, ?int $userId = null, int $jumlah = 5, ?int $disetujui = 5): PermintaanDistribusi
    {
        $userId = $userId ?: $this->makeUser([], [], $unitId ?: $this->unitA->id)->id;
        $permintaan = PermintaanDistribusi::create([
            'kode'               => CodeGenerator::generate('REQ', PermintaanDistribusi::class, 'kode', 'Ym', 3),
            'unit_id'            => $unitId ?: $this->unitA->id,
            'gudang_id'          => $gudangId ?: $this->gudang1->id,
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi'            => 'REGULER',
            'status'             => $status,
            'diajukan_oleh'      => $userId,
            'disetujui_oleh'     => $userId,
        ]);

        PermintaanDistribusiDetail::create([
            'permintaan_distribusi_id' => $permintaan->id,
            'barang_id'                => $this->barang->id,
            'jumlah'                   => $jumlah,
            'jumlah_disetujui'         => $disetujui,
        ]);

        return $permintaan->fresh(['detail']);
    }

    private function addStok(int $gudangId, int $qty = 10): StokBatch
    {
        $lokasi = LokasiGudang::firstOrCreate(
            ['kode' => 'RAK-PARITY-' . $gudangId, 'gudang_id' => $gudangId],
            ['nama' => 'Rak Parity ' . $gudangId, 'status' => 'AKTIF']
        );

        return StokBatch::create([
            'barang_id'           => $this->barang->id,
            'gudang_id'           => $gudangId,
            'lokasi_gudang_id'    => $lokasi->id,
            'nomor_batch'         => 'BATCH-PAR-' . uniqid(),
            'tanggal_kedaluwarsa' => now()->addYear()->toDateString(),
            'jumlah'              => $qty,
            'harga_satuan'        => 10000,
            'status'              => 'AKTIF',
        ]);
    }

    public function test_meja_2_quota_above_requested_returns_422_and_unchanged(): void
    {
        $admin = $this->makeUser([self::PERM_MEJA2_UPDATE], ['admin_logistik']);
        $req = $this->createPermintaan('DISETUJUI_KARU', $this->gudang1->id, $this->unitA->id, null, 5, 5);
        $detail = $req->detail->first();

        $response = $this->actingAs($admin)->postJson("/api/v1/approval/amprah/{$req->id}", [
            'aksi'  => 'setujui',
            'items' => [['id' => $detail->id, 'jumlah_disetujui' => 6]],
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'pesan' => 'Jumlah disetujui (6) tidak boleh melebihi jumlah yang diminta (5).',
        ]);

        $req->refresh();
        $this->assertSame('DISETUJUI_KARU', $req->status);
        $this->assertSame(5, (int) $detail->fresh()->jumlah_disetujui);
    }

    public function test_meja_2_quota_within_requested_returns_200_and_saved(): void
    {
        $admin = $this->makeUser([self::PERM_MEJA2_UPDATE], ['admin_logistik']);
        $req = $this->createPermintaan('DISETUJUI_KARU', $this->gudang1->id, $this->unitA->id, null, 5, 5);
        $detail = $req->detail->first();

        $response = $this->actingAs($admin)->postJson("/api/v1/approval/amprah/{$req->id}", [
            'aksi'  => 'setujui',
            'items' => [['id' => $detail->id, 'jumlah_disetujui' => 4]],
        ]);

        $response->assertOk();
        $req->refresh();
        $this->assertSame('DISETUJUI_GUDANG', $req->status);
        $this->assertSame(4, (int) $detail->fresh()->jumlah_disetujui);
    }

    public function test_batal_on_diajukan_returns_200_status_ditolak(): void
    {
        $requester = $this->makeUser([self::PERM_AMPRAH_CREATE], ['petugas_unit'], $this->unitA->id);
        $req = $this->createPermintaan('DIAJUKAN', $this->gudang1->id, $this->unitA->id, $requester->id);

        $response = $this->actingAs($requester)->postJson("/api/v1/distribusi/amprah/{$req->id}/batal");
        $response->assertOk();

        $req->refresh();
        $this->assertSame(StatusPermintaanDistribusi::Ditolak->value, $req->status);
        $this->assertStringContainsString('[Dibatalkan oleh pemohon]', (string) $req->catatan);
    }

    public function test_petugas_unit_cannot_batal_after_karu_approval_returns_403(): void
    {
        // Aturan sengaja: staf tidak boleh membatalkan setelah disetujui Kepala Ruangan (cancelDenial).
        $requester = $this->makeUser([self::PERM_AMPRAH_CREATE], ['petugas_unit'], $this->unitA->id);
        $req = $this->createPermintaan('DISETUJUI_KARU', $this->gudang1->id, $this->unitA->id, $requester->id);

        $this->actingAs($requester)->postJson("/api/v1/distribusi/amprah/{$req->id}/batal")->assertStatus(403);

        $this->assertSame('DISETUJUI_KARU', $req->fresh()->status);
    }

    public function test_batal_after_an_sppb_exists_returns_422(): void
    {
        $requester = $this->makeUser([self::PERM_AMPRAH_CREATE], ['petugas_unit'], $this->unitA->id);
        $req = $this->createPermintaan('DIAJUKAN', $this->gudang1->id, $this->unitA->id, $requester->id);

        PengirimanBarang::create([
            'kode'                     => CodeGenerator::generate('SPPB', PengirimanBarang::class, 'kode', 'Ymd', 4),
            'permintaan_distribusi_id' => $req->id,
            'gudang_id'                => $this->gudang1->id,
            'unit_id'                  => $this->unitA->id,
            'tanggal_kirim'            => now()->toDateString(),
            'status'                   => 'TERKIRIM',
            'dikirim_oleh'             => $requester->id,
        ]);

        $response = $this->actingAs($requester)->postJson("/api/v1/distribusi/amprah/{$req->id}/batal");
        $response->assertStatus(422);

        $this->assertSame('DIAJUKAN', $req->fresh()->status);
    }

    public function test_tutup_sisa_on_dikirim_sebagian_by_requester_unit_returns_200_selesai(): void
    {
        $requester = $this->makeUser([self::PERM_AMPRAH_UPDATE], ['petugas_unit'], $this->unitA->id);
        $req = $this->createPermintaan(StatusPermintaanDistribusi::DikirimSebagian->value, $this->gudang1->id, $this->unitA->id, $requester->id);

        $response = $this->actingAs($requester)->postJson("/api/v1/distribusi/amprah/{$req->id}/tutup-sisa");
        $response->assertOk();
        $response->assertJsonPath('data.status', 'SELESAI');
        $response->assertJsonPath('data.kode', $req->kode);

        $req->refresh();
        $this->assertSame(StatusPermintaanDistribusi::Selesai->value, $req->status);
        $this->assertStringContainsString('Sisa hutang ditutup oleh pemohon', (string) $req->catatan);
    }

    public function test_tutup_sisa_wrong_status_returns_422(): void
    {
        $requester = $this->makeUser([self::PERM_AMPRAH_UPDATE], ['petugas_unit'], $this->unitA->id);
        $req = $this->createPermintaan(StatusPermintaanDistribusi::Diajukan->value, $this->gudang1->id, $this->unitA->id, $requester->id);

        $response = $this->actingAs($requester)->postJson("/api/v1/distribusi/amprah/{$req->id}/tutup-sisa");
        $response->assertStatus(422);
        $response->assertJsonFragment([
            'pesan' => 'Hanya permintaan dengan status DIKIRIM_SEBAGIAN yang dapat ditutup sisa hutangnya.',
        ]);

        $req->refresh();
        $this->assertSame(StatusPermintaanDistribusi::Diajukan->value, $req->status);
    }

    public function test_tutup_sisa_by_other_unit_returns_403(): void
    {
        $requesterB = $this->makeUser([self::PERM_AMPRAH_UPDATE], ['petugas_unit'], $this->unitB->id);
        $req = $this->createPermintaan(StatusPermintaanDistribusi::DikirimSebagian->value, $this->gudang1->id, $this->unitA->id);

        $response = $this->actingAs($requesterB)->postJson("/api/v1/distribusi/amprah/{$req->id}/tutup-sisa");
        $response->assertStatus(403);

        $req->refresh();
        $this->assertSame(StatusPermintaanDistribusi::DikirimSebagian->value, $req->status);
    }

    public function test_response_includes_stok_tersedia_and_can_tutup_sisa(): void
    {
        $this->addStok($this->gudang1->id, 20);
        $requester = $this->makeUser([self::PERM_AMPRAH_VIEW, self::PERM_AMPRAH_UPDATE], ['petugas_unit'], $this->unitA->id);
        $req = $this->createPermintaan(StatusPermintaanDistribusi::DikirimSebagian->value, $this->gudang1->id, $this->unitA->id, $requester->id);

        $detailRes = $this->actingAs($requester)->getJson("/api/v1/distribusi/amprah/{$req->id}");
        $detailRes->assertOk();
        $detailRes->assertJsonPath('data.can_tutup_sisa', true);
        $detailRes->assertJsonPath('data.can_cancel', false);
        $this->assertIsInt($detailRes->json('data.items.0.stok_tersedia'));
        $this->assertGreaterThanOrEqual(0, $detailRes->json('data.items.0.stok_tersedia'));

        $listRes = $this->actingAs($requester)->getJson('/api/v1/distribusi/amprah');
        $listRes->assertOk();
        $item = collect($listRes->json('data'))->firstWhere('id', $req->id);
        $this->assertNotNull($item);
        $this->assertTrue($item['can_tutup_sisa']);
        $this->assertFalse($item['can_cancel']);
        $this->assertIsInt($item['items'][0]['stok_tersedia']);
    }

    public function test_kepala_gudang_of_gudang_1_does_not_see_amprah_of_gudang_2_in_list_and_gets_403_on_detail(): void
    {
        $reqGudang1 = $this->createPermintaan('DIAJUKAN', $this->gudang1->id, $this->unitA->id);
        $reqGudang2 = $this->createPermintaan('DIAJUKAN', $this->gudang2->id, $this->unitB->id);

        $kepalaGudang1 = $this->makeUser(
            ['view_any_gudang_stok::distribusi::approval'],
            ['kepala_gudang'],
            null,
            $this->gudang1->id
        );

        $listRes = $this->actingAs($kepalaGudang1)->getJson('/api/v1/distribusi/amprah');
        $listRes->assertOk();
        $ids = collect($listRes->json('data'))->pluck('id')->all();
        $this->assertContains($reqGudang1->id, $ids);
        $this->assertNotContains($reqGudang2->id, $ids);

        $this->actingAs($kepalaGudang1)->getJson("/api/v1/distribusi/amprah/{$reqGudang1->id}")->assertOk();
        $this->actingAs($kepalaGudang1)->getJson("/api/v1/distribusi/amprah/{$reqGudang2->id}")->assertStatus(403);
    }

    public function test_petugas_unit_still_sees_own_unit(): void
    {
        $reqUnitA = $this->createPermintaan('DIAJUKAN', $this->gudang1->id, $this->unitA->id);
        $reqUnitB = $this->createPermintaan('DIAJUKAN', $this->gudang1->id, $this->unitB->id);

        $petugasA = $this->makeUser(
            [self::PERM_AMPRAH_VIEW],
            ['petugas_unit'],
            $this->unitA->id
        );

        $listRes = $this->actingAs($petugasA)->getJson('/api/v1/distribusi/amprah');
        $listRes->assertOk();
        $ids = collect($listRes->json('data'))->pluck('id')->all();
        $this->assertContains($reqUnitA->id, $ids);
        $this->assertNotContains($reqUnitB->id, $ids);

        $this->actingAs($petugasA)->getJson("/api/v1/distribusi/amprah/{$reqUnitA->id}")->assertOk();
        $this->actingAs($petugasA)->getJson("/api/v1/distribusi/amprah/{$reqUnitB->id}")->assertStatus(403);
    }

    public function test_super_admin_sees_all(): void
    {
        $req1 = $this->createPermintaan('DIAJUKAN', $this->gudang1->id, $this->unitA->id);
        $req2 = $this->createPermintaan('DIAJUKAN', $this->gudang2->id, $this->unitB->id);

        $admin = $this->makeUser([], ['super_admin']);

        $listRes = $this->actingAs($admin)->getJson('/api/v1/distribusi/amprah');
        $listRes->assertOk();
        $ids = collect($listRes->json('data'))->pluck('id')->all();
        $this->assertContains($req1->id, $ids);
        $this->assertContains($req2->id, $ids);

        $this->actingAs($admin)->getJson("/api/v1/distribusi/amprah/{$req1->id}")->assertOk();
        $this->actingAs($admin)->getJson("/api/v1/distribusi/amprah/{$req2->id}")->assertOk();
    }
}
