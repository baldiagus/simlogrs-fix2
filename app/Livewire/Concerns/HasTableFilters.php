<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Url;
use Livewire\WithPagination;

trait HasTableFilters
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    public int $perPage = 15;

    #[Url(history: true)]
    public string $sortBy = 'id';

    #[Url(history: true)]
    public string $sortDirection = 'desc';
    public function mountHasTableFilters(): void
    {
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $this->sortBy)) {
            $this->sortBy = 'id';
        }
        $this->sortDirection = strtolower($this->sortDirection) === 'asc' ? 'asc' : 'desc';
    }

    public function sort(string $column): void
    {
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            $column = 'id';
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $this->sortBy)) {
            $this->sortBy = 'id';
        }
    }

    public function updatedSortDirection(): void
    {
        $this->sortDirection = strtolower($this->sortDirection) === 'asc' ? 'asc' : 'desc';
    }

    /**
     * Mengembalikan nama kolom pengurutan yang tervalidasi terhadap whitelist kolom aman.
     */
    public function getValidatedSortColumn(array $allowedColumns = []): string
    {
        $defaultAllowed = [
            'id', 'kode', 'nama', 'status', 'dibuat_pada', 'created_at', 'updated_at',
            'tanggal', 'tanggal_po', 'tanggal_kirim', 'tanggal_permintaan', 'tanggal_opname',
            'harga_beli', 'harga_estimasi', 'pagu', 'realisasi', 'serapan', 'total_biaya'
        ];
        $whitelist = array_merge($defaultAllowed, $allowedColumns);

        return in_array($this->sortBy, $whitelist, true) ? $this->sortBy : 'id';
    }

    /**
     * Memvalidasi arah pengurutan (hanya 'asc' atau 'desc').
     */
    public function getValidatedSortDirection(): string
    {
        return strtolower($this->sortDirection) === 'asc' ? 'asc' : 'desc';
    }
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }
}
