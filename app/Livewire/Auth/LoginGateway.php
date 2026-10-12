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

    public function render()
    {
        return view('livewire.auth.login-gateway')
            ->layout('layouts.guest', ['title' => 'Masuk - SIMLOG RS Non-Medis']);
    }
}
