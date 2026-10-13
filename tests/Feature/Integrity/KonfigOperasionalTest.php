<?php

namespace Tests\Feature\Integrity;

use App\Livewire\Auth\LoginGateway;
use App\Livewire\Ipsrs\LaporServisIndex;
use App\Livewire\Persediaan\MonitoringStokFefoIndex;
use App\Models\User;
use App\Support\File\FileOptimizer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Nilai operasional terpusat di config/simlog.php (override via SIMLOG_*).
 */
class KonfigOperasionalTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('email', 'superadmin@simlogrs.local')->firstOrFail();
    }

    public function test_default_values_equal_previous_hardcoded_constants(): void
    {
        $this->assertSame(60, config('simlog.api.throttle.max_attempts'));
        $this->assertSame(1, config('simlog.api.throttle.decay_minutes'));
        $this->assertSame(5, config('simlog.auth.max_login_attempts'));
        $this->assertSame(60, config('simlog.cache.dropdown_ttl'));
        $this->assertSame(10240, config('simlog.upload.max_kb'));
        $this->assertSame(1600, config('simlog.upload.image_max_dimension'));
        $this->assertSame(80, config('simlog.upload.image_quality'));

        $route = Route::getRoutes()->match(request()->create('/api/v1/auth/me', 'GET'));
        $this->assertContains('throttle:60,1', $route->gatherMiddleware());
    }

    public function test_default_login_limit_still_allows_third_attempt(): void
    {
        $user = $this->makeLoginUser('konfig.default@simlog.test');

        $component = Livewire::test(LoginGateway::class)
            ->set('email', $user->email)
            ->set('password', 'salah-sandi');

        for ($i = 0; $i < 3; $i++) {
            $component->call('login');
        }

        $this->assertStringContainsString('tidak cocok', $component->errors()->first('email'));
    }

    public function test_overridden_login_limit_locks_on_third_bad_attempt(): void
    {
        config(['simlog.auth.max_login_attempts' => 2]);
        $user = $this->makeLoginUser('konfig.lock@simlog.test');

        $component = Livewire::test(LoginGateway::class)
            ->set('email', $user->email)
            ->set('password', 'salah-sandi');

        $component->call('login');
        $component->call('login');
        $this->assertStringContainsString('tidak cocok', $component->errors()->first('email'));

        // Percobaan ke-3 terkunci walau kata sandi benar.
        $component->set('password', 'password')->call('login');

        $this->assertStringContainsString('Terlalu banyak percobaan', $component->errors()->first('email'));
        $this->assertGuest();
    }

    public function test_overridden_upload_max_kb_rejects_oversize_image_on_lapor_servis(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(LaporServisIndex::class)
            ->set('foto', $this->jpegOfKb(200))
            ->assertHasNoErrors('foto');

        config(['simlog.upload.max_kb' => 100]);

        $component = Livewire::test(LaporServisIndex::class)
            ->set('foto', $this->jpegOfKb(200))
            ->assertHasErrors(['foto' => 'max']);

        $this->assertSame('Ukuran foto maksimal adalah 10 MB.', $component->errors()->first('foto'));
    }

    public function test_lapor_servis_keeps_custom_foto_messages(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(LaporServisIndex::class)
            ->set('foto', UploadedFile::fake()->image('animasi.gif'))
            ->assertHasErrors(['foto' => 'mimes']);

        $this->assertSame('Format gambar yang didukung: JPG, PNG, WEBP.', $component->errors()->first('foto'));
    }

    public function test_overridden_dropdown_ttl_is_honored(): void
    {
        $this->actingAs($this->admin);
        Cache::forget('dropdown_gudang_aktif');
        config(['simlog.cache.dropdown_ttl' => 5]);

        Livewire::test(MonitoringStokFefoIndex::class)->assertOk();
        $this->assertTrue(Cache::has('dropdown_gudang_aktif'));

        // Dengan TTL default 60 detik kunci masih ada; dengan TTL 5 detik sudah kedaluwarsa.
        $this->travel(10)->seconds();
        $this->assertFalse(Cache::has('dropdown_gudang_aktif'));
    }

    public function test_file_optimizer_uses_configured_image_dimension(): void
    {
        Storage::fake('public');
        config(['simlog.upload.image_max_dimension' => 100]);

        $hasil = FileOptimizer::optimizeAndStore(UploadedFile::fake()->image('besar.jpg', 400, 200), 'uji');

        [$lebar, $tinggi] = getimagesizefromstring(Storage::disk('public')->get($hasil['path']));
        $this->assertSame(100, $lebar);
        $this->assertSame(50, $tinggi);
    }

    /**
     * JPEG valid dengan ukuran fisik nyata ~$kb KB (size() palsu tidak dipertahankan oleh upload Livewire).
     */
    protected function jpegOfKb(int $kb): UploadedFile
    {
        $file = UploadedFile::fake()->image('kerusakan.jpg', 50, 50);
        file_put_contents($file->getRealPath(), random_bytes($kb * 1024), FILE_APPEND);
        clearstatcache();

        return $file;
    }

    protected function makeLoginUser(string $email): User
    {
        RateLimiter::clear(strtolower($email) . '|127.0.0.1');

        return User::create([
            'nama'     => 'Pengguna Konfig',
            'username' => strstr($email, '@', true),
            'email'    => $email,
            'password' => bcrypt('password'),
            'status'   => 'AKTIF',
        ]);
    }
}
