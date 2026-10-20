<?php

namespace App\Livewire\Dashboard;

use App\Enums\Ipsrs\StatusMaintenance;
use App\Enums\Ipsrs\StatusSpk;
use App\Models\Ipsrs\JadwalMaintenance;
use App\Models\Ipsrs\PenugasanMaintenance;
use App\Models\Ipsrs\PermintaanMaintenance;
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

        // 1. Tiket Prioritas CITO / Tinggi yang Masih Aktif
        $tiketMendesak = PermintaanMaintenance::whereIn('prioritas', ['CITO', 'TINGGI'])
            ->whereIn('status', StatusMaintenance::aktif())
            ->with(['aset', 'unit'])
            ->latest('id')
            ->take(5)
            ->get();
        $totalTiketMendesak = PermintaanMaintenance::whereIn('prioritas', ['CITO', 'TINGGI'])
            ->whereIn('status', StatusMaintenance::aktif())
            ->count();

        // 2. Tiket Menunggu Penerbitan SPK
        $totalMenungguSpk = PermintaanMaintenance::whereIn('status', StatusMaintenance::menungguPenugasan())->count();

        // 3. SPK Aktif Berjalan (Jika teknisi biasa: hanya miliknya; jika koordinator: global)
        $spkAktif = PenugasanMaintenance::whereIn('status', StatusSpk::aktif())
            ->when(! $isKepalaTeknisi, fn($q) => $q->where('teknisi_id', $user->id))
            ->with(['permintaanMaintenance.aset', 'permintaanMaintenance.unit', 'teknisi'])
            ->latest('id')
            ->take(5)
            ->get();
        $totalSpkAktif = PenugasanMaintenance::whereIn('status', StatusSpk::aktif())
            ->when(! $isKepalaTeknisi, fn($q) => $q->where('teknisi_id', $user->id))
            ->count();

        // 4. Jadwal Pemeliharaan Preventif Mendatang
        $totalPreventif = JadwalMaintenance::when(! $isKepalaTeknisi, fn($q) => $q->where('teknisi_id', $user->id))->count();

        $portalTitle = $isKepalaTeknisi
            ? 'Pusat Komando & Dashboard Kepala Teknisi IPSRS'
            : 'Dashboard Teknisi Sarana & IPSRS';

        $portalSubtitle = $isKepalaTeknisi
            ? 'Pengawasan tiket perbaikan ruangan, disposisi SPK teknisi, pemeliharaan preventif, dan inventaris sarana RS.'
            : 'Disposisi SPK perbaikan, lembar kerja pengerjaan, pemakaian suku cadang, dan kalender servis berkala.';

        $title = $isKepalaTeknisi
            ? 'Pusat Komando Kepala Teknisi IPSRS'
            : 'Dashboard Teknisi IPSRS';

        return view('livewire.dashboard.teknisi-dashboard', [
            'portalTitle'        => $portalTitle,
            'portalSubtitle'     => $portalSubtitle,
            'isKepalaTeknisi'    => $isKepalaTeknisi,
            'totalTiketMendesak' => $totalTiketMendesak,
            'tiketMendesak'      => $tiketMendesak,
            'totalMenungguSpk'   => $totalMenungguSpk,
            'totalSpkAktif'      => $totalSpkAktif,
            'spkAktif'           => $spkAktif,
            'totalPreventif'     => $totalPreventif,
        ])->layout('layouts.portal', ['portal' => 'teknisi', 'title' => $title]);
    }
}
