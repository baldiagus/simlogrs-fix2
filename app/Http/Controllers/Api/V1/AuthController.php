<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

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

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
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
        $token = $user->createToken($deviceName)->plainTextToken;

        return $this->jsonSuccess([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->formatUserData($user),
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
