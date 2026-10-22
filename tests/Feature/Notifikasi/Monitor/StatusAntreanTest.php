<?php

namespace Tests\Feature\Notifikasi\Monitor;

use App\Jobs\KirimPushFcm;
use App\Livewire\System\SchedulerNotifikasiIndex;
use App\Models\Auth\UserDeviceToken;
use App\Models\User;
use App\Services\Notifikasi\FakePengirimPush;
use App\Services\Notifikasi\StatusAntrean;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class StatusAntreanTest extends TestCase
{
    use DatabaseTransactions;

    private StatusAntrean $status;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Cache::forget(StatusAntrean::KUNCI_DETAK);
        $this->status = new StatusAntrean();
    }

    private function antre(int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            Queue::pushOn('default', new KirimPushFcm(1));
        }
    }

    public function test_sehat_saat_antrean_kosong_walau_belum_ada_detak(): void
    {
        $this->assertSame(0, $this->status->jumlahTertunda());
        $this->assertNull($this->status->detakTerakhir());
        $this->assertTrue($this->status->sehat());
    }

    public function test_bermasalah_saat_antrean_menumpuk_tanpa_detak(): void
    {
        $this->antre(2);

        $this->assertSame(2, $this->status->jumlahTertunda());
        $this->assertFalse($this->status->sehat());
    }

    public function test_bermasalah_saat_antrean_menumpuk_dan_detak_lama(): void
    {
        $this->antre(1);
        Cache::put(StatusAntrean::KUNCI_DETAK, now()->subMinutes(6)->toIso8601String());

        $this->assertFalse($this->status->sehat());
    }

    public function test_sehat_saat_antrean_menumpuk_tapi_detak_baru(): void
    {
        $this->antre(1);
        Cache::put(StatusAntrean::KUNCI_DETAK, now()->subMinutes(4)->toIso8601String());

        $this->assertTrue($this->status->sehat());
    }

    public function test_hitung_token_dan_pengguna_unik(): void
    {
        $tokenAwal = UserDeviceToken::count();
        $penggunaAwal = UserDeviceToken::distinct()->count('pengguna_id');
        $baru = User::whereNotIn('id', UserDeviceToken::pluck('pengguna_id'))->take(2)->pluck('id');

        UserDeviceToken::create(['pengguna_id' => $baru[0], 'fcm_token' => 'mon-1', 'dibuat_pada' => now()]);
        UserDeviceToken::create(['pengguna_id' => $baru[0], 'fcm_token' => 'mon-2', 'dibuat_pada' => now()]);
        UserDeviceToken::create(['pengguna_id' => $baru[1], 'fcm_token' => 'mon-3', 'dibuat_pada' => now()]);

        $this->assertSame($tokenAwal + 3, $this->status->jumlahTokenTerdaftar());
        $this->assertSame($penggunaAwal + 2, $this->status->jumlahPenggunaBerToken());
    }

    public function test_kirim_push_fcm_menulis_detak(): void
    {
        $this->travelTo(now()->startOfMinute());

        (new KirimPushFcm(0))->handle(new FakePengirimPush());

        $this->assertSame(now()->toIso8601String(), Cache::get(StatusAntrean::KUNCI_DETAK));
        $this->assertTrue($this->status->detakTerakhir()->equalTo(now()));
    }

    public function test_halaman_scheduler_merender_kartu_status_push(): void
    {
        $admin = User::where('email', 'superadmin@simlogrs.local')->firstOrFail();
        $this->antre(3);

        $response = $this->actingAs($admin)->get('/super-admin/scheduler')
            ->assertOk()
            ->assertSee('Status Pengiriman Push')
            ->assertSee('docker compose up -d queue');

        // Livewire 3.8 tidak menyediakan assertSeeLivewire: periksa snapshot komponen di HTML.
        $this->assertMatchesRegularExpression('/wire:snapshot="[^"]*&quot;name&quot;:&quot;system\.scheduler-notifikasi-index&quot;/', $response->getContent());

        Livewire::actingAs($admin)->test(SchedulerNotifikasiIndex::class)
            ->assertViewHas('statusPush', fn (array $s) => $s['tertunda'] === 3
                && $s['detak'] === '-'
                && $s['sehat'] === false
                && $s['perangkat'] === UserDeviceToken::count()
                && $s['pengguna'] === UserDeviceToken::distinct()->count('pengguna_id'))
            ->assertSee('Bermasalah')
            ->assertDontSee('Sehat');
    }

    public function test_halaman_scheduler_sehat_saat_detak_baru(): void
    {
        $admin = User::where('email', 'superadmin@simlogrs.local')->firstOrFail();
        $this->antre(1);
        Cache::put(StatusAntrean::KUNCI_DETAK, now()->subMinute()->toIso8601String());

        Livewire::actingAs($admin)->test(SchedulerNotifikasiIndex::class)
            ->assertViewHas('statusPush', fn (array $s) => $s['sehat'] === true && $s['tertunda'] === 1)
            ->assertSee(now()->subMinute()->diffForHumans())
            ->assertSee('Sehat');
    }
}
