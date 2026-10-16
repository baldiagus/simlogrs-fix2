<?php

namespace Tests\Feature\Aset;

use App\Enums\Aset\StatusPeminjaman;
use App\Livewire\Concerns\AuthorizesPeminjaman;
use App\Models\Aset\Aset;
use App\Models\Aset\PermintaanPeminjaman;
use App\Models\Aset\PermintaanPeminjamanDetail;
use App\Models\Master\Barang;
use App\Models\Master\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AsetPeminjamanApprovalApiTest extends TestCase
{
    use DatabaseTransactions;

    private Unit $unitPenyedia;
    private Unit $unitPeminjam;
    private Unit $unitLain;
    private User $kepalaTeknisiPenyedia;
    private User $kepalaTeknisiLain;
    private User $pengaju;
    private PermintaanPeminjaman $peminjaman;

    protected function setUp(): void
    {
        parent::setUp();

        $units = Unit::where('status', 'AKTIF')->take(3)->get();
        $this->unitPenyedia = $units[0];
        $this->unitPeminjam = $units[1];
        $this->unitLain = $units[2];

        $this->pengaju = $this->createUser('pengaju', $this->unitPeminjam->id);

        $this->kepalaTeknisiPenyedia = $this->createUser('karu-tek-penyedia', $this->unitPenyedia->id);
        $this->kepalaTeknisiPenyedia->assignRole('kepala_teknisi');
        $this->kepalaTeknisiPenyedia->givePermissionTo('update_layanan_unit::approval::peminjaman');

        $this->kepalaTeknisiLain = $this->createUser('karu-tek-lain', $this->unitLain->id);
        $this->kepalaTeknisiLain->assignRole('kepala_teknisi');
        $this->kepalaTeknisiLain->givePermissionTo('update_layanan_unit::approval::peminjaman');

        $this->peminjaman = $this->createPeminjaman($this->unitPenyedia->id);
    }

    public function test_kepala_teknisi_can_see_loan_in_approval_queue(): void
    {
        $response = $this->actingAs($this->kepalaTeknisiPenyedia)
            ->getJson('/api/v1/aset/approval-peminjaman');

        $response->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonFragment([
                'id' => $this->peminjaman->id,
                'kode' => $this->peminjaman->kode,
            ]);

        $responseLain = $this->actingAs($this->kepalaTeknisiLain)
            ->getJson('/api/v1/aset/approval-peminjaman');

        $responseLain->assertOk()
            ->assertJsonMissing([
                'id' => $this->peminjaman->id,
            ]);
    }

    public function test_kepala_teknisi_can_approve_loan(): void
    {
        $response = $this->actingAs($this->kepalaTeknisiPenyedia)
            ->postJson("/api/v1/aset/peminjaman/{$this->peminjaman->id}/otorisasi", [
                'aksi' => 'setujui',
            ]);

        $response->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('data.status', StatusPeminjaman::Disetujui->value);

        $this->assertSame(StatusPeminjaman::Disetujui->value, $this->peminjaman->fresh()->status);
        $this->assertEquals($this->kepalaTeknisiPenyedia->id, $this->peminjaman->fresh()->disetujui_oleh);
    }

    public function test_kepala_teknisi_of_another_unit_is_denied(): void
    {
        $response = $this->actingAs($this->kepalaTeknisiLain)
            ->postJson("/api/v1/aset/peminjaman/{$this->peminjaman->id}/otorisasi", [
                'aksi' => 'setujui',
            ]);

        $response->assertForbidden();
        $this->assertSame(StatusPeminjaman::Diajukan->value, $this->peminjaman->fresh()->status);
    }

    public function test_authorizes_peminjaman_trait_allows_kepala_teknisi_for_ruangan(): void
    {
        $tester = new class {
            use AuthorizesPeminjaman {
                bolehPeminjaman as public;
            }
        };

        $this->assertTrue(
            $tester->bolehPeminjaman($this->kepalaTeknisiPenyedia, $this->peminjaman, 'approve'),
            'Kepala teknisi satu unit harus diizinkan approve peminjaman RUANGAN.'
        );

        $this->assertFalse(
            $tester->bolehPeminjaman($this->kepalaTeknisiLain, $this->peminjaman, 'approve'),
            'Kepala teknisi unit lain tidak boleh approve peminjaman RUANGAN.'
        );
    }

    private function createUser(string $prefix, ?int $unitId): User
    {
        $uniq = uniqid();

        return User::create([
            'nama' => "{$prefix}-{$uniq}",
            'username' => "{$prefix}-{$uniq}",
            'email' => "{$prefix}-{$uniq}@simlog.test",
            'password' => bcrypt('password'),
            'unit_id' => $unitId,
            'status' => 'AKTIF',
        ]);
    }

    private function createPeminjaman(int $unitPenyediaId): PermintaanPeminjaman
    {
        $barang = Barang::where('status', 'AKTIF')->first();

        $aset = Aset::create([
            'barang_id' => $barang->id,
            'unit_id' => $unitPenyediaId,
            'kode_aset' => 'AST-API-' . uniqid(),
            'nomor_seri' => 'SN-API-' . uniqid(),
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 5000000,
            'kondisi' => 'BAIK',
            'status' => 'TERSEDIA',
        ]);

        $pinjam = PermintaanPeminjaman::create([
            'kode' => 'PINJ-API-' . uniqid(),
            'tipe_penyedia' => 'RUANGAN',
            'unit_penyedia_id' => $unitPenyediaId,
            'unit_id' => $this->unitPeminjam->id,
            'diajukan_oleh' => $this->pengaju->id,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(2)->toDateString(),
            'keperluan' => 'Uji coba otorisasi kepala teknisi',
            'status' => StatusPeminjaman::Diajukan->value,
        ]);

        PermintaanPeminjamanDetail::create([
            'permintaan_peminjaman_id' => $pinjam->id,
            'aset_id' => $aset->id,
            'kondisi_saat_pinjam' => 'BAIK',
        ]);

        return $pinjam;
    }
}
