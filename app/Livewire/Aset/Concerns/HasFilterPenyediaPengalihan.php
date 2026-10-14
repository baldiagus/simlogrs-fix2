<?php

namespace App\Livewire\Aset\Concerns;

trait HasFilterPenyediaPengalihan
{
    /**
     * Hook Livewire saat pilihan barang id berubah untuk memuat data terkait.
     */
    public function updatedSelectedBarangId(): void
    {
        $this->selected_penyedia_key = null;
        $this->selected_aset_id = null;
        $this->quantity = 1;
        $this->searchPenyedia = '';
        $this->selected_aset_ids = [];
    }

    /**
     * Hook Livewire saat pilihan penyedia key berubah untuk memuat data terkait.
     */
    public function updatedSelectedPenyediaKey(): void
    {
        if (! $this->selected_penyedia_key) {
            $this->selected_aset_ids = [];
            $this->selected_aset_id = null;
            return;
        }

        $providers = $this->getPenyediaAsetList();
        $provider = collect($providers)->firstWhere('key', $this->selected_penyedia_key);
        if ($provider) {
            $maxAvailable = $provider['jumlah'];
            if ($this->quantity > $maxAvailable) {
                $this->quantity = max(1, $maxAvailable);
            }
            $this->tipe_asal = $provider['tipe'];
            if ($provider['tipe'] === 'RUANGAN') {
                $this->unit_asal_id = $provider['id'];
                $this->gudang_asal_id = null;
            } else {
                $this->gudang_asal_id = $provider['id'];
                $this->unit_asal_id = null;
            }
            $this->selected_aset_ids = array_slice($provider['aset_ids'], 0, max(1, (int) $this->quantity));
            $this->selected_aset_id = $provider['aset_ids'][0] ?? null;
        } else {
            $this->selected_aset_ids = [];
            $this->selected_aset_id = null;
        }
    }

    /**
     * Hook Livewire saat properti quantity diperbarui.
     */
    public function updatedQuantity($value): void
    {
        $max = $this->maxQuantity;
        if ((int) $value > $max) {
            $this->quantity = $max;
            $this->addError('quantity', "Jumlah unit melebihi stok tersedia (maksimal {$max} unit).");
        } elseif ((int) $value < 1) {
            $this->quantity = 1;
            $this->resetErrorBag('quantity');
        } else {
            $this->resetErrorBag('quantity');
        }

        if ($this->selected_penyedia_key) {
            $providers = $this->getPenyediaAsetList();
            $provider = collect($providers)->firstWhere('key', $this->selected_penyedia_key);
            if ($provider) {
                $qty = max(1, min((int) $this->quantity, count($provider['aset_ids'])));
                $this->selected_aset_ids = array_slice($provider['aset_ids'], 0, $qty);
            }
        }
    }
}
