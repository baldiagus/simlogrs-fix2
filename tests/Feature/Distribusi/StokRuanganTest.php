<?php

namespace Tests\Feature\Distribusi;

use App\Livewire\Distribusi\StokRuanganIndex;
use App\Models\Aset;
use App\Models\Barang;
use App\Models\Unit;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class StokRuanganTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Unit $unit;
    protected Barang $barang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::where('email', 'superadmin@simlogrs.local')->first();
        $this->unit = Unit::where('status', 'AKTIF')->first();
        $this->barang = Barang::where('status', 'AKTIF')->where('tipe', 'ASET')->first()
            ?: Barang::where('status', 'AKTIF')->first();
    }

    public function test_cannot_change_operational_status_of_borrowed_or_maintenance_assets(): void
    {
        $this->actingAs($this->user);

        // 1. Buat aset dengan status DIPINJAM
        $asetDipinjam = Aset::create([
            'barang_id' => $this->barang->id,
            'unit_id' => $this->unit->id,
            'gudang_id' => null,
            'kode_aset' => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri' => 'SN-PINJAM-001',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 5000000,
            'kondisi' => 'BAIK',
            'status' => 'DIPINJAM',
        ]);

        // Coba buka modal ubah status aset yang DIPINJAM -> HARUS DIGAGALKAN (403 atau error toast)
        Livewire::test(StokRuanganIndex::class)
            ->call('openUbahStatusAset', $asetDipinjam->id)
            ->assertForbidden();

        // Pastikan status aset tetap DIPINJAM
        $asetDipinjam->refresh();
        $this->assertEquals('DIPINJAM', $asetDipinjam->status);

        // 2. Buat aset dengan status DALAM_MAINTENANCE
        $asetMaintenance = Aset::create([
            'barang_id' => $this->barang->id,
            'unit_id' => $this->unit->id,
            'gudang_id' => null,
            'kode_aset' => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri' => 'SN-MNT-001',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 5000000,
            'kondisi' => 'RUSAK_BERAT',
            'status' => 'DALAM_MAINTENANCE',
        ]);

        // Coba buka modal ubah status aset yang DALAM_MAINTENANCE -> HARUS DIGAGALKAN (403 atau error toast)
        Livewire::test(StokRuanganIndex::class)
            ->call('openUbahStatusAset', $asetMaintenance->id)
            ->assertForbidden();

        // Pastikan status aset tetap DALAM_MAINTENANCE
        $asetMaintenance->refresh();
        $this->assertEquals('DALAM_MAINTENANCE', $asetMaintenance->status);
    }
    public function test_room_stock_consumption_records_audit_trail_with_keterangan(): void
    {
        $this->actingAs($this->user);

        $barangBhp = Barang::where('status', 'AKTIF')->where('tipe', 'HABIS_PAKAI')->first()
            ?: Barang::first();

        $stok = \App\Models\StokRuangan::updateOrCreate(
            ['unit_id' => $this->unit->id, 'barang_id' => $barangBhp->id],
            ['jumlah' => 10, 'stok_minimum' => 2, 'stok_maksimum' => 20]
        );

        $catatanPemakaian = 'Pasien Darurat Tindakan Bedah IGD Ref #9921';

        Livewire::test(StokRuanganIndex::class)
            ->call('openCatatPemakaian', $stok->id)
            ->set('jumlah_pakai', 3)
            ->set('keterangan', $catatanPemakaian)
            ->call('simpanPemakaian');

        // 1. Stok lemari berkurang menjadi 7
        $stok->refresh();
        $this->assertEquals(7, $stok->jumlah);

        // 2. Keterangan pemakaian HARUS tercatat di log_audit
        $auditLog = \App\Models\LogAudit::where('referensi_id', $stok->id)
            ->where('aksi', 'PEMAKAIAN')
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog, 'LogAudit dengan aksi PEMAKAIAN tidak ditemukan.');
        $this->assertEquals($catatanPemakaian, $auditLog->data_sesudah['keterangan'] ?? null);
        $this->assertEquals(3, $auditLog->data_sesudah['jumlah_pakai'] ?? null);
    }
}
