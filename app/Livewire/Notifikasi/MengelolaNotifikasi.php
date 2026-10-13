<?php

namespace App\Livewire\Notifikasi;

use App\Models\Master\Notifikasi;
use App\Services\Notifikasi\TautanWeb;
use Illuminate\Database\Eloquent\Builder;

/**
 * Akses notifikasi yang SELALU dibatasi ke pengguna login; ID dari klien tidak pernah dipercaya.
 */
trait MengelolaNotifikasi
{
    /**
     * Query dasar: hanya baris milik pengguna login.
     */
    protected function milikSaya(): Builder
    {
        return Notifikasi::query()->where('pengguna_id', auth()->id());
    }

    /**
     * Ambil satu notifikasi milik sendiri; 404 bila tidak ada atau milik orang lain.
     */
    protected function temukanMilikSaya(int $id): Notifikasi
    {
        $notifikasi = $this->milikSaya()->whereKey($id)->first();
        abort_if($notifikasi === null, 404);

        return $notifikasi;
    }

    /**
     * Tandai satu notifikasi milik sendiri sebagai dibaca.
     */
    public function tandaiDibaca(int $id): void
    {
        $this->temukanMilikSaya($id)->update(['dibaca' => true]);
    }

    /**
     * Tandai semua notifikasi milik sendiri sebagai dibaca.
     */
    public function tandaiSemuaDibaca(): void
    {
        $this->milikSaya()->belumDibaca()->update(['dibaca' => true]);
    }

    /**
     * Tandai dibaca lalu arahkan ke tautan notifikasi (sudah disesuaikan portal pengguna).
     */
    public function buka(int $id)
    {
        $notifikasi = $this->temukanMilikSaya($id);
        $notifikasi->update(['dibaca' => true]);

        return $this->redirect(app(TautanWeb::class)->resolve($notifikasi->tautan, auth()->user()), navigate: true);
    }
}
