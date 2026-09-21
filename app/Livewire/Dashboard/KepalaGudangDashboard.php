<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;

class KepalaGudangDashboard extends Component
{
    public function render()
    {
        abort_unless(auth()->user()?->can('page_KepalaGudangDashboard'), 403);

        return view('livewire.dashboard.portal-dashboard', [
            'portalTitle' => 'Dashboard Kepala Gudang',
            'portalSubtitle' => 'Otorisasi opname, monitoring kedaluwarsa FEFO, dan jadwal siklus barang.',
        ])->layout('layouts.portal', ['portal' => 'kepala-gudang', 'title' => 'Dashboard Kepala Gudang']);
    }
}
