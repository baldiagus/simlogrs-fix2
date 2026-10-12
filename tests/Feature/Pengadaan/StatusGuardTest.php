<?php

namespace Tests\Feature\Pengadaan;

use App\Livewire\Pengadaan\PenerimaanPoIndex;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\Pemasok;
use App\Models\PermintaanPengadaan;
use App\Models\PesananPembelian;
use App\Models\PesananPembelianDetail;
use App\Models\StokBatch;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ADR 0002 A5 & A7: guard status PO saat penerimaan dan penolakan PR oleh admin global.
 */
class StatusGuardTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('email', 'superadmin@simlogrs.local')->firstOrFail();
    }

    private function makePr(string $status): PermintaanPengadaan
    {
        return PermintaanPengadaan::create([
            'kode' => CodeGenerator::generatePr(),
            'unit_id' => 1,
            'diajukan_oleh' => $this->admin->id,
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi' => 'REGULER',
            'status' => $status,
        ]);
    }

    public function test_save_penerimaan_rechecks_po_status_at_save_time(): void
    {
        $pemasok = Pemasok::firstOrCreate(['kode' => 'VND-GUARD-01'], ['nama' => 'Vendor Guard', 'status' => 'AKTIF']);
        $barang = Barang::query()->orderBy('id')->firstOrFail();
        $gudang = Gudang::where('status', 'AKTIF')->orderBy('id')->firstOrFail();

        $po = PesananPembelian::create([
            'kode' => CodeGenerator::generatePo(),
            'pemasok_id' => $pemasok->id,
            'tanggal_po' => now()->toDateString(),
            'total_biaya' => 100000,
            'syarat_pembayaran' => 'TEMPO_30_HARI',
            'status' => 'DIKIRIM_VENDOR',
            'dibuat_oleh' => $this->admin->id,
        ]);
        $detail = PesananPembelianDetail::create([
            'pesanan_pembelian_id' => $po->id,
            'barang_id' => $barang->id,
            'jumlah_dipesan' => 10,
            'jumlah_diterima' => 0,
            'harga_satuan' => 10000,
            'subtotal' => 100000,
        ]);
        $nomorBatch = 'BATCH-GUARD-' . $po->id;

        $this->actingAs($this->admin);
        $component = Livewire::test(PenerimaanPoIndex::class)->call('terima', $po->id);

        // PO berubah status setelah modal penerimaan dibuka.
        $po->update(['status' => 'DIBATALKAN']);

        $component
            ->set('nomor_surat_jalan', 'SJ-GUARD-001')
            ->set('gudang_id', $gudang->id)
            ->set('itemRows.0.jumlah_diterima', 10)
            ->set('itemRows.0.nomor_batch', $nomorBatch)
            ->call('savePenerimaan');

        $this->assertSame('DIBATALKAN', $po->fresh()->status);
        $this->assertSame(0, (int) $detail->fresh()->jumlah_diterima);
        $this->assertFalse(StokBatch::where('nomor_batch', $nomorBatch)->exists());
    }

    /** @return array<string, array{string}> */
    public static function statusTidakBisaDitolakProvider(): array
    {
        return [
            'selesai' => ['SELESAI'],
            'ditolak' => ['DITOLAK'],
            'diproses' => ['DIPROSES'],
        ];
    }

    #[DataProvider('statusTidakBisaDitolakProvider')]
    public function test_global_admin_cannot_reject_processed_or_final_pr(string $status): void
    {
        $pr = $this->makePr($status);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/approval/pr/{$pr->id}", ['aksi' => 'tolak'])
            ->assertStatus(422);

        $this->assertSame($status, $pr->fresh()->status);
    }

    /** @return array<string, array{string}> */
    public static function statusNonFinalProvider(): array
    {
        return [
            'diajukan' => ['DIAJUKAN'],
            'disetujui legacy' => ['DISETUJUI'],
            'diteruskan' => ['DITERUSKAN_KE_PENGADAAN'],
        ];
    }

    #[DataProvider('statusNonFinalProvider')]
    public function test_global_admin_can_reject_non_final_pr(string $status): void
    {
        $pr = $this->makePr($status);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/approval/pr/{$pr->id}", ['aksi' => 'tolak'])
            ->assertOk();

        $this->assertSame('DITOLAK', $pr->fresh()->status);
    }
}
