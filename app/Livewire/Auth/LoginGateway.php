<?php

namespace App\Livewire\Auth;

use Livewire\Component;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginGateway extends Component
{
    #[Validate('required|email', message: ['required' => 'Email wajib diisi.', 'email' => 'Format email tidak valid.'])]
    public string $email = '';

    #[Validate('required|string', message: ['required' => 'Kata sandi wajib diisi.'])]
    public string $password = '';

    public bool $remember = false;

    /**
     * Daftar putih chip "Akun Percobaan Cepat" (label => email akun seed).
     */
    public const AKUN_PERCOBAAN = [
        'Super Admin'    => 'superadmin@simlogrs.local',
        'Admin Logistik' => 'adminlogistik@simlogrs.local',
        'Kepala Gudang'  => 'kepalagudang@simlogrs.local',
        'Petugas Gudang' => 'petugasgudang@simlogrs.local',
        'Pengadaan'      => 'pengadaan@simlogrs.local',
        'Karu IGD'       => 'kepalaunit@simlogrs.local',
        'Petugas Unit'   => 'petugasunit@simlogrs.local',
        'Kepala Teknisi' => 'karu_ipsrs@simlogrs.local',
        'Teknisi IPSRS'  => 'teknisi@simlogrs.local',
        'Manajemen'      => 'manajemen@simlogrs.local',
        'Auditor SPI'    => 'auditor@simlogrs.local',
    ];

    /**
     * Isi kolom email & kata sandi dari chip akun percobaan (meniru aplikasi mobile).
     * Tidak mengautentikasi: pengguna tetap menekan tombol Masuk sehingga alur login() berlaku penuh.
     */
    public function isiAkun(string $email): void
    {
        if (! config('simlog.quick_login.enabled')) {
            abort(404);
        }

        if (! in_array($email, self::AKUN_PERCOBAAN, true)) {
            abort(404);
        }

        $this->email = $email;
        $this->password = (string) config('simlog.quick_login.password');
        $this->resetValidation();
    }

    /**
     * Otentikasi kredensial pengguna, terapkan rate limit login, catat audit log, dan arahkan ke portal peran.
     */
    public function login()
    {
        $this->validate();

        // 1. Rate Limiting: Maksimal simlog.auth.max_login_attempts (default 5) percobaan gagal per menit per kombinasi email + IP
        $throttleKey = strtolower($this->email) . '|' . request()->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, (int) config('simlog.auth.max_login_attempts'))) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => "Terlalu banyak percobaan masuk. Silakan coba lagi dalam {$seconds} detik.",
            ]);
        }

        // 2. Autentikasi Kredensial Pengguna
        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey);
            throw ValidationException::withMessages([
                'email' => 'Kredensial email atau kata sandi tidak cocok dengan data kami.',
            ]);
        }

        $user = Auth::user();

        // 3. Validasi Status Akun Aktif
        if ($user->status !== 'AKTIF') {
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();
            throw ValidationException::withMessages([
                'email' => 'Akun Anda dinonaktifkan oleh administrator. Hubungi bagian IT / SIMRS.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        // 4. Catat Jejak Audit Login
        $user->update([
            'terakhir_login' => now(),
            'ip_terakhir'    => request()->ip(),
        ]);

        // 5. Cek Flag Wajib Ganti Password
        if ($user->wajib_ganti_password) {
            return redirect()->route('password.ganti-wajib');
        }

        // 6. Penyaluran Otomatis ke Portal Spesifik Peran (Dynamic Redirect)
        return redirect()->intended($this->resolvePortalUrl($user));
    }

    /**
     * Tentukan URL pengalihan portal tujuan berdasarkan peran utama pengguna.
     */
    protected function resolvePortalUrl($user): string
    {
        if ($user->hasRole('kepala_teknisi')) {
            return '/teknisi/dashboard';
        }

        $portalMap = [
            'page_Dashboard'              => '/super-admin/dashboard',
            'page_AdminLogistikDashboard' => '/admin-logistik/dashboard',
            'page_KepalaGudangDashboard'  => '/kepala-gudang/dashboard',
            'page_GudangDashboard'        => '/petugas-gudang/dashboard',
            'page_PengadaanDashboard'     => '/pengadaan/dashboard',
            'page_KepalaUnitDashboard'    => '/kepala-unit/dashboard',
            'page_UnitDashboard'          => '/petugas-unit/dashboard',
            'page_TeknisiDashboard'       => '/teknisi/dashboard',
            'page_ManajemenDashboard'     => '/manajemen/dashboard',
            'page_AuditorDashboard'       => '/auditor/dashboard',
        ];

        foreach ($portalMap as $permission => $url) {
            if ($user->can($permission)) {
                return $url;
            }
        }

        return '/login';
    }

    /**
     * Render tampilan blade komponen Livewire.
     */
    public function render()
    {
        $quickLoginEnabled = (bool) config('simlog.quick_login.enabled');

        return view('livewire.auth.login-gateway', [
            'quickLoginEnabled' => $quickLoginEnabled,
            'akunPercobaan'     => $quickLoginEnabled ? self::AKUN_PERCOBAAN : [],
        ])
            ->layout('layouts.guest', ['title' => 'Masuk - SIMLOG RS Non-Medis']);
    }
}
