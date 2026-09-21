<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;

class KepalaUnitDashboard extends Component
{
    public function render()
    {
        abort_unless(auth()->user()?->can('page_KepalaUnitDashboard'), 403);

        return view('livewire.dashboard.portal-dashboard', [
            'portalTitle' => 'Dashboard Kepala Unit / Karu',
            'portalSubtitle' => 'Otorisasi amprah ruangan, persetujuan pinjam aset, dan evaluasi pemakaian fasilitas.',
        ])->layout('layouts.portal', ['portal' => 'kepala-unit', 'title' => 'Dashboard Kepala Unit']);
    }
}
