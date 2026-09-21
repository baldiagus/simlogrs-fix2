<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // 1. Health Check
    Route::get('/health', function () {
        return response()->json([
            'sukses' => true,
            'pesan'  => 'SIMLOG RS API v1 Online',
            'meta'   => [
                'versi_api' => 'v1.0',
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    });

    // 2. Autentikasi Publik Mobile
    Route::post('/auth/login', [AuthController::class, 'login']);

    // 3. Endpoint Terproteksi Sanctum
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
    });
});
