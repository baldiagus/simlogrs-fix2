<?php

namespace App\Livewire\Notifikasi;

use App\Livewire\Concerns\HasPortalLayout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Halaman daftar penuh notifikasi pengguna login (paginasi 20, filter Semua/Belum dibaca).
 */
class DaftarNotifikasi extends Component
{
    use HasPortalLayout, MengelolaNotifikasi, WithPagination;

    public const PER_HALAMAN = 20;

    #[Url(history: true)]
    public string $filter = 'semua';

    /**
     * Hook Livewire saat filter  diubah untuk memperbarui data dan mereset paginasi.
     */
    public function updatedFilter(): void
    {
        if (! in_array($this->filter, ['semua', 'belum'], true)) {
            $this->filter = 'semua';
        }
        $this->resetPage();
    }

    /**
     * Render tampilan blade komponen Livewire.
     */
    public function render()
    {
        $query = $this->milikSaya()->orderByDesc('dibuat_pada')->orderByDesc('id');
        if ($this->filter === 'belum') {
            $query->belumDibaca();
        }

        return view('livewire.notifikasi.daftar-notifikasi', [
            'items' => $query->paginate(self::PER_HALAMAN),
            'belumDibaca' => $this->milikSaya()->belumDibaca()->count(),
        ])->layout('layouts.portal', [
            'title' => 'Notifikasi',
            'portal' => $this->getPortalName(),
        ]);
    }
}
