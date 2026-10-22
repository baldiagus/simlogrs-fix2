<?php

namespace Tests\Feature\User;

use App\Livewire\Auth\LoginGateway;
use App\Livewire\User\PenggunaIndex;
use App\Mail\AkunPegawaiBaruMail;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrasiAkunBaruTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('email', 'superadmin@simlogrs.local')->firstOrFail();
    }

    private function registrasi(string $username)
    {
        return Livewire::actingAs($this->admin)->test(PenggunaIndex::class)
            ->call('openCreate')
            ->set('nama', 'Pegawai Registrasi Baru')
            ->set('username', $username)
            ->set('email', "{$username}@gmail.test")
            ->set('selectedRole', 'auditor')
            ->set('status', 'AKTIF');
    }

    public function test_create_menyiapkan_password_otomatis_dan_mengirim_email_ke_alamat_input(): void
    {
        Mail::fake();

        $component = $this->registrasi('pegawaibaru1');
        $passwordOtomatis = $component->get('password');
        $this->assertGreaterThanOrEqual(8, strlen($passwordOtomatis));

        $component->call('save')->assertHasNoErrors();

        $user = User::where('username', 'pegawaibaru1')->firstOrFail();
        $this->assertTrue(Hash::check($passwordOtomatis, $user->password));
        $this->assertTrue((bool) $user->wajib_ganti_password);

        Mail::assertSent(AkunPegawaiBaruMail::class, function (AkunPegawaiBaruMail $mail) use ($user, $passwordOtomatis) {
            return $mail->hasTo('pegawaibaru1@gmail.test')
                && $mail->passwordSementara === $passwordOtomatis
                && $mail->pengguna->is($user);
        });
    }

    public function test_email_memuat_informasi_akun_dan_password_sementara(): void
    {
        $user = User::where('email', 'superadmin@simlogrs.local')->firstOrFail();

        $html = (new AkunPegawaiBaruMail($user, 'Sementara123'))->render();

        $this->assertStringContainsString($user->username, $html);
        $this->assertStringContainsString($user->email, $html);
        $this->assertStringContainsString('Sementara123', $html);
    }

    public function test_akun_tetap_dibuat_walau_pengiriman_email_gagal(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP tidak dapat dihubungi'));

        $this->registrasi('pegawaibaru2')->call('save')->assertHasNoErrors();

        $this->assertTrue((bool) User::where('username', 'pegawaibaru2')->firstOrFail()->wajib_ganti_password);
    }

    public function test_login_akun_baru_diarahkan_ke_ganti_password_wajib(): void
    {
        Mail::fake();

        $component = $this->registrasi('pegawaibaru3');
        $passwordOtomatis = $component->get('password');
        $component->call('save');

        auth()->logout();

        Livewire::test(LoginGateway::class)
            ->set('email', 'pegawaibaru3@gmail.test')
            ->set('password', $passwordOtomatis)
            ->call('login')
            ->assertRedirect(route('password.ganti-wajib'));
    }
}
