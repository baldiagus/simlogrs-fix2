<?php

namespace App\Livewire\Ipsrs;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Aset;
use App\Models\PermintaanMaintenance;
use App\Support\File\FileOptimizer;
use App\Support\Generator\CodeGenerator;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class LaporServisIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal, WithFileUploads;

    public bool $showModal = false;
    public bool $showDetailModal = false;
    public ?PermintaanMaintenance $selectedTiket = null;

    #[Validate('required|exists:aset,id')]
    public ?int $aset_id = null;

    #[Validate('required|in:RENDAH,SEDANG,TINGGI,CITO')]
    public string $prioritas = 'SEDANG';

    #[Validate('required|string|min:5')]
    public string $deskripsi_masalah = '';

    #[Validate('nullable|image|mimes:jpg,jpeg,png,webp|max:10240', message: [
        'image' => 'Berkas harus berupa gambar.',
        'mimes' => 'Format gambar yang didukung: JPG, PNG, WEBP.',
        'max'   => 'Ukuran foto maksimal adalah 10 MB.',
    ])]
    public $foto = null;

    #[Url(history: true)]
    public string $filterStatus = '';

    #[Url(history: true)]
    public string $filterPrioritas = '';

    public function create(): void
    {
        abort_unless(auth()->user()?->can('create_layanan_unit::lapor::servis'), 403);

        $this->reset(['aset_id', 'prioritas', 'deskripsi_masalah', 'foto']);
        $this->prioritas = 'SEDANG';
        $this->showModal = true;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('create_layanan_unit::lapor::servis'), 403);
        $this->validate();

        $user = auth()->user();
        $aset = Aset::findOrFail($this->aset_id);

        $kode = CodeGenerator::generateMnt();

        $fotoPath = null;
        if ($this->foto) {
            $opt = FileOptimizer::optimizeAndStore($this->foto, 'maintenance/kerusakan');
            $fotoPath = $opt['path'];
        }

        $tiket = PermintaanMaintenance::create([
            'kode'              => $kode,
            'aset_id'           => $aset->id,
            'unit_id'           => $aset->unit_id ?: $user->unit_id,
            'dilaporkan_oleh'   => $user->id,
            'deskripsi_masalah' => $this->deskripsi_masalah,
            'foto_kerusakan'    => $fotoPath,
            'prioritas'         => $this->prioritas,
            'status'            => 'DILAPORKAN',
        ]);

        // Jika prioritas CITO atau TINGGI, tandai aset ke status DALAM_MAINTENANCE
        if (in_array($this->prioritas, ['CITO', 'TINGGI'], true)) {
            $aset->update([
                'status'  => 'DALAM_MAINTENANCE',
                'kondisi' => $this->prioritas === 'CITO' ? 'RUSAK_BERAT' : 'RUSAK_RINGAN',
            ]);
        }

        $this->showModal = false;
        $this->reset(['aset_id', 'prioritas', 'deskripsi_masalah', 'foto']);
        $this->toast("Laporan kerusakan '{$kode}' berhasil dikirim ke IPSRS.", 'success');
    }
    public function hapusFotoPratinjau(): void
    {
        $this->foto = null;
    }


    public function showDetail(int $id): void
    {
        $this->selectedTiket = PermintaanMaintenance::with([
            'aset.barang',
            'unit',
            'pelapor',
            'penugasan.teknisi',
            'penugasan.penugast',
            'logMaintenance.teknisi',
            'sukuCadang.barang',
        ])->findOrFail($id);

        $this->showDetailModal = true;
    }

    public function confirmBatal(int $id): void
    {
        $tiket = PermintaanMaintenance::findOrFail($id);
        $user = auth()->user();

        if ($tiket->status !== 'DILAPORKAN') {
            $this->toast('Hanya tiket berstatus DILAPORKAN yang dapat dibatalkan.', 'danger');
            return;
        }

        $this->askConfirmation(
            target: $id,
            action: 'batal',
            title: 'Batalkan Laporan Kerusakan',
            description: "Apakah Anda yakin ingin membatalkan laporan tiket {$tiket->kode}?",
            buttonText: 'Ya, Batalkan Laporan',
            variant: 'danger'
        );
    }

    public function batal(int $id): void
    {
        $tiket = PermintaanMaintenance::findOrFail($id);

        if ($tiket->status !== 'DILAPORKAN') {
            $this->toast('Hanya tiket berstatus DILAPORKAN yang dapat dibatalkan.', 'danger');
            return;
        }

        $tiket->update(['status' => 'DIBATALKAN']);
        $this->toast("Laporan kerusakan '{$tiket->kode}' berhasil dibatalkan.", 'info');
    }

    public function render()
    {
        abort_unless(
            auth()->user()?->can('view_any_layanan_unit::lapor::servis') ||
            auth()->user()?->can('view_layanan_unit::lapor::servis'),
            403
        );

        $user = auth()->user();
        $userUnitId = $user?->unit_id;
        $isGlobalViewer = $user && (
            $user->hasRole(['super_admin', 'admin_logistik', 'teknisi', 'kepala_teknisi', 'auditor', 'manajemen']) ||
            $user->can('view_any_pemeliharaan::spk::perbaikan') ||
            $user->can('page_AdminLogistikDashboard')
        );

        $query = PermintaanMaintenance::with(['aset.barang', 'unit', 'pelapor', 'penugasanAktif.teknisi'])
            ->when($userUnitId && ! $isGlobalViewer, fn ($q) => $q->where('unit_id', $userUnitId))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterPrioritas, fn ($q) => $q->where('prioritas', $this->filterPrioritas))
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

        $daftarTiket = $query
            ->orderBy($this->sortBy ?: 'id', $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);

        // Aset yang dapat dipilih untuk dilaporkan: aset di unit bersangkutan (atau semua aset jika tanpa unit)
        $daftarAsetTersedia = Aset::with('barang')
            ->when($userUnitId, fn ($q) => $q->where('unit_id', $userUnitId))
            ->whereIn('status', ['TERSEDIA', 'DIGUNAKAN', 'DIPINJAM'])
            ->orderBy('kode_aset')
            ->get();

        return view('livewire.ipsrs.lapor-servis-index', [
            'daftarTiket'        => $daftarTiket,
            'daftarAsetTersedia' => $daftarAsetTersedia,
            'userUnitId'         => $userUnitId,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Lapor Kerusakan & Servis IPSRS']);
    }
}
