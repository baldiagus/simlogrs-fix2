<?php

namespace App\Livewire\Persediaan\Concerns;

use App\Models\StokBatch;

trait HasFormPengalihanBarang
{
    public function updatedSelectedBarangId(): void
    {
        $this->selected_stok_batch_id = null;
        $this->quantity = 1;
    }

    public function updatedGudangAsalId(): void
    {
        $this->selected_stok_batch_id = null;
        $this->quantity = 1;
    }

    public function updatedSelectedStokBatchId(): void
    {
        $this->quantity = 1;
    }

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

    public function getMaxQuantityProperty(): int
    {
        if (! $this->selected_stok_batch_id) {
            return 1;
        }

        $batch = StokBatch::find($this->selected_stok_batch_id);

        return $batch ? max(1, (int) $batch->jumlah) : 1;
    }
}
