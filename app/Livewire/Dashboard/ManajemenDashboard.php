<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;

class ManajemenDashboard extends Component
{
    public function render()
    {
        abort_unless(auth()->user()?->can('page_ManajemenDashboard'), 403);

        return view('livewire.dashboard.portal-dashboard', [
            'portalTitle' => 'Dashboard Manajemen & Direksi Eksekutif',
            'portalSubtitle' => 'Monitoring serapan anggaran pengadaan, valuasi persediaan, dan ringkasan risiko audit.',
        ])->layout('layouts.portal', ['portal' => 'manajemen', 'title' => 'Dashboard Manajemen Direksi']);
    }
}
