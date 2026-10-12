<?php

namespace App\Livewire\Distribusi;

use App\Enums\StatusPermintaanDistribusi;
use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Livewire\Distribusi\Concerns\AuthorizesDistribusiScope;
use App\Models\PermintaanDistribusi;
use App\Models\PermintaanDistribusiDetail;
use App\Models\StokBatch;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class DistribusiApprovalIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal, AuthorizesDistribusiScope;

    #[Url(history: true)]
    public string $filterStatus = StatusPermintaanDistribusi::DisetujuiKaru->value;

    #[Url(history: true)]
    public string $filterUrgensi = '';

    public bool $showReviewModal = false;
    public bool $showDetailModal = false;
    public ?int $selectedId = null;
    public ?PermintaanDistribusi $selectedPermintaan = null;
    public array $itemQtys = [];

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterUrgensi(): void
    {
        $this->resetPage();
    }

    public function openReview(int $id): void
    {
        $this->selectedPermintaan = PermintaanDistribusi::with(['unit', 'gudang', 'detail.barang.satuan'])
            ->findOrFail($id);
        $this->authorizeGudangAction($this->selectedPermintaan);

        $this->selectedId = $this->selectedPermintaan->id;
        $this->itemQtys = [];

        foreach ($this->selectedPermintaan->detail as $detail) {
            $stokGudang = $this->getStokTersediaGudang(
                (int) $detail->barang_id,
                $this->selectedPermintaan->gudang_id,
                $this->selectedPermintaan->id
            );

            $jumlahDiminta = (int) $detail->jumlah;
            // Kuota disetujui terkunci 100% mengikuti permohonan ruangan (tidak boleh dikurangi sepihak)
            $jumlahDisetujui = $jumlahDiminta;
            $this->itemQtys[$detail->id] = [
                'detail_id'        => $detail->id,
                'barang_id'        => $detail->barang_id,
                'barang_nama'      => $detail->barang?->nama ?? ('Barang #' . $detail->barang_id),
                'barang_kode'      => $detail->barang?->kode ?? '',
                'satuan'           => $detail->barang?->satuan?->nama ?? 'Pcs',
                'jumlah_diminta'   => $jumlahDiminta,
                'jumlah_disetujui' => $jumlahDisetujui,
                'stok_gudang'      => $stokGudang,
                'catatan'          => $detail->catatan ?? '',
            ];
        }

        $this->showReviewModal = true;
    }

    protected function getStokTersediaGudang(int $barangId, ?int $gudangId, ?int $excludePermintaanId = null): int
    {
        $stokQuery = StokBatch::where('barang_id', $barangId)
            ->where('status', 'AKTIF')
            ->where('jumlah', '>', 0)
            ->where(function ($q) {
                $q->whereNull('tanggal_kedaluwarsa')
                    ->orWhere('tanggal_kedaluwarsa', '>=', now()->toDateString());
            });

        if ($gudangId) {
            $stokQuery->where('gudang_id', $gudangId);
        }

        $stokFisik = (int) $stokQuery->sum('jumlah');

        $stokCommitted = (int) PermintaanDistribusiDetail::where('barang_id', $barangId)
            ->whereHas('permintaanDistribusi', function ($q) use ($gudangId, $excludePermintaanId) {
                $q->where('status', StatusPermintaanDistribusi::DisetujuiGudang->value)
                    ->when($gudangId, fn ($sub) => $sub->where('gudang_id', $gudangId))
                    ->when($excludePermintaanId, fn ($sub) => $sub->where('id', '!=', $excludePermintaanId));
            })
            ->sum('jumlah_disetujui');

        return max(0, $stokFisik - $stokCommitted);
    }

    public function confirmApproveGudang(): void
    {
        $this->validate([
            'itemQtys.*.jumlah_disetujui' => 'required|integer|min:0',
        ], [
            'itemQtys.*.jumlah_disetujui.required' => 'Jumlah disetujui wajib diisi.',
            'itemQtys.*.jumlah_disetujui.integer'  => 'Jumlah disetujui harus berupa angka bulat.',
            'itemQtys.*.jumlah_disetujui.min'      => 'Jumlah disetujui tidak boleh kurang dari 0.',
        ]);

        foreach ($this->itemQtys as $detailId => $item) {
            $disetujui = (int) $item['jumlah_disetujui'];
            $diminta = (int) $item['jumlah_diminta'];

            if ($disetujui > $diminta) {
                $this->addError("itemQtys.{$detailId}.jumlah_disetujui", "Jumlah disetujui ({$disetujui}) tidak boleh melebihi jumlah yang diminta ({$diminta}).");
                return;
            }
        }

        $this->askConfirmation(
            target: null,
            action: 'submitApproveGudang',
            title: 'Otorisasi Persetujuan Distribusi',
            description: 'Apakah Anda yakin ingin menyetujui distribusi amprah ini dengan kuota barang yang telah disesuaikan?',
            buttonText: 'Ya, Setujui Distribusi',
            variant: 'primary'
        );
    }

    public function submitApproveGudang(): void
    {
        abort_unless($this->canApproveGudang(), 403);

        if (! $this->selectedId || empty($this->itemQtys)) {
            return;
        }

        $this->validate([
            'itemQtys.*.jumlah_disetujui' => 'required|integer|min:0',
        ]);

        // Persetujuan mengesahkan kuota komitmen 100%; selisih terhadap stok fisik otomatis menjadi hutang gudang (backorder)

        DB::transaction(function () {
            $permintaan = PermintaanDistribusi::with('detail')->lockForUpdate()->findOrFail($this->selectedId);
            $this->authorizeGudangAction($permintaan);

            if ($permintaan->status !== StatusPermintaanDistribusi::DisetujuiKaru->value) {
                $this->toast("Permintaan {$permintaan->kode} sudah diproses sebelumnya.", 'warning');
                return;
            }

            foreach ($this->itemQtys as $detailId => $data) {
                PermintaanDistribusiDetail::where('id', $detailId)
                    ->where('permintaan_distribusi_id', $permintaan->id)
                    ->update([
                        'jumlah_disetujui' => (int) $data['jumlah_disetujui'],
                    ]);
            }

            $permintaan->update([
                'status'         => StatusPermintaanDistribusi::DisetujuiGudang->value,
                'disetujui_oleh' => auth()->id(),
            ]);
        });

        $this->showReviewModal = false;
        $this->toast('Permintaan distribusi berhasil disetujui untuk diproses gudang.', 'success');
        $this->reset(['selectedId', 'itemQtys', 'selectedPermintaan']);
    }

    public function confirmRejectGudang(int $id): void
    {
        $permintaan = PermintaanDistribusi::findOrFail($id);
        $this->authorizeGudangAction($permintaan);

        $this->askConfirmation(
            target: $id,
            action: 'rejectGudang',
            title: 'Tolak Permintaan Distribusi',
            description: "Apakah Anda yakin ingin menolak permintaan distribusi {$permintaan->kode}? Tindakan ini akan membatalkan amprah.",
            buttonText: 'Ya, Tolak Permintaan',
            variant: 'danger'
        );
    }

    public function rejectGudang(int $id): void
    {
        $permintaan = PermintaanDistribusi::findOrFail($id);
        $this->authorizeGudangAction($permintaan);

        if ($permintaan->status !== StatusPermintaanDistribusi::DisetujuiKaru->value) {
            $this->toast('Hanya permintaan berstatus DISETUJUI_KARU yang dapat ditolak oleh admin logistik.', 'warning');
            return;
        }

        $permintaan->update([
            'status'         => StatusPermintaanDistribusi::Ditolak->value,
            'disetujui_oleh' => auth()->id(),
        ]);

        $this->toast("Permintaan distribusi {$permintaan->kode} berhasil ditolak.", 'success');
    }

    public function openDetail(int $id): void
    {
        $this->authorizeView();

        $permintaan = PermintaanDistribusi::with(['unit', 'gudang', 'pengaju', 'penyetuju', 'detail.barang.satuan', 'pengiriman.detail'])
            ->findOrFail($id);
        $this->authorizeReadScoped($permintaan->unit_id, $permintaan->gudang_id);

        $this->selectedPermintaan = $permintaan;
        $this->showDetailModal = true;
    }

    protected function authorizeView(): void
    {
        abort_unless(
            auth()->user()?->canAny(['view_any_gudang_stok::distribusi::approval', 'view_gudang_stok::distribusi::approval']),
            403
        );
    }

    /**
     * Tahap Meja 2 (Kepala Gudang): role kepala_gudang dan permission update; super_admin/admin_logistik global.
     */
    protected function canApproveGudang(): bool
    {
        $user = auth()->user();

        return (bool) ($user
            && $user->can('update_gudang_stok::distribusi::approval')
            && ($this->isGlobalOperator() || $user->hasRole('kepala_gudang')));
    }

    protected function authorizeGudangAction(PermintaanDistribusi $permintaan): void
    {
        abort_unless($this->canApproveGudang(), 403, 'Anda tidak memiliki wewenang otorisasi distribusi gudang.');
        abort_unless($this->inGudangScope($permintaan->gudang_id), 403, 'Anda hanya berhak memproses permohonan untuk gudang Anda sendiri.');
    }

    public function showDetail(int $id): void
    {
        $this->openDetail($id);
    }

    public function render()
    {
        $this->authorizeView();
        $canApproveGudang = $this->canApproveGudang();

        $allowedStatuses = [
            StatusPermintaanDistribusi::DisetujuiKaru->value,
            StatusPermintaanDistribusi::DisetujuiGudang->value,
            StatusPermintaanDistribusi::SedangDikirim->value,
            StatusPermintaanDistribusi::Selesai->value,
            StatusPermintaanDistribusi::Ditolak->value,
        ];

        $query = PermintaanDistribusi::with(['unit', 'gudang', 'pengaju', 'penyetuju', 'detail.barang.satuan'])
            ->whereIn('status', $allowedStatuses)
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

        $items = $query
            ->orderBy($this->sortBy ?: 'id', $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);

        return view('livewire.distribusi.distribusi-approval-index', [
            'items' => $items,
            'canApproveGudang' => $canApproveGudang,
            'approvableGudangIds' => $canApproveGudang ? $this->ownGudangIds() : [],
            'isGlobalAdmin' => $this->isGlobalOperator(),
        ])->layout('layouts.portal', [
            'portal' => $this->getPortalName(),
            'title'  => 'Otorisasi Distribusi Logistik (Admin Sentral)',
        ]);
    }
}
