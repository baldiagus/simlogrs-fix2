<?php

namespace Tests\Feature\Aset;

use App\Enums\Aset\StatusPeminjaman;
use App\Livewire\Aset\PeminjamanAsetIndex;
use App\Models\Aset\PermintaanPeminjaman;
use App\Models\Master\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class PeminjamanBatalScopeTest extends TestCase
{
    use DatabaseTransactions;

    private Unit $unitPenyedia;
    private Unit $unitPeminjam;
    private User $karu;
    private User $petugas;
    private User $petugasLain;

    protected function setUp(): void
    {
        parent::setUp();

        $units = Unit::where('status', 'AKTIF')->take(2)->get();
        $this->unitPenyedia = $units[0];
        $this->unitPeminjam = $units[1];

        $this->karu = $this->createUser('karu', 'kepala_unit');
        $this->petugas = $this->createUser('petugas', 'petugas_unit');
        $this->petugasLain = $this->createUser('petugas-lain', 'petugas_unit');
    }

    public function test_petugas_cannot_cancel_peminjaman_submitted_by_karu(): void
    {
        $pinjam = $this->createPeminjaman($this->karu);

        $this->actingAs($this->petugas)
            ->postJson("/api/v1/aset/peminjaman/{$pinjam->id}/batal")
            ->assertForbidden()
            ->assertJsonPath('pesan', 'Anda hanya dapat membatalkan pengajuan peminjaman yang Anda ajukan sendiri.');

        $this->assertSame(StatusPeminjaman::Diajukan->value, $pinjam->fresh()->status);
    }

    public function test_petugas_cannot_cancel_peminjaman_submitted_by_another_petugas(): void
    {
        $pinjam = $this->createPeminjaman($this->petugasLain);

        $this->actingAs($this->petugas)
            ->postJson("/api/v1/aset/peminjaman/{$pinjam->id}/batal")
            ->assertForbidden();

        $this->assertSame(StatusPeminjaman::Diajukan->value, $pinjam->fresh()->status);
    }

    public function test_petugas_can_cancel_own_diajukan_peminjaman(): void
    {
        $pinjam = $this->createPeminjaman($this->petugas);

        $this->actingAs($this->petugas)
            ->postJson("/api/v1/aset/peminjaman/{$pinjam->id}/batal")
            ->assertOk()
            ->assertJsonPath('sukses', true);

        $this->assertSame(StatusPeminjaman::Dibatalkan->value, $pinjam->fresh()->status);
    }

    public function test_petugas_cannot_cancel_own_peminjaman_after_approval(): void
    {
        $pinjam = $this->createPeminjaman($this->petugas, StatusPeminjaman::Disetujui->value);

        $this->actingAs($this->petugas)
            ->postJson("/api/v1/aset/peminjaman/{$pinjam->id}/batal")
            ->assertStatus(422);

        $this->assertSame(StatusPeminjaman::Disetujui->value, $pinjam->fresh()->status);
    }

    public function test_karu_cannot_cancel_staff_peminjaman(): void
    {
        $pinjam = $this->createPeminjaman($this->petugas);

        $this->actingAs($this->karu)
            ->postJson("/api/v1/aset/peminjaman/{$pinjam->id}/batal")
            ->assertForbidden()
            ->assertJsonPath('pesan', 'Kepala Ruangan tidak memiliki wewenang membatalkan pengajuan peminjaman staf unit. Gunakan aksi Tolak pada menu persetujuan.');

        $this->assertSame(StatusPeminjaman::Diajukan->value, $pinjam->fresh()->status);
    }

    public function test_karu_can_cancel_own_peminjaman(): void
    {
        $pinjam = $this->createPeminjaman($this->karu);

        $this->actingAs($this->karu)
            ->postJson("/api/v1/aset/peminjaman/{$pinjam->id}/batal")
            ->assertOk();

        $this->assertSame(StatusPeminjaman::Dibatalkan->value, $pinjam->fresh()->status);
    }

    public function test_daftar_exposes_can_cancel_per_creator(): void
    {
        $milikSendiri = $this->createPeminjaman($this->petugas);
        $milikKaru = $this->createPeminjaman($this->karu);

        $rows = collect(
            $this->actingAs($this->petugas)
                ->getJson('/api/v1/aset/peminjaman')
                ->assertOk()
                ->json('data')
        )->keyBy('id');

        $this->assertTrue($rows[$milikSendiri->id]['can_cancel']);
        $this->assertSame($this->petugas->id, $rows[$milikSendiri->id]['diajukan_oleh']);
        $this->assertFalse($rows[$milikKaru->id]['can_cancel']);
    }

    public function test_livewire_enforces_cancel_scope(): void
    {
        $milikKaru = $this->createPeminjaman($this->karu);
        $milikPetugasLain = $this->createPeminjaman($this->petugasLain);
        $milikSendiri = $this->createPeminjaman($this->petugas);

        $this->actingAs($this->petugas);
        $component = Livewire::test(PeminjamanAsetIndex::class);

        $this->assertFalse($component->instance()->canCancelPeminjaman($milikKaru));
        $this->assertFalse($component->instance()->canCancelPeminjaman($milikPetugasLain));
        $this->assertTrue($component->instance()->canCancelPeminjaman($milikSendiri));

        Livewire::test(PeminjamanAsetIndex::class)->call('batalkanPinjam', $milikKaru->id)->assertForbidden();
        Livewire::test(PeminjamanAsetIndex::class)->call('confirmBatal', $milikPetugasLain->id)->assertForbidden();
        Livewire::test(PeminjamanAsetIndex::class)->call('batalkanPinjam', $milikSendiri->id)->assertHasNoErrors();

        $this->assertSame(StatusPeminjaman::Diajukan->value, $milikKaru->fresh()->status);
        $this->assertSame(StatusPeminjaman::Diajukan->value, $milikPetugasLain->fresh()->status);
        $this->assertSame(StatusPeminjaman::Dibatalkan->value, $milikSendiri->fresh()->status);

        $milikStaf = $this->createPeminjaman($this->petugasLain);
        $milikKaruBaru = $this->createPeminjaman($this->karu);

        $this->actingAs($this->karu);
        $karuComponent = Livewire::test(PeminjamanAsetIndex::class);

        $this->assertFalse($karuComponent->instance()->canCancelPeminjaman($milikStaf));
        $this->assertTrue($karuComponent->instance()->canCancelPeminjaman($milikKaruBaru));

        Livewire::test(PeminjamanAsetIndex::class)->call('batalkanPinjam', $milikStaf->id)->assertForbidden();
        Livewire::test(PeminjamanAsetIndex::class)->call('batalkanPinjam', $milikKaruBaru->id)->assertHasNoErrors();

        $this->assertSame(StatusPeminjaman::Diajukan->value, $milikStaf->fresh()->status);
        $this->assertSame(StatusPeminjaman::Dibatalkan->value, $milikKaruBaru->fresh()->status);
    }

    private function createUser(string $prefix, string $role): User
    {
        $uniq = uniqid();

        $user = User::create([
            'nama' => "{$prefix}-{$uniq}",
            'username' => "{$prefix}-{$uniq}",
            'email' => "{$prefix}-{$uniq}@simlog.test",
            'password' => bcrypt('password'),
            'unit_id' => $this->unitPeminjam->id,
            'status' => 'AKTIF',
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function createPeminjaman(User $pengaju, string $status = 'DIAJUKAN'): PermintaanPeminjaman
    {
        return PermintaanPeminjaman::create([
            'kode' => 'PINJ-BTL-' . uniqid(),
            'tipe_penyedia' => 'RUANGAN',
            'unit_penyedia_id' => $this->unitPenyedia->id,
            'unit_id' => $this->unitPeminjam->id,
            'diajukan_oleh' => $pengaju->id,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(2)->toDateString(),
            'keperluan' => 'Uji cakupan pembatalan',
            'status' => $status,
        ]);
    }
}
