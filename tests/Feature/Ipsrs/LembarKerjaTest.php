<?php

namespace Tests\Feature\Ipsrs;

use App\Livewire\Ipsrs\LembarKerjaPengerjaanIndex;
use App\Models\Aset;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\LokasiGudang;
use App\Models\PenugasanMaintenance;
use App\Models\PermintaanMaintenance;
use App\Models\StokBatch;
use App\Models\Unit;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class LembarKerjaTest extends TestCase
{
    use DatabaseTransactions;

    protected User $userTeknisi;
    protected Unit $unit;
    protected Gudang $gudang;
    protected LokasiGudang $lokasi;
    protected Barang $barangAset;
    protected Barang $sparepart;
    protected Aset $aset;
    protected PermintaanMaintenance $tiket;
    protected PenugasanMaintenance $spk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = Unit::where('status', 'AKTIF')->first();
        $this->gudang = Gudang::where('status', 'AKTIF')->first();
        $this->lokasi = LokasiGudang::firstOrCreate(
            ['kode' => 'RAK-LK-01', 'gudang_id' => $this->gudang->id],
            ['nama' => 'Rak Lembar Kerja Test', 'status' => 'AKTIF']
        );

        $this->userTeknisi = User::create([
            'nama' => 'Teknisi Lembar Kerja Test',
            'username' => 'teknisilembar',
            'email' => 'teknisilembar@simlog.test',
            'password' => bcrypt('password'),
            'status' => 'AKTIF',
        ]);
        $this->userTeknisi->givePermissionTo([
            'create_pemeliharaan::lembar::kerja',
            'view_any_pemeliharaan::lembar::kerja',
            'view_pemeliharaan::lembar::kerja',
            'update_pemeliharaan::lembar::kerja',
        ]);

        $this->barangAset = Barang::where('status', 'AKTIF')->where('tipe', 'ASET')->first()
            ?: Barang::where('status', 'AKTIF')->first();

        $this->sparepart = Barang::where('status', 'AKTIF')->where('tipe', 'SPAREPART')->first();
        if (! $this->sparepart) {
            $this->sparepart = Barang::create([
                'kode' => 'SPP-TEST-001',
                'nama' => 'Kapasitor Filter 470uF',
                'tipe' => 'SPAREPART',
                'satuan_id' => 1,
                'status' => 'AKTIF',
            ]);
        }

        $this->aset = Aset::create([
            'barang_id' => $this->barangAset->id,
            'unit_id' => $this->unit->id,
            'kode_aset' => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri' => 'SN-LK-TEST-01',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 15000000,
            'kondisi' => 'RUSAK_RINGAN',
            'status' => 'DALAM_MAINTENANCE',
        ]);

        $this->tiket = PermintaanMaintenance::create([
            'kode' => CodeGenerator::generateMnt(),
            'aset_id' => $this->aset->id,
            'unit_id' => $this->unit->id,
            'dilaporkan_oleh' => $this->userTeknisi->id,
            'deskripsi_masalah' => 'Power supply tegangan drop',
            'prioritas' => 'SEDANG',
            'status' => 'DITUGASKAN',
        ]);

        $this->spk = PenugasanMaintenance::create([
            'kode' => CodeGenerator::generateSpk(),
            'permintaan_maintenance_id' => $this->tiket->id,
            'teknisi_id' => $this->userTeknisi->id,
            'ditugaskan_oleh' => $this->userTeknisi->id,
            'tanggal_penugasan' => now()->toDateString(),
            'target_selesai' => now()->addDays(2)->toDateString(),
            'status' => 'DITUGASKAN',
        ]);
    }

    public function test_fefo_sparepart_allocation_strictly_excludes_expired_batches(): void
    {
        $this->actingAs($this->userTeknisi);

        // 1. Batch 1: Expired kemarin (5 unit) -> JANGAN DIALOKASIKAN
        $batchExpired = StokBatch::create([
            'barang_id' => $this->sparepart->id,
            'gudang_id' => $this->gudang->id,
            'lokasi_gudang_id' => $this->lokasi->id,
            'nomor_batch' => 'BATCH-SPP-EXPIRED',
            'tanggal_kedaluwarsa' => now()->subDay()->toDateString(),
            'jumlah' => 5,
            'harga_satuan' => 25000,
            'status' => 'AKTIF',
        ]);

        // 2. Batch 2: Valid masih 1 tahun lagi (5 unit) -> HARUS DIALOKASIKAN
        $batchValid = StokBatch::create([
            'barang_id' => $this->sparepart->id,
            'gudang_id' => $this->gudang->id,
            'lokasi_gudang_id' => $this->lokasi->id,
            'nomor_batch' => 'BATCH-SPP-VALID',
            'tanggal_kedaluwarsa' => now()->addYear()->toDateString(),
            'jumlah' => 5,
            'harga_satuan' => 25000,
            'status' => 'AKTIF',
        ]);

        // 3. Teknisi input lembar kerja memakai 2 unit suku cadang
        Livewire::test(LembarKerjaPengerjaanIndex::class)
            ->call('openLembarKerja', $this->spk->id)
            ->set('diagnosa', 'Kapasitor filter kering')
            ->set('tindakan', 'Penggantian kapasitor baru')
            ->set('hasil', 'BERHASIL')
            ->set('uji_fungsi_kelaikan', true)
            ->set('sparepartItems', [
                [
                    'barang_id' => $this->sparepart->id,
                    'jumlah' => 2,
                ]
            ])
            ->call('saveLembarKerja')
            ->assertHasNoErrors();

        // 4. Batch expired TIDAK BOLEH disentuh (tetap 5 unit)
        $batchExpired->refresh();
        $this->assertEquals(5, $batchExpired->jumlah, 'Batch suku cadang kedaluwarsa tidak boleh dipotong/dialokasikan.');

        // Batch valid HARUS terpotong 2 unit (sisa 3 unit)
        $batchValid->refresh();
        $this->assertEquals(3, $batchValid->jumlah, 'Batch suku cadang valid harus terpotong sesuai kuantitas pemakaian.');
    }
}
