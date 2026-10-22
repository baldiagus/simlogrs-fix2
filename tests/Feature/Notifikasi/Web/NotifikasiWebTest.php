<?php

namespace Tests\Feature\Notifikasi\Web;

use App\Livewire\Notifikasi\DaftarNotifikasi;
use App\Livewire\Notifikasi\Lonceng;
use App\Models\Master\Notifikasi;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class NotifikasiWebTest extends TestCase
{
    use DatabaseTransactions;

    private User $saya;
    private User $lain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->saya = User::where('username', 'kepalaunit')->firstOrFail();
        $this->lain = User::where('username', 'petugasunit')->firstOrFail();
        // Bersihkan inbox seeder agar hitungan deterministik (rollback oleh DatabaseTransactions).
        Notifikasi::whereIn('pengguna_id', [$this->saya->id, $this->lain->id])->delete();
    }

    private function notif(User $u, array $o = []): Notifikasi
    {
        return Notifikasi::create(array_merge([
            'pengguna_id' => $u->id, 'judul' => 'Judul', 'pesan' => 'Pesan', 'tipe' => 'APPROVAL_PR',
            'referensi_id' => '1', 'tautan' => '/kepala-unit/approval-permintaan', 'dibaca' => false, 'dibuat_pada' => now(),
        ], $o));
    }

    private function banyak(User $u, int $n, array $o = []): void
    {
        for ($i = 1; $i <= $n; $i++) {
            $this->notif($u, array_merge(['judul' => "N{$i}", 'dibuat_pada' => now()->subMinutes($n - $i)], $o));
        }
    }

    /** Livewire 3.8 tidak menyediakan assertSeeLivewire: periksa snapshot komponen di HTML. */
    private function assertKomponenTerender($response, string $namaKomponen): void
    {
        $this->assertMatchesRegularExpression(
            '/wire:snapshot="[^"]*&quot;name&quot;:&quot;' . preg_quote($namaKomponen, '/') . '&quot;/',
            $response->getContent(),
            "Komponen Livewire {$namaKomponen} tidak dirender."
        );
    }

    public function test_halaman_portal_merender_lonceng(): void
    {
        $this->notif($this->saya);

        $response = $this->actingAs($this->saya)->get('/kepala-unit/dashboard')->assertOk()->assertSee('lonceng-badge', false);
        $this->assertKomponenTerender($response, 'notifikasi.lonceng');
    }

    public function test_rute_daftar_notifikasi_dapat_dibuka(): void
    {
        $response = $this->actingAs($this->saya)->get('/kepala-unit/notifikasi')->assertOk();
        $this->assertKomponenTerender($response, 'notifikasi.daftar-notifikasi');
    }

    public function test_badge_hanya_menghitung_milik_sendiri(): void
    {
        $this->banyak($this->saya, 3);
        $this->notif($this->saya, ['dibaca' => true]);
        $this->banyak($this->lain, 5);

        Livewire::actingAs($this->saya)->test(Lonceng::class)
            ->assertViewHas('belumDibaca', 3);
    }

    public function test_badge_99_plus(): void
    {
        $this->banyak($this->saya, 100);

        Livewire::actingAs($this->saya)->test(Lonceng::class)->assertSee('99+');
    }

    public function test_dropdown_memuat_10_terbaru_urut_turun(): void
    {
        $this->banyak($this->saya, 12);

        $c = Livewire::actingAs($this->saya)->test(Lonceng::class);
        $items = $c->viewData('items');

        $this->assertCount(10, $items);
        $this->assertSame('N12', $items->first()->judul);
        $this->assertSame('N3', $items->last()->judul);
    }

    public function test_pengguna_tanpa_notifikasi_melihat_keadaan_kosong(): void
    {
        Livewire::actingAs($this->saya)->test(Lonceng::class)
            ->assertSee('Belum ada notifikasi.')->assertDontSee('lonceng-badge');

        Livewire::actingAs($this->saya)->test(DaftarNotifikasi::class)->assertSee('Belum ada notifikasi.');
    }

    public function test_tandai_dibaca_satu_dan_semua(): void
    {
        $a = $this->notif($this->saya);
        $b = $this->notif($this->saya);
        $punyaLain = $this->notif($this->lain);

        $c = Livewire::actingAs($this->saya)->test(Lonceng::class)->call('tandaiDibaca', $a->id);
        $this->assertTrue($a->fresh()->dibaca);
        $this->assertFalse($b->fresh()->dibaca);

        $c->call('tandaiSemuaDibaca');
        $this->assertTrue($b->fresh()->dibaca);
        $this->assertFalse($punyaLain->fresh()->dibaca, 'baris orang lain tidak boleh ikut berubah');
    }

    public function test_tandai_dibaca_milik_orang_lain_gagal_dan_tidak_mengubah(): void
    {
        $punyaLain = $this->notif($this->lain);

        Livewire::actingAs($this->saya)->test(Lonceng::class)->call('tandaiDibaca', $punyaLain->id)->assertNotFound();
        Livewire::actingAs($this->saya)->test(DaftarNotifikasi::class)->call('tandaiDibaca', $punyaLain->id)->assertNotFound();
        Livewire::actingAs($this->saya)->test(Lonceng::class)->call('buka', $punyaLain->id)->assertNotFound();

        $this->assertFalse($punyaLain->fresh()->dibaca);
    }

    public function test_buka_menandai_dibaca_dan_redirect_ke_tautan(): void
    {
        $n = $this->notif($this->saya, ['tautan' => '/kepala-unit/approval-permintaan']);

        Livewire::actingAs($this->saya)->test(Lonceng::class)->call('buka', $n->id)
            ->assertRedirect(url('/kepala-unit/approval-permintaan'));

        $this->assertTrue($n->fresh()->dibaca);
    }

    public function test_buka_dengan_tautan_null_menuju_daftar(): void
    {
        $n = $this->notif($this->saya, ['tautan' => null]);

        Livewire::actingAs($this->saya)->test(Lonceng::class)->call('buka', $n->id)
            ->assertRedirect(route('kepala-unit.notifikasi'));
    }

    public function test_daftar_memfilter_belum_dibaca_dan_hanya_milik_sendiri(): void
    {
        $this->notif($this->saya, ['judul' => 'SUDAH-BACA', 'dibaca' => true]);
        $this->notif($this->saya, ['judul' => 'BELUM-BACA']);
        $this->notif($this->lain, ['judul' => 'PUNYA-LAIN']);

        Livewire::actingAs($this->saya)->test(DaftarNotifikasi::class)
            ->assertSee('SUDAH-BACA')->assertSee('BELUM-BACA')->assertDontSee('PUNYA-LAIN')
            ->set('filter', 'belum')
            ->assertDontSee('SUDAH-BACA')->assertSee('BELUM-BACA');
    }

    public function test_daftar_memaginasi_20_per_halaman(): void
    {
        $this->banyak($this->saya, 25);

        $c = Livewire::actingAs($this->saya)->test(DaftarNotifikasi::class);
        $p1 = $c->viewData('items');
        $this->assertCount(20, $p1);
        $this->assertSame(25, $p1->total());

        $c->call('gotoPage', 2);
        $this->assertCount(5, $c->viewData('items'));
    }

    public function test_daftar_tandai_dibaca_per_item_dan_semua(): void
    {
        $a = $this->notif($this->saya);
        $b = $this->notif($this->saya);

        $c = Livewire::actingAs($this->saya)->test(DaftarNotifikasi::class)->call('tandaiDibaca', $a->id);
        $this->assertTrue($a->fresh()->dibaca);
        $this->assertFalse($b->fresh()->dibaca);

        $c->call('tandaiSemuaDibaca');
        $this->assertTrue($b->fresh()->dibaca);
    }
}
