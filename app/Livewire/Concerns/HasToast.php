<?php

namespace App\Livewire\Concerns;

use Flux;

trait HasToast
{
    public function toast(string $message, string $variant = 'success', string $heading = ''): void
    {
        Flux::toast(text: $message, heading: $heading ?: null, variant: $variant);
    }
}
