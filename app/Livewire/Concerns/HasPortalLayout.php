<?php

namespace App\Livewire\Concerns;

trait HasPortalLayout
{
    /**
     * Mengambil nama portal dari segmen pertama URL request.
     */
    public function getPortalName(): string
    {
        return request()->segment(1) ?? 'admin';
    }
}
