<?php

namespace App\Jobs;

use App\Models\Auth\UserDeviceToken;
use App\Models\Master\Notifikasi;
use App\Services\Notifikasi\PengirimPush;
use App\Services\Notifikasi\StatusAntrean;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Kirim push FCM untuk satu baris notifikasi ke semua perangkat penerimanya (payload: kontrak bagian 3).
 */
class KirimPushFcm implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $notifikasiId)
    {
    }

    /**
     * Jeda antar percobaan ulang (detik).
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(PengirimPush $pengirim): void
    {
        Cache::put(StatusAntrean::KUNCI_DETAK, now()->toIso8601String(), now()->addDay());

        $notifikasi = Notifikasi::find($this->notifikasiId);
        if (! $notifikasi) {
            return;
        }

        $tokens = UserDeviceToken::where('pengguna_id', $notifikasi->pengguna_id)->pluck('fcm_token')->all();
        if ($tokens === []) {
            return;
        }

        $tipe = $notifikasi->tipe ?? 'UMUM';
        $referensi = (string) $notifikasi->referensi_id;
        $payload = [
            'notification' => ['title' => $notifikasi->judul, 'body' => $notifikasi->pesan],
            'data'         => [
                'tipe'          => $tipe,
                'referensi_id'  => $referensi,
                'notifikasi_id' => (string) $notifikasi->id,
                'judul'         => $notifikasi->judul,
                'pesan'         => $notifikasi->pesan,
            ],
            'android'      => ['priority' => 'high', 'notification' => ['channel_id' => 'simlog_approval_channel']],
        ];

        $ditolak = $pengirim->kirim($tokens, $payload, "{$tipe}:{$referensi}");

        if ($ditolak !== []) {
            UserDeviceToken::whereIn('fcm_token', $ditolak)->delete();
        }
        UserDeviceToken::whereIn('fcm_token', array_diff($tokens, $ditolak))->update(['last_used_at' => now()]);
    }

    public function failed(Throwable $e): void
    {
        Log::warning('Push FCM gagal setelah percobaan maksimal.', [
            'notifikasi_id' => $this->notifikasiId,
            'galat'         => $e->getMessage(),
        ]);
    }
}
