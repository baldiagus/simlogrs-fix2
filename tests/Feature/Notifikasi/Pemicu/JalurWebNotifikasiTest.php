<?php

namespace Tests\Feature\Notifikasi\Pemicu;

use App\Livewire\Distribusi\PermintaanBarangIndex;
use App\Models\Distribusi\PermintaanDistribusi;
use App\Models\Master\Barang;
use App\Models\Master\Notifikasi;
use App\Models\User;
use App\Services\Notifikasi\FakePengirimPush;
use App\Services\Notifikasi\PengirimPush;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Bukti inti fitur: transaksi yang DIINPUT DI WEB (komponen Livewire) memicu notifikasi
 * yang sama dengan jalur API, karena pemicunya Model Observer, bukan controller.
 */
class JalurWebNotifikasiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(PengirimPush::class, new FakePengirimPush());
    }

    public function test_amprah_dibuat_lewat_web_memberi_notifikasi_ke_karu_unit_yang_sama_dan_bukan_pelaku(): void
    {
        $staf = User::where('username', 'petugasunit')->firstOrFail();
        $karu = User::where('username', 'kepalaunit')->firstOrFail();
        $barang = Barang::where('status', 'AKTIF')->where('tipe', 'HABIS_PAKAI')->firstOrFail();

        $this->assertSame((int) $staf->unit_id, (int) $karu->unit_id, 'prasyarat data uji: staf dan Karu satu unit');

        $sebelumKaru = Notifikasi::where('pengguna_id', $karu->id)->where('tipe', 'APPROVAL_AMPRAH')->count();

        $this->actingAs($staf);
        Livewire::test(PermintaanBarangIndex::class)
            ->call('create')
            ->set('items', [['barang_id' => $barang->id, 'jumlah' => 1, 'catatan' => '']])
            ->call('save')
            ->assertHasNoErrors();

        $amprah = PermintaanDistribusi::where('diajukan_oleh', $staf->id)->latest('id')->firstOrFail();

        $baruKaru = Notifikasi::where('pengguna_id', $karu->id)
            ->where('tipe', 'APPROVAL_AMPRAH')
            ->where('referensi_id', (string) $amprah->id)
            ->get();

        $this->assertCount(1, $baruKaru, 'Karu unit yang sama harus menerima tepat satu notifikasi dari input web');
        $this->assertSame($sebelumKaru + 1, Notifikasi::where('pengguna_id', $karu->id)->where('tipe', 'APPROVAL_AMPRAH')->count());
        $this->assertSame(
            0,
            Notifikasi::where('pengguna_id', $staf->id)->where('referensi_id', (string) $amprah->id)->count(),
            'pelaku tidak boleh menerima notifikasi atas aksinya sendiri'
        );
    }
}
