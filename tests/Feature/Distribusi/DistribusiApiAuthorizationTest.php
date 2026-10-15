<?php

namespace Tests\Feature\Distribusi;

use App\Models\Master\Barang;
use App\Support\Generator\CodeGenerator;
use App\Models\Master\Gudang;
use App\Models\Master\KategoriBarang;
use App\Models\Master\SatuanBarang;
use App\Models\Distribusi\PengirimanBarang;
use App\Models\Master\Unit;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DistribusiApiAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    private Unit $unit;
    private Gudang $gudang;
    private Barang $barang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = Unit::where('status', 'AKTIF')->firstOrFail();
        $this->gudang = Gudang::where('status', 'AKTIF')->firstOrFail();
        $kategori = KategoriBarang::firstOrCreate(
            ['kode' => 'KAT-DIST-AUTH'],
            ['nama' => 'Kategori Distribusi Auth', 'status' => 'AKTIF']
        );
        $satuan = SatuanBarang::firstOrCreate(
            ['kode' => 'PCS-DIST-AUTH'],
            ['nama' => 'Pcs', 'status' => 'AKTIF']
        );
        $this->barang = Barang::firstOrCreate(
            ['kode' => 'BRG-DIST-AUTH'],
            [
                'nama' => 'Barang Distribusi Auth',
                'kategori_id' => $kategori->id,
                'satuan_id' => $satuan->id,
                'tipe' => 'HABIS_PAKAI',
                'status' => 'AKTIF',
            ]
        );
    }

    public function test_unit_role_without_create_permission_cannot_submit_amprah(): void
    {
        $user = $this->user('unit-role-no-permission');
        $user->assignRole(Role::create(['name' => 'unit_role_without_amprah_permission', 'guard_name' => 'web']));

        $this->actingAs($user)
            ->postJson('/api/v1/distribusi/amprah', $this->payload())
            ->assertForbidden();
    }

    public function test_create_permission_without_workflow_role_submits_pending_approval(): void
    {
        $user = $this->user('permission-only');
        $user->givePermissionTo('create_layanan_unit::permintaan::barang');

        $response = $this->actingAs($user)
            ->postJson('/api/v1/distribusi/amprah', $this->payload())
            ->assertCreated();

        $this->assertSame('DIAJUKAN', $response->json('data.status'));
    }

    public function test_kepala_unit_role_and_approval_permission_submit_directly_to_warehouse(): void
    {
        $user = $this->user('karu-can-approve');
        $user->assignRole('kepala_unit');
        $user->givePermissionTo([
            'create_layanan_unit::permintaan::barang',
            'update_layanan_unit::approval::karu',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/distribusi/amprah', $this->payload())
            ->assertCreated();

        $this->assertSame('DISETUJUI_KARU', $response->json('data.status'));
    }

    public function test_kepala_teknisi_role_and_approval_permission_submit_directly_to_warehouse(): void
    {
        $user = $this->user('katek-can-approve');
        $user->assignRole('kepala_teknisi');
        $user->givePermissionTo([
            'create_layanan_unit::permintaan::barang',
            'update_layanan_unit::approval::karu',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/distribusi/amprah', $this->payload())
            ->assertCreated();

        $this->assertSame('DISETUJUI_KARU', $response->json('data.status'));
        $this->assertDatabaseHas('permintaan_distribusi', [
            'id' => $response->json('data.id'),
            'disetujui_oleh' => $user->id,
        ]);
    }

    public function test_petugas_unit_submits_pending_approval(): void
    {
        $user = $this->user('petugas-unit-amprah');
        $user->assignRole('petugas_unit');
        $user->givePermissionTo('create_layanan_unit::permintaan::barang');

        $response = $this->actingAs($user)
            ->postJson('/api/v1/distribusi/amprah', $this->payload())
            ->assertCreated();

        $this->assertSame('DIAJUKAN', $response->json('data.status'));
        $this->assertDatabaseHas('permintaan_distribusi', [
            'id' => $response->json('data.id'),
            'disetujui_oleh' => null,
        ]);
    }
    public function test_receipt_permission_does_not_allow_receiving_another_units_shipment(): void
    {
        $otherUnit = Unit::where('status', 'AKTIF')->where('id', '!=', $this->unit->id)->firstOrFail();
        $user = $this->user('permission-cross-unit');
        $user->unit_id = $otherUnit->id;
        $user->save();
        $user->givePermissionTo('update_layanan_unit::penerimaan::barang');

        $request = \App\Models\Distribusi\PermintaanDistribusi::create([
            'kode' => 'REQ-AUTH-' . uniqid(),
            'unit_id' => $this->unit->id,
            'gudang_id' => $this->gudang->id,
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi' => 'REGULER',
            'status' => 'DIKIRIM',
            'diajukan_oleh' => $user->id,
        ]);
        $sppb = PengirimanBarang::create([
            'kode' => 'SPPB-AUTH-' . uniqid(),
            'permintaan_distribusi_id' => $request->id,
            'gudang_id' => $this->gudang->id,
            'unit_id' => $this->unit->id,
            'tanggal_kirim' => now()->toDateString(),
            'status' => 'DIKIRIM',
            'dikirim_oleh' => $user->id,
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/distribusi/sppb/{$sppb->id}/terima")
            ->assertForbidden();

        $this->assertDatabaseMissing('penerimaan_distribusi', [
            'pengiriman_barang_id' => $sppb->id,
        ]);
    }
    public function test_warehouse_role_without_picking_permission_cannot_execute_picking(): void
    {
        $user = $this->user('warehouse-role-no-picking-permission');
        $user->gudang_id = $this->gudang->id;
        $user->unit_id = null;
        $user->save();
        $user->assignRole(Role::create(['name' => 'warehouse_role_without_picking_permission', 'guard_name' => 'web']));

        $this->actingAs($user)
            ->postJson('/api/v1/distribusi/picking', ['permintaan_id' => 1])
            ->assertForbidden();
    }

    public function test_warehouse_role_without_permission_cannot_read_global_warehouse_layout(): void
    {
        $user = $this->user('warehouse-layout-role-only');
        $user->assignRole(Role::create(['name' => 'warehouse_layout_role_only', 'guard_name' => 'web']));

        $this->actingAs($user)
            ->getJson('/api/v1/gudang/rak')
            ->assertForbidden();
    }

    public function test_view_permission_grants_global_warehouse_layout_access(): void
    {
        $user = $this->user('warehouse-layout-view');
        $user->givePermissionTo('view_any_master::lokasi::gudang');

        $this->actingAs($user)
            ->getJson('/api/v1/gudang/rak')
            ->assertOk();
    }

    public function test_permission_does_not_bypass_warehouse_scope_for_opname_count(): void
    {
        $otherWarehouse = Gudang::where('status', 'AKTIF')->where('id', '!=', $this->gudang->id)->firstOrFail();
        $user = $this->user('opname-scoped-permission');
        $user->gudang_id = $this->gudang->id;
        $user->save();
        $user->givePermissionTo('update_gudang_stok::stok::opname');
        $opname = \App\Models\Persediaan\StokOpname::create([
            'kode' => CodeGenerator::generateOpn(),
            'gudang_id' => $otherWarehouse->id,
            'tanggal_opname' => now()->toDateString(),
            'status' => 'BERJALAN',
            'dibuat_oleh' => $user->id,
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/gudang/opname-count', [
                'stok_opname_id' => $opname->id,
                'jumlah_fisik' => 0,
            ])
            ->assertForbidden();
    }




    public function test_role_and_permission_cannot_approve_another_units_borrow_request(): void
    {
        $otherUnit = Unit::where('status', 'AKTIF')->where('id', '!=', $this->unit->id)->firstOrFail();
        $user = $this->user('karu-cross-unit-approval');
        $user->assignRole('kepala_unit');
        $user->givePermissionTo('update_layanan_unit::approval::peminjaman');
        $user->save();
        $pinjam = \App\Models\Aset\PermintaanPeminjaman::create([
            'kode' => 'PINJAM-AUTH-' . uniqid(),
            'tipe_penyedia' => 'RUANGAN',
            'unit_penyedia_id' => $otherUnit->id,
            'unit_id' => $this->unit->id,
            'diajukan_oleh' => $user->id,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDay()->toDateString(),
            'keperluan' => 'Authorization test',
            'status' => 'DIAJUKAN',
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/aset/peminjaman/{$pinjam->id}/otorisasi", ['aksi' => 'setujui'])
            ->assertForbidden();

        $this->assertDatabaseHas('permintaan_peminjaman', ['id' => $pinjam->id, 'status' => 'DIAJUKAN']);
    }

    public function test_approval_permission_without_workflow_role_cannot_approve_borrow_request(): void
    {
        $user = $this->user('permission-only-borrow-approval');
        $user->givePermissionTo('update_layanan_unit::approval::peminjaman');
        $pinjam = \App\Models\Aset\PermintaanPeminjaman::create([
            'kode' => 'PINJAM-AUTH-' . uniqid(),
            'tipe_penyedia' => 'RUANGAN',
            'unit_penyedia_id' => $this->unit->id,
            'unit_id' => $this->unit->id,
            'diajukan_oleh' => $user->id,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDay()->toDateString(),
            'keperluan' => 'Authorization test',
            'status' => 'DIAJUKAN',
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/aset/peminjaman/{$pinjam->id}/otorisasi", ['aksi' => 'setujui'])
            ->assertForbidden();
    }

    private function user(string $username): User
    {
        return User::create([
            'nama' => $username,
            'username' => $username,
            'email' => $username . '@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $this->unit->id,
            'status' => 'AKTIF',
        ]);
    }

    private function payload(): array
    {
        return [
            'gudang_id' => $this->gudang->id,
            'urgensi' => 'REGULER',
            'items' => [[
                'barang_id' => $this->barang->id,
                'jumlah' => 2,
            ]],
        ];
    }
}
