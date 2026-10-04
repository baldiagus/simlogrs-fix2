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
use Illuminate\Support\Facades\DB;
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

    #[Validate('required|exists:aset,id', message: [
        'required' => 'Fasilitas atau unit aset yang rusak wajib dipilih.',
        'exists'   => 'Aset tidak ditemukan di sistem.',
    ])]
    public ?int $aset_id = null;

    #[Validate('required|in:RENDAH,SEDANG,TINGGI,CITO', message: [
        'required' => 'Tingkat prioritas darurat wajib ditentukan.',
    ])]
    public string $prioritas = 'SEDANG';

    #[Validate('required|string|min:5', message: [
        'required' => 'Deskripsi keluhan atau kerusakan sarana wajib dijelaskan.',
        'min'      => 'Deskripsi kerusakan minimal 5 karakter.',
    ])]
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
        $aset = Aset::with(['barang', 'unit', 'gudang'])->findOrFail($this->aset_id);

        if (! $aset->barang || $aset->barang->status !== 'AKTIF') {
            $this->addError('aset_id', 'Komoditas aset yang dipilih sudah berstatus nonaktif.');
            return;
        }

        if ($aset->unit && $aset->unit->status !== 'AKTIF') {
            $this->addError('aset_id', 'Unit ruangan penempatan aset sudah berstatus nonaktif.');
            return;
        }

        if ($aset->gudang && $aset->gudang->status !== 'AKTIF') {
            $this->addError('aset_id', 'Fasilitas gudang penempatan aset sudah berstatus nonaktif.');
            return;
        }
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
        $isGlobalViewer = $user && (
            $user->hasRole(['super_admin', 'admin_logistik', 'auditor', 'manajemen']) ||
            $user->can('page_AdminLogistikDashboard') ||
            $user->can('page_Dashboard')
        );

        if (! $isGlobalViewer) {
            $isOwner = (int) $user?->id === (int) $tiket->dilaporkan_oleh;
            $isSameUnit = $user?->unit_id && (int) $user->unit_id === (int) $tiket->unit_id;
            if (! $isOwner && ! $isSameUnit) {
                abort(403, 'Anda tidak berwenang membatalkan laporan kerusakan dari unit lain.');
            }
        }

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
        $tiket = PermintaanMaintenance::with('aset')->findOrFail($id);
        $user = auth()->user();
        $isGlobalViewer = $user && (
            $user->hasRole(['super_admin', 'admin_logistik', 'auditor', 'manajemen']) ||
            $user->can('page_AdminLogistikDashboard') ||
            $user->can('page_Dashboard')
        );

        if (! $isGlobalViewer) {
            $isOwner = (int) $user?->id === (int) $tiket->dilaporkan_oleh;
            $isSameUnit = $user?->unit_id && (int) $user->unit_id === (int) $tiket->unit_id;
            if (! $isOwner && ! $isSameUnit) {
                abort(403, 'Anda tidak berwenang membatalkan laporan kerusakan dari unit lain.');
            }
        }

        if ($tiket->status !== 'DILAPORKAN') {
            $this->toast('Hanya tiket berstatus DILAPORKAN yang dapat dibatalkan.', 'danger');
            return;
        }

        DB::transaction(function () use ($tiket) {
            $tiket->update(['status' => 'DIBATALKAN']);

            // Jika aset terkunci dalam maintenance akibat tiket ini dan tidak ada tiket aktif lain
            if ($tiket->aset && $tiket->aset->status === 'DALAM_MAINTENANCE') {
                $hasOtherActiveTicket = PermintaanMaintenance::where('aset_id', $tiket->aset_id)
                    ->where('id', '!=', $tiket->id)
                    ->whereIn('status', ['DILAPORKAN', 'DITUGASKAN', 'SEDANG_DIKERJAKAN', 'MENUNGGU_SPAREPART', 'UJI_FUNGSI'])
                    ->exists();

                if (! $hasOtherActiveTicket) {
                    $newStatus = $tiket->aset->unit_id ? 'DIGUNAKAN' : 'TERSEDIA';
                    $tiket->aset->update([
                        'status'  => $newStatus,
                        'kondisi' => 'BAIK',
                    ]);
                }
            }
        });

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
            ->whereHas('barang', fn ($b) => $b->where('status', 'AKTIF'))
            ->when($userUnitId, fn ($q) => $q->where('unit_id', $userUnitId), fn ($q) => $q->where(function ($sub) {
                $sub->whereNull('unit_id')
                    ->orWhereHas('unit', fn ($u) => $u->where('status', 'AKTIF'));
            }))
            ->where(function ($sub) {
                $sub->whereNull('gudang_id')
                    ->orWhereHas('gudang', fn ($g) => $g->where('status', 'AKTIF'));
            })
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
