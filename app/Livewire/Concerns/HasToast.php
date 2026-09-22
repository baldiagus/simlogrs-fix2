<?php

namespace App\Livewire\Concerns;

use Flux;

trait HasToast
{
    public function toast(string $message, string $variant = 'success', string $heading = ''): void
    {
        if (app()->bound('livewire') && app('livewire')->current()) {
            Flux::toast(text: $message, heading: $heading ?: null, variant: $variant);
        }
    }
}
