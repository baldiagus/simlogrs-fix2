<?php

namespace App\Observers;

use App\Services\Notifikasi\NotifikasiService;
use App\Services\Notifikasi\PenerimaResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Dasar observer pemicu notifikasi: memanggil tangani() saat model dibuat atau status berubah.
 * Kegagalan notifikasi tidak pernah menggagalkan transaksi bisnis.
 */
abstract class ObserverNotifikasi
{
    /**
     * Kirim notifikasi sesuai status model.
     *
     * @param  bool  $baru  true bila model baru dibuat; false bila status berubah
     */
    abstract protected function tangani(Model $model, bool $baru): void;

    /** Model baru dibuat. */
    public function created(Model $model): void
    {
        $this->aman($model, true);
    }

    /** Model diubah; hanya diproses bila kolom status berubah. */
    public function updated(Model $model): void
    {
        if ($model->wasChanged('status')) {
            $this->aman($model, false);
        }
    }

    protected function aman(Model $model, bool $baru): void
    {
        try {
            $this->tangani($model, $baru);
        } catch (Throwable $e) {
            Log::warning('Pemicu notifikasi gagal; transaksi bisnis tetap berjalan.', [
                'model' => $model::class, 'id' => $model->getKey(), 'galat' => $e->getMessage(),
            ]);
        }
    }

    protected function penerima(): PenerimaResolver
    {
        return app(PenerimaResolver::class);
    }

    /** Status sebelum perubahan (null untuk model baru). */
    protected function statusLama(Model $model, bool $baru): ?string
    {
        return $baru ? null : $model->getOriginal('status');
    }

    /** Nama dari relasi tunggal (dimuat bila belum); '-' bila kosong. */
    protected function nama(Model $model, string $relasi): string
    {
        return $model->loadMissing($relasi)->getRelation($relasi)?->nama ?? '-';
    }

    /** Kirim ke penerima; pelaku (Auth::id()) tidak ikut menerima. */
    protected function kirim(array $penerima, string $judul, string $pesan, string $tipe, Model $model, string $tautan): void
    {
        app(NotifikasiService::class)->kirim($penerima, $judul, $pesan, $tipe, (string) $model->getKey(), $tautan, Auth::id());
    }
}
