<?php

namespace Tests\Feature\Pengadaan;

use App\Models\Master\Barang;
use App\Models\Master\Pemasok;
use App\Models\Pengadaan\PesananPembelian;
use App\Models\Pengadaan\PesananPembelianDetail;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InboundPoApiFilterTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Pemasok $pemasok;
    protected Barang $barang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::where('email', 'superadmin@simlogrs.local')->first()
            ?? User::firstOrFail();

        $this->pemasok = Pemasok::firstOrCreate(
            ['kode' => 'VND-INBOUND-TEST'],
            ['nama' => 'Vendor Inbound Test PT', 'status' => 'AKTIF']
        );

        $this->barang = Barang::where('status', 'AKTIF')->firstOrFail();
    }

    private function createPo(string $status, ?Pemasok $pemasok = null, ?string $kode = null): PesananPembelian
    {
        $po = PesananPembelian::create([
            'kode'              => $kode ?? CodeGenerator::generatePo(),
            'pemasok_id'        => ($pemasok ?? $this->pemasok)->id,
            'tanggal_po'        => now()->toDateString(),
            'total_biaya'       => 100000,
            'syarat_pembayaran' => 'TEMPO_30_HARI',
            'status'            => $status,
            'dibuat_oleh'       => $this->user->id,
        ]);

        PesananPembelianDetail::create([
            'pesanan_pembelian_id' => $po->id,
            'barang_id'            => $this->barang->id,
            'jumlah_dipesan'       => 5,
            'jumlah_diterima'      => $status === 'DITERIMA_LENGKAP' ? 5 : 0,
            'harga_satuan'         => 20000,
            'subtotal'             => 100000,
        ]);

        return $po;
    }

    public function test_po_inbound_returns_all_statuses_by_default(): void
    {
        $poKirim = $this->createPo('DIKIRIM_VENDOR');
        $poLengkap = $this->createPo('DITERIMA_LENGKAP');
        $poTerbit = $this->createPo('DITERBITKAN');

        $response = $this->actingAs($this->user)->getJson('/api/v1/gudang/po-inbound');

        $response->assertOk()
            ->assertJsonPath('sukses', true);

        $returnedCodes = collect($response->json('data'))->pluck('kode_po');

        $this->assertTrue($returnedCodes->contains($poKirim->kode));
        $this->assertTrue($returnedCodes->contains($poLengkap->kode));
        $this->assertTrue($returnedCodes->contains($poTerbit->kode));

        $responseSemua = $this->actingAs($this->user)->getJson('/api/v1/gudang/po-inbound?status=SEMUA');
        $responseSemua->assertOk();
        $returnedCodesSemua = collect($responseSemua->json('data'))->pluck('kode_po');
        $this->assertTrue($returnedCodesSemua->contains($poKirim->kode));
        $this->assertTrue($returnedCodesSemua->contains($poLengkap->kode));
        $this->assertTrue($returnedCodesSemua->contains($poTerbit->kode));
    }

    public function test_po_inbound_filters_by_specific_status(): void
    {
        $poKirim = $this->createPo('DIKIRIM_VENDOR');
        $poLengkap = $this->createPo('DITERIMA_LENGKAP');
        $poTerbit = $this->createPo('DITERBITKAN');

        $response = $this->actingAs($this->user)->getJson('/api/v1/gudang/po-inbound?status=DITERIMA_LENGKAP');

        $response->assertOk()
            ->assertJsonPath('sukses', true);

        $returnedCodes = collect($response->json('data'))->pluck('kode_po');

        $this->assertTrue($returnedCodes->contains($poLengkap->kode));
        $this->assertFalse($returnedCodes->contains($poKirim->kode));
        $this->assertFalse($returnedCodes->contains($poTerbit->kode));

        foreach ($response->json('data') as $item) {
            $this->assertSame('DITERIMA_LENGKAP', $item['status']);
        }
    }

    public function test_po_inbound_filters_by_search_query(): void
    {
        $uniqueSuffix = uniqid();
        $vendorUnique = Pemasok::create([
            'kode'   => 'VND-' . $uniqueSuffix,
            'nama'   => 'Vendor Unik ' . $uniqueSuffix,
            'status' => 'AKTIF',
        ]);

        $poA = $this->createPo('DIKIRIM_VENDOR', null, 'PO-SEARCH-A-' . $uniqueSuffix);
        $poB = $this->createPo('DIKIRIM_VENDOR', $vendorUnique, 'PO-SEARCH-B-' . $uniqueSuffix);

        // Filter by code
        $responseCode = $this->actingAs($this->user)->getJson('/api/v1/gudang/po-inbound?search=' . $poA->kode);
        $responseCode->assertOk();
        $codesFromCodeSearch = collect($responseCode->json('data'))->pluck('kode_po');
        $this->assertTrue($codesFromCodeSearch->contains($poA->kode));
        $this->assertFalse($codesFromCodeSearch->contains($poB->kode));

        // Filter by vendor name
        $responseVendor = $this->actingAs($this->user)->getJson('/api/v1/gudang/po-inbound?search=' . urlencode($vendorUnique->nama));
        $responseVendor->assertOk();
        $codesFromVendorSearch = collect($responseVendor->json('data'))->pluck('kode_po');
        $this->assertTrue($codesFromVendorSearch->contains($poB->kode));
        $this->assertFalse($codesFromVendorSearch->contains($poA->kode));
    }
}
