<?php

namespace App\Services\Notifikasi;

use RuntimeException;

/**
 * Pengirim palsu untuk test: merekam kiriman, bisa dipaksa gagal atau menolak token tertentu.
 */
class FakePengirimPush implements PengirimPush
{
    /** @var list<array{tokens: list<string>, payload: array<string, mixed>, collapse_key: string}> */
    public array $terkirim = [];

    /** @var list<string> */
    public array $tokenDitolak = [];

    public bool $gagal = false;

    public function kirim(array $tokens, array $payload, string $collapseKey): array
    {
        if ($this->gagal) {
            throw new RuntimeException('FCM tidak terjangkau (fake).');
        }

        $this->terkirim[] = ['tokens' => $tokens, 'payload' => $payload, 'collapse_key' => $collapseKey];

        return array_values(array_intersect($tokens, $this->tokenDitolak));
    }
}
