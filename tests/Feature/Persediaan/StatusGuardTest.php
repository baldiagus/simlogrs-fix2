<?php

namespace Tests\Feature\Persediaan;

use App\Livewire\Persediaan\OtorisasiPenyesuaianIndex;
use App\Livewire\Persediaan\StockOpnameIndex;
use App\Models\Master\Barang;
use App\Models\Master\Gudang;
use App\Models\Persediaan\PenyesuaianStok;
use App\Models\Persediaan\StokOpname;
use App\Models\Persediaan\StokBatch;
use App\Models\Persediaan\StokOpnameDetail;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ADR 0002 A1-A3: guard transisi status opname & penyesuaian stok.
 */
class StatusGuardTest extends TestCase
{
    use DatabaseTransactions;

    protected Gudang $gudang;
    protected User $kepalaGudang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gudang = Gudang::where('status', 'AKTIF')->orderBy('id')->firstOrFail();
        $this->kepalaGudang = User::create([
            'nama' => 'Kepala Gudang Status Guard',
            'username' => 'kagud_guard_' . uniqid(),
            'email' => 'kagud_guard_' . uniqid() . '@simlog.test',
            'password' => bcrypt('password'),
            'gudang_id' => $this->gudang->id,
            'status' => 'AKTIF',
        ]);
        $this->kepalaGudang->assignRole('kepala_gudang');
        $this->kepalaGudang->givePermissionTo(['update_gudang_stok::stok::opname', 'update_gudang_stok::otorisasi::penyesuaian']);
    }

    private function makeOpname(string $status): StokOpname
    {
        return StokOpname::create([
            'kode' => CodeGenerator::generateOpn(),
            'gudang_id' => $this->gudang->id,
            'tanggal_opname' => now()->toDateString(),
            'status' => $status,
            'dibuat_oleh' => $this->kepalaGudang->id,
        ]);
    }

    private function makePenyesuaian(StokOpname $opname, string $status): PenyesuaianStok
    {
        return PenyesuaianStok::forceCreate([
            'kode' => 'ADJ-GUARD-' . uniqid(),
            'stok_opname_id' => $opname->id,
            'gudang_id' => $this->gudang->id,
            'tanggal_penyesuaian' => now()->toDateString(),
            'alasan' => 'Selisih uji guard status',
            'status' => $status,
            'diajukan_oleh' => $this->kepalaGudang->id,
        ]);
    }

    private function makeDetail(StokOpname $opname): StokOpnameDetail
    {
        $barangId = Barang::query()->orderBy('id')->value('id');
        $batch = StokBatch::create([
            'barang_id' => $barangId,
            'gudang_id' => $this->gudang->id,
            'nomor_batch' => 'BATCH-OPN-GUARD-' . uniqid(),
            'tanggal_kedaluwarsa' => now()->addYear()->toDateString(),
            'jumlah' => 5,
            'status' => 'AKTIF',
        ]);

        return StokOpnameDetail::create([
            'stok_opname_id' => $opname->id,
            'barang_id' => $barangId,
            'stok_batch_id' => $batch->id,
            'jumlah_sistem' => 5,
            'jumlah_fisik' => 0,
        ]);
    }

    public function test_web_reject_of_already_approved_adjustment_is_refused(): void
    {
        $opname = $this->makeOpname('DISETUJUI');
        $adj = $this->makePenyesuaian($opname, 'DISETUJUI');

        $this->actingAs($this->kepalaGudang);
        Livewire::test(OtorisasiPenyesuaianIndex::class)
            ->call('rejectPenyesuaian', $adj->id)
            ->assertOk();

        $this->assertSame('DISETUJUI', $adj->fresh()->status);
        $this->assertSame('DISETUJUI', $opname->fresh()->status);
    }

    public function test_web_reject_of_pending_adjustment_marks_opname_ditolak(): void
    {
        $opname = $this->makeOpname('MENUNGGU_APPROVAL');
        $adj = $this->makePenyesuaian($opname, 'MENUNGGU');

        $this->actingAs($this->kepalaGudang);
        Livewire::test(OtorisasiPenyesuaianIndex::class)->call('rejectPenyesuaian', $adj->id);

        $this->assertSame('DITOLAK', $adj->fresh()->status);
        $this->assertSame('DITOLAK', $opname->fresh()->status);
    }

    public function test_api_reject_marks_opname_ditolak_not_dibatalkan(): void
    {
        $opname = $this->makeOpname('MENUNGGU_APPROVAL');
        $adj = $this->makePenyesuaian($opname, 'MENUNGGU');

        $this->actingAs($this->kepalaGudang)
            ->postJson("/api/v1/gudang/penyesuaian/{$adj->id}/reject", ['alasan' => 'Hitung ulang'])
            ->assertOk();

        $this->assertSame('DITOLAK', $adj->fresh()->status);
        $this->assertSame('DITOLAK', $opname->fresh()->status);
    }

    /** @return array<string, array{string}> */
    public static function statusTertutupProvider(): array
    {
        return [
            'final disetujui' => ['DISETUJUI'],
            'final ditolak' => ['DITOLAK'],
            'sudah diajukan' => ['MENUNGGU_APPROVAL'],
        ];
    }

    #[DataProvider('statusTertutupProvider')]
    public function test_web_recount_of_closed_opname_is_refused(string $status): void
    {
        $opname = $this->makeOpname('BERJALAN');
        $detail = $this->makeDetail($opname);

        $this->actingAs($this->kepalaGudang);
        $component = Livewire::test(StockOpnameIndex::class)->call('openHitungFisik', $opname->id);

        // Status berubah setelah formulir dibuka (mis. diproses dari sesi lain).
        $opname->update(['status' => $status]);

        $component->set("countRows.{$detail->id}.jumlah_fisik", 3)->call('saveHitungFisik');

        $this->assertSame($status, $opname->fresh()->status);
        $this->assertSame(0, (int) $detail->fresh()->jumlah_fisik);
        $this->assertFalse(PenyesuaianStok::where('stok_opname_id', $opname->id)->exists());
    }

    public function test_web_recount_of_running_opname_creates_pending_adjustment(): void
    {
        $opname = $this->makeOpname('PROSES_COUNT');
        $detail = $this->makeDetail($opname);

        $this->actingAs($this->kepalaGudang);
        Livewire::test(StockOpnameIndex::class)
            ->call('openHitungFisik', $opname->id)
            ->set("countRows.{$detail->id}.jumlah_fisik", 3)
            ->call('saveHitungFisik');

        $this->assertSame('MENUNGGU_APPROVAL', $opname->fresh()->status);
        $this->assertSame(3, (int) $detail->fresh()->jumlah_fisik);
        $this->assertSame('MENUNGGU', PenyesuaianStok::where('stok_opname_id', $opname->id)->value('status'));
    }
}
