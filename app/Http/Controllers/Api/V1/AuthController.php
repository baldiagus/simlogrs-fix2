<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Models\LogAudit;

class AuthController extends ApiController
{
    /**
     * Autentikasi Klien Mobile & Penerbitan Sanctum Token.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string',
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email'    => 'Format email tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        if ($validator->fails()) {
            return $this->jsonError('Validasi data gagal.', 422, $validator->errors());
        }

        $email = $request->input('email');
        $throttleKey = strtolower($email) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, (int) config('simlog.auth.max_login_attempts'))) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return $this->jsonError("Terlalu banyak percobaan masuk. Coba lagi dalam {$seconds} detik.", 429);
        }

        if (! Auth::attempt($request->only('email', 'password'))) {
            RateLimiter::hit($throttleKey);
            return $this->jsonError('Kombinasi email atau kata sandi tidak valid.', 401);
        }

        $user = Auth::user();

        if ($user->status !== 'AKTIF') {
            Auth::logout();
            return $this->jsonError('Akun Anda dinonaktifkan oleh administrator.', 403);
        }

        RateLimiter::clear($throttleKey);

        // Update jejak login
        $user->update([
            'terakhir_login' => now(),
            'ip_terakhir'    => $request->ip(),
        ]);

        $deviceName = $request->input('device_name', $request->userAgent() ?? 'MobileApp');
        $abilities = $this->resolveTokenAbilities($user);
        $token = $user->createToken($deviceName, $abilities)->plainTextToken;

        return $this->jsonSuccess([
            'token'           => $token,
            'token_type'      => 'Bearer',
            'token_abilities' => $abilities,
            'user'            => $this->formatUserData($user),
        ], 'Autentikasi mobile berhasil.');
    }

    /**
     * Revoke Token Sesi Mobile.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->jsonSuccess(null, 'Berhasil keluar dari sesi mobile.');
    }


    /**
     * Memetakan peran pengguna ke dalam scope token abilities Sanctum yang terukur.
     */
    protected function resolveTokenAbilities($user): array
    {
        $abilities = ['mobile:access'];

        $roleAbilityMap = [
            'super_admin'    => ['role:super_admin', 'gudang:operator', 'unit:operator', 'teknisi:operator', 'pengadaan:operator', 'audit:operator'],
            'admin_logistik' => ['role:admin_logistik', 'gudang:operator', 'distribusi:operator'],
            'kepala_gudang'  => ['role:kepala_gudang', 'gudang:operator'],
            'petugas_gudang' => ['role:petugas_gudang', 'gudang:operator'],
            'kepala_unit'    => ['role:kepala_unit', 'unit:operator'],
            'petugas_unit'   => ['role:petugas_unit', 'unit:operator'],
            'kepala_teknisi' => ['role:kepala_teknisi', 'teknisi:operator'],
            'teknisi'        => ['role:teknisi', 'teknisi:operator'],
            'pengadaan'      => ['role:pengadaan', 'pengadaan:operator'],
            'manajemen'      => ['role:manajemen', 'manajemen:viewer'],
            'auditor'        => ['role:auditor', 'audit:viewer'],
        ];

        foreach ($user->getRoleNames() as $roleName) {
            if (isset($roleAbilityMap[$roleName])) {
                $abilities = array_merge($abilities, $roleAbilityMap[$roleName]);
            }
        }

        return array_values(array_unique($abilities));
    }
    /**
     * Mengambil Profil dan Wewenang Pengguna Aktif.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->jsonSuccess(
            $this->formatUserData($request->user()),
            'Data profil pengguna berhasil dimuat.'
        );
    }

    /**
     * Pergantian kata sandi wajib (force password change) untuk klien mobile.
     */
    public function gantiPasswordWajib(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'password_saat_ini'          => 'required|string',
            'password_baru'              => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
            'password_baru_confirmation' => 'required|string',
        ], [
            'password_saat_ini.required'          => 'Kata sandi saat ini wajib diisi.',
            'password_baru.required'              => 'Kata sandi baru wajib diisi.',
            'password_baru.min'                   => 'Kata sandi baru minimal 8 karakter.',
            'password_baru.confirmed'             => 'Konfirmasi kata sandi baru tidak cocok.',
            'password_baru_confirmation.required' => 'Ulangi kata sandi baru.',
        ]);

        if ($validator->fails()) {
            return $this->jsonError('Validasi data gagal.', 422, $validator->errors());
        }

        if (! Hash::check($request->input('password_saat_ini'), $user->password)) {
            return $this->jsonError('Kata sandi saat ini tidak valid.', 422, [
                'password_saat_ini' => ['Kata sandi saat ini tidak cocok.'],
            ]);
        }

        if (Hash::check($request->input('password_baru'), $user->password)) {
            return $this->jsonError('Kata sandi baru tidak boleh sama dengan kata sandi sementara saat ini.', 422, [
                'password_baru' => ['Kata sandi baru tidak boleh sama dengan kata sandi saat ini.'],
            ]);
        }

        // Perubahan sandi dan audit dalam satu transaksi (ADR 0002 B)
        DB::transaction(function () use ($user, $request) {
            $user->update([
                'password'             => Hash::make($request->input('password_baru')),
                'wajib_ganti_password' => false,
            ]);

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
                'ip_address'      => $request->ip(),
                'device'          => substr($request->userAgent() ?? 'MobileApp', 0, 255),
                'dibuat_pada'     => now(),
            ]);
        });

        return $this->jsonSuccess([
            'user'                 => $this->formatUserData($user),
            'wajib_ganti_password' => false,
        ], 'Kata sandi Anda berhasil diperbarui.');
    }

    /**
     * Format seragam data pengguna untuk klien mobile.
     */
    protected function formatUserData($user): array
    {
        $user->loadMissing(['unit', 'gudang']);

        return [
            'id'       => $user->id,
            'nama'     => $user->nama,
            'nip'      => $user->nip,
            'username' => $user->username,
            'email'    => $user->email,
            'no_telepon' => $user->no_telepon,
            'status'   => $user->status,
            'wajib_ganti_password' => (bool) $user->wajib_ganti_password,
            'unit'     => $user->unit ? [
                'id'   => $user->unit->id,
                'kode' => $user->unit->kode,
                'nama' => $user->unit->nama,
            ] : null,
            'gudang'   => $user->gudang ? [
                'id'   => $user->gudang->id,
                'kode' => $user->gudang->kode,
                'nama' => $user->gudang->nama,
            ] : null,
            'roles'    => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ];
    }
}
