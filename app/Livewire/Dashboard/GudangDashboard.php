<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;

class GudangDashboard extends Component
{
    public function render()
    {
        abort_unless(auth()->user()?->can('page_GudangDashboard'), 403);

        return view('livewire.dashboard.portal-dashboard', [
            'portalTitle' => 'Dashboard Petugas Gudang',
            'portalSubtitle' => 'Inbound barang vendor, picking distribusi FEFO, dan perhitungan opname fisik.',
        ])->layout('layouts.portal', ['portal' => 'petugas-gudang', 'title' => 'Dashboard Petugas Gudang']);
    }
}
