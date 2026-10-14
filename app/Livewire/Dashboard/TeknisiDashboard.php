<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;

class TeknisiDashboard extends Component
{
    /**
     * Render tampilan blade komponen Livewire.
     */
    public function render()
    {
        $user = auth()->user();
        abort_unless($user?->can('page_TeknisiDashboard'), 403);

        $isKepalaTeknisi = $user->hasRole('kepala_teknisi');

        $portalTitle = $isKepalaTeknisi
            ? 'Pusat Komando & Dashboard Kepala Teknisi IPSRS'
            : 'Dashboard Teknisi Sarana & IPSRS';

        $portalSubtitle = $isKepalaTeknisi
            ? 'Pengawasan tiket perbaikan ruangan, disposisi SPK teknisi, pemeliharaan preventif berkala, dan inventaris sarana RS.'
            : 'Disposisi SPK perbaikan, lembar kerja pengerjaan, pemakaian suku cadang, dan kalender servis.';

        $title = $isKepalaTeknisi
            ? 'Pusat Komando Kepala Teknisi IPSRS'
            : 'Dashboard Teknisi IPSRS';

        return view('livewire.dashboard.portal-dashboard', [
            'portalTitle' => $portalTitle,
            'portalSubtitle' => $portalSubtitle,
        ])->layout('layouts.portal', ['portal' => 'teknisi', 'title' => $title]);
    }
}
