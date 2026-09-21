<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

abstract class ApiController extends Controller
{
    /**
     * Respon sukses terstandarisasi.
     */
    protected function jsonSuccess(
        mixed $data = null,
        string $pesan = 'Operasi berhasil dilakukan.',
        int $code = 200,
        array $meta = []
    ): JsonResponse {
        return response()->json([
            'sukses' => true,
            'pesan'  => $pesan,
            'data'   => $data,
            'meta'   => array_merge([
                'versi_api' => 'v1.0',
            ], $meta),
        ], $code);
    }

    /**
     * Respon galat terstandarisasi.
     */
    protected function jsonError(
        string $pesan = 'Terjadi kesalahan.',
        int $code = 400,
        mixed $errors = null
    ): JsonResponse {
        $payload = [
            'sukses' => false,
            'pesan'  => $pesan,
            'meta'   => [
                'versi_api' => 'v1.0',
            ],
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $code);
    }
}
