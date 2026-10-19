<?php

namespace Tests\Feature\Distribusi;

use App\Enums\Distribusi\StatusPermintaanDistribusi;
use App\Models\Distribusi\PermintaanDistribusi;
use App\Models\Distribusi\PermintaanDistribusiDetail;
use App\Models\Master\Barang;
use App\Models\Master\Gudang;
use App\Models\Master\Unit;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DistribusiBatalScopeTest extends TestCase
{
    use DatabaseTransactions;

    protected Unit $unit;
    protected Gudang $gudang;
    protected Barang $barang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = Unit::where('status', 'AKTIF')->firstOrFail();
        $this->gudang = Gudang::where('status', 'AKTIF')->firstOrFail();
        $this->barang = Barang::where('status', 'AKTIF')->firstOrFail();
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    private function makeUser(string $role, ?int $unitId = null): User
    {
        $suffix = uniqid();
        $user = User::create([
            'nama'      => 'Uji Batal ' . $suffix,
            'username'  => 'uji_btl_' . $suffix,
            'email'     => "uji_btl_{$suffix}@simlog.test",
            'password'  => bcrypt('password'),
            'unit_id'   => $unitId,
            'status'    => 'AKTIF',
        ]);

        $user->assignRole($role);

        return $user->fresh();
    }

    private function createAmprah(int $unitId, int $userId, string $status = 'DIAJUKAN'): PermintaanDistribusi
    {
        $permintaan = PermintaanDistribusi::create([
            'kode'               => CodeGenerator::generate('REQ', PermintaanDistribusi::class, 'kode', 'Ym', 3),
            'unit_id'            => $unitId,
            'gudang_id'          => $this->gudang->id,
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi'            => 'REGULER',
            'status'             => $status,
            'diajukan_oleh'      => $userId,
        ]);

        PermintaanDistribusiDetail::create([
            'permintaan_distribusi_id' => $permintaan->id,
            'barang_id'                => $this->barang->id,
            'jumlah'                   => 2,
            'jumlah_disetujui'         => 2,
        ]);

        return $permintaan->fresh(['detail']);
    }

    public function test_karu_cannot_cancel_amprah_submitted_by_petugas_unit(): void
    {
        $petugas = $this->makeUser('petugas_unit', $this->unit->id);
        $karu = $this->makeUser('kepala_unit', $this->unit->id);

        $amprah = $this->createAmprah($this->unit->id, $petugas->id, StatusPermintaanDistribusi::Diajukan->value);

        $listRes = $this->actingAs($karu)->getJson('/api/v1/distribusi/amprah');
        $listRes->assertOk();
        $item = collect($listRes->json('data'))->firstWhere('id', $amprah->id);
        $this->assertNotNull($item);
        $this->assertFalse($item['can_cancel']);
        $this->assertSame((int) $petugas->id, $item['diajukan_oleh']);

        $detailRes = $this->actingAs($karu)->getJson("/api/v1/distribusi/amprah/{$amprah->id}");
        $detailRes->assertOk();
        $this->assertFalse($detailRes->json('data.can_cancel'));
        $this->assertSame((int) $petugas->id, $detailRes->json('data.diajukan_oleh'));

        $cancelRes = $this->actingAs($karu)->postJson("/api/v1/distribusi/amprah/{$amprah->id}/batal");
        $cancelRes->assertStatus(403);
        $cancelRes->assertJsonFragment([
            'pesan' => 'Kepala Ruangan tidak memiliki wewenang membatalkan permohonan staf unit. Gunakan aksi Tolak pada menu persetujuan.',
        ]);

        $this->assertSame(StatusPermintaanDistribusi::Diajukan->value, $amprah->fresh()->status);
    }

    public function test_petugas_unit_can_cancel_own_amprah(): void
    {
        $petugas = $this->makeUser('petugas_unit', $this->unit->id);
        $amprah = $this->createAmprah($this->unit->id, $petugas->id, StatusPermintaanDistribusi::Diajukan->value);

        $listRes = $this->actingAs($petugas)->getJson('/api/v1/distribusi/amprah');
        $listRes->assertOk();
        $item = collect($listRes->json('data'))->firstWhere('id', $amprah->id);
        $this->assertNotNull($item);
        $this->assertTrue($item['can_cancel']);
        $this->assertSame((int) $petugas->id, $item['diajukan_oleh']);

        $detailRes = $this->actingAs($petugas)->getJson("/api/v1/distribusi/amprah/{$amprah->id}");
        $detailRes->assertOk();
        $this->assertTrue($detailRes->json('data.can_cancel'));
        $this->assertSame((int) $petugas->id, $detailRes->json('data.diajukan_oleh'));

        $cancelRes = $this->actingAs($petugas)->postJson("/api/v1/distribusi/amprah/{$amprah->id}/batal");
        $cancelRes->assertOk();

        $this->assertSame(StatusPermintaanDistribusi::Ditolak->value, $amprah->fresh()->status);
    }

    public function test_karu_can_cancel_own_submitted_amprah(): void
    {
        $karu = $this->makeUser('kepala_unit', $this->unit->id);
        $amprah = $this->createAmprah($this->unit->id, $karu->id, StatusPermintaanDistribusi::Diajukan->value);

        $listRes = $this->actingAs($karu)->getJson('/api/v1/distribusi/amprah');
        $listRes->assertOk();
        $item = collect($listRes->json('data'))->firstWhere('id', $amprah->id);
        $this->assertNotNull($item);
        $this->assertTrue($item['can_cancel']);
        $this->assertSame((int) $karu->id, $item['diajukan_oleh']);

        $detailRes = $this->actingAs($karu)->getJson("/api/v1/distribusi/amprah/{$amprah->id}");
        $detailRes->assertOk();
        $this->assertTrue($detailRes->json('data.can_cancel'));
        $this->assertSame((int) $karu->id, $detailRes->json('data.diajukan_oleh'));

        $cancelRes = $this->actingAs($karu)->postJson("/api/v1/distribusi/amprah/{$amprah->id}/batal");
        $cancelRes->assertOk();

        $this->assertSame(StatusPermintaanDistribusi::Ditolak->value, $amprah->fresh()->status);
    }

    public function test_petugas_unit_cannot_cancel_amprah_submitted_by_karu(): void
    {
        $petugas = $this->makeUser('petugas_unit', $this->unit->id);
        $karu = $this->makeUser('kepala_unit', $this->unit->id);
        $amprah = $this->createAmprah($this->unit->id, $karu->id, StatusPermintaanDistribusi::Diajukan->value);

        $this->assertCannotCancel($petugas, $amprah, 'Anda hanya dapat membatalkan permohonan amprah yang Anda ajukan sendiri.');
    }

    public function test_petugas_unit_cannot_cancel_own_amprah_after_disetujui_karu(): void
    {
        $petugas = $this->makeUser('petugas_unit', $this->unit->id);
        $amprah = $this->createAmprah($this->unit->id, $petugas->id, StatusPermintaanDistribusi::DisetujuiKaru->value);

        $this->assertCannotCancel($petugas, $amprah, 'Permintaan amprah ini telah disetujui oleh Kepala Ruangan dan tidak dapat dibatalkan oleh staf.');
    }

    public function test_petugas_unit_cannot_cancel_amprah_submitted_by_other_petugas(): void
    {
        $petugas = $this->makeUser('petugas_unit', $this->unit->id);
        $lain = $this->makeUser('petugas_unit', $this->unit->id);
        $amprah = $this->createAmprah($this->unit->id, $lain->id, StatusPermintaanDistribusi::Diajukan->value);

        $this->assertCannotCancel($petugas, $amprah, 'Anda hanya dapat membatalkan permohonan amprah yang Anda ajukan sendiri.');
    }

    private function assertCannotCancel(User $user, PermintaanDistribusi $amprah, string $pesan): void
    {
        $statusAwal = $amprah->status;

        $detailRes = $this->actingAs($user)->getJson("/api/v1/distribusi/amprah/{$amprah->id}");
        $detailRes->assertOk();
        $this->assertFalse($detailRes->json('data.can_cancel'));

        $cancelRes = $this->actingAs($user)->postJson("/api/v1/distribusi/amprah/{$amprah->id}/batal");
        $cancelRes->assertStatus(403);
        $cancelRes->assertJsonFragment(['pesan' => $pesan]);

        $this->assertSame($statusAwal, $amprah->fresh()->status);
    }
}
