<?php

namespace Tests\Feature\Aset;

use App\Livewire\Aset\PeminjamanAsetIndex;
use App\Models\Aset\Aset;
use App\Models\Master\Barang;
use App\Models\Aset\PermintaanPeminjaman;
use App\Models\Aset\PermintaanPeminjamanDetail;
use App\Models\Master\Unit;
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
        $satuanId = \App\Models\Master\SatuanBarang::firstOrCreate(['kode' => 'UNIT-PINJ'], ['nama' => 'Unit', 'status' => 'AKTIF'])->id;
        $kategoriId = \App\Models\Master\KategoriBarang::firstOrCreate(['kode' => 'KAT-PINJ'], ['nama' => 'Kategori Pinjam', 'status' => 'AKTIF'])->id;
        $this->barangAset = Barang::firstOrCreate(
            ['kode' => 'BRG-PINJ-ISOLATED'],
            [
                'nama' => 'Barang Pinjam Isolated',
                'tipe' => 'ASET',
                'satuan_id' => $satuanId,
                'kategori_id' => $kategoriId,
                'status' => 'AKTIF',
            ]
        );
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

    public function test_room_active_loans_table_only_shows_loans_requested_by_user_unit(): void
    {
        $userPenyedia = User::create([
            'nama' => 'Staf Penyedia Test',
            'username' => 'penyediatest',
            'email' => 'penyediatest@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $this->unitPenyedia->id,
            'status' => 'AKTIF',
        ]);
        $userPenyedia->givePermissionTo([
            'create_layanan_unit::peminjaman::aset',
            'view_any_layanan_unit::peminjaman::aset',
        ]);

        $unitKetiga = Unit::where('status', 'AKTIF')->whereNotIn('id', [$this->unitPenyedia->id, $this->unitPeminjam->id])->first();

        // Loan 1: Unit Peminjam meminjam aset ke Unit Penyedia
        $loan1 = PermintaanPeminjaman::create([
            'kode' => CodeGenerator::generate('PINJ', PermintaanPeminjaman::class, 'kode', 'Y', 3),
            'tipe_penyedia' => 'RUANGAN',
            'unit_penyedia_id' => $this->unitPenyedia->id,
            'unit_id' => $this->unitPeminjam->id,
            'diajukan_oleh' => $this->userPeminjam->id,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(3)->toDateString(),
            'keperluan' => 'Permohonan pinjam unit peminjam',
            'status' => 'DIAJUKAN',
        ]);

        // Loan 2: Unit Penyedia meminjam aset ke Unit Ketiga
        $loan2 = PermintaanPeminjaman::create([
            'kode' => CodeGenerator::generate('PINJ', PermintaanPeminjaman::class, 'kode', 'Y', 3),
            'tipe_penyedia' => 'RUANGAN',
            'unit_penyedia_id' => $unitKetiga->id,
            'unit_id' => $this->unitPenyedia->id,
            'diajukan_oleh' => $userPenyedia->id,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(5)->toDateString(),
            'keperluan' => 'Permohonan pinjam unit penyedia',
            'status' => 'DIAJUKAN',
        ]);

        // User Peminjam: HANYA boleh melihat loan1 (permohonan pinjam unitnya sendiri)
        // loan2 (permohonan unit lain) TIDAK BOLEH tampil
        $this->actingAs($this->userPeminjam);
        Livewire::test(PeminjamanAsetIndex::class)
            ->assertSee($loan1->kode)
            ->assertDontSee($loan2->kode);

        // User Penyedia: HANYA boleh melihat loan2 (permohonan pinjam unitnya sendiri)
        // loan1 (permohonan dari unit peminjam) TIDAK BOLEH tampil di sini
        $this->actingAs($userPenyedia);
        Livewire::test(PeminjamanAsetIndex::class)
            ->assertSee($loan2->kode)
            ->assertDontSee($loan1->kode);
    }

    public function test_reported_asset_is_excluded_from_loanable_providers_and_catalog(): void
    {
        $this->actingAs($this->userPeminjam);

        // 1. Buat Aset di Unit Penyedia
        $aset = Aset::create([
            'barang_id' => $this->barangAset->id,
            'unit_id' => $this->unitPenyedia->id,
            'kode_aset' => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri' => 'SN-MAINT-TEST-001',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 10000000,
            'kondisi' => 'BAIK',
            'status' => 'TERSEDIA',
        ]);

        // 2. Buat tiket laporan kerusakan aktif untuk aset tersebut
        \App\Models\Ipsrs\PermintaanMaintenance::create([
            'kode'              => CodeGenerator::generateMnt(),
            'aset_id'           => $aset->id,
            'unit_id'           => $this->unitPenyedia->id,
            'dilaporkan_oleh'   => $this->userPeminjam->id,
            'deskripsi_masalah' => 'Sensor mengalami disfungsi',
            'prioritas'         => 'SEDANG',
            'status'            => 'DILAPORKAN',
        ]);

        // 3. Pastikan aset ini tidak terpilih saat memilih penyedia
        $component = Livewire::test(PeminjamanAsetIndex::class)
            ->call('createPinjam')
            ->set('selected_barang_id', $this->barangAset->id)
            ->call('selectPenyediaKey', "RUANGAN_{$this->unitPenyedia->id}");

        $this->assertNotEquals($aset->id, $component->get('selected_aset_id'));
    }

    public function test_cannot_submit_loan_request_for_asset_with_active_maintenance(): void
    {
        $this->actingAs($this->userPeminjam);

        $aset = Aset::create([
            'barang_id' => $this->barangAset->id,
            'unit_id' => $this->unitPenyedia->id,
            'kode_aset' => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri' => 'SN-MAINT-BLOCK-001',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 10000000,
            'kondisi' => 'BAIK',
            'status' => 'TERSEDIA',
        ]);

        \App\Models\Ipsrs\PermintaanMaintenance::create([
            'kode'              => CodeGenerator::generateMnt(),
            'aset_id'           => $aset->id,
            'unit_id'           => $this->unitPenyedia->id,
            'dilaporkan_oleh'   => $this->userPeminjam->id,
            'deskripsi_masalah' => 'Kerusakan fisik terlaporkan',
            'prioritas'         => 'SEDANG',
            'status'            => 'DILAPORKAN',
        ]);

        Livewire::test(PeminjamanAsetIndex::class)
            ->call('createPinjam')
            ->set('selected_barang_id', $this->barangAset->id)
            ->set('selected_penyedia_key', "RUANGAN_{$this->unitPenyedia->id}")
            ->set('tipe_penyedia', 'RUANGAN')
            ->set('unit_penyedia_id', $this->unitPenyedia->id)
            ->set('selected_aset_id', $aset->id)
            ->set('tanggal_pinjam', now()->toDateString())
            ->set('tanggal_kembali_rencana', now()->addDays(3)->toDateString())
            ->set('keperluan', 'Uji coba pinjam alat rusak')
            ->call('savePinjam')
            ->assertHasErrors(['selected_aset_id']);
    }

    public function test_user_can_borrow_multiple_asset_units_in_single_request(): void
    {
        $this->actingAs($this->userPeminjam);

        // Buat 3 unit aset tersedia di Unit Penyedia
        $asetA = Aset::create([
            'barang_id' => $this->barangAset->id,
            'unit_id' => $this->unitPenyedia->id,
            'kode_aset' => 'AST-QTY-A-' . uniqid(),
            'tanggal_perolehan' => now()->toDateString(),
            'status' => 'TERSEDIA',
            'kondisi' => 'BAIK',
        ]);
        $asetB = Aset::create([
            'barang_id' => $this->barangAset->id,
            'unit_id' => $this->unitPenyedia->id,
            'kode_aset' => 'AST-QTY-B-' . uniqid(),
            'tanggal_perolehan' => now()->toDateString(),
            'status' => 'TERSEDIA',
            'kondisi' => 'BAIK',
        ]);
        $asetC = Aset::create([
            'barang_id' => $this->barangAset->id,
            'unit_id' => $this->unitPenyedia->id,
            'kode_aset' => 'AST-QTY-C-' . uniqid(),
            'tanggal_perolehan' => now()->toDateString(),
            'status' => 'TERSEDIA',
            'kondisi' => 'BAIK',
        ]);

        Livewire::test(PeminjamanAsetIndex::class)
            ->call('createPinjam')
            ->set('selected_barang_id', $this->barangAset->id)
            ->call('selectPenyediaKey', "RUANGAN_{$this->unitPenyedia->id}")
            ->set('quantity', 2)
            ->set('tanggal_pinjam', now()->toDateString())
            ->set('tanggal_kembali_rencana', now()->addDays(3)->toDateString())
            ->set('keperluan', 'Peminjaman 2 unit sekaligus untuk ruang ICU')
            ->call('savePinjam')
            ->assertHasNoErrors();

        $latestPinjam = PermintaanPeminjaman::where('unit_id', $this->unitPeminjam->id)->latest('id')->first();
        $this->assertNotNull($latestPinjam);
        $this->assertEquals(2, $latestPinjam->detail()->count());
    }

    public function test_borrow_quantity_cannot_exceed_available_units_at_provider(): void
    {
        $this->actingAs($this->userPeminjam);

        // Hanya buat 1 aset
        Aset::create([
            'barang_id' => $this->barangAset->id,
            'unit_id' => $this->unitPenyedia->id,
            'kode_aset' => 'AST-QTY-ONLY1-' . uniqid(),
            'tanggal_perolehan' => now()->toDateString(),
            'status' => 'TERSEDIA',
            'kondisi' => 'BAIK',
        ]);

        Livewire::test(PeminjamanAsetIndex::class)
            ->call('createPinjam')
            ->set('selected_barang_id', $this->barangAset->id)
            ->call('selectPenyediaKey', "RUANGAN_{$this->unitPenyedia->id}")
            ->set('quantity', 99) // melebihi stok tersedia
            ->set('tanggal_pinjam', now()->toDateString())
            ->set('tanggal_kembali_rencana', now()->addDays(3)->toDateString())
            ->set('keperluan', 'Uji coba quantity berlebih')
            ->call('savePinjam')
            ->assertHasErrors(['quantity']);
    }

    public function test_searching_provider_in_loan_modal_filters_displayed_cards(): void
    {
        $this->actingAs($this->userPeminjam);

        // Buat aset di unit penyedia
        Aset::create([
            'barang_id' => $this->barangAset->id,
            'unit_id' => $this->unitPenyedia->id,
            'kode_aset' => 'AST-SEARCH-P1-' . uniqid(),
            'tanggal_perolehan' => now()->toDateString(),
            'status' => 'TERSEDIA',
            'kondisi' => 'BAIK',
        ]);

        // Filter dengan nama unit yang cocok
        Livewire::test(PeminjamanAsetIndex::class)
            ->call('createPinjam')
            ->set('selected_barang_id', $this->barangAset->id)
            ->set('searchPenyedia', substr($this->unitPenyedia->nama, 0, 4))
            ->assertSee($this->unitPenyedia->nama)
            ->set('searchPenyedia', 'NAMA_TIDAK_ADA_XYZ')
            ->assertDontSee($this->unitPenyedia->nama);
    }

    public function test_quantity_is_clamped_to_selected_provider_stock(): void
    {
        $this->actingAs($this->userPeminjam);

        Aset::create([
            'barang_id' => $this->barangAset->id,
            'unit_id' => $this->unitPenyedia->id,
            'kode_aset' => 'AST-CLAMP-P1-' . uniqid(),
            'tanggal_perolehan' => now()->toDateString(),
            'status' => 'TERSEDIA',
            'kondisi' => 'BAIK',
        ]);

        $test = Livewire::test(PeminjamanAsetIndex::class)
            ->call('createPinjam')
            ->set('selected_barang_id', $this->barangAset->id)
            ->set('quantity', 10)
            ->call('selectPenyediaKey', "RUANGAN_{$this->unitPenyedia->id}");

        // Harus di-clamp ke maksimal unit tersedia
        $this->assertEquals(1, $test->get('quantity'));
    }
}
