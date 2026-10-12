<?php

namespace Tests\Feature\Pengadaan;

use App\Enums\StatusPermintaanPengadaan;
use App\Livewire\Pengadaan\MasterRekananIndex;
use App\Livewire\Pengadaan\PenerimaanPoIndex;
use App\Livewire\Pengadaan\PermintaanPengadaanIndex;
use App\Livewire\Pengadaan\PesananPembelianIndex;
use App\Livewire\Pengadaan\RealisasiAnggaranIndex;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\PenerimaanBarang;
use App\Models\Pemasok;
use App\Models\PermintaanPengadaan;
use App\Models\PermintaanPengadaanDetail;
use App\Models\PesananPembelian;
use App\Models\PesananPembelianDetail;
use App\Models\Unit;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * ADR 0001 untuk modul Pengadaan: role saja ditolak, permission diizinkan,
 * batas role workflow (otorisasi kepala), dan akses lintas unit/gudang ditolak.
 */
class PengadaanWebAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected Unit $unitA;
    protected Unit $unitB;
    protected Gudang $gudangA;
    protected Gudang $gudangB;
    protected Barang $barang;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $units = Unit::where('status', 'AKTIF')->orderBy('id')->take(2)->get();
        $this->unitA = $units[0];
        $this->unitB = $units[1];

        $gudangs = Gudang::where('status', 'AKTIF')->orderBy('id')->take(2)->get();
        $this->gudangA = $gudangs[0];
        $this->gudangB = $gudangs[1];

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
            'nama' => 'Uji Pengadaan ' . $suffix,
            'username' => 'uji_pengadaan_' . $suffix,
            'email' => "uji_pengadaan_{$suffix}@simlog.test",
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

    /** Cabut permission dari role (di dalam transaksi tes) agar tersisa "role saja". */
    private function revokeFromRole(string $role, array $permissions): void
    {
        Role::findByName($role, 'web')->revokePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function makePr(?int $unitId, ?int $gudangId, string $status = StatusPermintaanPengadaan::Diajukan->value): PermintaanPengadaan
    {
        $pr = PermintaanPengadaan::create([
            'kode' => CodeGenerator::generatePr(),
            'unit_id' => $unitId,
            'gudang_id' => $gudangId,
            'diajukan_oleh' => User::where('email', 'superadmin@simlogrs.local')->value('id'),
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi' => 'REGULER',
            'status' => $status,
        ]);

        PermintaanPengadaanDetail::create([
            'permintaan_pengadaan_id' => $pr->id,
            'barang_id' => $this->barang->id,
            'jumlah' => 3,
        ]);

        return $pr;
    }

    private function makePo(string $status = 'DIKIRIM_VENDOR'): PesananPembelian
    {
        $pemasok = Pemasok::firstOrCreate(['kode' => 'VND-AUTHZ-01'], ['nama' => 'Vendor Uji Otorisasi', 'status' => 'AKTIF']);

        $po = PesananPembelian::create([
            'kode' => CodeGenerator::generatePo(),
            'pemasok_id' => $pemasok->id,
            'tanggal_po' => now()->toDateString(),
            'total_biaya' => 30000,
            'syarat_pembayaran' => 'TEMPO_30_HARI',
            'status' => $status,
            'dibuat_oleh' => User::where('email', 'superadmin@simlogrs.local')->value('id'),
        ]);

        PesananPembelianDetail::create([
            'pesanan_pembelian_id' => $po->id,
            'barang_id' => $this->barang->id,
            'jumlah_dipesan' => 3,
            'harga_satuan' => 10000,
            'subtotal' => 30000,
        ]);

        return $po;
    }

    // ---------------------------------------------------------------
    // Halaman: role / dashboard saja ditolak, permission view diizinkan
    // ---------------------------------------------------------------

    public function test_role_only_without_view_permission_cannot_open_rekanan_and_anggaran(): void
    {
        $this->revokeFromRole('pengadaan', [
            'view_any_pengadaan::master::rekanan', 'view_pengadaan::master::rekanan',
            'view_any_pengadaan::realisasi::anggaran', 'view_pengadaan::realisasi::anggaran',
        ]);
        $this->actingAs($this->makeUser(roles: ['pengadaan']));

        Livewire::test(MasterRekananIndex::class)->assertForbidden();
        Livewire::test(RealisasiAnggaranIndex::class)->assertForbidden();
    }

    public function test_dashboard_or_page_permission_alone_is_not_a_view_bypass(): void
    {
        $this->actingAs($this->makeUser([
            'page_ManajemenDashboard', 'page_MasterRekananIndex', 'page_RealisasiAnggaranIndex',
        ]));

        Livewire::test(MasterRekananIndex::class)->assertForbidden();
        Livewire::test(RealisasiAnggaranIndex::class)->assertForbidden();
    }

    public function test_view_permission_allows_pengadaan_pages(): void
    {
        $this->actingAs($this->makeUser([
            'view_any_pengadaan::master::rekanan',
            'view_any_pengadaan::realisasi::anggaran',
            'view_any_pengadaan::pesanan::pembelian',
            'view_any_pengadaan::permintaan::pengadaan',
            'view_any_gudang_stok::penerimaan::po',
        ], gudangId: $this->gudangA->id));

        Livewire::test(MasterRekananIndex::class)->assertOk();
        Livewire::test(RealisasiAnggaranIndex::class)->assertOk();
        Livewire::test(PesananPembelianIndex::class)->assertOk();
        Livewire::test(PermintaanPengadaanIndex::class)->assertOk();
        Livewire::test(PenerimaanPoIndex::class)->assertOk();
    }

    // ---------------------------------------------------------------
    // Aksi kapabilitas: permission granular, role saja tidak cukup
    // ---------------------------------------------------------------

    public function test_po_status_update_requires_update_permission_not_role(): void
    {
        $po = $this->makePo('DITERBITKAN');

        $this->revokeFromRole('pengadaan', ['update_pengadaan::pesanan::pembelian']);
        $this->actingAs($this->makeUser(roles: ['pengadaan']));
        Livewire::test(PesananPembelianIndex::class)
            ->call('updateStatusKirim', $po->id)
            ->assertForbidden();
        $this->assertSame('DITERBITKAN', $po->fresh()->status);

        $this->actingAs($this->makeUser([
            'view_any_pengadaan::pesanan::pembelian', 'update_pengadaan::pesanan::pembelian',
        ]));
        Livewire::test(PesananPembelianIndex::class)->call('updateStatusKirim', $po->id);
        $this->assertSame('DIKIRIM_VENDOR', $po->fresh()->status);
    }

    // ---------------------------------------------------------------
    // Tahap workflow: otorisasi PR butuh role kepala DAN permission
    // ---------------------------------------------------------------

    public function test_pr_approval_permission_without_kepala_role_is_denied(): void
    {
        $pr = $this->makePr($this->unitA->id, null);

        $this->actingAs($this->makeUser([
            'view_any_pengadaan::permintaan::pengadaan', 'update_pengadaan::permintaan::pengadaan',
        ], unitId: $this->unitA->id));

        Livewire::test(PermintaanPengadaanIndex::class)->call('approve', $pr->id)->assertForbidden();
        $this->assertSame(StatusPermintaanPengadaan::Diajukan->value, $pr->fresh()->status);
    }

    public function test_pr_approval_kepala_role_without_permission_is_denied(): void
    {
        $pr = $this->makePr($this->unitA->id, null);

        $this->revokeFromRole('kepala_unit', ['update_pengadaan::permintaan::pengadaan']);
        $this->actingAs($this->makeUser(roles: ['kepala_unit'], unitId: $this->unitA->id));

        Livewire::test(PermintaanPengadaanIndex::class)->call('approve', $pr->id)->assertForbidden();
        $this->assertSame(StatusPermintaanPengadaan::Diajukan->value, $pr->fresh()->status);
    }

    public function test_pr_approval_kepala_role_and_permission_on_own_unit_is_allowed(): void
    {
        $pr = $this->makePr($this->unitA->id, null);

        $this->actingAs($this->makeUser(roles: ['kepala_unit'], unitId: $this->unitA->id));

        Livewire::test(PermintaanPengadaanIndex::class)->call('approve', $pr->id)->assertOk();
        $this->assertSame(StatusPermintaanPengadaan::DiteruskanKePengadaan->value, $pr->fresh()->status);
    }

    public function test_pr_approval_dashboard_permission_is_not_a_global_bypass(): void
    {
        $pr = $this->makePr($this->unitA->id, null);

        $this->actingAs($this->makeUser([
            'page_AdminLogistikDashboard', 'page_Dashboard',
            'view_any_pengadaan::permintaan::pengadaan', 'update_pengadaan::permintaan::pengadaan',
        ]));

        Livewire::test(PermintaanPengadaanIndex::class)->call('approve', $pr->id)->assertForbidden();
        $this->assertSame(StatusPermintaanPengadaan::Diajukan->value, $pr->fresh()->status);
    }

    public function test_pr_submitted_without_kepala_role_is_not_auto_forwarded(): void
    {
        $this->actingAs($this->makeUser([
            'view_any_pengadaan::permintaan::pengadaan',
            'create_pengadaan::permintaan::pengadaan',
            'update_pengadaan::permintaan::pengadaan',
        ], unitId: $this->unitA->id));

        Livewire::test(PermintaanPengadaanIndex::class)
            ->call('create')
            ->set('items', [['barang_id' => $this->barang->id, 'jumlah' => 2]])
            ->call('save')
            ->assertHasNoErrors();

        $pr = PermintaanPengadaan::where('unit_id', $this->unitA->id)->latest('id')->first();
        $this->assertSame(StatusPermintaanPengadaan::Diajukan->value, $pr->status);
    }

    // ---------------------------------------------------------------
    // Scope: non-global dibatasi ke unit/gudang miliknya
    // ---------------------------------------------------------------

    public function test_pr_approval_on_other_unit_is_denied(): void
    {
        $pr = $this->makePr($this->unitB->id, null);

        $this->actingAs($this->makeUser(roles: ['kepala_unit'], unitId: $this->unitA->id));

        Livewire::test(PermintaanPengadaanIndex::class)->call('approve', $pr->id)->assertForbidden();
        $this->assertSame(StatusPermintaanPengadaan::Diajukan->value, $pr->fresh()->status);
    }

    public function test_pr_submission_for_other_unit_is_denied(): void
    {
        $before = PermintaanPengadaan::where('unit_id', $this->unitB->id)->count();

        $this->actingAs($this->makeUser([
            'view_any_pengadaan::permintaan::pengadaan', 'create_pengadaan::permintaan::pengadaan',
        ], unitId: $this->unitA->id));

        Livewire::test(PermintaanPengadaanIndex::class)
            ->call('create')
            ->set('unit_id', $this->unitB->id)
            ->set('items', [['barang_id' => $this->barang->id, 'jumlah' => 2]])
            ->call('save')
            ->assertForbidden();

        $this->assertSame($before, PermintaanPengadaan::where('unit_id', $this->unitB->id)->count());
    }

    public function test_pr_list_and_detail_of_other_unit_are_hidden_even_with_dashboard_permission(): void
    {
        $prA = $this->makePr($this->unitA->id, null);
        $prB = $this->makePr($this->unitB->id, null);

        $this->actingAs($this->makeUser([
            'page_PengadaanDashboard', 'page_ManajemenDashboard',
            'view_any_pengadaan::permintaan::pengadaan', 'view_pengadaan::permintaan::pengadaan',
        ], unitId: $this->unitA->id));

        Livewire::test(PermintaanPengadaanIndex::class)
            ->assertSee($prA->kode)
            ->assertDontSee($prB->kode)
            ->call('showDetail', $prB->id)
            ->assertForbidden();
    }

    public function test_penerimaan_into_other_gudang_is_denied(): void
    {
        $po = $this->makePo('DIKIRIM_VENDOR');

        $this->actingAs($this->makeUser([
            'view_any_gudang_stok::penerimaan::po', 'create_gudang_stok::penerimaan::po',
        ], gudangId: $this->gudangA->id));

        Livewire::test(PenerimaanPoIndex::class)
            ->call('terima', $po->id)
            ->assertSet('gudang_id', $this->gudangA->id)
            ->set('gudang_id', $this->gudangB->id)
            ->set('nomor_surat_jalan', 'SJ-AUTHZ-001')
            ->call('savePenerimaan')
            ->assertForbidden();

        $this->assertFalse(PenerimaanBarang::where('pesanan_pembelian_id', $po->id)->exists());
    }

    public function test_penerimaan_history_of_other_gudang_is_hidden(): void
    {
        $po = $this->makePo('DITERIMA_SEBAGIAN');
        $rcvB = PenerimaanBarang::create([
            'kode' => 'RCV-AUTHZ-' . uniqid(),
            'pesanan_pembelian_id' => $po->id,
            'gudang_id' => $this->gudangB->id,
            'nomor_surat_jalan' => 'SJ-AUTHZ-B',
            'tanggal_terima' => now()->toDateString(),
            'status' => 'SEBAGIAN',
            'diterima_oleh' => User::where('email', 'superadmin@simlogrs.local')->value('id'),
        ]);

        $this->actingAs($this->makeUser(['view_any_gudang_stok::penerimaan::po'], gudangId: $this->gudangA->id));

        Livewire::test(PenerimaanPoIndex::class)
            ->call('switchTab', 'riwayat')
            ->assertDontSee($rcvB->kode)
            ->call('showDetail', $rcvB->id)
            ->assertForbidden();
    }

    public function test_realisasi_anggaran_detail_of_other_unit_is_denied(): void
    {
        $this->actingAs($this->makeUser(['view_any_pengadaan::realisasi::anggaran'], unitId: $this->unitA->id));

        Livewire::test(RealisasiAnggaranIndex::class)
            ->call('openDetail', $this->unitA->id)
            ->assertSet('showDetailModal', true)
            ->call('openDetail', $this->unitB->id)
            ->assertForbidden();
    }
}
