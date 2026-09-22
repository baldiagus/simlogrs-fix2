<?php

namespace App\Livewire\Ipsrs;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\PenugasanMaintenance;
use App\Models\PermintaanMaintenance;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;

class DaftarSpkPerbaikanIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal;

    #[Url(history: true)]
    public string $activeTab = 'antrean'; // 'antrean' atau 'spk'

    #[Url(history: true)]
    public string $filterStatus = '';

    public bool $showSpkModal = false;
    public bool $showDetailModal = false;
    public ?PenugasanMaintenance $selectedSpk = null;

    // Form Terbitkan SPK
    public ?int $selected_tiket_id = null;
    public ?PermintaanMaintenance $selectedTiketForSpk = null;

    #[Validate('required|exists:pengguna,id')]
    public ?int $teknisi_id = null;

    #[Validate('required|date')]
    public string $tanggal_penugasan = '';

    #[Validate('nullable|date|after_or_equal:tanggal_penugasan')]
    public ?string $target_selesai = null;

    #[Validate('nullable|string')]
    public ?string $instruksi_khusus = null;

    public function mount(): void
    {
        $this->tanggal_penugasan = now()->toDateString();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function openTerbitkanSpk(int $tiketId): void
    {
        abort_unless(auth()->user()?->can('create_pemeliharaan::spk::perbaikan'), 403);

        $this->selected_tiket_id = $tiketId;
        $this->selectedTiketForSpk = PermintaanMaintenance::with(['aset.barang', 'unit', 'pelapor'])->findOrFail($tiketId);

        $this->tanggal_penugasan = now()->toDateString();
        $this->target_selesai = now()->addDays(2)->toDateString();
        $this->instruksi_khusus = null;
        $this->teknisi_id = null;

        $this->showSpkModal = true;
    }

    public function saveSpk(): void
    {
        abort_unless(auth()->user()?->can('create_pemeliharaan::spk::perbaikan'), 403);
        $this->validate();

        $user = auth()->user();
        $tiket = PermintaanMaintenance::with('aset')->findOrFail($this->selected_tiket_id);

        $kodeSpk = CodeGenerator::generateSpk();

        $spk = PenugasanMaintenance::create([
            'kode'                      => $kodeSpk,
            'permintaan_maintenance_id' => $tiket->id,
            'teknisi_id'                => $this->teknisi_id,
            'ditugaskan_oleh'           => $user->id,
            'tanggal_penugasan'         => $this->tanggal_penugasan,
            'target_selesai'            => $this->target_selesai,
            'instruksi_khusus'          => $this->instruksi_khusus,
            'status'                    => 'DITUGASKAN',
        ]);

        $tiket->update(['status' => 'DITUGASKAN']);

        // Pastikan aset masuk ke status DALAM_MAINTENANCE
        if ($tiket->aset && $tiket->aset->status !== 'DALAM_MAINTENANCE') {
            $tiket->aset->update([
                'status'  => 'DALAM_MAINTENANCE',
                'kondisi' => $tiket->prioritas === 'CITO' ? 'RUSAK_BERAT' : 'RUSAK_RINGAN',
            ]);
        }

        $this->showSpkModal = false;
        $this->reset(['selected_tiket_id', 'selectedTiketForSpk', 'teknisi_id', 'instruksi_khusus']);
        $this->toast("Surat Perintah Kerja '{$kodeSpk}' berhasil diterbitkan.", 'success');

        // Beralih ke tab SPK aktif
        $this->activeTab = 'spk';
    }

    public function showDetailSpk(int $id): void
    {
        $this->selectedSpk = PenugasanMaintenance::with([
            'permintaanMaintenance.aset.barang',
            'permintaanMaintenance.unit',
            'permintaanMaintenance.pelapor',
            'teknisi',
            'penugast',
            'logMaintenance.teknisi',
            'logMaintenance.sukuCadang.barang',
        ])->findOrFail($id);

        $this->showDetailModal = true;
    }

    public function render()
    {
        abort_unless(
            auth()->user()?->can('view_any_pemeliharaan::spk::perbaikan') ||
            auth()->user()?->can('view_pemeliharaan::spk::perbaikan'),
            403
        );

        $countAntrean = PermintaanMaintenance::where('status', 'DILAPORKAN')->count();
        $countSpkAktif = PenugasanMaintenance::whereIn('status', ['DITUGASKAN', 'DITERIMA'])->count();

        if ($this->activeTab === 'antrean') {
            $query = PermintaanMaintenance::with(['aset.barang', 'unit', 'pelapor'])
                ->where('status', 'DILAPORKAN')
                ->when($this->search, function ($q) {
                    $q->where(function ($sub) {
                        $sub->where('kode', 'ilike', "%{$this->search}%")
                            ->orWhere('deskripsi_masalah', 'ilike', "%{$this->search}%")
                            ->orWhereHas('aset', function ($a) {
                                $a->where('kode_aset', 'ilike', "%{$this->search}%")
                                    ->orWhere('nomor_seri', 'ilike', "%{$this->search}%")
                                    ->orWhereHas('barang', fn ($b) => $b->where('nama', 'ilike', "%{$this->search}%"));
                            });
                    });
                });

            $daftarData = $query
                ->orderByRaw("CASE WHEN prioritas = 'CITO' THEN 1 WHEN prioritas = 'TINGGI' THEN 2 WHEN prioritas = 'SEDANG' THEN 3 ELSE 4 END")
                ->orderBy('id', 'desc')
                ->paginate($this->perPage);
        } else {
            $query = PenugasanMaintenance::with([
                'permintaanMaintenance.aset.barang',
                'permintaanMaintenance.unit',
                'teknisi',
                'penugast',
            ])
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('kode', 'ilike', "%{$this->search}%")
                        ->orWhereHas('teknisi', fn ($t) => $t->where('nama', 'ilike', "%{$this->search}%"))
                        ->orWhereHas('permintaanMaintenance', function ($m) {
                            $m->where('kode', 'ilike', "%{$this->search}%")
                                ->orWhereHas('aset', fn ($a) => $a->where('kode_aset', 'ilike', "%{$this->search}%"));
                        });
                });
            });

            $daftarData = $query
                ->orderBy($this->sortBy ?: 'id', $this->sortDirection === 'asc' ? 'asc' : 'desc')
                ->paginate($this->perPage);
        }

        // Daftar teknisi untuk dropdown disposisi
        $daftarTeknisi = User::where('status', 'AKTIF')
            ->where(function ($q) {
                $q->whereHas('roles', fn ($r) => $r->where('name', 'teknisi'))
                    ->orWhere('gudang_id', 2); // Gudang IPSRS jika ada
            })
            ->orderBy('nama')
            ->get();

        // Fallback jika tidak ada user dengan role khusus teknisi, tampilkan user aktif
        if ($daftarTeknisi->isEmpty()) {
            $daftarTeknisi = User::where('status', 'AKTIF')->orderBy('nama')->get();
        }

        return view('livewire.ipsrs.daftar-spk-perbaikan-index', [
            'daftarData'    => $daftarData,
            'countAntrean'  => $countAntrean,
            'countSpkAktif' => $countSpkAktif,
            'daftarTeknisi' => $daftarTeknisi,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Surat Perintah Kerja (SPK) IPSRS']);
    }
}
