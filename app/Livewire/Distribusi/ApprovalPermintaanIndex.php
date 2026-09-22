<?php

namespace App\Livewire\Distribusi;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\PermintaanDistribusi;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class ApprovalPermintaanIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal;

    #[Url(history: true)]
    public string $filterStatus = 'DIAJUKAN';

    #[Url(history: true)]
    public string $filterUrgensi = '';

    public bool $showDetailModal = false;
    public ?PermintaanDistribusi $selectedPermintaan = null;

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterUrgensi(): void
    {
        $this->resetPage();
    }

    public function confirmApproveKaru(int $id): void
    {
        $permintaan = PermintaanDistribusi::with('unit')->findOrFail($id);
        $this->authorizeAction($permintaan);

        if ($permintaan->status !== 'DIAJUKAN') {
            $this->toast('Hanya permintaan berstatus DIAJUKAN yang dapat disetujui.', 'danger');
            return;
        }

        $this->showDetailModal = false;

        $this->askConfirmation(
            target: $id,
            action: 'approveKaru',
            title: 'Konfirmasi Persetujuan Amprah',
            description: "Apakah Anda yakin ingin menyetujui permohonan amprah {$permintaan->kode} dari {$permintaan->unit?->nama}?",
            buttonText: 'Ya, Setujui',
            variant: 'primary'
        );
    }

    public function approveKaru(int $id): void
    {
        $permintaan = PermintaanDistribusi::findOrFail($id);
        $this->authorizeAction($permintaan);

        if ($permintaan->status !== 'DIAJUKAN') {
            $this->toast('Hanya permintaan berstatus DIAJUKAN yang dapat disetujui.', 'danger');
            return;
        }

        DB::transaction(function () use ($permintaan) {
            $permintaan->update([
                'status' => 'DISETUJUI_KARU',
                'disetujui_oleh' => auth()->id(),
            ]);

            $permintaan->detail()->update([
                'jumlah_disetujui' => DB::raw('jumlah'),
            ]);
        });

        $this->toast("Permintaan amprah '{$permintaan->kode}' berhasil disetujui.", 'success');
    }

    public function confirmRejectKaru(int $id): void
    {
        $permintaan = PermintaanDistribusi::with('unit')->findOrFail($id);
        $this->authorizeAction($permintaan);

        if ($permintaan->status !== 'DIAJUKAN') {
            $this->toast('Hanya permintaan berstatus DIAJUKAN yang dapat ditolak.', 'danger');
            return;
        }

        $this->showDetailModal = false;

        $this->askConfirmation(
            target: $id,
            action: 'rejectKaru',
            title: 'Konfirmasi Penolakan Amprah',
            description: "Apakah Anda yakin ingin menolak permohonan amprah {$permintaan->kode}? Permintaan yang ditolak tidak dapat diproses lebih lanjut.",
            buttonText: 'Ya, Tolak Amprah',
            variant: 'danger'
        );
    }

    public function rejectKaru(int $id): void
    {
        $permintaan = PermintaanDistribusi::findOrFail($id);
        $this->authorizeAction($permintaan);

        if ($permintaan->status !== 'DIAJUKAN') {
            $this->toast('Hanya permintaan berstatus DIAJUKAN yang dapat ditolak.', 'danger');
            return;
        }

        $permintaan->update([
            'status' => 'DITOLAK',
            'disetujui_oleh' => auth()->id(),
        ]);

        $this->toast("Permintaan amprah '{$permintaan->kode}' telah ditolak.", 'info');
    }

    public function showDetail(int $id): void
    {
        $permintaan = PermintaanDistribusi::with(['unit', 'gudang', 'pengaju', 'penyetuju', 'detail.barang.satuan'])->findOrFail($id);

        $user = auth()->user();
        $isGlobalAdmin = $user && ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard'));

        if (! $isGlobalAdmin) {
            if ($user?->unit_id && (int) $user->unit_id !== (int) $permintaan->unit_id) {
                abort(403, 'Anda tidak memiliki wewenang untuk melihat permohonan dari unit ini.');
            }
            if ($user?->gudang_id && (int) $user->gudang_id !== (int) $permintaan->gudang_id) {
                abort(403, 'Anda tidak memiliki wewenang untuk melihat permohonan ke gudang ini.');
            }
        }
        $this->selectedPermintaan = $permintaan;
        $this->showDetailModal = true;
    }

    protected function authorizeAction(PermintaanDistribusi $permintaan): void
    {
        $user = auth()->user();
        abort_unless(
            $user && (
                $user->can('update_layanan_unit::approval::karu') ||
                $user->can('layanan_unit::approval::karu') ||
                $user->can('page_ApprovalPermintaanIndex') ||
                $user->can('page_Dashboard') ||
                $user->can('page_AdminLogistikDashboard')
            ),
            403,
            'Anda tidak memiliki wewenang untuk memproses otorisasi amprah ruangan.'
        );

        $isGlobalAdmin = $user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard');
        if (! $isGlobalAdmin) {
            if ($user?->unit_id && (int) $user->unit_id !== (int) $permintaan->unit_id) {
                abort(403, 'Anda tidak memiliki wewenang untuk memproses permohonan dari unit ini.');
            }
            if ($user?->gudang_id && (int) $user->gudang_id !== (int) $permintaan->gudang_id) {
                abort(403, 'Anda tidak memiliki wewenang untuk memproses permohonan ke gudang ini.');
            }
        }
    }

    public function render()
    {
        abort_unless(
            auth()->user()?->can('page_ApprovalPermintaanIndex') ||
            auth()->user()?->can('view_any_layanan_unit::approval::karu') ||
            auth()->user()?->can('view_layanan_unit::approval::karu') ||
            auth()->user()?->can('layanan_unit::approval::karu') ||
            auth()->user()?->can('page_Dashboard') ||
            auth()->user()?->can('page_AdminLogistikDashboard'),
            403
        );

        $user = auth()->user();
        $isGlobalAdmin = $user && ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard'));

        $query = PermintaanDistribusi::with(['unit', 'gudang', 'pengaju', 'penyetuju', 'detail.barang.satuan'])
            ->withCount('detail')
            ->when(! $isGlobalAdmin, function ($q) use ($user) {
                if ($user?->unit_id) {
                    $q->where('unit_id', $user->unit_id);
                } elseif ($user?->gudang_id) {
                    $q->where('gudang_id', $user->gudang_id);
                }
            })
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterUrgensi, fn ($q) => $q->where('urgensi', $this->filterUrgensi))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('kode', 'ilike', "%{$this->search}%")
                        ->orWhereHas('unit', fn ($u) => $u->where('nama', 'ilike', "%{$this->search}%"))
                        ->orWhereHas('gudang', fn ($g) => $g->where('nama', 'ilike', "%{$this->search}%"));
                });
            });

        $daftarPermintaan = $query
            ->orderBy($this->sortBy ?: 'id', $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);

        $pageTitle = $this->getPageTitle();

        return view('livewire.distribusi.approval-permintaan-index', [
            'daftarPermintaan' => $daftarPermintaan,
            'isGlobalAdmin'    => $isGlobalAdmin,
            'userUnitId'       => $user?->unit_id,
            'pageTitle'        => $pageTitle,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => $pageTitle]);
    }

    public function getPageTitle(): string
    {
        $portal = $this->getPortalName();

        $portalTitles = [
            'kepala-gudang'  => 'Otorisasi Amprah (Kepala Gudang)',
            'kepala-unit'    => 'Otorisasi Amprah Ruangan (Kepala Unit)',
            'admin-logistik' => 'Otorisasi Amprah (Admin Logistik)',
            'super-admin'    => 'Otorisasi Amprah (Super Administrator)',
            'petugas-gudang' => 'Otorisasi Amprah (Petugas Gudang)',
            'petugas-unit'   => 'Otorisasi Amprah (Petugas Unit)',
            'manajemen'      => 'Otorisasi Amprah (Manajemen)',
            'auditor'        => 'Otorisasi Amprah (Auditor Internal)',
        ];

        if (isset($portalTitles[$portal])) {
            return $portalTitles[$portal];
        }

        $role = auth()->user()?->roles?->first();
        $label = $role?->nama ?? $role?->name ?? 'Pimpinan';
        return "Otorisasi Amprah ({$label})";
    }
}
