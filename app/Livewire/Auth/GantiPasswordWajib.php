<?php

namespace App\Livewire\Auth;

use App\Models\Audit\LogAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Validate;
use Livewire\Component;

class GantiPasswordWajib extends Component
{
    #[Validate('required|string', message: ['required' => 'Kata sandi saat ini wajib diisi.'])]
    public string $password_saat_ini = '';

    public string $password_baru = '';
    public string $password_baru_confirmation = '';

    public function mount()
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        // Jika tidak berstatus wajib ganti password, arahkan langsung ke dashboard
        if (! $user->wajib_ganti_password) {
            return redirect()->intended($this->resolvePortalUrl($user));
        }
    }

    public function gantiPassword()
    {
        $this->validate([
            'password_saat_ini'          => ['required', 'string'],
            'password_baru'              => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
            'password_baru_confirmation' => ['required', 'string'],
        ], [
            'password_saat_ini.required'          => 'Kata sandi saat ini wajib diisi.',
            'password_baru.required'              => 'Kata sandi baru wajib diisi.',
            'password_baru.min'                   => 'Kata sandi baru minimal 8 karakter.',
            'password_baru.confirmed'             => 'Konfirmasi kata sandi baru tidak cocok.',
            'password_baru_confirmation.required' => 'Ulangi kata sandi baru.',
        ]);

        $user = Auth::user();

        // 1. Verifikasi kecocokan sandi lama / sementara
        if (! Hash::check($this->password_saat_ini, $user->password)) {
            $this->addError('password_saat_ini', 'Kata sandi saat ini tidak valid.');
            return;
        }

        // 2. Cegah penggunaan kata sandi yang sama persis dengan sandi sementara
        if (Hash::check($this->password_baru, $user->password)) {
            $this->addError('password_baru', 'Kata sandi baru tidak boleh sama dengan kata sandi sementara saat ini.');
            return;
        }

        // 3. Simpan kata sandi baru, cabut token, dan catat audit dalam satu transaksi (ADR 0002 B)
        DB::transaction(function () use ($user) {
            $user->update([
                'password'             => Hash::make($this->password_baru),
                'wajib_ganti_password' => false,
            ]);

            // Revoke seluruh token / sesi mobile lama agar klien login ulang dengan kredensial baru
            $user->tokens()->delete();

            LogAudit::create([
                'pengguna_id'     => $user->id,
                'peran_id'        => $user->roles?->first()?->id,
                'referensi_jenis' => 'User',
                'referensi_id'    => $user->id,
                'aksi'            => 'GANTI_PASSWORD',
                'data_sebelum'    => [
                    'user_id'              => $user->id,
                    'username'             => $user->username,
                    'wajib_ganti_password' => true,
                ],
                'data_sesudah'    => [
                    'user_id'              => $user->id,
                    'username'             => $user->username,
                    'wajib_ganti_password' => false,
                ],
                'ip_address'      => request()->ip(),
                'device'          => substr(request()->userAgent() ?? 'Web Browser', 0, 255),
                'dibuat_pada'     => now(),
            ]);
        });

        session()->flash('success', 'Kata sandi Anda berhasil diperbarui. Selamat datang di SIMLOG RS.');

        return redirect()->to($this->resolvePortalUrl($user));
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
        return view('livewire.auth.ganti-password-wajib')
            ->layout('layouts.guest', ['title' => 'Ganti Kata Sandi Wajib - SIMLOG RS']);
    }
}
