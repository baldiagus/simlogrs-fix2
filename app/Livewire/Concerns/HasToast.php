<?php

namespace App\Livewire\Concerns;

trait HasToast
{
    /**
     * Dispatch notification event to Flux toast.
     */
    public function toast(string $message, string $variant = 'success', string $heading = ''): void
    {
        $this->dispatch('toast', text: $message, variant: $variant, heading: $heading);
    }
}
