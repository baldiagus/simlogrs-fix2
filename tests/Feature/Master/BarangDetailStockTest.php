<?php

namespace Tests\Feature\Master;

use App\Livewire\Master\BarangIndex;
use App\Models\Aset;
use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\SatuanBarang;
use App\Models\Unit;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class BarangDetailStockTest extends TestCase
{
    use DatabaseTransactions;

    public function test_room_stock_card_shows_correct_asset_count_for_asset_commodity(): void
    {
        $user = User::where('email', 'superadmin@simlogrs.local')->first();
        $this->actingAs($user);

        $kategori = KategoriBarang::firstOrCreate(['kode' => 'KAT-IT-TEST'], ['nama' => 'IT Test', 'status' => 'AKTIF']);
        $satuan = SatuanBarang::firstOrCreate(['kode' => 'UNIT-IT-TEST'], ['nama' => 'Unit', 'status' => 'AKTIF']);
        $unit = Unit::where('status', 'AKTIF')->first();

        // 1. Buat barang tipe ASET
        $barangAset = Barang::firstOrCreate(
            ['kode' => 'BRG-PRN-TEST-01'],
            [
                'nama' => 'Printer Test Unit',
                'kategori_id' => $kategori->id,
                'satuan_id' => $satuan->id,
                'tipe' => 'ASET',
                'status' => 'AKTIF',
            ]
        );

        // 2. Buat 1 unit fisik aset di ruangan IGD (unit_id)
        Aset::create([
            'barang_id' => $barangAset->id,
            'unit_id' => $unit->id,
            'gudang_id' => null,
            'kode_aset' => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri' => 'SN-PRN-001',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 5000000,
            'kondisi' => 'BAIK',
            'status' => 'TERSEDIA',
        ]);

        // 3. Buka detail modal di BarangIndex
        $test = Livewire::test(BarangIndex::class)
            ->call('showDetail', $barangAset->id);

        $html = $test->html();
        // Nilai Stok di Ruangan harus 1 (karena ada 1 printer di unit IGD), BUKAN 0
        $this->assertMatchesRegularExpression('/Stok di Ruangan.*?1\s*<span/s', $html);
    }
}
