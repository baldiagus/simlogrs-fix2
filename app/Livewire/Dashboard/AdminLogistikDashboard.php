<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;

class AdminLogistikDashboard extends Component
{
    public function render()
    {
        abort_unless(auth()->user()?->can('page_AdminLogistikDashboard'), 403);

        return view('livewire.dashboard.portal-dashboard', [
            'portalTitle' => 'Dashboard Admin Logistik Sentral',
            'portalSubtitle' => 'Pengawasan distribusi stok sentral, kuota barang, dan sirkulasi gudang.',
        ])->layout('layouts.portal', ['portal' => 'admin-logistik', 'title' => 'Dashboard Admin Logistik']);
    }
}
