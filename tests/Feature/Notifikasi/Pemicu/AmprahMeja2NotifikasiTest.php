<?php

namespace Tests\Feature\Notifikasi\Pemicu;

use App\Models\Distribusi\PermintaanDistribusi;
use App\Models\Master\Notifikasi;
use App\Models\User;
use App\Services\Notifikasi\FakePengirimPush;
use App\Services\Notifikasi\PengirimPush;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Notifikasi amprah Meja 2: DISETUJUI_KARU ke kepala gudang, DISETUJUI_GUDANG ke staf gudang (antrean picking).
 */
class AmprahMeja2NotifikasiTest extends TestCase
{
    use DatabaseTransactions;

    /** Pasang pengirim push palsu agar tidak ada push sungguhan. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(PengirimPush::class, new FakePengirimPush());
    }

    /** Ambil pengguna seeder berdasarkan username. */
    private function u(string $username): User
    {
        return User::where('username', $username)->firstOrFail();
    }

    /** Buat amprah DIAJUKAN unit 1 untuk gudang 1. */
    private function amprah(): PermintaanDistribusi
    {
        return PermintaanDistribusi::create([
            'kode' => 'REQ-' . Str::upper(Str::random(8)), 'unit_id' => 1, 'gudang_id' => 1,
            'diajukan_oleh' => $this->u('petugasunit')->id, 'tanggal_permintaan' => now()->toDateString(),
            'urgensi' => 'NORMAL', 'status' => 'DIAJUKAN',
        ]);
    }

    /** ID penerima notifikasi bertipe tertentu untuk amprah, terurut. */
    private function penerima(string $tipe, int $ref, ?string $judul = null): array
    {
        return Notifikasi::where('tipe', $tipe)->where('referensi_id', (string) $ref)
            ->when($judul, fn ($q) => $q->where('judul', $judul))->orderBy('pengguna_id')->pluck('pengguna_id')->all();
    }

    public function test_disetujui_karu_notifikasi_ke_kepala_gudang_bukan_antrean_picking(): void
    {
        $a = $this->amprah();
        $this->actingAs($this->u('kepalaunit'));
        $a->update(['status' => 'DISETUJUI_KARU']);

        $this->assertSame([$this->u('kepalagudang')->id], $this->penerima('APPROVAL_AMPRAH', $a->id, 'Amprah Menunggu Otorisasi Gudang'));
        $this->assertSame([], $this->penerima('ANTREAN_PICKING', $a->id));
    }

    public function test_disetujui_gudang_notifikasi_antrean_picking_ke_staf_gudang(): void
    {
        $a = $this->amprah();
        $this->actingAs($this->u('kepalaunit'));
        $a->update(['status' => 'DISETUJUI_KARU']);
        $a->update(['status' => 'DISETUJUI_GUDANG']);

        $ids = [$this->u('kepalagudang')->id, $this->u('petugasgudang')->id];
        sort($ids);
        $this->assertSame($ids, $this->penerima('ANTREAN_PICKING', $a->id));
    }
}
