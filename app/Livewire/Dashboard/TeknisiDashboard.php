<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;

class TeknisiDashboard extends Component
{
    public function render()
    {
        abort_unless(auth()->user()?->can('page_TeknisiDashboard'), 403);

        return view('livewire.dashboard.portal-dashboard', [
            'portalTitle' => 'Dashboard Teknisi Sarana & IPSRS',
            'portalSubtitle' => 'Disposisi SPK perbaikan, lembar kerja pengerjaan, pemakaian suku cadang, dan kalender servis.',
        ])->layout('layouts.portal', ['portal' => 'teknisi', 'title' => 'Dashboard Teknisi IPSRS']);
    }
}
