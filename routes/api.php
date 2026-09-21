<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'sukses' => true,
            'pesan' => 'SIMLOG RS API v1 Online',
            'meta' => [
                'versi_api' => 'v1.0',
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    });
});
