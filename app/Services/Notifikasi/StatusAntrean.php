<?php

namespace App\Services\Notifikasi;

use App\Models\Auth\UserDeviceToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

/**
 * Ringkasan kesehatan pengiriman push: panjang antrean, detak worker, dan token terdaftar.
 */
class StatusAntrean
{
    public const KUNCI_DETAK = 'simlog:queue:last_heartbeat';

    /** Batas usia detak (menit) agar worker dianggap hidup. */
    public const BATAS_MENIT = 5;

    /**
     * Jumlah job yang menunggu di antrean 'default'.
     */
    public function jumlahTertunda(): int
    {
        return (int) Queue::size('default');
    }

    /**
     * Waktu detak worker terakhir (ditulis KirimPushFcm), null bila belum pernah.
     */
    public function detakTerakhir(): ?Carbon
    {
        $nilai = Cache::get(self::KUNCI_DETAK);

        return $nilai ? Carbon::parse($nilai) : null;
    }

    /**
     * Sehat bila antrean kosong atau worker berdetak dalam 5 menit terakhir.
     */
    public function sehat(): bool
    {
        if ($this->jumlahTertunda() === 0) {
            return true;
        }

        $detak = $this->detakTerakhir();

        return $detak !== null && $detak->gte(now()->subMinutes(self::BATAS_MENIT));
    }

    /**
     * Jumlah token perangkat FCM terdaftar.
     */
    public function jumlahTokenTerdaftar(): int
    {
        return UserDeviceToken::count();
    }

    /**
     * Jumlah pengguna unik yang memiliki minimal satu token.
     */
    public function jumlahPenggunaBerToken(): int
    {
        return UserDeviceToken::distinct()->count('pengguna_id');
    }
}
