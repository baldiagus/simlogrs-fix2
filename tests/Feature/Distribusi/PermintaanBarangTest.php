<?php

namespace Tests\Feature\Distribusi;

use App\Livewire\Distribusi\PermintaanBarangIndex;
use App\Models\Master\Barang;
use App\Models\Master\Gudang;
use App\Models\Distribusi\PermintaanDistribusi;
use App\Models\Distribusi\PermintaanDistribusiDetail;
use App\Models\Master\Unit;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class PermintaanBarangTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Unit $unit;
    protected Gudang $gudang;
    protected Barang $barang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::where('email', 'superadmin@simlogrs.local')->first();
        $this->unit = Unit::where('status', 'AKTIF')->first();
        $this->gudang = Gudang::where('status', 'AKTIF')->first();
        $this->barang = Barang::where('status', 'AKTIF')->first();
    }

    public function test_can_cancel_unprocessed_requisition_with_disetujui_karu_status(): void
    {
        $this->actingAs($this->user);

        // Permintaan diajukan oleh Kepala Unit sehingga otomatis berstatus DISETUJUI_KARU
        $permintaan = PermintaanDistribusi::create([
            'kode' => CodeGenerator::generate('REQ', PermintaanDistribusi::class, 'kode', 'Ym', 3),
            'unit_id' => $this->unit->id,
            'gudang_id' => $this->gudang->id,
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi' => 'REGULER',
            'status' => 'DISETUJUI_KARU',
            'diajukan_oleh' => $this->user->id,
            'disetujui_oleh' => $this->user->id,
        ]);

        PermintaanDistribusiDetail::create([
            'permintaan_distribusi_id' => $permintaan->id,
            'barang_id' => $this->barang->id,
            'jumlah' => 10,
            'jumlah_disetujui' => 10,
        ]);

        // Pemohon ingin membatalkan sebelum diproses gudang -> HARUS BISA
        Livewire::test(PermintaanBarangIndex::class)
            ->call('batal', $permintaan->id);

        $permintaan->refresh();
        $this->assertEquals('DITOLAK', $permintaan->status);
    }
}
