<?php

// Konfigurasi minimal kreait/laravel-firebase (hanya FCM). Lihat docs/NOTIFICATION_CONTRACT.md.
return [
    'default' => 'app',

    'projects' => [
        'app' => [
            // Path berkas Service Account (relatif terhadap base_path). Tidak ada berkas = push dilewati.
            'credentials' => env('FIREBASE_CREDENTIALS', 'storage/app/firebase/firebase_credentials.json'),
            'project_id'  => env('FIREBASE_PROJECT_ID', 'simlog-rs'),
        ],
    ],
];
