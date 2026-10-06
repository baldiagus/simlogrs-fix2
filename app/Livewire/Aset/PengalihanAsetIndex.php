<?php

namespace App\Livewire\Aset;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Aset;
use App\Models\Gudang;
use App\Models\LokasiGudang;
use App\Models\PengalihanAset;
use App\Models\Unit;
use App\Services\Aset\PengalihanAsetService;
use Livewire\Attributes\Url;
use Livewire\Component;

class PengalihanAsetIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal;

    #[Url(history: true)]
    public string $activeTab = 'aktif'; // 'aktif' | 'riwayat'

    #[Url(history: true)]
    public string $filterStatus = '';

    #[Url(history: true)]
    public string $filterTipe = 'semua';


    // Modal Create State
    public bool $showCreateModal = false;
    public string $tipe_asal = 'RUANGAN';
    public ?int $unit_asal_id = null;
    public ?int $gudang_asal_id = null;
    public string $tipe_tujuan = 'RUANGAN';
    public ?int $unit_tujuan_id = null;
    public ?int $gudang_tujuan_id = null;
    public ?int $lokasi_gudang_tujuan_id = null;
    public string $tanggal_pengalihan = '';
    public string $alasan = '';
    public string $catatan = '';
    public array $selected_aset_ids = [];

    // Modal Detail / BAST State
    public bool $showDetailModal = false;
    public ?PengalihanAset $selectedTransfer = null;

    // Modal Otorisasi
    public bool $showApprovalModal = false;
    public ?int $actionTransferId = null;
    public string $actionType = ''; // 'APPROVE' | 'REJECT'
    public string $catatanApproval = '';

    // Modal Serah Terima Fisik
    public bool $showTerimaModal = false;
    public ?PengalihanAset $transferTerima = null;
    public array $kondisiTerima = []; // [$asetId => ['kondisi' => 'BAIK', 'catatan' => '']]

    public function mount(): void
    {
        $this->tanggal_pengalihan = now()->toDateString();
        $user = auth()->user();

        if ($user?->unit_id) {
            $this->tipe_asal = 'RUANGAN';
            $this->unit_asal_id = $user->unit_id;
        } elseif ($user?->gudang_id) {
            $this->tipe_asal = 'GUDANG';
            $this->gudang_asal_id = $user->gudang_id;
        } else {
            $this->unit_asal_id = Unit::where('status', 'AKTIF')->value('id');
        }
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['aktif', 'riwayat'], true) ? $tab : 'aktif';
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $user = auth()->user();
        abort_unless(
            $user?->can('create_layanan_unit::pengalihan::aset') ||
            $user?->can('page_PengalihanAsetIndex') ||
            $user?->hasRole(['super_admin', 'admin_logistik']),
            403,
            'Anda tidak memiliki wewenang untuk mengajukan pengalihan aset.'
        );

        $this->resetValidation();
        $this->tanggal_pengalihan = now()->toDateString();
        $this->selected_aset_ids = [];
        $this->alasan = '';
        $this->catatan = '';
        $this->showCreateModal = true;
    }

    public function updatedTipeAsal(): void
    {
        $this->selected_aset_ids = [];
        if ($this->tipe_asal === 'RUANGAN' && ! $this->unit_asal_id) {
            $this->unit_asal_id = Unit::where('status', 'AKTIF')->value('id');
            $this->gudang_asal_id = null;
        } elseif ($this->tipe_asal === 'GUDANG' && ! $this->gudang_asal_id) {
            $this->gudang_asal_id = Gudang::where('status', 'AKTIF')->value('id');
            $this->unit_asal_id = null;
        }
    }

    public function updatedUnitAsalId(): void
    {
        $this->selected_aset_ids = [];
    }

    public function updatedGudangAsalId(): void
    {
        $this->selected_aset_ids = [];
    }

    public function updatedTipeTujuan(): void
    {
        if ($this->tipe_tujuan === 'RUANGAN') {
            $this->gudang_tujuan_id = null;
            $this->lokasi_gudang_tujuan_id = null;
        } else {
            $this->unit_tujuan_id = null;
        }
    }

    public function submitPengalihan(): void
    {
        $user = auth()->user();
        abort_unless(
            $user?->can('create_layanan_unit::pengalihan::aset') ||
            $user?->hasRole(['super_admin', 'admin_logistik']),
            403
        );

        $this->validate([
            'tipe_asal' => 'required|in:RUANGAN,GUDANG',
            'unit_asal_id' => 'required_if:tipe_asal,RUANGAN|nullable|exists:unit,id',
            'gudang_asal_id' => 'required_if:tipe_asal,GUDANG|nullable|exists:gudang,id',
            'tipe_tujuan' => 'required|in:RUANGAN,GUDANG',
            'unit_tujuan_id' => 'required_if:tipe_tujuan,RUANGAN|nullable|exists:unit,id',
            'gudang_tujuan_id' => 'required_if:tipe_tujuan,GUDANG|nullable|exists:gudang,id',
            'lokasi_gudang_tujuan_id' => 'nullable|exists:lokasi_gudang,id',
            'tanggal_pengalihan' => 'required|date',
            'alasan' => 'required|string|min:5|max:1000',
            'selected_aset_ids' => 'required|array|min:1',
            'selected_aset_ids.*' => 'exists:aset,id',
        ], [
            'selected_aset_ids.required' => 'Pilih minimal satu aset fisik yang hendak dialihkan.',
            'selected_aset_ids.min' => 'Pilih minimal satu aset fisik yang hendak dialihkan.',
            'alasan.required' => 'Justifikasi/alasan pengalihan aset wajib diisi.',
            'alasan.min' => 'Alasan pengalihan minimal 5 karakter.',
        ]);

        if ($this->tipe_asal === 'RUANGAN' && $this->tipe_tujuan === 'RUANGAN' && (int) $this->unit_asal_id === (int) $this->unit_tujuan_id) {
            $this->addError('unit_tujuan_id', 'Unit tujuan tidak boleh sama dengan unit asal.');
            return;
        }
        if ($this->tipe_asal === 'GUDANG' && $this->tipe_tujuan === 'GUDANG' && (int) $this->gudang_asal_id === (int) $this->gudang_tujuan_id) {
            $this->addError('gudang_tujuan_id', 'Gudang tujuan tidak boleh sama dengan gudang asal.');
            return;
        }

        try {
            $service = app(PengalihanAsetService::class);
            $transfer = $service->ajukanPengalihan([
                'tipe_asal' => $this->tipe_asal,
                'unit_asal_id' => $this->unit_asal_id,
                'gudang_asal_id' => $this->gudang_asal_id,
                'tipe_tujuan' => $this->tipe_tujuan,
                'unit_tujuan_id' => $this->unit_tujuan_id,
                'gudang_tujuan_id' => $this->gudang_tujuan_id,
                'lokasi_gudang_tujuan_id' => $this->lokasi_gudang_tujuan_id,
                'tanggal_pengalihan' => $this->tanggal_pengalihan,
                'alasan' => $this->alasan,
                'catatan' => $this->catatan,
                'diajukan_oleh' => $user->id,
                'aset_ids' => $this->selected_aset_ids,
            ]);

            $this->showCreateModal = false;
            $this->toast("Pengalihan aset {$transfer->kode} berhasil diajukan dan aset telah dikunci.", 'success');
            $this->reset(['selected_aset_ids', 'alasan', 'catatan']);
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->toast($e->getMessage(), 'danger', 'Gagal Mengajukan');
        }
    }

    public function showDetail(int $id): void
    {
        $this->selectedTransfer = PengalihanAset::with([
            'unitAsal', 'unitTujuan', 'gudangAsal', 'gudangTujuan', 'lokasiGudangTujuan',
            'pengaju', 'penyetuju', 'penerima',
            'detail.aset.barang.satuan',
        ])->findOrFail($id);

        $this->showDetailModal = true;
    }

    public function openApprovalModal(int $id, string $type): void
    {
        $this->actionTransferId = $id;
        $this->actionType = $type; // 'APPROVE' | 'REJECT'
        $this->catatanApproval = '';
        $this->showApprovalModal = true;
    }

    public function executeApproval(): void
    {
        $user = auth()->user();
        $isGlobalAdmin = (bool) ($user && ($user->hasRole(['super_admin', 'admin_logistik']) || $user->can('page_Dashboard')));
        abort_unless(
            $user?->can('update_layanan_unit::pengalihan::aset') ||
            $isGlobalAdmin ||
            $user?->hasRole(['super_admin', 'admin_logistik', 'kepala_unit', 'kepala_gudang']),
            403,
            'Anda tidak memiliki wewenang untuk menyetujui atau menolak pengalihan aset.'
        );

        $service = app(PengalihanAsetService::class);

        try {
            if ($this->actionType === 'APPROVE') {
                $transfer = $service->setujuiPengalihan($this->actionTransferId, $user->id, $this->catatanApproval);
                $this->toast("Dokumen {$transfer->kode} berhasil disetujui untuk serah terima fisik.", 'success');
            } else {
                $transfer = $service->tolakPengalihan($this->actionTransferId, $user->id, $this->catatanApproval);
                $this->toast("Dokumen {$transfer->kode} ditolak dan kunci aset telah dilepas.", 'warning');
            }

            $this->showApprovalModal = false;
            $this->actionTransferId = null;
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->toast($e->getMessage(), 'danger');
        }
    }

    public function openTerimaModal(int $id): void
    {
        $user = auth()->user();
        $transfer = PengalihanAset::with(['detail.aset.barang'])->findOrFail($id);

        abort_unless(
            $transfer->status === 'DISETUJUI',
            403,
            'Pengalihan belum disetujui.'
        );

        $this->transferTerima = $transfer;
        $this->kondisiTerima = [];
        foreach ($transfer->detail as $item) {
            $this->kondisiTerima[$item->aset_id] = [
                'kondisi' => $item->kondisi_sebelum ?: 'BAIK',
                'catatan' => '',
            ];
        }

        $this->showTerimaModal = true;
    }

    public function executeTerima(): void
    {
        $user = auth()->user();
        $isGlobalAdmin = (bool) ($user && ($user->hasRole(['super_admin', 'admin_logistik']) || $user->can('page_Dashboard')));
        abort_unless(
            $user?->can('update_layanan_unit::pengalihan::aset') ||
            $user?->can('create_layanan_unit::pengalihan::aset') ||
            $isGlobalAdmin ||
            $user?->hasRole(['super_admin', 'admin_logistik', 'kepala_unit', 'petugas_unit', 'kepala_gudang', 'petugas_gudang']),
            403,
            'Anda tidak memiliki wewenang untuk mengonfirmasi serah terima fisik aset.'
        );

        try {
            $service = app(PengalihanAsetService::class);
            $transfer = $service->eksekusiSerahTerima($this->transferTerima->id, $user->id, $this->kondisiTerima);

            $this->showTerimaModal = false;
            $this->transferTerima = null;
            $this->toast("Serah terima {$transfer->kode} selesai! Lokasi kepemilikan aset resmi dimutasi.", 'success');
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->toast($e->getMessage(), 'danger');
        }
    }

    public function cancelPengalihan(int $id): void
    {
        $user = auth()->user();
        $isGlobalAdmin = (bool) ($user && ($user->hasRole(['super_admin', 'admin_logistik']) || $user->can('page_Dashboard')));
        abort_unless(
            $user?->can('delete_layanan_unit::pengalihan::aset') ||
            $user?->can('create_layanan_unit::pengalihan::aset') ||
            $isGlobalAdmin,
            403,
            'Anda tidak memiliki wewenang untuk membatalkan pengalihan aset.'
        );

        try {
            $service = app(PengalihanAsetService::class);
            $transfer = $service->batalkanPengalihan($id, $user->id, 'Dibatalkan oleh pengaju');
            $this->toast("Permohonan {$transfer->kode} telah dibatalkan dan aset dikembalikan.", 'info');
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->toast($e->getMessage(), 'danger');
        }
    }

    public function render()
    {
        $user = auth()->user();
        $isGlobalAdmin = (bool) ($user && ($user->hasRole(['super_admin', 'admin_logistik']) || $user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard')));

        abort_unless(
            $user && (
                $user->can('view_any_layanan_unit::pengalihan::aset') ||
                $user->can('view_layanan_unit::pengalihan::aset') ||
                $user->can('page_PengalihanAsetIndex') ||
                $isGlobalAdmin
            ),
            403,
            'Anda tidak memiliki wewenang untuk mengakses halaman Pengalihan & Mutasi Aset.'
        );
        // Query data pengalihan
        $query = PengalihanAset::with([
            'unitAsal', 'unitTujuan', 'gudangAsal', 'gudangTujuan',
            'pengaju', 'penyetuju', 'penerima',
            'detail.aset.barang',
        ]);

        // Isolasi unit jika bukan global admin
        if (! $isGlobalAdmin) {
            if ($user?->unit_id) {
                $query->where(function ($q) use ($user) {
                    $q->where(fn ($sub) => $sub->where('tipe_asal', 'RUANGAN')->where('unit_asal_id', $user->unit_id))
                      ->orWhere(fn ($sub) => $sub->where('tipe_tujuan', 'RUANGAN')->where('unit_tujuan_id', $user->unit_id));
                });
            } elseif ($user?->gudang_id) {
                $query->where(function ($q) use ($user) {
                    $q->where(fn ($sub) => $sub->where('tipe_asal', 'GUDANG')->where('gudang_asal_id', $user->gudang_id))
                      ->orWhere(fn ($sub) => $sub->where('tipe_tujuan', 'GUDANG')->where('gudang_tujuan_id', $user->gudang_id));
                });
            }
        }

        // Tab filter
        if ($this->activeTab === 'aktif') {
            $query->whereIn('status', ['DIAJUKAN', 'DISETUJUI']);
        } else {
            $query->whereIn('status', ['SELESAI', 'DITOLAK', 'DIBATALKAN']);
        }

        // Status filter
        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('kode', 'ilike', "%{$this->search}%")
                  ->orWhere('alasan', 'ilike', "%{$this->search}%")
                  ->orWhereHas('unitAsal', fn ($u) => $u->where('nama', 'ilike', "%{$this->search}%"))
                  ->orWhereHas('unitTujuan', fn ($u) => $u->where('nama', 'ilike', "%{$this->search}%"))
                  ->orWhereHas('detail.aset', fn ($a) => $a->where('kode_aset', 'ilike', "%{$this->search}%")->orWhere('nomor_seri', 'ilike', "%{$this->search}%"))
                  ->orWhereHas('detail.aset.barang', fn ($b) => $b->where('nama', 'ilike', "%{$this->search}%"));
            });
        }

        $items = $query->orderBy('id', 'desc')->paginate($this->perPage);

        // Metrik KPI
        $kpiBerjalan = PengalihanAset::whereIn('status', ['DIAJUKAN', 'DISETUJUI'])->count();
        $kpiMenungguTerima = PengalihanAset::where('status', 'DISETUJUI')->count();
        $kpiSelesaiBulanIni = PengalihanAset::where('status', 'SELESAI')
            ->whereMonth('tanggal_serah_terima', now()->month)
            ->whereYear('tanggal_serah_terima', now()->year)
            ->count();
        $kpiTotalAset = \App\Models\PengalihanAsetDetail::whereHas('pengalihanAset', fn ($q) => $q->where('status', 'SELESAI'))->count();

        // Daftar Aset yang dapat dipilih untuk form create
        $asetTersedia = collect();
        if ($this->showCreateModal) {
            $asetQuery = Aset::with('barang')
                ->whereIn('status', ['TERSEDIA', 'DIGUNAKAN'])
                ->whereNotIn('kondisi', ['RUSAK_BERAT', 'DIHAPUSKAN']);

            if ($this->tipe_asal === 'RUANGAN' && $this->unit_asal_id) {
                $asetQuery->where('unit_id', $this->unit_asal_id);
            } elseif ($this->tipe_asal === 'GUDANG' && $this->gudang_asal_id) {
                $asetQuery->where('gudang_id', $this->gudang_asal_id);
            } else {
                $asetQuery->whereRaw('1=0');
            }

            $asetTersedia = $asetQuery->orderBy('kode_aset')->get();
        }

        $units = Unit::where('status', 'AKTIF')->orderBy('nama')->get();
        $gudangs = Gudang::where('status', 'AKTIF')->orderBy('nama')->get();
        $raks = $this->gudang_tujuan_id ? LokasiGudang::where('gudang_id', $this->gudang_tujuan_id)->where('status', 'AKTIF')->orderBy('kode')->get() : collect();

        return view('livewire.aset.pengalihan-aset-index', [
            'items' => $items,
            'kpiBerjalan' => $kpiBerjalan,
            'kpiMenungguTerima' => $kpiMenungguTerima,
            'kpiSelesaiBulanIni' => $kpiSelesaiBulanIni,
            'kpiTotalAset' => $kpiTotalAset,
            'asetTersedia' => $asetTersedia,
            'units' => $units,
            'gudangs' => $gudangs,
            'raks' => $raks,
            'isGlobalAdmin' => $isGlobalAdmin,
        ])->layout('layouts.portal', [
            'portal' => $this->getPortalName(),
            'title' => 'Pengalihan & Mutasi Aset',
        ]);
    }
}
