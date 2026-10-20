<?php

namespace App\Livewire\Dashboard;

use App\Enums\Audit\StatusCapa;
use App\Models\Audit\LogAudit;
use App\Models\Audit\TemuanAudit;
use Livewire\Component;

class AuditorDashboard extends Component
{
    /**
     * Render tampilan blade komponen Livewire.
     */
    public function render()
    {
        abort_unless(auth()->user()?->can('page_AuditorDashboard'), 403);

        // 1. Temuan Audit CAPA Aktif (In Progress / In Review)
        $temuanAktif = TemuanAudit::whereIn('status_capa', StatusCapa::aktif())
            ->with(['unit', 'auditor'])
            ->latest('id')
            ->take(5)
            ->get();
        $totalTemuanAktif = TemuanAudit::whereIn('status_capa', StatusCapa::aktif())->count();

        // 2. Temuan Klasifikasi Mayor Aktif
        $totalMayor = TemuanAudit::where('tingkat_risiko', 'Mayor')
            ->whereIn('status_capa', StatusCapa::aktif())
            ->count();

        // 3. Rasio Penyelesaian CAPA (Closing Rate)
        $totalTemuan = TemuanAudit::count();
        $closedTemuan = TemuanAudit::where('status_capa', StatusCapa::Closed->value)->count();
        $closingRate = $totalTemuan > 0 ? round(($closedTemuan / $totalTemuan) * 100, 1) : 100;

        // 4. Log Forensik Digital Audit Trail Hari Ini
        $totalLogHariIni = LogAudit::whereDate('dibuat_pada', today())->count();
        $logTerbaru = LogAudit::with(['pengguna', 'peran'])
            ->latest('id')
            ->take(5)
            ->get();

        return view('livewire.dashboard.auditor-dashboard', [
            'totalTemuanAktif' => $totalTemuanAktif,
            'temuanAktif'      => $temuanAktif,
            'totalMayor'       => $totalMayor,
            'closingRate'      => $closingRate,
            'totalTemuan'      => $totalTemuan,
            'totalLogHariIni'  => $totalLogHariIni,
            'logTerbaru'       => $logTerbaru,
        ])->layout('layouts.portal', ['portal' => 'auditor', 'title' => 'Dashboard Auditor SPI']);
    }
}
