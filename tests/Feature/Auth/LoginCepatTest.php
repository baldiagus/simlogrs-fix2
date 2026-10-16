<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\LoginGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Kartu "Akun Percobaan Cepat" di /login: chip hanya mengisi kolom, tidak mengautentikasi.
 */
class LoginCepatTest extends TestCase
{
    use DatabaseTransactions;

    private const SANDI_UJI = 'SandiUji@2026!';

    public function test_flag_mati_isi_akun_404_dan_kartu_tidak_dirender(): void
    {
        config(['simlog.quick_login.enabled' => false, 'simlog.quick_login.password' => self::SANDI_UJI]);

        $component = Livewire::test(LoginGateway::class)
            ->assertDontSee('Akun Percobaan Cepat')
            ->assertDontSee('karu_ipsrs@simlogrs.local')
            ->assertDontSee(self::SANDI_UJI);

        $component->call('isiAkun', 'karu_ipsrs@simlogrs.local')->assertStatus(404);
    }

    public function test_flag_hidup_isi_akun_mengisi_email_dan_password(): void
    {
        config(['simlog.quick_login.enabled' => true, 'simlog.quick_login.password' => self::SANDI_UJI]);

        Livewire::test(LoginGateway::class)
            ->assertSee('Akun Percobaan Cepat')
            ->assertSee('Kepala Teknisi')
            ->assertDontSee(self::SANDI_UJI)
            ->call('isiAkun', 'karu_ipsrs@simlogrs.local')
            ->assertSet('email', 'karu_ipsrs@simlogrs.local')
            ->assertSet('password', self::SANDI_UJI)
            ->assertHasNoErrors();

        $this->assertGuest();
    }

    public function test_email_di_luar_daftar_putih_ditolak_walau_flag_hidup(): void
    {
        config(['simlog.quick_login.enabled' => true, 'simlog.quick_login.password' => self::SANDI_UJI]);

        Livewire::test(LoginGateway::class)
            ->call('isiAkun', 'penyusup@luar.test')
            ->assertStatus(404);
    }

    public function test_isi_akun_lalu_login_kepala_teknisi_diarahkan_ke_portal_teknisi(): void
    {
        config(['simlog.quick_login.enabled' => true, 'simlog.quick_login.password' => self::SANDI_UJI]);

        $email = 'karu_ipsrs@simlogrs.local';
        RateLimiter::clear($email . '|127.0.0.1');

        $user = User::updateOrCreate(['email' => $email], [
            'nama'                 => 'Kepala Teknisi Uji',
            'username'             => 'karu_ipsrs',
            'password'             => bcrypt(self::SANDI_UJI),
            'status'               => 'AKTIF',
            'wajib_ganti_password' => false,
        ]);
        $user->syncRoles([Role::findOrCreate('kepala_teknisi', 'web')]);

        Livewire::test(LoginGateway::class)
            ->call('isiAkun', $email)
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect('/teknisi/dashboard');

        $this->assertAuthenticatedAs($user);
    }
}
