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

    public function test_pr_submitted_by_kepala_gudang_or_unit_leader_is_automatically_forwarded_to_pengadaan(): void
    {
        $barang = Barang::first();
        $gudang = \App\Models\Gudang::where('status', 'AKTIF')->first();
        $unit = Unit::where('status', 'AKTIF')->first();

        // 1. Kepala Gudang mengajukan PR stok gudang
        $kepalaGudang = User::where('email', 'kepalagudang@simlogrs.local')->first();
        $this->actingAs($kepalaGudang);

        Livewire::test(PermintaanPengadaanIndex::class)
            ->set('gudang_id', $gudang->id)
            ->set('unit_id', null)
            ->set('urgensi', 'REGULER')
            ->set('items', [
                ['barang_id' => $barang->id, 'jumlah' => 10],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $latestPrGudang = PermintaanPengadaan::where('gudang_id', $gudang->id)->latest('id')->first();
        $this->assertNotNull($latestPrGudang);
        // Status HARUS LANGSUNG DITERUSKAN_KE_PENGADAAN (tidak perlu Setujui Karu lagi)
        $this->assertEquals(PermintaanPengadaan::STATUS_DITERUSKAN_KE_PENGADAAN, $latestPrGudang->status);
        $this->assertEquals($kepalaGudang->id, $latestPrGudang->disetujui_oleh);

        // 2. Kepala Unit mengajukan PR kebutuhan ruangan
        $kepalaUnit = User::where('email', 'kepalaunit@simlogrs.local')->first();
        $this->actingAs($kepalaUnit);

        Livewire::test(PermintaanPengadaanIndex::class)
            ->set('unit_id', $unit->id)
            ->set('gudang_id', null)
            ->set('urgensi', 'REGULER')
            ->set('items', [
                ['barang_id' => $barang->id, 'jumlah' => 5],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $latestPrUnit = PermintaanPengadaan::where('unit_id', $unit->id)->latest('id')->first();
        $this->assertNotNull($latestPrUnit);
        $this->assertEquals(PermintaanPengadaan::STATUS_DITERUSKAN_KE_PENGADAAN, $latestPrUnit->status);
        $this->assertEquals($kepalaUnit->id, $latestPrUnit->disetujui_oleh);
    }

    public function test_pr_submitted_by_staff_remains_in_diajukan_status(): void
    {
        $barang = Barang::first();
        $unit = Unit::where('status', 'AKTIF')->first();
        $petugasUnit = User::where('email', 'petugasunit@simlogrs.local')->first();
        $this->actingAs($petugasUnit);

        Livewire::test(PermintaanPengadaanIndex::class)
            ->set('unit_id', $unit->id)
            ->set('gudang_id', null)
            ->set('urgensi', 'REGULER')
            ->set('items', [
                ['barang_id' => $barang->id, 'jumlah' => 3],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $latestPrStaff = PermintaanPengadaan::where('unit_id', $unit->id)->latest('id')->first();
        $this->assertNotNull($latestPrStaff);
        // Staf bawahan mengajukan -> Status tetap DIAJUKAN menunggu otorisasi pimpinan
        $this->assertEquals(PermintaanPengadaan::STATUS_DIAJUKAN, $latestPrStaff->status);
        $this->assertNull($latestPrStaff->disetujui_oleh);
    }
}
