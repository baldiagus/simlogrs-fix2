<?php

namespace Tests\Feature\Distribusi;

use App\Models\Master\Barang;
use App\Models\Master\Gudang;
use App\Models\Persediaan\StokBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DistribusiKatalogApiTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Gudang $gudangAtk;
    protected Gudang $gudangB3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::where('status', 'AKTIF')->firstOrFail();

        $this->gudangAtk = Gudang::where('kode', 'GUD_ATK')->first()
            ?: Gudang::firstOrCreate(
                ['nama' => 'Gudang ATK & Cetakan Rumah Sakit'],
                ['kode' => 'GUD_ATK', 'jenis' => 'SENTRAL', 'status' => 'AKTIF']
            );

        $this->gudangB3 = Gudang::where('kode', 'GUD_B3')->first()
            ?: Gudang::firstOrCreate(
                ['nama' => 'Gudang Bahan Berbahaya & Beracun (B3)'],
                ['kode' => 'GUD_B3', 'jenis' => 'SENTRAL', 'status' => 'AKTIF']
            );
    }

    private function createBarangWithStock(int $stokAtk = 25, int $stokB3 = 10): Barang
    {
        $existing = Barang::where('status', 'AKTIF')->first();
        $suffix = uniqid();

        $barang = Barang::create([
            'kode'                => 'BRG-KAT-' . $suffix,
            'nama'                => 'Barang Uji Katalog ' . $suffix,
            'tipe'                => 'HABIS_PAKAI',
            'kategori_id'         => $existing?->kategori_id ?? 1,
            'satuan_id'           => $existing?->satuan_id ?? 1,
            'stok_minimum'        => 0,
            'stok_maksimum'       => 100,
            'titik_pesan'         => 10,
            'harga_beli_terakhir' => 10000,
            'harga_estimasi'      => 10000,
            'status'              => 'AKTIF',
        ]);

        if ($stokAtk > 0) {
            StokBatch::create([
                'barang_id'           => $barang->id,
                'gudang_id'           => $this->gudangAtk->id,
                'nomor_batch'         => 'BAT-ATK-' . $suffix,
                'tanggal_kedaluwarsa' => now()->addYear()->toDateString(),
                'jumlah'              => $stokAtk,
                'jumlah_dipesan'      => 0,
                'harga_satuan'        => 10000,
                'status'              => 'AKTIF',
            ]);
        }

        if ($stokB3 > 0) {
            StokBatch::create([
                'barang_id'           => $barang->id,
                'gudang_id'           => $this->gudangB3->id,
                'nomor_batch'         => 'BAT-B3-' . $suffix,
                'tanggal_kedaluwarsa' => now()->addYear()->toDateString(),
                'jumlah'              => $stokB3,
                'jumlah_dipesan'      => 0,
                'harga_satuan'        => 10000,
                'status'              => 'AKTIF',
            ]);
        }

        return $barang;
    }

    public function test_katalog_barang_returns_best_gudang_metadata_and_correct_stock(): void
    {
        $barang = $this->createBarangWithStock(25, 10);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/distribusi/katalog-barang');

        $response->assertOk();

        $item = collect($response->json('data'))->firstWhere('id', $barang->id);

        $this->assertNotNull($item, 'Barang uji harus ditemukan di katalog.');
        $this->assertSame(35, $item['stok_tersedia']);
        $this->assertSame($this->gudangAtk->id, $item['best_gudang_id']);
        $this->assertSame($this->gudangAtk->nama, $item['best_gudang_nama']);
        $this->assertSame(25, $item['best_gudang_stok']);
    }

    public function test_katalog_barang_with_explicit_gudang_id(): void
    {
        $barang = $this->createBarangWithStock(25, 10);

        // Query dengan gudang B3 (stok 10)
        $responseB3 = $this->actingAs($this->user)
            ->getJson('/api/v1/distribusi/katalog-barang?gudang_id=' . $this->gudangB3->id);

        $responseB3->assertOk();

        $itemB3 = collect($responseB3->json('data'))->firstWhere('id', $barang->id);

        $this->assertNotNull($itemB3, 'Barang uji harus ditemukan di katalog filter B3.');
        $this->assertSame(10, $itemB3['stok_tersedia']);
        $this->assertSame($this->gudangAtk->id, $itemB3['best_gudang_id']);
        $this->assertSame($this->gudangAtk->nama, $itemB3['best_gudang_nama']);
        $this->assertSame(25, $itemB3['best_gudang_stok']);

        // Query dengan gudang ATK (stok 25)
        $responseAtk = $this->actingAs($this->user)
            ->getJson('/api/v1/distribusi/katalog-barang?gudang_id=' . $this->gudangAtk->id);

        $responseAtk->assertOk();

        $itemAtk = collect($responseAtk->json('data'))->firstWhere('id', $barang->id);

        $this->assertNotNull($itemAtk, 'Barang uji harus ditemukan di katalog filter ATK.');
        $this->assertSame(25, $itemAtk['stok_tersedia']);
        $this->assertSame($this->gudangAtk->id, $itemAtk['best_gudang_id']);
        $this->assertSame($this->gudangAtk->nama, $itemAtk['best_gudang_nama']);
        $this->assertSame(25, $itemAtk['best_gudang_stok']);
    }
}
