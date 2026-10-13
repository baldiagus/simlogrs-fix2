<?php

namespace Tests\Feature\Notifikasi;

use App\Models\Auth\UserDeviceToken;
use App\Models\Master\Notifikasi;
use App\Models\User;
use App\Services\Notifikasi\FakePengirimPush;
use App\Services\Notifikasi\NotifikasiService;
use App\Services\Notifikasi\PengirimPush;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class NotifikasiServiceTest extends TestCase
{
    use DatabaseTransactions;

    private FakePengirimPush $push;

    protected function setUp(): void
    {
        parent::setUp();
        $this->push = new FakePengirimPush();
        $this->app->instance(PengirimPush::class, $this->push);
    }

    private function user(string $nama): User
    {
        return User::create([
            'nama' => $nama, 'username' => $nama . '-ntf', 'email' => $nama . '-ntf@simlog.test',
            'password' => bcrypt('password'), 'status' => 'AKTIF',
        ]);
    }

    private function token(User $u, string $token): void
    {
        UserDeviceToken::create(['pengguna_id' => $u->id, 'fcm_token' => $token, 'dibuat_pada' => now()]);
    }

    public function test_kirim_menulis_satu_baris_per_penerima_tanpa_duplikat_dan_tanpa_pelaku(): void
    {
        [$a, $b, $pelaku] = [$this->user('a'), $this->user('b'), $this->user('pelaku')];

        $hasil = app(NotifikasiService::class)->kirim(
            [$a->id, $b->id, $a->id, $pelaku->id], 'Judul', 'Pesan', 'APPROVAL_PR', '14', '/kepala-unit/approval', $pelaku->id
        );

        $this->assertCount(2, $hasil);
        $this->assertSame(2, Notifikasi::where('tipe', 'APPROVAL_PR')->where('referensi_id', '14')->count());
        $this->assertSame(0, Notifikasi::where('pengguna_id', $pelaku->id)->count());
        $baris = Notifikasi::where('pengguna_id', $a->id)->first();
        $this->assertFalse($baris->dibaca);
        $this->assertSame('/kepala-unit/approval', $baris->tautan);
        $this->assertNotNull($baris->dibuat_pada);
    }

    public function test_push_memakai_payload_dan_collapse_key_sesuai_kontrak(): void
    {
        $a = $this->user('a');
        $this->token($a, 'tok-1');
        $this->token($a, 'tok-2');

        $n = app(NotifikasiService::class)->kirim([$a->id], 'Pengajuan PR Baru', 'PR-1 menunggu.', 'APPROVAL_PR', '14')->first();

        $this->assertCount(1, $this->push->terkirim);
        $kirim = $this->push->terkirim[0];
        $this->assertEqualsCanonicalizing(['tok-1', 'tok-2'], $kirim['tokens']);
        $this->assertSame('APPROVAL_PR:14', $kirim['collapse_key']);
        $this->assertSame([
            'notification' => ['title' => 'Pengajuan PR Baru', 'body' => 'PR-1 menunggu.'],
            'data'         => [
                'tipe' => 'APPROVAL_PR', 'referensi_id' => '14', 'notifikasi_id' => (string) $n->id,
                'judul' => 'Pengajuan PR Baru', 'pesan' => 'PR-1 menunggu.',
            ],
            'android'      => ['priority' => 'high', 'notification' => ['channel_id' => 'simlog_approval_channel']],
        ], $kirim['payload']);
    }

    public function test_token_ditolak_fcm_dihapus(): void
    {
        $a = $this->user('a');
        $this->token($a, 'tok-mati');
        $this->token($a, 'tok-hidup');
        $this->push->tokenDitolak = ['tok-mati'];

        app(NotifikasiService::class)->kirim([$a->id], 'J', 'P', 'UMUM');

        $this->assertSame(['tok-hidup'], UserDeviceToken::where('pengguna_id', $a->id)->pluck('fcm_token')->all());
    }

    public function test_kegagalan_push_tidak_menggagalkan_penulisan_baris(): void
    {
        $a = $this->user('a');
        $this->token($a, 'tok-1');
        $this->push->gagal = true;

        $hasil = app(NotifikasiService::class)->kirim([$a->id], 'J', 'P', 'UMUM', '1');

        $this->assertCount(1, $hasil);
        $this->assertSame(1, Notifikasi::where('pengguna_id', $a->id)->count());
    }
}
