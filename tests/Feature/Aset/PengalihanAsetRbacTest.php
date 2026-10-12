<?php

namespace Tests\Feature\Aset;

use App\Livewire\Aset\PengalihanAsetIndex;
use App\Models\Aset;
use App\Models\Barang;
use App\Models\PengalihanAset;
use App\Models\Unit;
use App\Models\User;
use App\Services\Aset\PengalihanAsetService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class PengalihanAsetRbacTest extends TestCase
{
    use DatabaseTransactions;

    protected Unit $unitA;
    protected Unit $unitB;
    protected Aset $aset;
    protected User $userPolos;
    protected User $userViewer;
    protected User $userCreator;
    protected User $userApprover;

    protected function setUp(): void
    {
        parent::setUp();

        $units = Unit::where('status', 'AKTIF')->take(2)->get();
        $this->unitA = $units[0];
        $this->unitB = $units[1];

        $satuanId = \App\Models\SatuanBarang::firstOrCreate(['kode' => 'UNT-RBAC'], ['nama' => 'Unit', 'status' => 'AKTIF'])->id;
        $kategoriId = \App\Models\KategoriBarang::firstOrCreate(['kode' => 'KAT-RBAC'], ['nama' => 'Kategori RBAC', 'status' => 'AKTIF'])->id;
        $barang = Barang::firstOrCreate(
            ['kode' => 'BRG-RBAC-001'],
            ['nama' => 'USG 4D Portable', 'tipe' => 'ASET', 'satuan_id' => $satuanId, 'kategori_id' => $kategoriId, 'status' => 'AKTIF']
        );

        $this->aset = Aset::create([
            'barang_id' => $barang->id,
            'unit_id' => $this->unitA->id,
            'gudang_id' => null,
            'lokasi_gudang_id' => null,
            'kode_aset' => 'AST-RBAC-' . uniqid(),
            'nomor_seri' => 'SN-RBAC-001',
            'tanggal_perolehan' => now()->toDateString(),
            'kondisi' => 'BAIK',
            'status' => 'TERSEDIA',
        ]);

        // User tanpa wewenang apapun
        $this->userPolos = User::create([
            'nama' => 'User Polos',
            'username' => 'user_polos',
            'email' => 'polos@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $this->unitA->id,
            'status' => 'AKTIF',
        ]);

        // User hanya Lihat Data
        $this->userViewer = User::create([
            'nama' => 'User Viewer',
            'username' => 'user_viewer',
            'email' => 'viewer@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $this->unitA->id,
            'status' => 'AKTIF',
        ]);
        $this->userViewer->givePermissionTo('view_any_layanan_unit::pengalihan::aset');

        // User Tambah (Creator)
        $this->userCreator = User::create([
            'nama' => 'User Creator',
            'username' => 'user_creator',
            'email' => 'creator@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $this->unitA->id,
            'status' => 'AKTIF',
        ]);
        $this->userCreator->givePermissionTo([
            'view_any_layanan_unit::pengalihan::aset',
            'create_layanan_unit::pengalihan::aset',
        ]);

        // User Otorisator (Approver)
        $this->userApprover = User::create([
            'nama' => 'User Approver',
            'username' => 'user_approver',
            'email' => 'approver@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $this->unitA->id,
            'status' => 'AKTIF',
        ]);
        $this->userApprover->givePermissionTo([
            'view_any_layanan_unit::pengalihan::aset',
            'update_layanan_unit::pengalihan::aset',
        ]);
        $this->userApprover->assignRole('kepala_unit');
    }

    public function test_user_without_permission_is_forbidden_from_viewing_page(): void
    {
        $this->actingAs($this->userPolos);

        Livewire::test(PengalihanAsetIndex::class)
            ->assertForbidden();
    }

    public function test_user_with_view_permission_can_view_page_but_cannot_submit(): void
    {
        $this->userViewer->assignRole(\Spatie\Permission\Models\Role::create([
            'name' => 'role_only_without_transfer_permission',
            'guard_name' => 'web',
        ]));
        $this->actingAs($this->userViewer);

        Livewire::test(PengalihanAsetIndex::class)
            ->assertOk()
            ->call('openCreateModal')
            ->assertForbidden();
    }

    public function test_user_with_create_permission_can_submit_transfer(): void
    {
        $this->actingAs($this->userCreator);

        Livewire::test(PengalihanAsetIndex::class)
            ->call('openCreateModal')
            ->set('tipe_asal', 'RUANGAN')
            ->set('unit_asal_id', $this->unitA->id)
            ->set('tipe_tujuan', 'RUANGAN')
            ->set('unit_tujuan_id', $this->unitB->id)
            ->set('selected_aset_ids', [$this->aset->id])
            ->set('alasan', 'Permintaan mutasi alat oleh unit B')
            ->call('submitPengalihan')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pengalihan_aset', [
            'unit_asal_id' => $this->unitA->id,
            'unit_tujuan_id' => $this->unitB->id,
            'status' => 'DIAJUKAN',
        ]);
    }


    public function test_approver_cannot_approve_transfer_from_another_unit(): void
    {
        $otherUnitAsset = Aset::create([
            'barang_id' => $this->aset->barang_id,
            'unit_id' => $this->unitB->id,
            'gudang_id' => null,
            'lokasi_gudang_id' => null,
            'kode_aset' => 'AST-RBAC-OTHER-' . uniqid(),
            'nomor_seri' => 'SN-RBAC-OTHER-' . uniqid(),
            'tanggal_perolehan' => now()->toDateString(),
            'kondisi' => 'BAIK',
            'status' => 'TERSEDIA',
        ]);
        $transfer = app(PengalihanAsetService::class)->ajukanPengalihan([
            'tipe_asal' => 'RUANGAN',
            'unit_asal_id' => $this->unitB->id,
            'tipe_tujuan' => 'RUANGAN',
            'unit_tujuan_id' => $this->unitA->id,
            'tanggal_pengalihan' => now()->toDateString(),
            'alasan' => 'Test batas scope unit',
            'diajukan_oleh' => $this->userCreator->id,
            'aset_ids' => [$otherUnitAsset->id],
        ]);

        $this->actingAs($this->userApprover);
        Livewire::test(PengalihanAsetIndex::class)
            ->set('actionTransferId', $transfer->id)
            ->set('actionType', 'APPROVE')
            ->call('executeApproval')
            ->assertForbidden();
    }

    public function test_workflow_role_without_update_permission_cannot_approve(): void
    {
        $transfer = app(PengalihanAsetService::class)->ajukanPengalihan([
            'tipe_asal' => 'RUANGAN',
            'unit_asal_id' => $this->unitA->id,
            'tipe_tujuan' => 'RUANGAN',
            'unit_tujuan_id' => $this->unitB->id,
            'tanggal_pengalihan' => now()->toDateString(),
            'alasan' => 'Test batas permission workflow',
            'diajukan_oleh' => $this->userViewer->id,
            'aset_ids' => [$this->aset->id],
        ]);

        $this->userViewer->givePermissionTo('update_layanan_unit::pengalihan::aset');
        $this->actingAs($this->userViewer);
        Livewire::test(PengalihanAsetIndex::class)
            ->set('actionTransferId', $transfer->id)
            ->set('actionType', 'APPROVE')
            ->call('executeApproval')
            ->assertForbidden();
    }

    public function test_user_with_update_permission_can_approve_transfer(): void
    {
        $service = app(PengalihanAsetService::class);
        $transfer = $service->ajukanPengalihan([
            'tipe_asal' => 'RUANGAN',
            'unit_asal_id' => $this->unitA->id,
            'tipe_tujuan' => 'RUANGAN',
            'unit_tujuan_id' => $this->unitB->id,
            'tanggal_pengalihan' => now()->toDateString(),
            'alasan' => 'Test otorisasi mutasi',
            'diajukan_oleh' => $this->userCreator->id,
            'aset_ids' => [$this->aset->id],
        ]);

        $this->actingAs($this->userApprover);

        Livewire::test(PengalihanAsetIndex::class)
            ->set('actionTransferId', $transfer->id)
            ->set('actionType', 'APPROVE')
            ->call('executeApproval')
            ->assertHasNoErrors();

        $transfer->refresh();
        $this->assertEquals('DISETUJUI', $transfer->status);
    }
}
