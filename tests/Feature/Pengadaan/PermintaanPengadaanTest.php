<?php

namespace Tests\Feature\Pengadaan;

use App\Livewire\Pengadaan\PermintaanPengadaanIndex;
use App\Models\Barang;
use App\Models\PermintaanPengadaan;
use App\Models\PermintaanPengadaanDetail;
use App\Models\Unit;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PermintaanPengadaanTest extends TestCase
{
    use DatabaseTransactions;

    public function test_staff_without_matching_unit_cannot_approve_unit_pr(): void
    {
        // 1. Buat user tanpa unit_id dengan permission update_pengadaan::permintaan::pengadaan
        $permission = Permission::firstOrCreate(['name' => 'update_pengadaan::permintaan::pengadaan']);
        $permissionView = Permission::firstOrCreate(['name' => 'view_any_pengadaan::permintaan::pengadaan']);

        $unassignedUser = User::create([
            'nama' => 'Staf Tanpa Unit',
            'username' => 'staftanpaunit',
            'email' => 'staftanpaunit@test.local',
            'password' => bcrypt('password'),
            'unit_id' => null,
            'gudang_id' => null,
            'status' => 'AKTIF',
        ]);
        $unassignedUser->givePermissionTo([$permission, $permissionView]);

        $this->actingAs($unassignedUser);

        // 2. Buat unit dan PR milik unit tersebut
        $unit = Unit::first();
        $barang = Barang::first();

        $pr = PermintaanPengadaan::create([
            'kode' => CodeGenerator::generatePr(),
            'unit_id' => $unit->id,
            'diajukan_oleh' => $unassignedUser->id,
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi' => 'REGULER',
            'status' => PermintaanPengadaan::STATUS_DIAJUKAN,
        ]);

        PermintaanPengadaanDetail::create([
            'permintaan_pengadaan_id' => $pr->id,
            'barang_id' => $barang->id,
            'jumlah' => 5,
        ]);

        // 3. User tanpa unit mencoba approve PR -> HARUS DITOLAK 403
        Livewire::test(PermintaanPengadaanIndex::class)
            ->call('approve', $pr->id)
            ->assertForbidden();

        // Pastikan status PR tetap DIAJUKAN
        $pr->refresh();
        $this->assertEquals(PermintaanPengadaan::STATUS_DIAJUKAN, $pr->status);
    }
}
