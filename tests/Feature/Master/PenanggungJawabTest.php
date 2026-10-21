<?php

namespace Tests\Feature\Master;

use App\Livewire\Master\GudangIndex;
use App\Livewire\Master\UnitIndex;
use App\Models\Master\Gudang;
use App\Models\Master\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class PenanggungJawabTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::where('email', 'superadmin@simlogrs.local')->first();
        $this->actingAs($admin);
    }

    public function test_unit_index_dropdown_only_contains_allowed_roles_on_create(): void
    {
        $test = Livewire::test(UnitIndex::class)
            ->call('create');

        $daftarPengguna = $test->viewData('daftarPengguna');
        $this->assertNotEmpty($daftarPengguna);

        $allowedUserIds = User::where('status', 'AKTIF')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['kepala_unit', 'kepala_teknisi']))
            ->pluck('id')
            ->toArray();

        foreach ($daftarPengguna as $p) {
            $this->assertContains($p->id, $allowedUserIds);
        }

        // Pastikan petugas_gudang tidak masuk dalam dropdown
        $petugasGudang = User::whereHas('roles', fn ($q) => $q->where('name', 'petugas_gudang'))->first();
        if ($petugasGudang) {
            $this->assertFalse($daftarPengguna->contains('id', $petugasGudang->id));
        }
    }

    public function test_unit_index_validation_rejects_unauthorized_penanggung_jawab(): void
    {
        $petugasGudang = User::whereHas('roles', fn ($q) => $q->where('name', 'petugas_gudang'))->first();

        Livewire::test(UnitIndex::class)
            ->call('create')
            ->set('kode', 'UNIT-TEST-FAIL')
            ->set('nama', 'Unit Uji Coba Gagal')
            ->set('jenis', 'PELAYANAN_MEDIS')
            ->set('status', 'AKTIF')
            ->set('kepala_unit_id', $petugasGudang->id)
            ->call('save')
            ->assertHasErrors(['kepala_unit_id']);
    }

    public function test_unit_index_validation_accepts_valid_kepala_unit(): void
    {
        $kepalaUnit = User::whereHas('roles', fn ($q) => $q->where('name', 'kepala_unit'))->first();

        Livewire::test(UnitIndex::class)
            ->call('create')
            ->set('kode', 'UNIT-TEST-OK')
            ->set('nama', 'Unit Uji Coba Berhasil')
            ->set('jenis', 'PELAYANAN_MEDIS')
            ->set('status', 'AKTIF')
            ->set('kepala_unit_id', $kepalaUnit->id)
            ->call('save')
            ->assertHasNoErrors(['kepala_unit_id']);

        $this->assertDatabaseHas('unit', [
            'kode' => 'UNIT-TEST-OK',
            'kepala_unit_id' => $kepalaUnit->id,
        ]);
    }

    public function test_gudang_index_dropdown_only_contains_kepala_gudang_on_create(): void
    {
        $test = Livewire::test(GudangIndex::class)
            ->call('create');

        $daftarPengguna = $test->viewData('daftarPengguna');
        $this->assertNotEmpty($daftarPengguna);

        $allowedUserIds = User::where('status', 'AKTIF')
            ->whereHas('roles', fn ($q) => $q->where('name', 'kepala_gudang'))
            ->pluck('id')
            ->toArray();

        foreach ($daftarPengguna as $p) {
            $this->assertContains($p->id, $allowedUserIds);
        }

        // Pastikan teknisi atau petugas_unit tidak masuk dalam dropdown
        $petugasUnit = User::whereHas('roles', fn ($q) => $q->where('name', 'petugas_unit'))->first();
        if ($petugasUnit) {
            $this->assertFalse($daftarPengguna->contains('id', $petugasUnit->id));
        }
    }

    public function test_gudang_index_validation_rejects_unauthorized_penanggung_jawab(): void
    {
        $petugasUnit = User::whereHas('roles', fn ($q) => $q->where('name', 'petugas_unit'))->first();

        Livewire::test(GudangIndex::class)
            ->call('create')
            ->set('kode', 'GUD-TEST-FAIL')
            ->set('nama', 'Gudang Uji Coba Gagal')
            ->set('jenis', 'SENTRAL')
            ->set('status', 'AKTIF')
            ->set('kepala_gudang_id', $petugasUnit->id)
            ->call('save')
            ->assertHasErrors(['kepala_gudang_id']);
    }

    public function test_gudang_index_validation_accepts_valid_kepala_gudang(): void
    {
        $kepalaGudang = User::whereHas('roles', fn ($q) => $q->where('name', 'kepala_gudang'))->first();

        Livewire::test(GudangIndex::class)
            ->call('create')
            ->set('kode', 'GUD-TEST-OK')
            ->set('nama', 'Gudang Uji Coba Berhasil')
            ->set('jenis', 'SENTRAL')
            ->set('status', 'AKTIF')
            ->set('kepala_gudang_id', $kepalaGudang->id)
            ->call('save')
            ->assertHasNoErrors(['kepala_gudang_id']);

        $this->assertDatabaseHas('gudang', [
            'kode' => 'GUD-TEST-OK',
            'kepala_gudang_id' => $kepalaGudang->id,
        ]);
    }

    public function test_unit_index_allows_assigned_unit_user_on_edit(): void
    {
        $unit = Unit::where('status', 'AKTIF')->first();
        $petugasUnit = User::where('unit_id', $unit->id)->first();
        $this->assertNotNull($petugasUnit);

        $test = Livewire::test(UnitIndex::class)
            ->call('edit', $unit->id);

        $daftarPengguna = $test->viewData('daftarPengguna');
        $this->assertTrue($daftarPengguna->contains('id', $petugasUnit->id));

        $test->set('kepala_unit_id', $petugasUnit->id)
            ->call('save')
            ->assertHasNoErrors(['kepala_unit_id']);

        $this->assertEquals($petugasUnit->id, $unit->fresh()->kepala_unit_id);
    }

    public function test_gudang_index_allows_assigned_gudang_user_on_edit(): void
    {
        $gudang = Gudang::where('status', 'AKTIF')->first();
        $petugasGudang = User::where('gudang_id', $gudang->id)->first();
        $this->assertNotNull($petugasGudang);

        $test = Livewire::test(GudangIndex::class)
            ->call('edit', $gudang->id);

        $daftarPengguna = $test->viewData('daftarPengguna');
        $this->assertTrue($daftarPengguna->contains('id', $petugasGudang->id));

        $test->set('kepala_gudang_id', $petugasGudang->id)
            ->call('save')
            ->assertHasNoErrors(['kepala_gudang_id']);

        $this->assertEquals($petugasGudang->id, $gudang->fresh()->kepala_gudang_id);
    }
}
