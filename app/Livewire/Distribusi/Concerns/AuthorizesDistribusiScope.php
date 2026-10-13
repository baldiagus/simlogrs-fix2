<?php

namespace App\Livewire\Distribusi\Concerns;

use App\Models\Master\Gudang;
use App\Models\Master\Unit;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aturan scope modul distribusi sesuai ADR 0001:
 * - Hanya role super_admin / admin_logistik yang melewati scope.
 * - Pengguna ber-unit/ber-gudang dibatasi ke unit/gudang miliknya (termasuk yang dikepalai).
 * - Pengguna tanpa penempatan (auditor/manajemen) hanya membaca lewat permission view*; aksi selalu wajib scope.
 */
trait AuthorizesDistribusiScope
{
    protected function isGlobalOperator(): bool
    {
        return (bool) auth()->user()?->hasRole(['super_admin', 'admin_logistik']);
    }

    /** Pembaca global read-only (ADR 0001): auditor/manajemen via permission view*; bukan operator. */
    protected function isGlobalReader(): bool
    {
        return (bool) auth()->user()?->hasRole(['auditor', 'manajemen']);
    }

    protected function ownUnitIds(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        return Unit::where('kepala_unit_id', $user->id)->pluck('id')
            ->push($user->unit_id)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function ownGudangIds(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        return Gudang::where('kepala_gudang_id', $user->id)->pluck('id')
            ->push($user->gudang_id)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function inUnitScope(?int $unitId): bool
    {
        return $this->isGlobalOperator() || ($unitId && in_array((int) $unitId, $this->ownUnitIds(), true));
    }

    protected function inGudangScope(?int $gudangId): bool
    {
        return $this->isGlobalOperator() || ($gudangId && in_array((int) $gudangId, $this->ownGudangIds(), true));
    }

    /**
     * Scope baca: unit milik pengguna, lalu gudang miliknya; tanpa penempatan = pembaca global via permission view*.
     */
    protected function applyReadScope(Builder $query, ?string $unitColumn = 'unit_id', ?string $gudangColumn = 'gudang_id'): Builder
    {
        if ($this->isGlobalOperator()) {
            return $query;
        }

        $unitIds = $this->ownUnitIds();
        $gudangIds = $this->ownGudangIds();

        if ($unitColumn && $unitIds) {
            return $query->whereIn($unitColumn, $unitIds);
        }

        if ($gudangColumn && $gudangIds) {
            return $query->whereIn($gudangColumn, $gudangIds);
        }

        if ($unitIds || $gudangIds || ! $this->isGlobalReader()) {
            return $query->whereRaw('1 = 0');
        }

        return $query;
    }

    protected function canReadScoped(?int $unitId, ?int $gudangId, bool $checkUnit = true, bool $checkGudang = true): bool
    {
        if ($this->isGlobalOperator()) {
            return true;
        }

        $unitIds = $this->ownUnitIds();
        $gudangIds = $this->ownGudangIds();

        if ($checkUnit && $unitIds) {
            return $unitId && in_array((int) $unitId, $unitIds, true);
        }

        if ($checkGudang && $gudangIds) {
            return $gudangId && in_array((int) $gudangId, $gudangIds, true);
        }

        return ! $unitIds && ! $gudangIds && $this->isGlobalReader();
    }

    protected function authorizeReadScoped(?int $unitId, ?int $gudangId, bool $checkUnit = true, bool $checkGudang = true): void
    {
        abort_unless(
            $this->canReadScoped($unitId, $gudangId, $checkUnit, $checkGudang),
            403,
            'Anda tidak memiliki wewenang untuk melihat data di luar unit/gudang Anda.'
        );
    }
}
