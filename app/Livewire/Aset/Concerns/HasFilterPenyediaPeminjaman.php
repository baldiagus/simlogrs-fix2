<?php

namespace App\Livewire\Aset\Concerns;

trait HasFilterPenyediaPeminjaman
{
    public function updatedFilterKatalogLokasi(): void
    {
        $this->filterKatalogUnitId = null;
        $this->filterKatalogGudangId = null;
        $this->resetPage();
    }

    public function updatedFilterKatalogUnitId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterKatalogGudangId(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedBarangId(): void
    {
        $this->selected_penyedia_key = null;
        $this->selected_aset_id = null;
        $this->unit_penyedia_id = null;
        $this->gudang_penyedia_id = null;

        $providers = $this->getPenyediaAsetList();
        $validProviders = array_values(array_filter($providers, fn ($p) => ! $p['is_own']));
        if (count($validProviders) === 1) {
            $this->selectPenyediaKey($validProviders[0]['key']);
        }
    }

    public function updatedSelectedPenyediaKey($value): void
    {
        $this->selectPenyediaKey($value);
    }

    public function updatedQuantity($value): void
    {
        $max = $this->maxQuantity;
        if ((int) $value > $max) {
            $this->addError('quantity', "Jumlah unit melebihi stok tersedia (maksimal {$max} unit).");
        } else {
            $this->resetErrorBag('quantity');
        }
    }
}
