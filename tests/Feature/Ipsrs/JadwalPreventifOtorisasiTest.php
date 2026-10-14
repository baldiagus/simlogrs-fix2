<?php

namespace Tests\Feature\Ipsrs;

use App\Livewire\Ipsrs\PemeliharaanBerkalaIndex;
use App\Models\Aset\Aset;
use App\Models\Ipsrs\JadwalMaintenance;
use App\Models\Master\Barang;
use App\Models\Master\Unit;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Otorisasi eksekusi SPK preventif (API) dan modal detail jadwal (web).
 */
class JadwalPreventifOtorisasiTest extends TestCase
{
    use DatabaseTransactions;

    private JadwalMaintenance $jadwal;

    /**
     * Siapkan satu jadwal preventif aktif milik seorang teknisi.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $teknisi = $this->user('teknisi_prv', 'teknisi');
        $barang = Barang::where('status', 'AKTIF')->first();
        $aset = Aset::create([
            'barang_id' => $barang->id,
            'unit_id' => Unit::where('status', 'AKTIF')->first()->id,
            'kode_aset' => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri' => 'SN-PRV-OTOR',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 1000000,
            'kondisi' => 'BAIK',
            'status' => 'DIGUNAKAN',
        ]);
        $this->jadwal = JadwalMaintenance::create([
            'kode' => CodeGenerator::generatePrv(),
            'aset_id' => $aset->id,
            'jenis_maintenance' => 'Servis Uji Otorisasi',
            'frekuensi' => 'BULANAN',
            'interval_nilai' => 1,
            'interval_tipe' => 'BULAN',
            'tanggal_berikutnya' => now()->toDateString(),
            'teknisi_id' => $teknisi->id,
            'status' => 'AKTIF',
            'catatan' => 'Catatan uji detail',
            'dibuat_oleh' => $teknisi->id,
        ]);
    }

    /**
     * Buat pengguna aktif dengan satu peran dan izin SPK + lihat jadwal.
     */
    private function user(string $username, string $role): User
    {
        $u = User::create([
            'nama' => ucfirst($username), 'username' => $username, 'email' => "$username@simlog.test",
            'password' => bcrypt('password'), 'status' => 'AKTIF',
        ]);
        $u->assignRole($role);
        $u->givePermissionTo([
            'create_pemeliharaan::spk::perbaikan',
            'view_any_pemeliharaan::pemeliharaan::berkala',
            'view_pemeliharaan::pemeliharaan::berkala',
        ]);

        return $u;
    }

    /**
     * Teknisi berizin SPK tetap ditolak 403 di API, selaras dengan aturan web.
     */
    public function test_teknisi_ditolak_menerbitkan_spk_preventif_via_api(): void
    {
        $this->actingAs(User::where('username', 'teknisi_prv')->first())
            ->postJson("/api/v1/maintenance/preventif/{$this->jadwal->id}/eksekusi")
            ->assertForbidden();
    }

    /**
     * Kepala teknisi berizin SPK berhasil menerbitkan SPK preventif via API.
     */
    public function test_kepala_teknisi_boleh_menerbitkan_spk_preventif_via_api(): void
    {
        $this->actingAs($this->user('kateknisi_prv', 'kepala_teknisi'))
            ->postJson("/api/v1/maintenance/preventif/{$this->jadwal->id}/eksekusi")
            ->assertOk();
    }

    /**
     * Modal detail terbuka dan menampilkan data jadwal yang benar.
     */
    public function test_modal_detail_terbuka_dengan_data_jadwal(): void
    {
        Livewire::actingAs($this->user('kateknisi_dtl', 'kepala_teknisi'))
            ->test(PemeliharaanBerkalaIndex::class)
            ->call('lihatDetail', $this->jadwal->id)
            ->assertSet('showDetail', true)
            ->assertSet('detailId', $this->jadwal->id)
            ->assertSee('Servis Uji Otorisasi')
            ->assertSee('Catatan uji detail')
            ->assertSee('Terbitkan SPK');
    }

    /**
     * Teknisi melihat detail tetapi tanpa tombol Terbitkan SPK.
     */
    public function test_detail_tanpa_tombol_spk_untuk_teknisi(): void
    {
        Livewire::actingAs(User::where('username', 'teknisi_prv')->first())
            ->test(PemeliharaanBerkalaIndex::class)
            ->call('lihatDetail', $this->jadwal->id)
            ->assertSet('showDetail', true)
            ->assertDontSee('Terbitkan SPK');
    }
}
