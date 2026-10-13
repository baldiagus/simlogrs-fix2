<?php

namespace App\Services\Notifikasi;

use App\Jobs\KirimPushFcm;
use App\Models\Master\Notifikasi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Satu-satunya pintu notifikasi: tulis ke inbox, lalu push FCM setelah transaksi commit.
 */
class NotifikasiService
{
    /**
     * Tulis satu notifikasi per penerima (tanpa duplikat, tanpa pelaku) dan jadwalkan push.
     * Kegagalan push tidak pernah dilempar ke pemanggil.
     *
     * @param  array<int>|Collection<int, int>  $penggunaIds
     * @return Collection<int, Notifikasi>
     */
    public function kirim(
        array|Collection $penggunaIds,
        string $judul,
        string $pesan,
        string $tipe,
        ?string $referensiId = null,
        ?string $tautan = null,
        ?int $kecualiPenggunaId = null,
    ): Collection {
        $penerima = collect($penggunaIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn (int $id) => $id === $kecualiPenggunaId)
            ->values();

        if ($penerima->isEmpty()) {
            return collect();
        }

        $baris = DB::transaction(fn () => $penerima->map(fn (int $id) => Notifikasi::create([
            'pengguna_id'  => $id,
            'judul'        => $judul,
            'pesan'        => $pesan,
            'tipe'         => $tipe,
            'referensi_id' => $referensiId,
            'tautan'       => $tautan,
            'dibaca'       => false,
            'dibuat_pada'  => now(),
        ])));

        DB::afterCommit(function () use ($baris) {
            foreach ($baris as $notifikasi) {
                try {
                    KirimPushFcm::dispatch($notifikasi->id);
                } catch (Throwable $e) {
                    Log::warning('Push FCM gagal dijadwalkan; notifikasi tetap di inbox.', [
                        'notifikasi_id' => $notifikasi->id,
                        'galat'         => $e->getMessage(),
                    ]);
                }
            }
        });

        return $baris;
    }
}
