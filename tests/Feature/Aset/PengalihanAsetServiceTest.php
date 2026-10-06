<?php

namespace Tests\Feature\Aset;

use App\Models\Aset;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\LokasiGudang;
use App\Models\PengalihanAset;
use App\Models\Unit;
use App\Models\User;
use App\Services\Aset\PengalihanAsetService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PengalihanAsetServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected User $userPengirim;
    protected User $userPenerima;
    protected User $userKaru;
    protected Unit $unitAsal;
    protected Unit $unitTujuan;
    protected Gudang $gudangTujuan;
    protected LokasiGudang $rakTujuan;
    protected Aset $asetUji;

    protected function setUp(): void
    {
        parent::setUp();

        $units = Unit::where('status', 'AKTIF')->take(2)->get();
        $this->unitAsal = $units[0];
        $this->unitTujuan = $units[1];

        $this->gudangTujuan = Gudang::where('status', 'AKTIF')->first();
        $this->rakTujuan = LokasiGudang::firstOrCreate(
            ['gudang_id' => $this->gudangTujuan->id, 'kode' => 'RAK-TEST-TF'],
            ['nama' => 'Rak Uji Pengalihan', 'kapasitas' => 50, 'status' => 'AKTIF']
        );

        $this->userPengirim = User::create([
            'nama' => 'Perawat Pengirim',
            'username' => 'perawat_kirim',
            'email' => 'kirim@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $this->unitAsal->id,
            'status' => 'AKTIF',
        ]);

        $this->userPenerima = User::create([
            'nama' => 'Perawat Penerima',
            'username' => 'perawat_terima',
            'email' => 'terima@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $this->unitTujuan->id,
            'status' => 'AKTIF',
        ]);

        $this->userKaru = User::create([
            'nama' => 'Karu Otorisator',
            'username' => 'karu_oto',
            'email' => 'karu_oto@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $this->unitAsal->id,
            'status' => 'AKTIF',
        ]);

        $satuanId = \App\Models\SatuanBarang::firstOrCreate(['kode' => 'UNIT-TF'], ['nama' => 'Unit', 'status' => 'AKTIF'])->id;
        $kategoriId = \App\Models\KategoriBarang::firstOrCreate(['kode' => 'KAT-TF'], ['nama' => 'Kategori TF', 'status' => 'AKTIF'])->id;
        $barang = Barang::firstOrCreate(
            ['kode' => 'BRG-TF-001'],
            [
                'nama' => 'Alat Medis Uji Transfer',
                'tipe' => 'ASET',
                'satuan_id' => $satuanId,
                'kategori_id' => $kategoriId,
                'status' => 'AKTIF',
            ]
        );

        $this->asetUji = Aset::create([
            'barang_id' => $barang->id,
            'unit_id' => $this->unitAsal->id,
            'gudang_id' => null,
            'lokasi_gudang_id' => null,
            'kode_aset' => 'AST-TF-' . uniqid(),
            'nomor_seri' => 'SN-TF-999',
            'tanggal_perolehan' => now()->toDateString(),
            'kondisi' => 'BAIK',
            'status' => 'TERSEDIA',
        ]);
    }

    public function test_submitting_pengalihan_locks_asset_status_to_dalam_pengalihan_and_creates_document(): void
    {
        $service = app(PengalihanAsetService::class);

        $transfer = $service->ajukanPengalihan([
            'tipe_asal' => 'RUANGAN',
            'unit_asal_id' => $this->unitAsal->id,
            'tipe_tujuan' => 'RUANGAN',
            'unit_tujuan_id' => $this->unitTujuan->id,
            'tanggal_pengalihan' => now()->toDateString(),
            'alasan' => 'Relokasi ruang tindakan ICU',
            'diajukan_oleh' => $this->userPengirim->id,
            'aset_ids' => [$this->asetUji->id],
        ]);

        $this->assertInstanceOf(PengalihanAset::class, $transfer);
        $this->assertStringStartsWith('TRF-', $transfer->kode);
        $this->assertEquals('DIAJUKAN', $transfer->status);
        $this->assertCount(1, $transfer->detail);

        $this->asetUji->refresh();
        $this->assertEquals('DALAM_PENGALIHAN', $this->asetUji->status);
    }

    public function test_cannot_transfer_asset_with_invalid_status_or_location(): void
    {
        $this->asetUji->update(['status' => 'DALAM_MAINTENANCE']);
        $service = app(PengalihanAsetService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/tidak dapat dialihkan/');

        $service->ajukanPengalihan([
            'tipe_asal' => 'RUANGAN',
            'unit_asal_id' => $this->unitAsal->id,
            'tipe_tujuan' => 'RUANGAN',
            'unit_tujuan_id' => $this->unitTujuan->id,
            'tanggal_pengalihan' => now()->toDateString(),
            'alasan' => 'Relokasi ruang tindakan ICU',
            'diajukan_oleh' => $this->userPengirim->id,
            'aset_ids' => [$this->asetUji->id],
        ]);
    }

    public function test_approving_transfer_sets_status_to_disetujui(): void
    {
        $service = app(PengalihanAsetService::class);

        $transfer = $service->ajukanPengalihan([
            'tipe_asal' => 'RUANGAN',
            'unit_asal_id' => $this->unitAsal->id,
            'tipe_tujuan' => 'RUANGAN',
            'unit_tujuan_id' => $this->unitTujuan->id,
            'tanggal_pengalihan' => now()->toDateString(),
            'alasan' => 'Relokasi ruang tindakan ICU',
            'diajukan_oleh' => $this->userPengirim->id,
            'aset_ids' => [$this->asetUji->id],
        ]);

        $service->setujuiPengalihan($transfer->id, $this->userKaru->id, 'Disetujui untuk pemindahan alat');

        $transfer->refresh();
        $this->assertEquals('DISETUJUI', $transfer->status);
        $this->assertEquals($this->userKaru->id, $transfer->disetujui_oleh);
    }

    public function test_rejecting_transfer_unlocks_asset_back_to_tersedia(): void
    {
        $service = app(PengalihanAsetService::class);

        $transfer = $service->ajukanPengalihan([
            'tipe_asal' => 'RUANGAN',
            'unit_asal_id' => $this->unitAsal->id,
            'tipe_tujuan' => 'RUANGAN',
            'unit_tujuan_id' => $this->unitTujuan->id,
            'tanggal_pengalihan' => now()->toDateString(),
            'alasan' => 'Relokasi ruang tindakan ICU',
            'diajukan_oleh' => $this->userPengirim->id,
            'aset_ids' => [$this->asetUji->id],
        ]);

        $service->tolakPengalihan($transfer->id, $this->userKaru->id, 'Unit asal masih memerlukan alat ini');

        $transfer->refresh();
        $this->assertEquals('DITOLAK', $transfer->status);

        $this->asetUji->refresh();
        $this->assertEquals('TERSEDIA', $this->asetUji->status);
    }

    public function test_executing_receipt_permanently_mutates_asset_location_with_xor_integrity(): void
    {
        $service = app(PengalihanAsetService::class);

        // Unit -> Unit transfer
        $transfer = $service->ajukanPengalihan([
            'tipe_asal' => 'RUANGAN',
            'unit_asal_id' => $this->unitAsal->id,
            'tipe_tujuan' => 'RUANGAN',
            'unit_tujuan_id' => $this->unitTujuan->id,
            'tanggal_pengalihan' => now()->toDateString(),
            'alasan' => 'Relokasi permanen ke ICU',
            'diajukan_oleh' => $this->userPengirim->id,
            'aset_ids' => [$this->asetUji->id],
        ]);

        $service->setujuiPengalihan($transfer->id, $this->userKaru->id);

        $service->eksekusiSerahTerima($transfer->id, $this->userPenerima->id, [
            $this->asetUji->id => ['kondisi' => 'BAIK', 'catatan' => 'Fisik mulus dan berfungsi normal'],
        ]);

        $transfer->refresh();
        $this->assertEquals('SELESAI', $transfer->status);
        $this->assertEquals($this->userPenerima->id, $transfer->diterima_oleh);
        $this->assertNotNull($transfer->tanggal_serah_terima);

        // Verifikasi aset termutasi permanen
        $this->asetUji->refresh();
        $this->assertEquals($this->unitTujuan->id, $this->asetUji->unit_id);
        $this->assertNull($this->asetUji->gudang_id);
        $this->assertNull($this->asetUji->lokasi_gudang_id);
        $this->assertEquals('TERSEDIA', $this->asetUji->status);
        $this->assertEquals('BAIK', $this->asetUji->kondisi);
    }

    public function test_transfer_from_unit_to_warehouse_clears_unit_and_assigns_warehouse_rack(): void
    {
        $service = app(PengalihanAsetService::class);

        // Unit -> Gudang transfer
        $transfer = $service->ajukanPengalihan([
            'tipe_asal' => 'RUANGAN',
            'unit_asal_id' => $this->unitAsal->id,
            'tipe_tujuan' => 'GUDANG',
            'gudang_tujuan_id' => $this->gudangTujuan->id,
            'lokasi_gudang_tujuan_id' => $this->rakTujuan->id,
            'tanggal_pengalihan' => now()->toDateString(),
            'alasan' => 'Pengembalian permanen ke gudang sentral',
            'diajukan_oleh' => $this->userPengirim->id,
            'aset_ids' => [$this->asetUji->id],
        ]);

        $service->setujuiPengalihan($transfer->id, $this->userKaru->id);

        $service->eksekusiSerahTerima($transfer->id, $this->userPenerima->id);

        // Verifikasi aset berada di gudang dan unit_id menjadi NULL (patuh XOR constraint)
        $this->asetUji->refresh();
        $this->assertNull($this->asetUji->unit_id);
        $this->assertEquals($this->gudangTujuan->id, $this->asetUji->gudang_id);
        $this->assertEquals($this->rakTujuan->id, $this->asetUji->lokasi_gudang_id);
        $this->assertEquals('TERSEDIA', $this->asetUji->status);
    }
}
