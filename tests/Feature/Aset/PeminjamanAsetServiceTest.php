<?php

namespace Tests\Feature\Aset;

use App\Models\Aset;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\LokasiGudang;
use App\Models\PermintaanMaintenance;
use App\Models\PermintaanPeminjaman;
use App\Models\PermintaanPeminjamanDetail;
use App\Models\Unit;
use App\Models\User;
use App\Services\Aset\PeminjamanAsetService;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PeminjamanAsetServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Unit $unitPeminjam;
    protected Gudang $gudangPenyedia;
    protected Barang $barangAset;
    protected LokasiGudang $lokasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::where('email', 'superadmin@simlogrs.local')->first();
        $this->unitPeminjam = Unit::where('status', 'AKTIF')->first();
        $this->gudangPenyedia = Gudang::where('status', 'AKTIF')->first();
        $this->lokasi = LokasiGudang::where('gudang_id', $this->gudangPenyedia->id)->where('status', 'AKTIF')->first();

        $this->barangAset = Barang::where('status', 'AKTIF')->where('tipe', 'ASET')->first()
            ?: Barang::where('status', 'AKTIF')->first();
    }

    public function test_damaged_warehouse_asset_return_triggers_maintenance_without_misassigning_borrower_unit(): void
    {
        // 1. Buat aset milik Gudang Penyedia
        $aset = Aset::create([
            'barang_id' => $this->barangAset->id,
            'unit_id' => null,
            'gudang_id' => $this->gudangPenyedia->id,
            'lokasi_gudang_id' => $this->lokasi->id,
            'kode_aset' => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri' => 'SN-GUDANG-MNT-01',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 7500000,
            'kondisi' => 'BAIK',
            'status' => 'TERSEDIA',
        ]);

        // 2. Transaksi peminjaman dari Gudang ke Unit Peminjam
        $pinjam = PermintaanPeminjaman::create([
            'kode' => CodeGenerator::generate('PINJ', PermintaanPeminjaman::class, 'kode', 'Y', 3),
            'tipe_penyedia' => 'GUDANG',
            'gudang_penyedia_id' => $this->gudangPenyedia->id,
            'unit_penyedia_id' => null,
            'unit_id' => $this->unitPeminjam->id,
            'diajukan_oleh' => $this->user->id,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(5)->toDateString(),
            'keperluan' => 'Peminjaman alat gudang',
            'status' => 'DISETUJUI',
        ]);

        $pinjamDetail = PermintaanPeminjamanDetail::create([
            'permintaan_peminjaman_id' => $pinjam->id,
            'aset_id' => $aset->id,
            'kondisi_saat_pinjam' => 'BAIK',
        ]);

        $service = app(PeminjamanAsetService::class);

        // 3. Serah terima fisik -> aset beralih ke DIPINJAM
        $service->serahTerimaFisik($pinjam, $this->user);
        $aset->refresh();
        $this->assertEquals('DIPINJAM', $aset->status);

        // 4. Proses pengembalian dengan kondisi RUSAK_BERAT
        $inspeksiItems = [
            [
                'permintaan_peminjaman_detail_id' => $pinjamDetail->id,
                'aset_id' => $aset->id,
                'kondisi_saat_kembali' => 'RUSAK_BERAT',
                'catatan' => 'Display monitor konslet',
            ]
        ];

        $pengembalian = $service->prosesPengembalian($pinjam, $inspeksiItems, $this->user, 'Inspeksi kembali rusak');

        // 5. Verifikasi tiket maintenance terbit otomatis
        $maintenance = PermintaanMaintenance::where('aset_id', $aset->id)->latest('id')->first();
        $this->assertNotNull($maintenance, 'PermintaanMaintenance otomatis tidak terbit.');
        $this->assertEquals('RUSAK_BERAT', $aset->fresh()->kondisi);
        $this->assertEquals('DALAM_MAINTENANCE', $aset->fresh()->status);

        // Tiket maintenance untuk aset milik GUDANG TIDAK BOLEH membebankan unit_id peminjam!
        // unit_id harus NULL (aset sentral/gudang)
        $this->assertNull($maintenance->unit_id, 'unit_id maintenance tidak boleh membebani unit peminjam.');
    }
    public function test_returned_warehouse_asset_restores_valid_bin_location(): void
    {
        // 1. Aset gudang di lokasi rak tertentu
        $aset = Aset::create([
            'barang_id' => $this->barangAset->id,
            'unit_id' => null,
            'gudang_id' => $this->gudangPenyedia->id,
            'lokasi_gudang_id' => $this->lokasi->id,
            'kode_aset' => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri' => 'SN-GUDANG-RACK-01',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 7500000,
            'kondisi' => 'BAIK',
            'status' => 'TERSEDIA',
        ]);

        // 2. Peminjaman
        $pinjam = PermintaanPeminjaman::create([
            'kode' => CodeGenerator::generate('PINJ', PermintaanPeminjaman::class, 'kode', 'Y', 3),
            'tipe_penyedia' => 'GUDANG',
            'gudang_penyedia_id' => $this->gudangPenyedia->id,
            'unit_penyedia_id' => null,
            'unit_id' => $this->unitPeminjam->id,
            'diajukan_oleh' => $this->user->id,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(5)->toDateString(),
            'keperluan' => 'Peminjaman alat gudang',
            'status' => 'DISETUJUI',
        ]);

        $pinjamDetail = PermintaanPeminjamanDetail::create([
            'permintaan_peminjaman_id' => $pinjam->id,
            'aset_id' => $aset->id,
            'kondisi_saat_pinjam' => 'BAIK',
        ]);

        $service = app(PeminjamanAsetService::class);
        $service->serahTerimaFisik($pinjam, $this->user);

        // Saat dipinjam: lokasi_gudang_id disetel null
        $this->assertNull($aset->fresh()->lokasi_gudang_id);

        // 3. Pengembalian dalam kondisi BAIK
        $inspeksiItems = [
            [
                'permintaan_peminjaman_detail_id' => $pinjamDetail->id,
                'aset_id' => $aset->id,
                'kondisi_saat_kembali' => 'BAIK',
                'catatan' => 'Kembali lengkap dan mulus',
            ]
        ];

        $service->prosesPengembalian($pinjam, $inspeksiItems, $this->user);

        // 4. Verifikasi: aset kembali ke gudang DAN lokasi_gudang_id TIDAK BOLEH NULL!
        $asetFresh = $aset->fresh();
        $this->assertEquals($this->gudangPenyedia->id, $asetFresh->gudang_id);
        $this->assertNotNull($asetFresh->lokasi_gudang_id, 'lokasi_gudang_id tidak boleh null saat aset gudang kembali.');
    }
}
