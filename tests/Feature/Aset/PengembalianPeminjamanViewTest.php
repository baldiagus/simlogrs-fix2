<?php

namespace Tests\Feature\Aset;

use App\Livewire\Aset\PengembalianPeminjamanIndex;
use App\Models\Aset;
use App\Models\Barang;
use App\Models\PermintaanPeminjaman;
use App\Models\PermintaanPeminjamanDetail;
use App\Models\Unit;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class PengembalianPeminjamanViewTest extends TestCase
{
    use DatabaseTransactions;

    protected User $userPeminjam;
    protected User $userPenyedia;
    protected Unit $unitPenyedia;
    protected Unit $unitPeminjam;
    protected Barang $barangAset;
    protected Aset $aset;
    protected PermintaanPeminjaman $pinjam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitPenyedia = Unit::firstOrCreate(
            ['kode' => 'UNIT-PROV-TEST'],
            ['nama' => 'Unit Provider Test', 'jenis' => 'PELAYANAN_MEDIS', 'penanggung_jawab' => 'Tester Prov', 'lokasi_gedung' => 'Gedung A']
        );
        $this->unitPeminjam = Unit::firstOrCreate(
            ['kode' => 'UNIT-BORR-TEST'],
            ['nama' => 'Unit Borrower Test', 'jenis' => 'PELAYANAN_MEDIS', 'penanggung_jawab' => 'Tester Borr', 'lokasi_gedung' => 'Gedung B']
        );
        $this->userPeminjam = User::create([
            'nama' => 'Staf Peminjam Return Test',
            'username' => 'stafpeminjamreturn',
            'email' => 'stafpeminjamreturn@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $this->unitPeminjam->id,
            'status' => 'AKTIF',
        ]);
        $this->userPeminjam->givePermissionTo([
            'view_any_layanan_unit::peminjaman::aset',
            'view_layanan_unit::peminjaman::aset',
            'create_layanan_unit::peminjaman::aset',
            'update_layanan_unit::peminjaman::aset',
        ]);

        $this->barangAset = Barang::where('status', 'AKTIF')->where('tipe', 'ASET')->first()
            ?: Barang::where('status', 'AKTIF')->first();

        $this->aset = Aset::create([
            'barang_id' => $this->barangAset->id,
            'unit_id' => $this->unitPeminjam->id, // Saat dipinjam, unit_id beralih ke peminjam
            'kode_aset' => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri' => 'SN-RETURN-TEST',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 8000000,
            'kondisi' => 'BAIK',
            'status' => 'DIPINJAM',
        ]);

        $this->pinjam = PermintaanPeminjaman::create([
            'kode' => CodeGenerator::generate('PINJ', PermintaanPeminjaman::class, 'kode', 'Y', 3),
            'tipe_penyedia' => 'RUANGAN',
            'unit_penyedia_id' => $this->unitPenyedia->id,
            'unit_id' => $this->unitPeminjam->id,
            'diajukan_oleh' => $this->userPeminjam->id,
            'tanggal_pinjam' => now()->subDays(2)->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(3)->toDateString(),
            'keperluan' => 'Peminjaman untuk diuji pengembalian',
            'status' => 'DIPINJAM',
        ]);

        PermintaanPeminjamanDetail::create([
            'permintaan_peminjaman_id' => $this->pinjam->id,
            'aset_id' => $this->aset->id,
            'kondisi_saat_pinjam' => 'BAIK',
        ]);
    }

    public function test_borrower_cannot_trigger_inspection_modal_action(): void
    {
        $this->actingAs($this->userPeminjam);

        // Saat peminjam melihat daftar pengembalian, tombol "Proses Pengembalian" TIDAK BOLEH tampil untuk dia
        $component = Livewire::test(PengembalianPeminjamanIndex::class);
        $component->assertDontSee('Proses Pengembalian');

        // Dan jika method modal dipanggil langsung oleh peminjam -> HARUS 403 Forbidden
        $component->call('openPengembalianModal', $this->pinjam->id)
            ->assertForbidden();
    }
}
