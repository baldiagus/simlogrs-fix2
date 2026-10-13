<?php

namespace App\Livewire\Distribusi\Concerns;

use App\Models\Persediaan\StokBatch;

trait HasDraftAmprah
{
    public function addItem(): void
    {
        $this->items[] = ['barang_id' => null, 'jumlah' => 1, 'catatan' => ''];
    }

    public function removeItem(int $index): void
    {
        if (count($this->items) > 1) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
        }
    }

    /**
     * Saat barang dalam daftar item berubah, otomatis arahkan ke gudang yang memiliki stok.
     */
    public function updatedItems(): void
    {
        $firstBarangId = collect($this->items)->pluck('barang_id')->filter()->first();
        if ($firstBarangId) {
            $bestGudang = StokBatch::where('barang_id', $firstBarangId)
                ->where('status', 'AKTIF')
                ->where('jumlah', '>', 0)
                ->groupBy('gudang_id')
                ->selectRaw('gudang_id, sum(jumlah) as total')
                ->orderByDesc('total')
                ->value('gudang_id');
            if ($bestGudang) {
                $this->gudang_id = $bestGudang;
            }
        }
    }

    /**
     * Menghitung saldo stok fisik aktif dan belum kedaluwarsa di gudang terkait.
     */
    public function getStokTersedia(int $barangId, ?int $gudangId = null): int
    {
        $gid = $gudangId ?: (int) $this->gudang_id;
        if (! $gid || ! $barangId) {
            return 0;
        }

        return (int) StokBatch::where('gudang_id', $gid)
            ->where('barang_id', $barangId)
            ->where('status', 'AKTIF')
            ->where('jumlah', '>', 0)
            ->where(function ($q) {
                $q->whereNull('tanggal_kedaluwarsa')
                    ->orWhere('tanggal_kedaluwarsa', '>=', now()->toDateString());
            })
            ->sum('jumlah');
    }
}
