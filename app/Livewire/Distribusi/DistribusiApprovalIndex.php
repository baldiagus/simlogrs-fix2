<?php

namespace App\Livewire\Distribusi;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\PermintaanDistribusi;
use App\Models\PermintaanDistribusiDetail;
use App\Models\StokBatch;
use App\Models\Gudang;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class DistribusiApprovalIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal;

    #[Url(history: true)]
    public string $filterStatus = 'DISETUJUI_KARU';

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
        abort_unless(
            auth()->user()?->can('update_gudang_stok::distribusi::approval')
            || auth()->user()?->can('gudang_stok::distribusi::approval')
            || auth()->user()?->can('page_DistribusiApprovalIndex'),
            403
        );

        $this->selectedPermintaan = PermintaanDistribusi::with(['unit', 'gudang', 'detail.barang.satuan'])
            ->findOrFail($id);

        $this->selectedId = $this->selectedPermintaan->id;
        $this->itemQtys = [];

        foreach ($this->selectedPermintaan->detail as $detail) {
            $stokQuery = StokBatch::where('barang_id', $detail->barang_id)
                ->where('status', 'AKTIF')
                ->where('jumlah', '>', 0)
                ->where(function ($q) {
                    $q->whereNull('tanggal_kedaluwarsa')
                        ->orWhere('tanggal_kedaluwarsa', '>=', now()->toDateString());
                });

            if ($this->selectedPermintaan->gudang_id) {
                $stokQuery->where('gudang_id', $this->selectedPermintaan->gudang_id);
            }

            $stokGudang = (int) $stokQuery->sum('jumlah');
            $jumlahDiminta = (int) $detail->jumlah;
            $jumlahDisetujui = $detail->jumlah_disetujui !== null
                ? (int) $detail->jumlah_disetujui
                : min($jumlahDiminta, $stokGudang);

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
            $stok = (int) $item['stok_gudang'];

            if ($disetujui > $diminta) {
                $this->addError("itemQtys.{$detailId}.jumlah_disetujui", "Jumlah disetujui ({$disetujui}) melebihi jumlah yang diminta ({$diminta}).");
                return;
            }

            if ($disetujui > $stok) {
                $this->addError("itemQtys.{$detailId}.jumlah_disetujui", "Jumlah disetujui ({$disetujui}) melebihi saldo fisik gudang ({$stok}).");
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
        abort_unless(
            auth()->user()?->can('update_gudang_stok::distribusi::approval')
            || auth()->user()?->can('gudang_stok::distribusi::approval')
            || auth()->user()?->can('page_DistribusiApprovalIndex'),
            403
        );

        if (! $this->selectedId || empty($this->itemQtys)) {
            return;
        }

        $this->validate([
            'itemQtys.*.jumlah_disetujui' => 'required|integer|min:0',
        ]);

        foreach ($this->itemQtys as $detailId => $item) {
            $disetujui = (int) $item['jumlah_disetujui'];
            $stok = (int) $item['stok_gudang'];
            if ($disetujui > $stok) {
                $this->toast("Jumlah disetujui melebihi stok gudang untuk {$item['barang_nama']}.", 'danger');
                return;
            }
        }

        DB::transaction(function () {
            $permintaan = PermintaanDistribusi::with('detail')->findOrFail($this->selectedId);

            if ($permintaan->status !== 'DISETUJUI_KARU') {
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
                'status'         => 'DISETUJUI_GUDANG',
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
        abort_unless(
            auth()->user()?->can('update_gudang_stok::distribusi::approval')
            || auth()->user()?->can('gudang_stok::distribusi::approval')
            || auth()->user()?->can('page_DistribusiApprovalIndex'),
            403
        );

        $permintaan = PermintaanDistribusi::findOrFail($id);

        if ($permintaan->status !== 'DISETUJUI_KARU') {
            $this->toast('Hanya permintaan berstatus DISETUJUI_KARU yang dapat ditolak oleh admin logistik.', 'warning');
            return;
        }

        $permintaan->update([
            'status'         => 'DITOLAK',
            'disetujui_oleh' => auth()->id(),
        ]);

        $this->toast("Permintaan distribusi {$permintaan->kode} berhasil ditolak.", 'success');
    }

    public function openDetail(int $id): void
    {
        abort_unless(
            auth()->user()?->can('view_any_gudang_stok::distribusi::approval')
            || auth()->user()?->can('view_gudang_stok::distribusi::approval')
            || auth()->user()?->can('gudang_stok::distribusi::approval')
            || auth()->user()?->can('page_DistribusiApprovalIndex'),
            403
        );

        $this->selectedPermintaan = PermintaanDistribusi::with(['unit', 'gudang', 'pengaju', 'penyetuju', 'detail.barang.satuan'])
            ->findOrFail($id);
        $this->showDetailModal = true;
    }

    public function showDetail(int $id): void
    {
        $this->openDetail($id);
    }

    public function render()
    {
        abort_unless(
            auth()->user()?->can('view_any_gudang_stok::distribusi::approval')
            || auth()->user()?->can('view_gudang_stok::distribusi::approval')
            || auth()->user()?->can('gudang_stok::distribusi::approval')
            || auth()->user()?->can('page_DistribusiApprovalIndex'),
            403
        );
        $user = auth()->user();
        $isGlobalAdmin = $user && ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard'));

        $allowedStatuses = ['DISETUJUI_KARU', 'DISETUJUI_GUDANG', 'SEDANG_DIKIRIM', 'SELESAI', 'DITOLAK'];

        $query = PermintaanDistribusi::with(['unit', 'gudang', 'pengaju', 'penyetuju', 'detail.barang.satuan'])
            ->whereIn('status', $allowedStatuses)
            ->when(! $isGlobalAdmin && $user?->gudang_id, function ($q) use ($user) {
                $gudangIds = Gudang::where('kepala_gudang_id', $user->id)->pluck('id');
                $q->whereIn('gudang_id', $gudangIds->isNotEmpty() ? $gudangIds : [$user->gudang_id]);
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

        $items = $query
            ->orderBy($this->sortBy ?: 'id', $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);

        return view('livewire.distribusi.distribusi-approval-index', [
            'items' => $items,
        ])->layout('layouts.portal', [
            'portal' => $this->getPortalName(),
            'title'  => 'Otorisasi Distribusi Logistik (Admin Sentral)',
        ]);
    }
}
