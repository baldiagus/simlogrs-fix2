<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;

class PengadaanDashboard extends Component
{
    public function render()
    {
        abort_unless(auth()->user()?->can('page_PengadaanDashboard'), 403);

        return view('livewire.dashboard.portal-dashboard', [
            'portalTitle' => 'Dashboard Bagian Pengadaan',
            'portalSubtitle' => 'Verifikasi permohonan pengadaan (PR), penerbitan PO vendor, dan kontrol pagu anggaran.',
        ])->layout('layouts.portal', ['portal' => 'pengadaan', 'title' => 'Dashboard Pengadaan']);
    }
}
