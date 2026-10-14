<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;

class AuditorDashboard extends Component
{
    /**
     * Render tampilan blade komponen Livewire.
     */
    public function render()
    {
        abort_unless(auth()->user()?->can('page_AuditorDashboard'), 403);

        return view('livewire.dashboard.portal-dashboard', [
            'portalTitle' => 'Dashboard Auditor Internal (SPI)',
            'portalSubtitle' => 'Forensik digital audit trail, uji kepatuhan silang transaksi, dan register temuan CAPA.',
        ])->layout('layouts.portal', ['portal' => 'auditor', 'title' => 'Dashboard Auditor SPI']);
    }
}
