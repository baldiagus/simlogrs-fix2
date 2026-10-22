<?php

namespace Tests\Feature\Notifikasi;

use App\Models\Auth\UserDeviceToken;
use App\Models\Master\Notifikasi;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class NotifikasiApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $a;
    private User $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = $this->user('a');
        $this->b = $this->user('b');
    }

    private function user(string $nama): User
    {
        return User::create([
            'nama' => $nama, 'username' => $nama . '-ntfapi', 'email' => $nama . '-ntfapi@simlog.test',
            'password' => bcrypt('password'), 'status' => 'AKTIF',
        ]);
    }

    private function notif(User $u, array $o = []): Notifikasi
    {
        return Notifikasi::create(array_merge([
            'pengguna_id' => $u->id, 'judul' => 'J', 'pesan' => 'P', 'tipe' => 'APPROVAL_PR',
            'referensi_id' => '1', 'dibaca' => false, 'dibuat_pada' => now(),
        ], $o));
    }

    public function test_simpan_token_idempoten_dan_memindahkan_antar_pengguna(): void
    {
        $this->actingAs($this->a)->postJson('/api/v1/auth/fcm-token', ['token' => 'tok-x'])
            ->assertOk()->assertJsonPath('data.terdaftar', true);
        $this->actingAs($this->a)->postJson('/api/v1/auth/fcm-token', ['token' => 'tok-x'])->assertOk();
        $this->assertSame(1, UserDeviceToken::where('fcm_token', 'tok-x')->count());

        $this->actingAs($this->b)->postJson('/api/v1/auth/fcm-token', ['token' => 'tok-x', 'device_type' => 'android'])->assertOk();
        $this->assertSame($this->b->id, UserDeviceToken::where('fcm_token', 'tok-x')->sole()->pengguna_id);
    }

    public function test_simpan_token_wajib_diisi(): void
    {
        $this->actingAs($this->a)->postJson('/api/v1/auth/fcm-token', [])->assertStatus(422);
    }

    public function test_hapus_token_hanya_milik_sendiri_dan_tanpa_token_menghapus_semua(): void
    {
        foreach ([[$this->a, 't1'], [$this->a, 't2'], [$this->b, 't3']] as [$u, $t]) {
            UserDeviceToken::create(['pengguna_id' => $u->id, 'fcm_token' => $t, 'dibuat_pada' => now()]);
        }

        $this->actingAs($this->a)->deleteJson('/api/v1/auth/fcm-token', ['token' => 't3'])->assertJsonPath('data.dihapus', 0);
        $this->actingAs($this->a)->deleteJson('/api/v1/auth/fcm-token', ['token' => 't1'])->assertJsonPath('data.dihapus', 1);
        $this->actingAs($this->a)->deleteJson('/api/v1/auth/fcm-token')->assertJsonPath('data.dihapus', 1);
        $this->assertSame(['t3'], UserDeviceToken::whereIn('pengguna_id', [$this->a->id, $this->b->id])->pluck('fcm_token')->all());
    }

    public function test_index_hanya_milik_pengguna_dengan_paginasi_dan_belum_dibaca(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->notif($this->a, ['dibuat_pada' => now()->subMinutes($i)]);
        }
        $this->notif($this->a, ['dibaca' => true, 'tipe' => null]);
        $this->notif($this->b);

        $res = $this->actingAs($this->a)->getJson('/api/v1/notifikasi')->assertOk();
        $res->assertJsonPath('data.paginasi', ['total' => 26, 'per_halaman' => 20, 'halaman_ini' => 1, 'total_halaman' => 2])
            ->assertJsonPath('data.belum_dibaca', 25)
            ->assertJsonCount(20, 'data.items');
        $this->assertSame(['id', 'judul', 'pesan', 'tipe', 'referensi_id', 'tautan', 'dibaca', 'dibuat_pada'], array_keys($res->json('data.items.0')));

        $this->actingAs($this->a)->getJson('/api/v1/notifikasi?page=2')->assertJsonCount(6, 'data.items');
        $this->actingAs($this->a)->getJson('/api/v1/notifikasi?hanya_belum_dibaca=1')->assertJsonPath('data.paginasi.total', 25);
    }

    public function test_tipe_kosong_dikembalikan_sebagai_umum(): void
    {
        $this->notif($this->a, ['tipe' => null]);

        $this->actingAs($this->a)->getJson('/api/v1/notifikasi')->assertJsonPath('data.items.0.tipe', 'UMUM');
    }

    public function test_dibaca_dan_baca_semua_tidak_menyentuh_milik_orang_lain(): void
    {
        $milikA1 = $this->notif($this->a);
        $this->notif($this->a);
        $milikB = $this->notif($this->b);

        $this->actingAs($this->a)->postJson("/api/v1/notifikasi/{$milikB->id}/dibaca")->assertNotFound();
        $this->assertFalse($milikB->fresh()->dibaca);

        $this->actingAs($this->a)->postJson("/api/v1/notifikasi/{$milikA1->id}/dibaca")
            ->assertOk()->assertJsonPath('data.belum_dibaca', 1);

        $this->actingAs($this->a)->postJson('/api/v1/notifikasi/baca-semua')->assertJsonPath('data.belum_dibaca', 0);
        $this->assertFalse($milikB->fresh()->dibaca);
    }

    public function test_dashboard_summary_memuat_belum_dibaca(): void
    {
        $this->notif($this->a);
        $this->notif($this->a, ['dibaca' => true]);

        $this->actingAs($this->a)->getJson('/api/v1/dashboard/summary')->assertOk()->assertJsonPath('data.belum_dibaca', 1);
    }
}
