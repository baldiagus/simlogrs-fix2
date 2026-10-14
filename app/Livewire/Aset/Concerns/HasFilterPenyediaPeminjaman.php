<?php

namespace App\Livewire\Aset\Concerns;

trait HasFilterPenyediaPeminjaman
{
    /**
     * Hook Livewire saat filter katalog lokasi diubah untuk memperbarui data dan mereset paginasi.
     */
    public function updatedFilterKatalogLokasi(): void
    {
        $this->filterKatalogUnitId = null;
        $this->filterKatalogGudangId = null;
        $this->resetPage();
    }

    /**
     * Hook Livewire saat filter katalog unit id diubah untuk memperbarui data dan mereset paginasi.
     */
    public function updatedFilterKatalogUnitId(): void
    {
        $this->resetPage();
    }

    /**
     * Hook Livewire saat filter katalog gudang id diubah untuk memperbarui data dan mereset paginasi.
     */
    public function updatedFilterKatalogGudangId(): void
    {
        $this->resetPage();
    }

    /**
     * Hook Livewire saat pilihan barang id berubah untuk memuat data terkait.
     */
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

    /**
     * Hook Livewire saat pilihan penyedia key berubah untuk memuat data terkait.
     */
    public function updatedSelectedPenyediaKey($value): void
    {
        $this->selectPenyediaKey($value);
    }

    /**
     * Hook Livewire saat properti quantity diperbarui.
     */
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
