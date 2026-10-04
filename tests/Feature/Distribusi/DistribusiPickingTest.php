<?php

namespace Tests\Feature\Distribusi;

use App\Models\Barang;
use App\Models\Gudang;
use App\Models\LokasiGudang;
use App\Models\PermintaanDistribusi;
use App\Models\PermintaanDistribusiDetail;
use App\Models\StokBatch;
use App\Models\Unit;
use App\Models\User;
use App\Services\Distribusi\DistribusiService;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DistribusiPickingTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Gudang $gudang;
    protected Unit $unit;
    protected Barang $barangA;
    protected Barang $barangB;
    protected LokasiGudang $lokasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::where('email', 'superadmin@simlogrs.local')->first();
        $this->gudang = Gudang::where('status', 'AKTIF')->first();
        $this->unit = Unit::where('status', 'AKTIF')->first();
        $this->lokasi = LokasiGudang::where('gudang_id', $this->gudang->id)->where('status', 'AKTIF')->first();

        // 2 barang berbeda
        $barangs = Barang::where('status', 'AKTIF')->take(2)->get();
        $this->barangA = $barangs[0];
        $this->barangB = $barangs[1];
    }

    public function test_picking_fefo_skips_items_with_zero_approved_quota(): void
    {
        // Siapkan stok di gudang hanya untuk Barang A (10 unit)
        StokBatch::create([
            'barang_id' => $this->barangA->id,
            'gudang_id' => $this->gudang->id,
            'lokasi_gudang_id' => $this->lokasi->id,
            'nomor_batch' => 'BATCH-TEST-A',
            'tanggal_kedaluwarsa' => now()->addYear()->toDateString(),
            'jumlah' => 10,
            'harga_satuan' => 10000,
            'status' => 'AKTIF',
        ]);

        // Barang B TIDAK ADA STOK SAMA SEKALI di gudang (0 unit)
        StokBatch::where('barang_id', $this->barangB->id)
            ->where('gudang_id', $this->gudang->id)
            ->update(['jumlah' => 0, 'status' => 'HABIS']);

        // Buat permintaan amprah
        $permintaan = PermintaanDistribusi::create([
            'kode' => CodeGenerator::generate('REQ', PermintaanDistribusi::class, 'kode', 'Ym', 3),
            'unit_id' => $this->unit->id,
            'gudang_id' => $this->gudang->id,
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi' => 'REGULER',
            'status' => 'DISETUJUI_GUDANG',
            'diajukan_oleh' => $this->user->id,
        ]);

        // Detail 1: Barang A diminta 5, disetujui 5
        PermintaanDistribusiDetail::create([
            'permintaan_distribusi_id' => $permintaan->id,
            'barang_id' => $this->barangA->id,
            'jumlah' => 5,
            'jumlah_disetujui' => 5,
        ]);

        // Detail 2: Barang B diminta 10, namun Kepala Gudang menyetujui 0 (Ditolak / 0 kuota)
        PermintaanDistribusiDetail::create([
            'permintaan_distribusi_id' => $permintaan->id,
            'barang_id' => $this->barangB->id,
            'jumlah' => 10,
            'jumlah_disetujui' => 0, // Eksplisit 0!
        ]);

        // Eksekusi picking FEFO
        $service = app(DistribusiService::class);
        $sppb = $service->eksekusiPickingFefo($permintaan, $this->user);

        // Assert SPPB berhasil terbit
        $this->assertNotNull($sppb);
        $this->assertEquals('DIKIRIM', $sppb->status);

        // SPPB hanya boleh memuat Barang A (karena Barang B kuotanya 0)
        $this->assertEquals(1, $sppb->detail()->count());
        $this->assertEquals($this->barangA->id, $sppb->detail()->first()->barang_id);
        $this->assertEquals(5, $sppb->detail()->first()->jumlah_dikirim);
    }
    public function test_picking_fefo_prevents_duplicate_sppb_issuance(): void
    {
        StokBatch::create([
            'barang_id' => $this->barangA->id,
            'gudang_id' => $this->gudang->id,
            'lokasi_gudang_id' => $this->lokasi->id,
            'nomor_batch' => 'BATCH-TEST-DUP',
            'tanggal_kedaluwarsa' => now()->addYear()->toDateString(),
            'jumlah' => 20,
            'harga_satuan' => 10000,
            'status' => 'AKTIF',
        ]);

        $permintaan = PermintaanDistribusi::create([
            'kode' => CodeGenerator::generate('REQ', PermintaanDistribusi::class, 'kode', 'Ym', 3),
            'unit_id' => $this->unit->id,
            'gudang_id' => $this->gudang->id,
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi' => 'REGULER',
            'status' => 'DISETUJUI_GUDANG',
            'diajukan_oleh' => $this->user->id,
        ]);

        PermintaanDistribusiDetail::create([
            'permintaan_distribusi_id' => $permintaan->id,
            'barang_id' => $this->barangA->id,
            'jumlah' => 5,
            'jumlah_disetujui' => 5,
        ]);

        $service = app(DistribusiService::class);
        // Pemanggilan pertama: harus sukses
        $sppb1 = $service->eksekusiPickingFefo($permintaan, $this->user);
        $this->assertNotNull($sppb1);

        // Pemanggilan kedua dengan simulasi konkurensi (status memori masih DISETUJUI_GUDANG)
        $permintaanKonkuren = clone $permintaan;
        $permintaanKonkuren->status = 'DISETUJUI_GUDANG';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/sudah/i');
        $service->eksekusiPickingFefo($permintaanKonkuren, $this->user);
    }
}
