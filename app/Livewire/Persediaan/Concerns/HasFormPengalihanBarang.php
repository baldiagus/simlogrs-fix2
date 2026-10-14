<?php

namespace App\Livewire\Persediaan\Concerns;

use App\Models\Persediaan\StokBatch;

trait HasFormPengalihanBarang
{
    /**
     * Hook Livewire saat pilihan barang id berubah untuk memuat data terkait.
     */
    public function updatedSelectedBarangId(): void
    {
        $this->selected_stok_batch_id = null;
        $this->quantity = 1;
    }

    /**
     * Hook saat gudang asal pengalihan diubah untuk memuat stok barang terkait.
     */
    public function updatedGudangAsalId(): void
    {
        $this->selected_stok_batch_id = null;
        $this->quantity = 1;
    }

    /**
     * Hook saat batch stok dipilih untuk menetapkan batas kuantitas transfer.
     */
    public function updatedSelectedStokBatchId(): void
    {
        $this->quantity = 1;
    }

    /**
     * Hook Livewire saat properti quantity diperbarui.
     */
    public function updatedQuantity($value): void
    {
        $max = $this->maxQuantity;
        if ((int) $value > $max) {
            $this->quantity = $max;
            $this->addError('quantity', "Jumlah unit melebihi stok batch tersedia (maksimal {$max}).");
        } elseif ((int) $value < 1) {
            $this->quantity = 1;
            $this->resetErrorBag('quantity');
        } else {
            $this->resetErrorBag('quantity');
        }
    }

    /**
     * Computed property Livewire untuk mengakses data max quantity.
     */
    public function getMaxQuantityProperty(): int
    {
        if (! $this->selected_stok_batch_id) {
            return 1;
        }

        $batch = StokBatch::find($this->selected_stok_batch_id);

        return $batch ? max(1, (int) $batch->jumlah) : 1;
    }
}
