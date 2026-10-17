<?php

namespace Tests\Feature\Pengadaan;

use App\Models\Master\Barang;
use App\Models\Master\Gudang;
use App\Models\Master\Pemasok;
use App\Models\Master\Unit;
use App\Models\Pengadaan\PenerimaanBarang;
use App\Models\Pengadaan\PesananPembelian;
use App\Models\Pengadaan\PesananPembelianDetail;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InboundTolakKirimanApiTest extends TestCase
{
    use DatabaseTransactions;

    private const PERM_TERIMA_PO = 'create_gudang_stok::penerimaan::po';

    protected Gudang $gudang;
    protected Pemasok $pemasok;
    protected Barang $barang;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::where('email', 'superadmin@simlogrs.local')->first()
            ?? User::firstOrFail();

        $this->gudang = Gudang::where('status', 'AKTIF')->firstOrFail();
        $this->barang = Barang::where('status', 'AKTIF')->firstOrFail();
        $this->pemasok = Pemasok::firstOrCreate(
            ['kode' => 'VND-TOLAK-TEST'],
            ['nama' => 'Vendor Tolak Test PT', 'status' => 'AKTIF']
        );
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    private function makeUser(array $roles = [], array $permissions = [], ?int $gudangId = null, ?int $unitId = null): User
    {
        $suffix = uniqid();
        $user = User::create([
            'nama'                 => 'User Tolak ' . $suffix,
            'username'             => 'user_tolak_' . $suffix,
            'email'                => "user_tolak_{$suffix}@simlog.test",
            'password'             => bcrypt('password'),
            'gudang_id'            => $gudangId,
            'unit_id'              => $unitId,
            'status'               => 'AKTIF',
            'wajib_ganti_password' => false,
        ]);

        if ($roles) {
            $user->assignRole($roles);
        }

        foreach ($permissions as $perm) {
            $user->givePermissionTo(Permission::findOrCreate($perm, 'web'));
        }

        return $user->fresh();
    }

    private function createPo(string $status = 'DIKIRIM_VENDOR'): PesananPembelian
    {
        $po = PesananPembelian::create([
            'kode'              => CodeGenerator::generatePo(),
            'pemasok_id'        => $this->pemasok->id,
            'tanggal_po'        => now()->toDateString(),
            'total_biaya'       => 150000,
            'syarat_pembayaran' => 'TEMPO_30_HARI',
            'status'            => $status,
            'dibuat_oleh'       => $this->adminUser->id,
        ]);

        PesananPembelianDetail::create([
            'pesanan_pembelian_id' => $po->id,
            'barang_id'            => $this->barang->id,
            'jumlah_dipesan'       => 10,
            'jumlah_diterima'      => 0,
            'harga_satuan'         => 15000,
            'subtotal'             => 150000,
        ]);

        return $po;
    }

    public function test_petugas_gudang_dapat_menolak_kiriman_po_status_dikirim_vendor(): void
    {
        $po = $this->createPo('DIKIRIM_VENDOR');

        $user = $this->makeUser(
            roles: ['petugas_gudang'],
            permissions: [self::PERM_TERIMA_PO],
            gudangId: $this->gudang->id
        );

        $response = $this->actingAs($user)->postJson('/api/v1/gudang/penerimaan-po/tolak', [
            'pesanan_pembelian_id' => $po->id,
            'nomor_surat_jalan'    => 'SJ-TOLAK-001',
            'alasan_penolakan'     => 'Barang rusak kemasan pecah parah saat diterima.',
        ]);

        $response->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('data.status', 'DITOLAK');

        $rcvKode = $response->json('data.kode_rcv');
        $this->assertNotEmpty($rcvKode);

        $rcv = PenerimaanBarang::where('kode', $rcvKode)->first();
        $this->assertNotNull($rcv);
        $this->assertSame('DITOLAK', $rcv->status);
        $this->assertDatabaseHas('penerimaan_barang', [
            'kode'   => $rcvKode,
            'status' => 'DITOLAK',
        ]);
    }

    public function test_kepala_gudang_dapat_menolak_kiriman_po(): void
    {
        $po = $this->createPo('DIKIRIM_VENDOR');

        $user = $this->makeUser(
            roles: ['kepala_gudang'],
            permissions: [self::PERM_TERIMA_PO],
            gudangId: $this->gudang->id
        );

        $response = $this->actingAs($user)->postJson('/api/v1/gudang/penerimaan-po/tolak', [
            'pesanan_pembelian_id' => $po->id,
            'nomor_surat_jalan'    => 'SJ-TOLAK-002',
            'alasan_penolakan'     => 'Penolakan oleh Kepala Gudang karena spesifikasi tidak cocok.',
        ]);

        $response->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('data.status', 'DITOLAK');

        $rcvKode = $response->json('data.kode_rcv');
        $this->assertNotEmpty($rcvKode);
    }

    public function test_penolakan_po_tanpa_nomor_surat_jalan_atau_alasan_ditolak_validasi(): void
    {
        $po = $this->createPo('DIKIRIM_VENDOR');

        $user = $this->makeUser(
            roles: ['petugas_gudang'],
            permissions: [self::PERM_TERIMA_PO],
            gudangId: $this->gudang->id
        );

        $response = $this->actingAs($user)->postJson('/api/v1/gudang/penerimaan-po/tolak', [
            'pesanan_pembelian_id' => $po->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nomor_surat_jalan', 'alasan_penolakan']);
    }

    public function test_user_tanpa_wewenang_gudang_ditolak(): void
    {
        $po = $this->createPo('DIKIRIM_VENDOR');

        $unit = Unit::where('status', 'AKTIF')->firstOrFail();
        $user = $this->makeUser(
            roles: ['petugas_unit'],
            permissions: [],
            gudangId: null,
            unitId: $unit->id
        );

        $response = $this->actingAs($user)->postJson('/api/v1/gudang/penerimaan-po/tolak', [
            'pesanan_pembelian_id' => $po->id,
            'nomor_surat_jalan'    => 'SJ-TOLAK-003',
            'alasan_penolakan'     => 'Mencoba menolak tanpa hak akses gudang.',
        ]);

        $response->assertStatus(403);
    }

    public function test_po_yang_belum_dikirim_vendor_tidak_dapat_ditolak(): void
    {
        $po = $this->createPo('DITERBITKAN');

        $user = $this->makeUser(
            roles: ['petugas_gudang'],
            permissions: [self::PERM_TERIMA_PO],
            gudangId: $this->gudang->id
        );

        $response = $this->actingAs($user)->postJson('/api/v1/gudang/penerimaan-po/tolak', [
            'pesanan_pembelian_id' => $po->id,
            'nomor_surat_jalan'    => 'SJ-TOLAK-004',
            'alasan_penolakan'     => 'Mencoba menolak PO yang belum dikirim vendor.',
        ]);

        $response->assertStatus(422);
    }
}
