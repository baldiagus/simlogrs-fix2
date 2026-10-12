<?php

namespace Tests\Feature;

use App\Livewire\Aset\ApprovalPeminjamanIndex;
use App\Livewire\Aset\PengalihanAsetIndex;
use App\Livewire\Aset\PengembalianPeminjamanIndex;
use App\Livewire\Audit\AuditTrailIndex;
use App\Livewire\Dashboard\SuperAdminDashboard;
use App\Livewire\Distribusi\PermintaanBarangIndex;
use App\Livewire\Master\SatuanBarangIndex;
use App\Livewire\Persediaan\BukuMutasiIndex;
use App\Livewire\User\PenggunaIndex;
use App\Models\Aset;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\LogAudit;
use App\Models\PengalihanAset;
use App\Models\PergerakanStok;
use App\Models\PermintaanDistribusi;
use App\Models\PermintaanPeminjaman;
use App\Models\PermintaanPeminjamanDetail;
use App\Models\SatuanBarang;
use App\Models\StokBatch;
use App\Models\Unit;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * ADR 0001: role saja ditolak, permission diizinkan, batas role workflow, dan akses lintas scope ditolak.
 */
class WebAuthorizationConsolidationTest extends TestCase
{
    use DatabaseTransactions;

    private Unit $unitA;
    private Unit $unitB;
    private Gudang $gudangA;
    private Gudang $gudangB;
    private User $pemohon;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->unitA, $this->unitB] = Unit::where('status', 'AKTIF')->orderBy('id')->take(2)->get()->all();
        [$this->gudangA, $this->gudangB] = Gudang::where('status', 'AKTIF')->orderBy('id')->take(2)->get()->all();
        $this->pemohon = $this->user('pemohon', ['unit_id' => $this->unitB->id]);
    }

    private function user(string $name, array $attrs = [], array $perms = [], ?string $role = null): User
    {
        $suffix = uniqid();
        $user = User::create(array_merge([
            'nama' => $name,
            'username' => $name . '_' . $suffix,
            'email' => $name . '_' . $suffix . '@simlog.test',
            'password' => bcrypt('password'),
            'status' => 'AKTIF',
        ], $attrs));
        $role && $user->assignRole($role);
        $perms && $user->givePermissionTo($perms);

        return $user;
    }

    private function revoke(string $role, string $permission): void
    {
        Role::findByName($role, 'web')->revokePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function pinjam(string $status = 'DIAJUKAN'): PermintaanPeminjaman
    {
        $aset = Aset::create([
            'barang_id' => Barang::where('status', 'AKTIF')->value('id'),
            'unit_id' => $this->unitA->id,
            'kode_aset' => 'AST-WEB-' . uniqid(),
            'nomor_seri' => 'SN-WEB',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 1000,
            'kondisi' => 'BAIK',
            'status' => 'TERSEDIA',
        ]);
        $pinjam = PermintaanPeminjaman::create([
            'kode' => CodeGenerator::generate('PINJ', PermintaanPeminjaman::class, 'kode', 'Y', 3),
            'tipe_penyedia' => 'RUANGAN',
            'unit_penyedia_id' => $this->unitA->id,
            'unit_id' => $this->unitB->id,
            'diajukan_oleh' => $this->pemohon->id,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(3)->toDateString(),
            'keperluan' => 'Uji otorisasi',
            'status' => $status,
        ]);
        PermintaanPeminjamanDetail::create([
            'permintaan_peminjaman_id' => $pinjam->id,
            'aset_id' => $aset->id,
            'kondisi_saat_pinjam' => 'BAIK',
        ]);

        return $pinjam;
    }

    // ---- Approval peminjaman: tahap workflow = role + permission + scope penyedia

    public function test_karu_role_without_approval_permission_cannot_approve_loan(): void
    {
        $this->revoke('kepala_unit', 'update_layanan_unit::approval::peminjaman');
        $karu = $this->user('karu_role_only', ['unit_id' => $this->unitA->id], ['view_any_layanan_unit::approval::peminjaman'], 'kepala_unit');
        $pinjam = $this->pinjam();

        Livewire::actingAs($karu)->test(ApprovalPeminjamanIndex::class)
            ->call('approve', $pinjam->id)
            ->assertForbidden();
        $this->assertSame('DIAJUKAN', $pinjam->fresh()->status);
    }

    public function test_view_only_permission_cannot_approve_loan(): void
    {
        $viewer = $this->user('viewer_pinjam', ['unit_id' => $this->unitA->id], ['view_any_layanan_unit::approval::peminjaman']);
        $pinjam = $this->pinjam();

        Livewire::actingAs($viewer)->test(ApprovalPeminjamanIndex::class)
            ->assertOk()
            ->call('approve', $pinjam->id)
            ->assertForbidden();
    }

    public function test_permission_without_karu_role_cannot_approve_loan(): void
    {
        $user = $this->user('perm_only_pinjam', ['unit_id' => $this->unitA->id], [
            'view_any_layanan_unit::approval::peminjaman', 'update_layanan_unit::approval::peminjaman',
        ]);
        $pinjam = $this->pinjam();

        Livewire::actingAs($user)->test(ApprovalPeminjamanIndex::class)
            ->call('approve', $pinjam->id)
            ->assertForbidden();
    }

    public function test_provider_karu_with_permission_approves_but_other_unit_karu_cannot(): void
    {
        $perms = ['view_any_layanan_unit::approval::peminjaman', 'update_layanan_unit::approval::peminjaman'];
        $penyedia = $this->user('karu_penyedia', ['unit_id' => $this->unitA->id], $perms, 'kepala_unit');
        $lain = $this->user('karu_lain', ['unit_id' => $this->unitB->id], $perms, 'kepala_unit');
        $pinjam = $this->pinjam();

        Livewire::actingAs($lain)->test(ApprovalPeminjamanIndex::class)
            ->call('approve', $pinjam->id)
            ->assertForbidden();
        $this->assertSame('DIAJUKAN', $pinjam->fresh()->status);

        Livewire::actingAs($penyedia)->test(ApprovalPeminjamanIndex::class)
            ->call('approve', $pinjam->id)
            ->assertOk();
        $this->assertSame('DISETUJUI', $pinjam->fresh()->status);
    }

    public function test_auditor_reads_loans_globally_but_cannot_mutate(): void
    {
        $auditor = $this->user('auditor_pinjam', [], [], 'auditor');
        $pinjam = $this->pinjam();

        Livewire::actingAs($auditor)->test(ApprovalPeminjamanIndex::class)
            ->assertOk()
            ->assertSee($pinjam->kode)
            ->call('approve', $pinjam->id)
            ->assertForbidden();
    }

    // ---- Pengembalian: aksi butuh permission, scope kosong fail-closed

    public function test_return_modal_requires_action_permission_and_provider_scope(): void
    {
        $pinjam = $this->pinjam('DIPINJAM');

        $viewOnly = $this->user('kembali_view', ['unit_id' => $this->unitA->id], ['view_any_layanan_unit::pengembalian::aset']);
        Livewire::actingAs($viewOnly)->test(PengembalianPeminjamanIndex::class)
            ->call('openPengembalianModal', $pinjam->id)
            ->assertForbidden();

        $lain = $this->user('kembali_lain', ['unit_id' => $this->unitB->id], [
            'view_any_layanan_unit::pengembalian::aset', 'create_layanan_unit::pengembalian::aset',
        ]);
        Livewire::actingAs($lain)->test(PengembalianPeminjamanIndex::class)
            ->call('openPengembalianModal', $pinjam->id)
            ->assertForbidden();

        $penyedia = $this->user('kembali_penyedia', ['unit_id' => $this->unitA->id], [
            'view_any_layanan_unit::pengembalian::aset', 'create_layanan_unit::pengembalian::aset',
        ]);
        Livewire::actingAs($penyedia)->test(PengembalianPeminjamanIndex::class)
            ->call('openPengembalianModal', $pinjam->id)
            ->assertSet('showPengembalianModal', true);
    }

    public function test_user_without_placement_sees_no_returns(): void
    {
        $pinjam = $this->pinjam('DIPINJAM');
        $tanpaPenempatan = $this->user('kembali_tanpa_unit', [], ['view_any_layanan_unit::pengembalian::aset']);

        Livewire::actingAs($tanpaPenempatan)->test(PengembalianPeminjamanIndex::class)
            ->assertDontSee($pinjam->kode);
    }

    // ---- Pengalihan web: terima = scope tujuan, batal = pengaju + permission delete

    private function pengalihan(string $status = 'DIAJUKAN'): PengalihanAset
    {
        return PengalihanAset::create([
            'kode' => 'TRF-WEB-' . uniqid(),
            'tipe_asal' => 'RUANGAN', 'unit_asal_id' => $this->unitA->id,
            'tipe_tujuan' => 'RUANGAN', 'unit_tujuan_id' => $this->unitB->id,
            'tanggal_pengalihan' => now()->toDateString(),
            'alasan' => 'Uji otorisasi web',
            'status' => $status,
            'diajukan_oleh' => $this->pemohon->id,
        ]);
    }

    public function test_transfer_receipt_only_by_destination_facility(): void
    {
        $dokumen = $this->pengalihan('DISETUJUI');
        $perms = ['view_any_layanan_unit::pengalihan::aset', 'update_layanan_unit::pengalihan::aset'];

        $asal = $this->user('trf_asal', ['unit_id' => $this->unitA->id], $perms);
        Livewire::actingAs($asal)->test(PengalihanAsetIndex::class)
            ->call('openTerimaModal', $dokumen->id)
            ->assertForbidden();

        $tujuan = $this->user('trf_tujuan', ['unit_id' => $this->unitB->id], $perms);
        Livewire::actingAs($tujuan)->test(PengalihanAsetIndex::class)
            ->call('openTerimaModal', $dokumen->id)
            ->assertSet('showTerimaModal', true);
    }

    public function test_transfer_cancel_requires_requester_and_delete_permission(): void
    {
        $dokumen = $this->pengalihan();
        $lain = $this->user('trf_bukan_pengaju', ['unit_id' => $this->unitB->id], [
            'view_any_layanan_unit::pengalihan::aset', 'delete_layanan_unit::pengalihan::aset',
        ]);
        Livewire::actingAs($lain)->test(PengalihanAsetIndex::class)
            ->call('cancelPengalihan', $dokumen->id)
            ->assertForbidden();
        $this->assertSame('DIAJUKAN', $dokumen->fresh()->status);

        $this->pemohon->givePermissionTo(['view_any_layanan_unit::pengalihan::aset', 'delete_layanan_unit::pengalihan::aset']);
        Livewire::actingAs($this->pemohon)->test(PengalihanAsetIndex::class)
            ->call('cancelPengalihan', $dokumen->id);
        $this->assertSame('DIBATALKAN', $dokumen->fresh()->status);
    }

    // ---- Scope baca: tanpa penempatan hanya untuk pembaca global (auditor/manajemen)

    public function test_unplaced_view_user_cannot_read_distribution_requests(): void
    {
        $req = PermintaanDistribusi::create([
            'kode' => CodeGenerator::generate('REQ', PermintaanDistribusi::class, 'kode', 'Ym', 3),
            'unit_id' => $this->unitA->id, 'gudang_id' => $this->gudangA->id,
            'tanggal_permintaan' => now()->toDateString(), 'urgensi' => 'REGULER',
            'status' => 'DIAJUKAN', 'diajukan_oleh' => $this->pemohon->id,
        ]);

        $tanpaPenempatan = $this->user('dist_tanpa_unit', [], ['view_any_layanan_unit::permintaan::barang']);
        Livewire::actingAs($tanpaPenempatan)->test(PermintaanBarangIndex::class)
            ->assertDontSee($req->kode)
            ->call('showDetail', $req->id)
            ->assertForbidden();

        $auditor = $this->user('dist_auditor', [], ['view_any_layanan_unit::permintaan::barang'], 'auditor');
        Livewire::actingAs($auditor)->test(PermintaanBarangIndex::class)
            ->assertSee($req->kode);
    }

    // ---- Buku mutasi: showDetail wajib permission dan scope gudang

    public function test_stock_ledger_detail_enforces_permission_and_warehouse_scope(): void
    {
        $barang = Barang::where('status', 'AKTIF')->first();
        $batch = StokBatch::create([
            'barang_id' => $barang->id, 'gudang_id' => $this->gudangB->id,
            'nomor_batch' => 'B-LEDGER-' . uniqid(), 'tanggal_kedaluwarsa' => now()->addYear()->toDateString(),
            'jumlah' => 5, 'status' => 'AKTIF',
        ]);
        $mutasi = PergerakanStok::create([
            'barang_id' => $barang->id, 'stok_batch_id' => $batch->id, 'gudang_id' => $this->gudangB->id,
            'jenis' => 'MASUK', 'jumlah' => 5, 'saldo_sebelum' => 0, 'saldo_sesudah' => 5,
            'referensi_jenis' => 'STOCK_OPNAME', 'referensi_id' => 1, 'dibuat_oleh' => $this->pemohon->id,
        ]);

        $tanpaIzin = $this->user('mutasi_polos', ['gudang_id' => $this->gudangB->id]);
        Livewire::actingAs($tanpaIzin)->test(BukuMutasiIndex::class)->assertForbidden();

        $gudangA = $this->user('mutasi_gudang_a', ['gudang_id' => $this->gudangA->id], ['view_gudang_stok::buku::mutasi']);
        Livewire::actingAs($gudangA)->test(BukuMutasiIndex::class)
            ->call('showDetail', $mutasi->id)
            ->assertForbidden();

        $gudangB = $this->user('mutasi_gudang_b', ['gudang_id' => $this->gudangB->id], ['view_gudang_stok::buku::mutasi']);
        Livewire::actingAs($gudangB)->test(BukuMutasiIndex::class)
            ->call('showDetail', $mutasi->id)
            ->assertSet('showDetailModal', true);

        $auditor = $this->user('mutasi_auditor', [], ['view_gudang_stok::buku::mutasi'], 'auditor');
        Livewire::actingAs($auditor)->test(BukuMutasiIndex::class)
            ->call('showDetail', $mutasi->id)
            ->assertSet('showDetailModal', true);
    }

    // ---- showDetail tanpa guard: master satuan dan audit trail

    public function test_detail_actions_require_view_permission(): void
    {
        $satuan = SatuanBarang::firstOrCreate(['kode' => 'SAT-WEB'], ['nama' => 'Satuan Web', 'status' => 'AKTIF']);
        $log = LogAudit::first() ?? LogAudit::create([
            'pengguna_id' => $this->pemohon->id, 'referensi_jenis' => 'User', 'referensi_id' => $this->pemohon->id,
            'aksi' => 'UBAH', 'data_sebelum' => [], 'data_sesudah' => [], 'ip_address' => '127.0.0.1',
            'device' => 'test', 'dibuat_pada' => now(),
        ]);

        // Izin dicabut setelah halaman terbuka: aksi Livewire langsung harus tetap menolak.
        $polos = $this->user('detail_polos', ['unit_id' => $this->unitA->id], ['view_any_master::satuan::barang', 'view_any_audit_manajemen::audit::trail']);
        $satuanPage = Livewire::actingAs($polos)->test(SatuanBarangIndex::class);
        $auditPage = Livewire::actingAs($polos)->test(AuditTrailIndex::class);

        $polos->revokePermissionTo(['view_any_master::satuan::barang', 'view_any_audit_manajemen::audit::trail']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $satuanPage->call('showDetail', $satuan->id)->assertForbidden();
        $auditPage->call('showDetail', $log->id)->assertForbidden();
    }

    // ---- Pengguna: mode create/update dan self-promotion

    public function test_create_permission_cannot_update_and_update_permission_cannot_create(): void
    {
        $target = $this->user('target_pengguna', ['unit_id' => $this->unitA->id], [], 'petugas_unit');

        $pembuat = $this->user('pembuat', [], ['view_any_user::pengguna', 'create_user::pengguna']);
        Livewire::actingAs($pembuat)->test(PenggunaIndex::class)
            ->call('openEdit', $target->id)
            ->assertForbidden();

        $pengubah = $this->user('pengubah', [], ['view_any_user::pengguna', 'update_user::pengguna']);
        Livewire::actingAs($pengubah)->test(PenggunaIndex::class)
            ->call('openCreate')
            ->assertForbidden();
    }

    public function test_user_manager_cannot_change_own_role(): void
    {
        $pengelola = $this->user('pengelola', [], ['view_any_user::pengguna', 'update_user::pengguna'], 'petugas_unit');

        Livewire::actingAs($pengelola)->test(PenggunaIndex::class)
            ->call('openEdit', $pengelola->id)
            ->set('selectedRole', 'kepala_gudang')
            ->call('save')
            ->assertForbidden();
        $this->assertFalse($pengelola->fresh()->hasRole('kepala_gudang'));
    }

    // ---- Emulasi konteks dan cetak SPK: page_* bukan bypass

    public function test_dashboard_permission_alone_cannot_emulate_context(): void
    {
        $user = $this->user('emulasi', [], ['page_Dashboard']);

        Livewire::actingAs($user)->test(SuperAdminDashboard::class)
            ->call('setUnitEmulasi', $this->unitA->id)
            ->assertForbidden();
        Livewire::actingAs($user)->test(SuperAdminDashboard::class)
            ->call('setGudangEmulasi', $this->gudangA->id)
            ->assertForbidden();
    }

    public function test_spk_print_does_not_treat_dashboard_permission_as_supervisor(): void
    {
        $user = $this->user('cetak_spk', [], ['page_Dashboard', 'page_AdminLogistikDashboard']);

        $this->actingAs($user)->get('/teknisi/spk/1/cetak-pdf')->assertForbidden();
    }
}
