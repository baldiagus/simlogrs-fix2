<?php

namespace Tests\Feature\Notifikasi\Web;

use App\Models\User;
use App\Services\Notifikasi\TautanWeb;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TautanWebTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $username): User
    {
        return User::where('username', $username)->firstOrFail();
    }

    private function resolve(?string $tautan, string $username): string
    {
        return app(TautanWeb::class)->resolve($tautan, $this->user($username));
    }

    public function test_tautan_null_atau_tidak_valid_menuju_daftar(): void
    {
        $daftar = route('kepala-unit.notifikasi');

        foreach ([null, '', 'bukan-path', '/tidak-ada-portal/x', '/kepala-unit', '/kepala-unit/rute-tidak-ada'] as $t) {
            $this->assertSame($daftar, $this->resolve($t, 'kepalaunit'), var_export($t, true));
        }
    }

    public function test_tautan_portal_sendiri_dipertahankan(): void
    {
        $this->assertSame(
            url('/kepala-unit/approval-permintaan'),
            $this->resolve('/kepala-unit/approval-permintaan', 'kepalaunit')
        );
    }

    public function test_query_string_dipertahankan(): void
    {
        $this->assertSame(
            url('/kepala-unit/approval-permintaan') . '?id=5',
            $this->resolve('/kepala-unit/approval-permintaan?id=5', 'kepalaunit')
        );
    }

    public function test_portal_tak_boleh_diakses_tidak_pernah_menghasilkan_portal_itu(): void
    {
        // petugasunit tidak punya portal petugas-gudang: hasil tidak boleh berprefix itu.
        $hasil = $this->resolve('/petugas-gudang/picking-distribusi', 'petugasunit');

        $this->assertStringNotContainsString('/petugas-gudang/', $hasil);
        $this->assertStringStartsWith(url('/petugas-unit/'), $hasil);
    }

    public function test_hasil_selalu_url_yang_lolos_middleware_portal(): void
    {
        $user = $this->user('adminlogistik');
        $hasil = app(TautanWeb::class)->resolve('/kepala-unit/approval-permintaan', $user);

        $this->assertStringStartsWith(url('/'), $hasil);
        $this->assertNotSame(url('/kepala-unit/approval-permintaan') . 'x', $hasil);
        $this->actingAs($user)->get($hasil)->assertStatus(200);
    }
}
