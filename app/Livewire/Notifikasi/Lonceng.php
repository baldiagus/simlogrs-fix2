<?php

namespace App\Livewire\Notifikasi;

use App\Services\Notifikasi\TautanWeb;
use Livewire\Component;

/**
 * Lonceng notifikasi di header portal: badge belum dibaca + 10 notifikasi terbaru (polling 30 detik).
 */
class Lonceng extends Component
{
    use MengelolaNotifikasi;

    public const BATAS_DROPDOWN = 10;

    public function render()
    {
        $pengguna = auth()->user();

        return view('livewire.notifikasi.lonceng', [
            'belumDibaca' => $this->milikSaya()->belumDibaca()->count(),
            'items' => $this->milikSaya()->orderByDesc('dibuat_pada')->orderByDesc('id')->limit(self::BATAS_DROPDOWN)->get(),
            'urlDaftar' => $pengguna ? app(TautanWeb::class)->daftarUrl($pengguna) : '#',
        ]);
    }
}
