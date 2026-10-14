<?php

namespace App\Livewire\Distribusi;

use App\Enums\Distribusi\StatusPermintaanDistribusi;
use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Livewire\Distribusi\Concerns\AuthorizesDistribusiScope;
use App\Models\Distribusi\PermintaanDistribusi;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class ApprovalPermintaanIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal, AuthorizesDistribusiScope;

    #[Url(history: true)]
    public string $filterStatus = StatusPermintaanDistribusi::Diajukan->value;

    #[Url(history: true)]
    public string $filterUrgensi = '';

    public bool $showDetailModal = false;
    public ?PermintaanDistribusi $selectedPermintaan = null;

    /**
     * Hook Livewire saat filter status diubah untuk memperbarui data dan mereset paginasi.
     */
    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    /**
     * Hook Livewire saat filter urgensi diubah untuk memperbarui data dan mereset paginasi.
     */
    public function updatedFilterUrgensi(): void
    {
        $this->resetPage();
    }

    /**
     * Tampilkan modal konfirmasi persetujuan oleh Kepala Ruangan (Karu).
     */
    public function confirmApproveKaru(int $id): void
    {
        $permintaan = PermintaanDistribusi::with('unit')->findOrFail($id);
        $this->authorizeAction($permintaan);

        if ($permintaan->status !== StatusPermintaanDistribusi::Diajukan->value) {
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

    /**
     * Eksekusi persetujuan permintaan oleh Kepala Ruangan.
     */
    public function approveKaru(int $id): void
    {
        $user = auth()->user();

        DB::transaction(function () use ($id, $user) {
            $permintaan = PermintaanDistribusi::lockForUpdate()->findOrFail($id);
            $this->authorizeAction($permintaan);

            if ($permintaan->status !== StatusPermintaanDistribusi::Diajukan->value) {
                $this->toast('Hanya permintaan berstatus DIAJUKAN yang dapat disetujui.', 'danger');
                return;
            }

            $permintaan->update([
                'status'         => StatusPermintaanDistribusi::DisetujuiKaru->value,
                'disetujui_oleh' => $user->id,
            ]);

            $permintaan->detail()->update([
                'jumlah_disetujui' => DB::raw('jumlah'),
            ]);

            $this->toast("Permintaan amprah '{$permintaan->kode}' berhasil disetujui.", 'success');
        });
    }

    /**
     * Tampilkan modal konfirmasi penolakan permintaan oleh Kepala Ruangan.
     */
    public function confirmRejectKaru(int $id): void
    {
        $permintaan = PermintaanDistribusi::with('unit')->findOrFail($id);
        $this->authorizeAction($permintaan);

        if ($permintaan->status !== StatusPermintaanDistribusi::Diajukan->value) {
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

    /**
     * Eksekusi penolakan permintaan oleh Kepala Ruangan beserta alasan.
     */
    public function rejectKaru(int $id): void
    {
        $user = auth()->user();

        DB::transaction(function () use ($id, $user) {
            $permintaan = PermintaanDistribusi::lockForUpdate()->findOrFail($id);
            $this->authorizeAction($permintaan);

            if ($permintaan->status !== StatusPermintaanDistribusi::Diajukan->value) {
                $this->toast('Hanya permintaan berstatus DIAJUKAN yang dapat ditolak.', 'danger');
                return;
            }

            $permintaan->update([
                'status'         => StatusPermintaanDistribusi::Ditolak->value,
                'disetujui_oleh' => $user->id,
            ]);

            $this->toast("Permintaan amprah '{$permintaan->kode}' telah ditolak.", 'info');
        });
    }

    /**
     * Buka modal dialog dan muat data rincian record terpilih.
     */
    public function showDetail(int $id): void
    {
        $this->authorizeView();

        $permintaan = PermintaanDistribusi::with(['unit', 'gudang', 'pengaju', 'penyetuju', 'detail.barang.satuan', 'pengiriman.detail'])->findOrFail($id);
        $this->authorizeReadScoped($permintaan->unit_id, $permintaan->gudang_id);

        $this->selectedPermintaan = $permintaan;
        $this->showDetailModal = true;
    }

    /**
     * Verifikasi wewenang pengguna untuk melihat data pada halaman ini.
     */
    protected function authorizeView(): void
    {
        abort_unless(
            auth()->user()?->canAny(['view_any_layanan_unit::approval::karu', 'view_layanan_unit::approval::karu']),
            403
        );
    }

    /**
     * Tahap Meja 1 (Kepala Ruangan): role kepala_unit dan permission update; super_admin/admin_logistik global.
     */
    protected function canApproveKaru(): bool
    {
        $user = auth()->user();

        return (bool) ($user
            && $user->can('update_layanan_unit::approval::karu')
            && ($this->isGlobalOperator() || $user->hasRole('kepala_unit')));
    }

    /**
     * Verifikasi wewenang pengguna untuk melakukan tindakan operasional.
     */
    protected function authorizeAction(PermintaanDistribusi $permintaan): void
    {
        abort_unless($this->canApproveKaru(), 403, 'Anda tidak memiliki wewenang untuk memproses otorisasi amprah ruangan.');
        abort_unless($this->inUnitScope($permintaan->unit_id), 403, 'Anda tidak memiliki wewenang untuk memproses permohonan dari unit ini.');
    }

    /**
     * Render tampilan blade komponen Livewire.
     */
    public function render()
    {
        $this->authorizeView();

        $user = auth()->user();
        $isGlobalAdmin = $this->isGlobalOperator();
        $canApproveKaru = $this->canApproveKaru();

        $query = PermintaanDistribusi::with(['unit', 'gudang', 'pengaju', 'penyetuju', 'detail.barang.satuan'])
            ->withCount('detail')
            ->tap(fn ($q) => $this->applyReadScope($q))
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
            'canApproveKaru'   => $canApproveKaru,
            'approvableUnitIds' => $canApproveKaru ? $this->ownUnitIds() : [],
            'userUnitId'       => $user?->unit_id,
            'pageTitle'        => $pageTitle,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => $pageTitle]);
    }

    /**
     * Dapatkan judul halaman sesuai dengan konteks komponen.
     */
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
