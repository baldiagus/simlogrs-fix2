<?php

namespace App\Services\Notifikasi;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;

/**
 * Pengirim push lewat Firebase Cloud Messaging (HTTP v1, paket kreait/laravel-firebase).
 * Tanpa berkas Service Account, push dilewati dengan log warning (inbox tetap terisi).
 */
class FcmPengirimPush implements PengirimPush
{
    public function kirim(array $tokens, array $payload, string $collapseKey): array
    {
        if ($tokens === []) {
            return [];
        }

        $kredensial = $this->pathKredensial();
        if ($kredensial === null || ! is_file($kredensial)) {
            Log::warning('FCM dilewati: berkas kredensial Service Account tidak ditemukan.', ['path' => $kredensial]);

            return [];
        }

        $payload['android']['collapse_key'] = $collapseKey;
        $laporan = app(Messaging::class)->sendMulticast(CloudMessage::fromArray($payload), $tokens);

        foreach ($laporan->failures()->getItems() as $item) {
            Log::warning('FCM gagal terkirim ke satu token.', ['galat' => $item->error()?->getMessage()]);
        }

        return array_values(array_unique(array_merge($laporan->unknownTokens(), $laporan->invalidTokens())));
    }

    /**
     * Path absolut berkas kredensial dari config firebase (string path; JSON inline tidak didukung untuk pengecekan).
     */
    private function pathKredensial(): ?string
    {
        $path = config('firebase.projects.app.credentials');
        if (! is_string($path) || $path === '') {
            return null;
        }

        return str_starts_with($path, '/') || str_contains($path, ':\\') ? $path : base_path($path);
    }
}
