<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;

class UnitDashboard extends Component
{
    /**
     * Render tampilan blade komponen Livewire.
     */
    public function render()
    {
        abort_unless(auth()->user()?->can('page_UnitDashboard'), 403);

        return view('livewire.dashboard.portal-dashboard', [
            'portalTitle' => 'Dashboard Petugas / Staf Ruangan',
            'portalSubtitle' => 'Pengajuan amprah komoditas, peminjaman aset, lapor servis kerusakan, dan stok ruangan.',
        ])->layout('layouts.portal', ['portal' => 'petugas-unit', 'title' => 'Dashboard Petugas Ruangan']);
    }
}
