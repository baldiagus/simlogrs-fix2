<?php

namespace App\Livewire\Master\Concerns;

use App\Models\Aset\Aset;

trait HasScopeAset
{
    /**
     * Periksa apakah pengguna memiliki wewenang untuk menyunting data aset.
     */
    public function canUserEditAset($user, Aset $aset): bool
    {
        return $user && $user->can('update_master::aset') && $this->isInUserScope($user, $aset->unit_id, $aset->gudang_id);
    }

    /**
     * Periksa apakah pengguna memiliki wewenang untuk menghapus data aset.
     */
    public function canUserDeleteAset($user, Aset $aset): bool
    {
        return $user && $user->can('delete_master::aset') && $this->isInUserScope($user, $aset->unit_id, $aset->gudang_id);
    }

    /**
     * Hanya super_admin/admin_logistik lintas scope; pengguna lain dibatasi ke unit/gudang miliknya.
     */
    private function isInUserScope($user, ?int $unitId, ?int $gudangId): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'admin_logistik'])) {
            return true;
        }

        return ($unitId !== null && $user->unit_id && (int) $user->unit_id === (int) $unitId)
            || ($gudangId !== null && $user->gudang_id && (int) $user->gudang_id === (int) $gudangId);
    }

    /**
     * Baca register lintas unit: role global, auditor/manajemen (ADR), dan IPSRS lewat permission
     * pemeliharaan; selain itu hanya aset di unit/gudang pengguna.
     */
    private function hasGlobalRead(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->hasRole(['super_admin', 'admin_logistik', 'auditor', 'manajemen'])
            || $user?->can('view_any_pemeliharaan::spk::perbaikan'));
    }

    /**
     * Verifikasi wewenang pengguna untuk melihat data rincian aset terpilih.
     */
    private function authorizeViewAset(Aset $aset): void
    {
        abort_unless(auth()->user()?->can('view_any_master::aset') || auth()->user()?->can('view_master::aset'), 403);
        abort_unless($this->hasGlobalRead() || $this->isInUserScope(auth()->user(), $aset->unit_id, $aset->gudang_id), 403);
    }
}
