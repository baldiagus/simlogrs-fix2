<?php

namespace App\Livewire\Ipsrs;

use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Models\Aset;
use Livewire\Attributes\Url;
use Livewire\Component;

class RekamMedisAsetIndex extends Component
{
    use HasTableFilters, HasPortalLayout;

    public bool $showDetailModal = false;
    public ?Aset $selectedAset = null;

    #[Url(history: true)]
    public string $filterKondisi = '';

    public function showBukuRiwayat(int $asetId): void
    {
        abort_unless(
            auth()->user()?->can('view_any_pemeliharaan::rekam::medis::aset') ||
            auth()->user()?->can('view_pemeliharaan::rekam::medis::aset'),
            403
        );

        $this->selectedAset = Aset::with([
            'barang',
            'unit',
            'gudang',
            'permintaanMaintenance.penugasan.teknisi',
            'permintaanMaintenance.logMaintenance.teknisi',
            'permintaanMaintenance.sukuCadang.barang',
            'pengalihanDetail.pengalihanAset.unitAsal',
            'pengalihanDetail.pengalihanAset.unitTujuan',
            'pengalihanDetail.pengalihanAset.gudangAsal',
            'pengalihanDetail.pengalihanAset.gudangTujuan',
            'pengalihanDetail.pengalihanAset.penerima',
        ])->findOrFail($asetId);

        $this->showDetailModal = true;
    }
    public function openRekamMedis(int $asetId): void
    {
        $this->showBukuRiwayat($asetId);
    }

    public function render()
    {
        abort_unless(
            auth()->user()?->can('view_any_pemeliharaan::rekam::medis::aset') ||
            auth()->user()?->can('view_pemeliharaan::rekam::medis::aset'),
            403
        );

        $query = Aset::with(['barang', 'unit', 'gudang'])
            ->withCount('permintaanMaintenance')
            ->when($this->filterKondisi, fn ($q) => $q->where('kondisi', $this->filterKondisi))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('kode_aset', 'ilike', "%{$this->search}%")
                        ->orWhere('nomor_seri', 'ilike', "%{$this->search}%")
                        ->orWhereHas('barang', fn ($b) => $b->where('nama', 'ilike', "%{$this->search}%"))
                        ->orWhereHas('unit', fn ($u) => $u->where('nama', 'ilike', "%{$this->search}%"));
                });
            });

        $daftarAset = $query
            ->orderBy($this->sortBy ?: 'id', $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);

        return view('livewire.ipsrs.rekam-medis-aset-index', [
            'daftarAset' => $daftarAset,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Rekam Medis Sarana & Aset']);
    }
}
