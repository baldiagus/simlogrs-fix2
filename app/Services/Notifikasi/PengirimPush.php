<?php

namespace App\Services\Notifikasi;

/**
 * Kontrak pengiriman push ke perangkat (implementasi nyata: FCM; test: Fake).
 */
interface PengirimPush
{
    /**
     * Kirim payload (kontrak bagian 3) ke semua token.
     *
     * @param  list<string>  $tokens
     * @param  array<string, mixed>  $payload  {notification, data, android}
     * @return list<string> token yang ditolak permanen (UNREGISTERED/INVALID_ARGUMENT), wajib dihapus pemanggil
     */
    public function kirim(array $tokens, array $payload, string $collapseKey): array;
}
