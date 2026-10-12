<?php

namespace App\Livewire\Ipsrs;

use App\Enums\StatusMaintenance;
use App\Enums\StatusSpk;
use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\PenugasanMaintenance;
use App\Models\PermintaanMaintenance;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Support\Facades\DB;
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

    #[Validate('required|exists:pengguna,id', message: [
        'required' => 'Teknisi pelaksana perbaikan wajib ditugaskan.',
    ])]
    public ?int $teknisi_id = null;

    #[Validate('required|date', message: [
        'required' => 'Tanggal penugasan SPK wajib diisi.',
    ])]
    public string $tanggal_penugasan = '';

    #[Validate('nullable|date|after_or_equal:tanggal_penugasan', message: [
        'after_or_equal' => 'Target selesai tidak boleh sebelum tanggal penugasan.',
    ])]
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
        abort_unless($this->canTerbitkanSpk(), 403);

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
        abort_unless($this->canTerbitkanSpk(), 403);
        $this->validate();

        $user = auth()->user();
        $kodeSpk = CodeGenerator::generateSpk();
        $success = false;

        DB::transaction(function () use ($user, $kodeSpk, &$success) {
            $tiket = PermintaanMaintenance::lockForUpdate()->with('aset')->findOrFail($this->selected_tiket_id);

            if ($tiket->status !== StatusMaintenance::Dilaporkan->value) {
                $this->toast("Tiket {$tiket->kode} sudah diproses sebelumnya (Status: {$tiket->status}).", 'warning');
                return;
            }

            $alreadyHasSpk = PenugasanMaintenance::where('permintaan_maintenance_id', $tiket->id)
                ->where('status', '!=', StatusSpk::Dibatalkan->value)
                ->exists();
            if ($alreadyHasSpk) {
                $this->toast("Tiket {$tiket->kode} sudah memiliki SPK aktif.", 'warning');
                return;
            }

            PenugasanMaintenance::create([
                'kode'                      => $kodeSpk,
                'permintaan_maintenance_id' => $tiket->id,
                'teknisi_id'                => $this->teknisi_id,
                'ditugaskan_oleh'           => $user->id,
                'tanggal_penugasan'         => $this->tanggal_penugasan,
                'target_selesai'            => $this->target_selesai,
                'instruksi_khusus'          => $this->instruksi_khusus,
                'status'                    => StatusSpk::Ditugaskan->value,
            ]);

            $tiket->update(['status' => StatusMaintenance::Ditugaskan->value]);

            // Pastikan aset masuk ke status DALAM_MAINTENANCE
            if ($tiket->aset && $tiket->aset->status !== 'DALAM_MAINTENANCE') {
                $tiket->aset->update([
                    'status'  => 'DALAM_MAINTENANCE',
                    'kondisi' => $tiket->prioritas === 'CITO' ? 'RUSAK_BERAT' : 'RUSAK_RINGAN',
                ]);
            }

            $success = true;
        });

        if (! $success) {
            $this->showSpkModal = false;
            return;
        }

        $this->showSpkModal = false;
        $this->reset(['selected_tiket_id', 'selectedTiketForSpk', 'teknisi_id', 'instruksi_khusus']);
        $this->toast("Surat Perintah Kerja '{$kodeSpk}' berhasil diterbitkan.", 'success');
        $this->activeTab = 'spk';
    }

    public function showDetailSpk(int $id): void
    {
        $this->authorizeView();

        $this->selectedSpk = PenugasanMaintenance::with([
            'permintaanMaintenance.aset.barang',
            'permintaanMaintenance.unit',
            'permintaanMaintenance.pelapor',
            'teknisi',
            'penugast',
            'logMaintenance.teknisi',
            'logMaintenance.sukuCadang.barang',
        ])->findOrFail($id);
        abort_unless(! $this->isOwnSpkOnly() || (int) $this->selectedSpk->teknisi_id === (int) auth()->id(), 403);

        $this->showDetailModal = true;
    }

    /**
     * Penerbitan SPK adalah tahap disposisi Koordinator IPSRS: role kepala_teknisi
     * (atau role global) dan permission aksi.
     */
    public function canTerbitkanSpk(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->can('create_pemeliharaan::spk::perbaikan')
            && $user->hasRole(['kepala_teknisi', 'super_admin', 'admin_logistik']));
    }

    /**
     * Teknisi hanya melihat SPK yang ditugaskan kepadanya; koordinator, role global,
     * dan auditor/manajemen membaca seluruh departemen.
     */
    private function isOwnSpkOnly(): bool
    {
        return ! auth()->user()?->hasRole(['super_admin', 'admin_logistik', 'kepala_teknisi', 'auditor', 'manajemen']);
    }

    private function authorizeView(): void
    {
        abort_unless(
            auth()->user()?->can('view_any_pemeliharaan::spk::perbaikan') ||
            auth()->user()?->can('view_pemeliharaan::spk::perbaikan'),
            403
        );
    }

    public function render()
    {
        $this->authorizeView();

        $ownSpkOnly = $this->isOwnSpkOnly();
        $countAntrean = PermintaanMaintenance::where('status', StatusMaintenance::Dilaporkan->value)->count();
        $countSpkAktif = PenugasanMaintenance::whereIn('status', StatusSpk::aktif())
            ->when($ownSpkOnly, fn ($q) => $q->where('teknisi_id', auth()->id()))
            ->count();

        if ($this->activeTab === 'antrean') {
            $query = PermintaanMaintenance::with(['aset.barang', 'unit', 'pelapor'])
                ->where('status', StatusMaintenance::Dilaporkan->value)
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
            ->when($ownSpkOnly, fn ($q) => $q->where('teknisi_id', auth()->id()))
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

        // Daftar teknisi resmi IPSRS untuk dropdown disposisi SPK
        $daftarTeknisi = User::where('status', 'AKTIF')
            ->whereHas('roles', fn ($r) => $r->where('name', 'teknisi'))
            ->orderBy('nama')
            ->get();

        return view('livewire.ipsrs.daftar-spk-perbaikan-index', [
            'daftarData'    => $daftarData,
            'countAntrean'  => $countAntrean,
            'countSpkAktif' => $countSpkAktif,
            'daftarTeknisi' => $daftarTeknisi,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Surat Perintah Kerja (SPK) IPSRS']);
    }
}
