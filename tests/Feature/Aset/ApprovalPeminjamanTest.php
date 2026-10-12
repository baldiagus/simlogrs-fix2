<?php

namespace Tests\Feature\Aset;

use App\Livewire\Aset\ApprovalPeminjamanIndex;
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

class ApprovalPeminjamanTest extends TestCase
{
    use DatabaseTransactions;

    protected User $userPenyedia;
    protected Unit $unitPenyedia;
    protected Unit $unitPeminjam1;
    protected Unit $unitPeminjam2;
    protected Barang $barangAset;
    protected Aset $aset;

    protected function setUp(): void
    {
        parent::setUp();

        $units = Unit::where('status', 'AKTIF')->take(3)->get();
        $this->unitPenyedia = $units[0];
        $this->unitPeminjam1 = $units[1];
        $this->unitPeminjam2 = $units[2];

        // User Karu Penyedia
        $this->userPenyedia = User::create([
            'nama' => 'Karu Penyedia Test',
            'username' => 'karupenyediatest',
            'email' => 'karupenyedia@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $this->unitPenyedia->id,
            'status' => 'AKTIF',
        ]);
        $this->userPenyedia->givePermissionTo([
            'view_any_layanan_unit::approval::peminjaman',
            'update_layanan_unit::approval::peminjaman',
            'view_any_layanan_unit::peminjaman::aset',
            'update_layanan_unit::peminjaman::aset',
        ]);
        $this->userPenyedia->assignRole('kepala_unit'); // tahap approval = role + permission (ADR 0001)

        $this->barangAset = Barang::where('status', 'AKTIF')->where('tipe', 'ASET')->first()
            ?: Barang::where('status', 'AKTIF')->first();

        // Buat 1 aset fisik di unit penyedia
        $this->aset = Aset::create([
            'barang_id' => $this->barangAset->id,
            'unit_id' => $this->unitPenyedia->id,
            'kode_aset' => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri' => 'SN-DOUBLE-001',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 10000000,
            'kondisi' => 'BAIK',
            'status' => 'TERSEDIA',
        ]);
    }

    public function test_concurrent_or_subsequent_approval_on_same_asset_prevents_double_booking(): void
    {
        $this->actingAs($this->userPenyedia);

        // Permintaan 1 dari Unit Peminjam 1
        $pinjam1 = PermintaanPeminjaman::create([
            'kode' => CodeGenerator::generate('PINJ', PermintaanPeminjaman::class, 'kode', 'Y', 3),
            'tipe_penyedia' => 'RUANGAN',
            'unit_penyedia_id' => $this->unitPenyedia->id,
            'unit_id' => $this->unitPeminjam1->id,
            'diajukan_oleh' => $this->userPenyedia->id,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(3)->toDateString(),
            'keperluan' => 'Permintaan unit 1',
            'status' => 'DIAJUKAN',
        ]);
        PermintaanPeminjamanDetail::create([
            'permintaan_peminjaman_id' => $pinjam1->id,
            'aset_id' => $this->aset->id,
            'kondisi_saat_pinjam' => 'BAIK',
        ]);

        // Permintaan 2 dari Unit Peminjam 2 (untuk aset fisik yang sama)
        $pinjam2 = PermintaanPeminjaman::create([
            'kode' => CodeGenerator::generate('PINJ', PermintaanPeminjaman::class, 'kode', 'Y', 3),
            'tipe_penyedia' => 'RUANGAN',
            'unit_penyedia_id' => $this->unitPenyedia->id,
            'unit_id' => $this->unitPeminjam2->id,
            'diajukan_oleh' => $this->userPenyedia->id,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(4)->toDateString(),
            'keperluan' => 'Permintaan unit 2',
            'status' => 'DIAJUKAN',
        ]);
        PermintaanPeminjamanDetail::create([
            'permintaan_peminjaman_id' => $pinjam2->id,
            'aset_id' => $this->aset->id,
            'kondisi_saat_pinjam' => 'BAIK',
        ]);

        // 1. Setujui Permintaan 1
        Livewire::test(ApprovalPeminjamanIndex::class)
            ->call('approve', $pinjam1->id);

        $pinjam1->refresh();
        $this->assertEquals('DISETUJUI', $pinjam1->status);

        // 2. Coba setujui Permintaan 2 -> HARUS DIGAGALKAN (status $pinjam2 tetap DIAJUKAN)
        Livewire::test(ApprovalPeminjamanIndex::class)
            ->call('approve', $pinjam2->id);

        $pinjam2->refresh();
        $this->assertEquals('DIAJUKAN', $pinjam2->status, 'Permintaan 2 tidak boleh disetujui untuk aset yang sudah disetujui.');
    }

    public function test_set_filter_tab_updates_filter_status_and_resets_filters(): void
    {
        $this->actingAs($this->userPenyedia);

        Livewire::test(ApprovalPeminjamanIndex::class)
            ->assertViewHas('countSemua')
            ->assertSet('filterStatus', '')
            ->call('setFilterTab', 'DIAJUKAN')
            ->assertSet('filterStatus', 'DIAJUKAN')
            ->set('search', 'test')
            ->call('resetFilters')
            ->assertSet('filterStatus', '')
            ->assertSet('search', '');
    }
}
