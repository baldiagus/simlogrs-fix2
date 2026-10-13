<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Models\Auth\UserDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Pendaftaran dan penghapusan token FCM perangkat mobile.
 */
class FcmTokenApiController extends ApiController
{
    /**
     * Daftarkan token (idempoten; token milik pengguna lain dipindahkan ke pengguna ini).
     * POST /api/v1/auth/fcm-token
     */
    public function simpan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token'       => 'required|string|max:255',
            'device_type' => 'nullable|string|max:20',
        ], [
            'token.required' => 'Token FCM wajib diisi.',
            'token.max'      => 'Token FCM maksimal 255 karakter.',
        ]);

        if ($validator->fails()) {
            return $this->jsonError('Validasi data gagal.', 422, $validator->errors());
        }

        UserDeviceToken::updateOrCreate(
            ['fcm_token' => $request->input('token')],
            [
                'pengguna_id'  => $request->user()->id,
                'device_type'  => $request->input('device_type', 'android'),
                'last_used_at' => now(),
            ]
        );

        return $this->jsonSuccess(['terdaftar' => true], 'Token perangkat terdaftar.');
    }

    /**
     * Hapus token milik pengguna. Tanpa body token: hapus semua token pengguna.
     * DELETE /api/v1/auth/fcm-token
     */
    public function hapus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), ['token' => 'nullable|string|max:255']);
        if ($validator->fails()) {
            return $this->jsonError('Validasi data gagal.', 422, $validator->errors());
        }

        $query = UserDeviceToken::where('pengguna_id', $request->user()->id);
        if ($request->filled('token')) {
            $query->where('fcm_token', $request->input('token'));
        }

        return $this->jsonSuccess(['dihapus' => $query->delete()], 'Token perangkat dihapus.');
    }
}
