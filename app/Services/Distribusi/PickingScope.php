<?php

namespace App\Services\Distribusi;

use App\Models\Distribusi\PermintaanDistribusi;
use App\Models\Master\Gudang;
use App\Models\User;

class PickingScope
{
    /**
     * Operator global (super_admin / admin_logistik) melewati pembatasan gudang.
     */
    public static function isGlobalOperator(?User $user): bool
    {
        return (bool) $user?->hasRole(['super_admin', 'admin_logistik']);
    }

    /**
     * Ambil daftar ID gudang yang berada dalam wewenang pengguna (kepala gudang dan/atau penempatan user->gudang_id).
     *
     * @return int[]
     */
    public static function ownGudangIds(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return Gudang::where('kepala_gudang_id', $user->id)
            ->pluck('id')
            ->push($user->gudang_id)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Otorisasi hak akses picking untuk dokumen permintaan:
     * 1. Operator global selalu diizinkan.
     * 2. Pengguna non-global wajib memiliki minimal satu fasilitas gudang terkait.
     * 3. Jika permintaan sudah terikat ke gudang tertentu, harus cocok dengan gudang pengguna.
     */
    public static function authorize(?User $user, PermintaanDistribusi $permintaan): void
    {
        if (static::isGlobalOperator($user)) {
            return;
        }

        $gudangIds = static::ownGudangIds($user);
        abort_unless(! empty($gudangIds), 403, 'Akun Anda tidak terhubung dengan fasilitas gudang.');
        abort_unless(
            ! $permintaan->gudang_id || in_array((int) $permintaan->gudang_id, $gudangIds, true),
            403,
            'Anda hanya berhak memproses picking untuk gudang Anda sendiri.'
        );
    }
}
