<?php

namespace Tests\Feature\Distribusi;

use App\Livewire\Distribusi\StokRuanganIndex;
use App\Livewire\Ipsrs\RekamMedisAsetIndex;
use App\Models\Aset;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\LokasiGudang;
use App\Models\StokBatch;
use App\Models\Unit;
use App\Models\User;
use App\Services\Aset\PengalihanAsetService;
use App\Services\Persediaan\PengalihanBarangService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class PengalihanPersediaanIntegrasiTest extends TestCase
{
    use DatabaseTransactions;

    protected User $userPetugasGudang;
    protected User $userPetugasUnit;
    protected Gudang $gudang;
    protected Unit $unitAsal;
    protected Unit $unitTujuan;
    protected Barang $barangBhp;
    protected Barang $barangAset;
    protected StokBatch $batch;
    protected Aset $aset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gudang = Gudang::where('status', 'AKTIF')->first();
        $units = Unit::where('status', 'AKTIF')->take(2)->get();
        $this->unitAsal = $units[0];
        $this->unitTujuan = $units[1];

        $this->userPetugasGudang = User::create([
            'nama' => 'Petugas Gudang Integrasi',
            'username' => 'petugas_gdg_int',
            'email' => 'gdg_int@simlog.test',
            'password' => bcrypt('password'),
            'gudang_id' => $this->gudang->id,
            'status' => 'AKTIF',
        ]);
        $this->userPetugasGudang->givePermissionTo([
            'view_any_gudang_stok::pengalihan::barang',
            'create_gudang_stok::pengalihan::barang',
            'update_gudang_stok::pengalihan::barang',
        ]);

        $this->userPetugasUnit = User::create([
            'nama' => 'Petugas Unit Integrasi',
            'username' => 'petugas_unit_int',
            'email' => 'unit_int@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $this->unitTujuan->id,
            'status' => 'AKTIF',
        ]);
        $this->userPetugasUnit->givePermissionTo([
            'view_any_layanan_unit::stok::ruangan',
            'view_layanan_unit::stok::ruangan',
            'page_StokRuanganIndex',
            'view_any_pemeliharaan::rekam::medis::aset',
            'view_pemeliharaan::rekam::medis::aset',
        ]);

        $satuanId = \App\Models\SatuanBarang::firstOrCreate(['kode' => 'UNT-INT'], ['nama' => 'Unit', 'status' => 'AKTIF'])->id;
        $kategoriBhpId = \App\Models\KategoriBarang::firstOrCreate(['kode' => 'KAT-BHP-INT'], ['nama' => 'BHP Int', 'status' => 'AKTIF'])->id;
        $kategoriAsetId = \App\Models\KategoriBarang::firstOrCreate(['kode' => 'KAT-AST-INT'], ['nama' => 'Aset Int', 'status' => 'AKTIF'])->id;

        $this->barangBhp = Barang::firstOrCreate(
            ['kode' => 'BRG-BHP-INT-99'],
            [
                'nama' => 'Kasa Steril Mutasi',
                'tipe' => 'HABIS_PAKAI',
                'satuan_id' => $satuanId,
                'kategori_id' => $kategoriBhpId,
                'status' => 'AKTIF',
            ]
        );

        $this->barangAset = Barang::firstOrCreate(
            ['kode' => 'BRG-AST-INT-99'],
            [
                'nama' => 'Infusion Pump Mutasi',
                'tipe' => 'ASET',
                'satuan_id' => $satuanId,
                'kategori_id' => $kategoriAsetId,
                'status' => 'AKTIF',
            ]
        );

        $lokasi = LokasiGudang::firstOrCreate(
            ['gudang_id' => $this->gudang->id, 'kode' => 'RAK-INT-99'],
            ['nama' => 'Rak Integrasi', 'status' => 'AKTIF']
        );

        $this->batch = StokBatch::create([
            'barang_id' => $this->barangBhp->id,
            'gudang_id' => $this->gudang->id,
            'lokasi_gudang_id' => $lokasi->id,
            'nomor_batch' => 'BATCH-INT-99',
            'tanggal_kedaluwarsa' => now()->addYear()->toDateString(),
            'jumlah' => 100,
            'status' => 'AKTIF',
        ]);

        $this->aset = Aset::create([
            'barang_id' => $this->barangAset->id,
            'unit_id' => $this->unitAsal->id,
            'kode_aset' => 'AST-INT-PUMP-99',
            'nomor_seri' => 'SN-INT-PUMP-99',
            'tanggal_perolehan' => now()->toDateString(),
            'status' => 'TERSEDIA',
            'kondisi' => 'BAIK',
        ]);
    }

    public function test_transferred_goods_are_recorded_in_room_inventory_bhp_tab(): void
    {
        // 1. Eksekusi pengalihan barang dari Gudang ke Unit Tujuan
        $service = app(PengalihanBarangService::class);
        $transfer = $service->ajukanPengalihan([
            'tipe_asal' => 'GUDANG',
            'gudang_asal_id' => $this->gudang->id,
            'tipe_tujuan' => 'RUANGAN',
            'unit_tujuan_id' => $this->unitTujuan->id,
            'tanggal_pengalihan' => now()->toDateString(),
            'alasan' => 'Drop pasokan ke ruang IGD',
            'diajukan_oleh' => $this->userPetugasGudang->id,
            'items' => [
                [
                    'barang_id' => $this->barangBhp->id,
                    'stok_batch_id' => $this->batch->id,
                    'jumlah' => 25,
                ],
            ],
        ]);
        $service->setujuiPengalihan($transfer->id, $this->userPetugasGudang->id);
        $service->eksekusiSerahTerima($transfer->id, $this->userPetugasUnit->id);

        // 2. Petugas ruangan membuka halaman Persediaan Lokal Ruangan
        $this->actingAs($this->userPetugasUnit);

        Livewire::test(StokRuanganIndex::class)
            ->assertSee($this->barangBhp->nama)
            ->assertSee('25');
    }

    public function test_transferred_asset_is_recorded_in_room_inventory_asset_tab_and_medical_record(): void
    {
        // 1. Eksekusi pengalihan aset dari Unit Asal ke Unit Tujuan
        $service = app(PengalihanAsetService::class);
        $transfer = $service->ajukanPengalihan([
            'tipe_asal' => 'RUANGAN',
            'unit_asal_id' => $this->unitAsal->id,
            'tipe_tujuan' => 'RUANGAN',
            'unit_tujuan_id' => $this->unitTujuan->id,
            'tanggal_pengalihan' => now()->toDateString(),
            'alasan' => 'Mutasi alat medis pompa infus',
            'diajukan_oleh' => $this->userPetugasGudang->id,
            'aset_ids' => [$this->aset->id],
        ]);
        $service->setujuiPengalihan($transfer->id, $this->userPetugasGudang->id);
        $service->eksekusiSerahTerima($transfer->id, $this->userPetugasUnit->id);

        // 2. Petugas ruangan membuka halaman Persediaan Lokal Ruangan tab Aset
        $this->actingAs($this->userPetugasUnit);

        Livewire::test(StokRuanganIndex::class)
            ->call('setTab', 'aset')
            ->assertSee($this->aset->kode_aset)
            ->assertSee($this->barangAset->nama);

        // 3. Verifikasi tercatat di Rekam Medis Aset
        Livewire::test(RekamMedisAsetIndex::class)
            ->call('showBukuRiwayat', $this->aset->id)
            ->assertSee($transfer->kode)
            ->assertSee('MUTASI PERMANEN');
    }
}
