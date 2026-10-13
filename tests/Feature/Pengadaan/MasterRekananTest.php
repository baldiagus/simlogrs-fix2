<?php

namespace Tests\Feature\Pengadaan;

use App\Livewire\Pengadaan\MasterRekananIndex;
use App\Models\Master\Pemasok;
use App\Models\Pengadaan\PesananPembelian;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class MasterRekananTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::where('email', 'superadmin@simlogrs.local')->first();
    }

    public function test_vendor_spending_metrics_exclude_cancelled_pos(): void
    {
        $this->actingAs($this->user);

        // 1. Buat vendor pengujian terisolasi
        $vendor = Pemasok::create([
            'kode' => 'VND-TEST-99',
            'nama' => 'PT Vendor Uji Metrik',
            'nama_kontak' => 'Budi',
            'no_telepon' => '08123456789',
            'email' => 'budi@vendoruji.test',
            'kota' => 'Surabaya',
            'status' => 'AKTIF',
        ]);

        // 2. Buat 1 PO Aktif senilai Rp 10.000.000
        PesananPembelian::create([
            'kode' => CodeGenerator::generatePo(),
            'pemasok_id' => $vendor->id,
            'tanggal_po' => now()->toDateString(),
            'total_biaya' => 10000000,
            'syarat_pembayaran' => 'TEMPO_30_HARI',
            'status' => 'DITERIMA_LENGKAP',
            'dibuat_oleh' => $this->user->id,
        ]);

        // 3. Buat 1 PO DIBATALKAN senilai Rp 5.000.000
        PesananPembelian::create([
            'kode' => CodeGenerator::generatePo(),
            'pemasok_id' => $vendor->id,
            'tanggal_po' => now()->toDateString(),
            'total_biaya' => 5000000,
            'syarat_pembayaran' => 'TEMPO_30_HARI',
            'status' => 'DIBATALKAN',
            'dibuat_oleh' => $this->user->id,
        ]);

        // 4. Test render Livewire MasterRekananIndex
        $component = Livewire::test(MasterRekananIndex::class)
            ->set('search', 'PT Vendor Uji Metrik');

        $items = $component->viewData('items');
        $testedItem = collect($items->items())->firstWhere('id', $vendor->id);

        $this->assertNotNull($testedItem);
        // PO count harus 1 (bukan 2)
        $this->assertEquals(1, $testedItem->pesanan_pembelian_count);
        // Total belanja harus 10.000.000 (bukan 15.000.000)
        $this->assertEquals(10000000, (float) $testedItem->pesanan_pembelian_sum_total_biaya);
    }
}
