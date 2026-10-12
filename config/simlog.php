<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi Operasional SIMLOG
|--------------------------------------------------------------------------
|
| Nilai operasional yang dapat disesuaikan per lingkungan melalui variabel
| SIMLOG_*. Default di sini identik dengan nilai hardcoded sebelumnya.
|
| Catatan config:cache: jangan memanggil env() di luar folder config/.
| Setelah `php artisan config:cache`, env() mengembalikan null di luar
| berkas konfigurasi; baca nilai ini melalui config('simlog.*').
|
*/

return [

    'api' => [
        'throttle' => [
            'max_attempts'  => (int) env('SIMLOG_API_THROTTLE_MAX_ATTEMPTS', 60),
            'decay_minutes' => (int) env('SIMLOG_API_THROTTLE_DECAY_MINUTES', 1),
        ],
    ],

    'auth' => [
        'max_login_attempts' => (int) env('SIMLOG_AUTH_MAX_LOGIN_ATTEMPTS', 5),
    ],

    'cache' => [
        // TTL (detik) untuk cache data dropdown master di komponen Livewire.
        'dropdown_ttl' => (int) env('SIMLOG_CACHE_DROPDOWN_TTL', 60),
    ],

    'upload' => [
        // Ukuran maksimum berkas unggahan dalam kilobyte (aturan validasi max:).
        'max_kb'              => (int) env('SIMLOG_UPLOAD_MAX_KB', 10240),
        'image_max_dimension' => (int) env('SIMLOG_UPLOAD_IMAGE_MAX_DIMENSION', 1600),
        'image_quality'       => (int) env('SIMLOG_UPLOAD_IMAGE_QUALITY', 80),
    ],

];
