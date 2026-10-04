<?php

namespace Tests\Feature\Aset;

use App\Livewire\Aset\PeminjamanAsetIndex;
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

class PeminjamanAsetTest extends TestCase
{
    use DatabaseTransactions;

    protected User $userPeminjam;
    protected Unit $unitPenyedia;
    protected Unit $unitPeminjam;
    protected Barang $barangAset;

    protected function setUp(): void
    {
        parent::setUp();

        $units = Unit::where('status', 'AKTIF')->take(2)->get();
        $this->unitPenyedia = $units[0];
        $this->unitPeminjam = $units[1];

        $this->userPeminjam = User::create([
            'nama' => 'Perawat Peminjam Test',
            'username' => 'peminjamtest',
            'email' => 'peminjamtest@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $this->unitPeminjam->id,
            'status' => 'AKTIF',
        ]);
        $this->userPeminjam->givePermissionTo([
            'create_layanan_unit::peminjaman::aset',
            'view_any_layanan_unit::peminjaman::aset',
        ]);
        $this->barangAset = Barang::where('status', 'AKTIF')->where('tipe', 'ASET')->first()
            ?: Barang::where('status', 'AKTIF')->first();
    }

    public function test_provider_selection_picks_unlocked_asset_when_another_unit_is_in_active_request(): void
    {
        $this->actingAs($this->userPeminjam);

        // 1. Buat Aset 1 dan Aset 2 di Unit Penyedia
        $aset1 = Aset::create([
            'barang_id' => $this->barangAset->id,
            'unit_id' => $this->unitPenyedia->id,
            'kode_aset' => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri' => 'SN-LOCKED-001',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 10000000,
            'kondisi' => 'BAIK',
            'status' => 'TERSEDIA',
        ]);

        $aset2 = Aset::create([
            'barang_id' => $this->barangAset->id,
            'unit_id' => $this->unitPenyedia->id,
            'kode_aset' => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri' => 'SN-AVAILABLE-002',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 10000000,
            'kondisi' => 'BAIK',
            'status' => 'TERSEDIA',
        ]);

        // 2. Kunci Aset 1 dengan permohonan pinjam aktif dari unit lain
        $pinjamLain = PermintaanPeminjaman::create([
            'kode' => CodeGenerator::generate('PINJ', PermintaanPeminjaman::class, 'kode', 'Y', 3),
            'tipe_penyedia' => 'RUANGAN',
            'unit_penyedia_id' => $this->unitPenyedia->id,
            'unit_id' => $this->unitPeminjam->id,
            'diajukan_oleh' => $this->userPeminjam->id,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(3)->toDateString(),
            'keperluan' => 'Tindakan unit lain',
            'status' => 'DIAJUKAN',
        ]);

        PermintaanPeminjamanDetail::create([
            'permintaan_peminjaman_id' => $pinjamLain->id,
            'aset_id' => $aset1->id,
            'kondisi_saat_pinjam' => 'BAIK',
        ]);

        // 3. User sekarang mengajukan permohonan baru untuk barang yang sama ke Unit Penyedia
        // selectPenyediaKey() HARUS memilih $aset2->id (bukan $aset1->id yang terkunci)!
        $component = Livewire::test(PeminjamanAsetIndex::class)
            ->call('createPinjam')
            ->set('selected_barang_id', $this->barangAset->id)
            ->call('selectPenyediaKey', "RUANGAN_{$this->unitPenyedia->id}")
            ->set('tanggal_pinjam', now()->toDateString())
            ->set('tanggal_kembali_rencana', now()->addDays(5)->toDateString())
            ->set('keperluan', 'Operasi pasien darurat')
            ->call('savePinjam');

        // Pastikan tidak ada error validasi aset terkunci
        $component->assertHasNoErrors();

        // Pastikan permohonan baru berhasil tersimpan mengacu ke $aset2->id
        $this->assertDatabaseHas('permintaan_peminjaman_detail', [
            'aset_id' => $aset2->id,
        ]);
    }
    public function test_user_cannot_cancel_or_extend_another_rooms_loan_request(): void
    {
        $this->actingAs($this->userPeminjam);

        // Buat unit ke-3 sebagai unit pemilik peminjaman asli
        $unitKetiga = Unit::where('status', 'AKTIF')->whereNotIn('id', [$this->unitPenyedia->id, $this->unitPeminjam->id])->first();
        $userKetiga = User::create([
            'nama' => 'Staf Unit Lain',
            'username' => 'stafunitlain',
            'email' => 'stafunitlain@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $unitKetiga->id,
            'status' => 'AKTIF',
        ]);

        $pinjamUnitLain = PermintaanPeminjaman::create([
            'kode' => CodeGenerator::generate('PINJ', PermintaanPeminjaman::class, 'kode', 'Y', 3),
            'tipe_penyedia' => 'RUANGAN',
            'unit_penyedia_id' => $this->unitPenyedia->id,
            'unit_id' => $unitKetiga->id, // Milik unit lain!
            'diajukan_oleh' => $userKetiga->id,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(5)->toDateString(),
            'keperluan' => 'Peminjaman sah unit lain',
            'status' => 'DIAJUKAN',
        ]);

        // User dari unit berbeda mencoba batalkan permohonan milik unit lain -> HARUS 403
        Livewire::test(PeminjamanAsetIndex::class)
            ->call('batalkanPinjam', $pinjamUnitLain->id)
            ->assertForbidden();

        $pinjamUnitLain->refresh();
        $this->assertEquals('DIAJUKAN', $pinjamUnitLain->status);

        // Ubah status ke DIPINJAM dan coba perpanjang -> HARUS 403
        $pinjamUnitLain->update(['status' => 'DIPINJAM']);

        Livewire::test(PeminjamanAsetIndex::class)
            ->set('perpanjangId', $pinjamUnitLain->id)
            ->set('tanggal_kembali_baru', now()->addDays(10)->toDateString())
            ->set('alasan_perpanjang', 'Upaya perpanjangan ilegal oleh unit lain')
            ->call('submitPerpanjang')
            ->assertForbidden();
    }
}
