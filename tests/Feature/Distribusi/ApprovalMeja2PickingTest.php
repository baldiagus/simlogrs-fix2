<?php

namespace Tests\Feature\Distribusi;

use App\Models\Distribusi\PermintaanDistribusi;
use App\Models\Distribusi\PermintaanDistribusiDetail;
use App\Models\Master\Barang;
use App\Models\Master\Gudang;
use App\Models\Master\KategoriBarang;
use App\Models\Master\SatuanBarang;
use App\Models\Master\Unit;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Alur Meja 2 via API: amprah DISETUJUI_KARU wajib otorisasi gudang sebelum masuk antrean/eksekusi picking.
 */
class ApprovalMeja2PickingTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $petugas;
    private Unit $unit;
    private Gudang $gudang;
    private Barang $barang;

    /** Siapkan admin logistik (Meja 2), petugas picking, serta unit/gudang/barang uji. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = Unit::where('status', 'AKTIF')->firstOrFail();
        $this->gudang = Gudang::where('status', 'AKTIF')->firstOrFail();
        $kategori = KategoriBarang::firstOrCreate(['kode' => 'KAT-M2'], ['nama' => 'Kategori M2', 'status' => 'AKTIF']);
        $satuan = SatuanBarang::firstOrCreate(['kode' => 'PCS-M2'], ['nama' => 'Pcs', 'status' => 'AKTIF']);
        $this->barang = Barang::firstOrCreate(
            ['kode' => 'BRG-M2'],
            ['nama' => 'Barang M2', 'kategori_id' => $kategori->id, 'satuan_id' => $satuan->id, 'tipe' => 'HABIS_PAKAI', 'status' => 'AKTIF']
        );

        $this->admin = $this->buatUser('adminm2', 'admin_logistik');
        $this->petugas = $this->buatUser('petugasm2', 'petugas_gudang', ['create_gudang_stok::picking::distribusi', 'view_any_gudang_stok::picking::distribusi']);
        $this->petugas->update(['gudang_id' => $this->gudang->id]);
    }

    /** Buat pengguna aktif dengan peran dan izin langsung tertentu. */
    private function buatUser(string $username, string $peran, array $izin = []): User
    {
        $user = User::create([
            'nama' => $username, 'username' => $username, 'email' => "{$username}@simlog.test",
            'password' => bcrypt('password'), 'status' => 'AKTIF',
        ]);
        $user->assignRole($peran);
        if ($izin) {
            $user->givePermissionTo($izin);
        }

        return $user;
    }

    /** Buat amprah dengan satu item (jumlah 5, jumlah_disetujui 5 dari Meja 1) pada status tertentu. */
    private function amprah(string $status): PermintaanDistribusi
    {
        $req = PermintaanDistribusi::create([
            'kode' => CodeGenerator::generate('REQ', PermintaanDistribusi::class, 'kode', 'Ym', 3),
            'unit_id' => $this->unit->id, 'gudang_id' => $this->gudang->id,
            'tanggal_permintaan' => now()->toDateString(), 'urgensi' => 'REGULER',
            'status' => $status, 'diajukan_oleh' => $this->admin->id,
        ]);
        PermintaanDistribusiDetail::create([
            'permintaan_distribusi_id' => $req->id, 'barang_id' => $this->barang->id, 'jumlah' => 5, 'jumlah_disetujui' => 5,
        ]);

        return $req->load('detail');
    }

    /** Kode amprah pada respons antrean picking. */
    private function kodeAntrean(): array
    {
        return collect($this->actingAs($this->petugas)->getJson('/api/v1/distribusi/antrean-picking')->assertOk()->json('data'))->pluck('kode')->all();
    }

    public function test_disetujui_karu_tidak_muncul_di_antrean_picking_sampai_meja2_setuju(): void
    {
        $req = $this->amprah('DISETUJUI_KARU');
        $this->assertNotContains($req->kode, $this->kodeAntrean());

        $this->actingAs($this->admin)->postJson("/api/v1/approval/amprah/{$req->id}", ['aksi' => 'setujui'])->assertOk();

        $this->assertContains($req->kode, $this->kodeAntrean());
    }

    public function test_eksekusi_picking_pada_disetujui_karu_ditolak(): void
    {
        $req = $this->amprah('DISETUJUI_KARU');

        $this->actingAs($this->petugas)->postJson('/api/v1/distribusi/picking', ['permintaan_id' => $req->id])->assertStatus(422);
        $this->assertSame('DISETUJUI_KARU', $req->fresh()->status);
    }

    public function test_tolak_meja2_menjadi_ditolak_dan_menyimpan_catatan(): void
    {
        $req = $this->amprah('DISETUJUI_KARU');

        $this->actingAs($this->admin)->postJson("/api/v1/approval/amprah/{$req->id}", ['aksi' => 'tolak', 'catatan' => 'Stok tidak ada'])->assertOk();

        $req->refresh();
        $this->assertSame('DITOLAK', $req->status);
        $this->assertSame('Stok tidak ada', $req->catatan);
    }

    public function test_setuju_meja2_dengan_items_menyimpan_jumlah_disetujui(): void
    {
        $req = $this->amprah('DISETUJUI_KARU');
        $detail = $req->detail->first();

        $this->actingAs($this->admin)->postJson("/api/v1/approval/amprah/{$req->id}", [
            'aksi' => 'setujui', 'items' => [['id' => $detail->id, 'jumlah_disetujui' => 3]],
        ])->assertOk();

        $this->assertSame('DISETUJUI_GUDANG', $req->fresh()->status);
        $this->assertSame(3, (int) $detail->fresh()->jumlah_disetujui);
    }

    public function test_setuju_meja2_tanpa_items_mempertahankan_nilai_meja1(): void
    {
        $req = $this->amprah('DISETUJUI_KARU');

        $this->actingAs($this->admin)->postJson("/api/v1/approval/amprah/{$req->id}", ['aksi' => 'setujui'])->assertOk();

        $this->assertSame(5, (int) $req->detail->first()->fresh()->jumlah_disetujui);
    }

    public function test_items_ditolak_bila_id_bukan_milik_amprah_atau_negatif(): void
    {
        $req = $this->amprah('DISETUJUI_KARU');
        $lain = $this->amprah('DISETUJUI_KARU')->detail->first();
        $detail = $req->detail->first();

        $this->actingAs($this->admin)->postJson("/api/v1/approval/amprah/{$req->id}", [
            'aksi' => 'setujui', 'items' => [['id' => $lain->id, 'jumlah_disetujui' => 1]],
        ])->assertStatus(422);
        $this->actingAs($this->admin)->postJson("/api/v1/approval/amprah/{$req->id}", [
            'aksi' => 'setujui', 'items' => [['id' => $detail->id, 'jumlah_disetujui' => -1]],
        ])->assertStatus(422);

        $this->assertSame('DISETUJUI_KARU', $req->fresh()->status);
        $this->assertSame(5, (int) $detail->fresh()->jumlah_disetujui);
    }
}
