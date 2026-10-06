<?php

namespace App\Livewire\Persediaan;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\LokasiGudang;
use App\Models\PengalihanBarang;
use App\Models\StokBatch;
use App\Models\Unit;
use App\Services\Persediaan\PengalihanBarangService;
use Livewire\Attributes\Url;
use Livewire\Component;

class PengalihanBarangIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal;

    #[Url(history: true)]
    public string $activeTab = 'aktif'; // 'aktif' | 'riwayat'

    #[Url(history: true)]
    public string $filterStatus = '';

    #[Url(history: true)]
    public ?int $filterGudangAsalId = null;

    // Modal Create State
    public bool $showCreateModal = false;
    public ?int $selected_barang_id = null;
    public string $tipe_asal = 'GUDANG';
    public ?int $gudang_asal_id = null;
    public ?int $unit_asal_id = null;
    public ?int $selected_stok_batch_id = null;
    public string $tipe_tujuan = 'GUDANG';
    public ?int $gudang_tujuan_id = null;
    public ?int $unit_tujuan_id = null;
    public ?int $lokasi_gudang_tujuan_id = null;
    public int $quantity = 1;
    public string $tanggal_pengalihan = '';
    public string $alasan = '';
    public string $catatan = '';

    // Modal Detail / BAST
    public bool $showDetailModal = false;
    public ?PengalihanBarang $selectedTransfer = null;

    // Modal Otorisasi
    public bool $showApprovalModal = false;
    public ?int $actionTransferId = null;
    public string $actionType = ''; // 'APPROVE' | 'REJECT'
    public string $catatanApproval = '';

    public function mount(): void
    {
        $this->tanggal_pengalihan = now()->toDateString();
        $user = auth()->user();
        $isGlobalAdmin = (bool) ($user && ($user->hasRole(['super_admin', 'admin_logistik']) || $user->can('page_Dashboard')));

        if (! $isGlobalAdmin) {
            if ($user?->gudang_id) {
                $this->tipe_tujuan = 'GUDANG';
                $this->gudang_tujuan_id = $user->gudang_id;
                $this->unit_tujuan_id = null;
            } elseif ($user?->unit_id) {
                $this->tipe_tujuan = 'RUANGAN';
                $this->unit_tujuan_id = $user->unit_id;
                $this->gudang_tujuan_id = null;
            }

            $this->tipe_asal = 'GUDANG';
            $this->gudang_asal_id = Gudang::where('status', 'AKTIF')
                ->where('id', '!=', $this->gudang_tujuan_id)
                ->value('id');
        } else {
            $this->gudang_asal_id = Gudang::where('status', 'AKTIF')->value('id');
            $this->tipe_tujuan = 'GUDANG';
            $this->gudang_tujuan_id = Gudang::where('status', 'AKTIF')
                ->where('id', '!=', $this->gudang_asal_id)
                ->value('id');
        }
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['aktif', 'otorisasi', 'riwayat'], true) ? $tab : 'aktif';
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $user = auth()->user();
        $canCreate = $user && (
            $user->can('create_gudang_stok::pengalihan::barang') ||
            $user->can('page_PengalihanBarangIndex') ||
            $user->hasRole(['super_admin', 'admin_logistik', 'kepala_gudang', 'petugas_gudang'])
        );

        abort_unless($canCreate, 403, 'Akses ditolak: Hanya fasilitas gudang logistik yang berwenang mengajukan pengalihan barang.');

        $this->resetValidation();
        $this->tanggal_pengalihan = now()->toDateString();
        $this->selected_barang_id = null;
        $this->selected_stok_batch_id = null;
        $this->quantity = 1;
        $this->alasan = '';
        $this->catatan = '';

        $isGlobalAdmin = (bool) ($user && ($user->hasRole(['super_admin', 'admin_logistik']) || $user->can('page_Dashboard')));
        if (! $isGlobalAdmin) {
            if ($user?->gudang_id) {
                $this->tipe_tujuan = 'GUDANG';
                $this->gudang_tujuan_id = $user->gudang_id;
                $this->unit_tujuan_id = null;
            } elseif ($user?->unit_id) {
                $this->tipe_tujuan = 'RUANGAN';
                $this->unit_tujuan_id = $user->unit_id;
                $this->gudang_tujuan_id = null;
            }

            $this->tipe_asal = 'GUDANG';
            $this->gudang_asal_id = Gudang::where('status', 'AKTIF')
                ->where('id', '!=', $this->gudang_tujuan_id)
                ->value('id');
        }

        $this->showCreateModal = true;
    }

    public function updatedSelectedBarangId(): void
    {
        $this->selected_stok_batch_id = null;
        $this->quantity = 1;
    }

    public function updatedGudangAsalId(): void
    {
        $this->selected_stok_batch_id = null;
        $this->quantity = 1;
    }

    public function updatedSelectedStokBatchId(): void
    {
        $this->quantity = 1;
    }

    public function updatedQuantity($value): void
    {
        $max = $this->maxQuantity;
        if ((int) $value > $max) {
            $this->quantity = $max;
            $this->addError('quantity', "Jumlah unit melebihi stok batch tersedia (maksimal {$max}).");
        } elseif ((int) $value < 1) {
            $this->quantity = 1;
            $this->resetErrorBag('quantity');
        } else {
            $this->resetErrorBag('quantity');
        }
    }

    public function getMaxQuantityProperty(): int
    {
        if (! $this->selected_stok_batch_id) {
            return 1;
        }

        $batch = StokBatch::find($this->selected_stok_batch_id);

        return $batch ? max(1, (int) $batch->jumlah) : 1;
    }

    public function submitPengalihan(): void
    {
        $user = auth()->user();
        $canCreate = $user && (
            $user->can('create_gudang_stok::pengalihan::barang') ||
            $user->can('page_PengalihanBarangIndex') ||
            $user->hasRole(['super_admin', 'admin_logistik', 'kepala_gudang', 'petugas_gudang'])
        );

        abort_unless($canCreate, 403, 'Akses ditolak.');

        $isGlobalAdmin = (bool) ($user && ($user->hasRole(['super_admin', 'admin_logistik']) || $user->can('page_Dashboard')));
        if (! $isGlobalAdmin) {
            if ($user?->gudang_id) {
                $this->tipe_tujuan = 'GUDANG';
                $this->gudang_tujuan_id = $user->gudang_id;
                $this->unit_tujuan_id = null;
            } elseif ($user?->unit_id) {
                $this->tipe_tujuan = 'RUANGAN';
                $this->unit_tujuan_id = $user->unit_id;
                $this->gudang_tujuan_id = null;
            }
        }

        $this->validate([
            'selected_barang_id' => 'required|exists:barang,id',
            'tipe_asal' => 'required|in:GUDANG,RUANGAN',
            'gudang_asal_id' => 'required_if:tipe_asal,GUDANG|nullable|exists:gudang,id',
            'selected_stok_batch_id' => 'required_if:tipe_asal,GUDANG|nullable|exists:stok_batch,id',
            'tipe_tujuan' => 'required|in:GUDANG,RUANGAN',
            'gudang_tujuan_id' => 'required_if:tipe_tujuan,GUDANG|nullable|exists:gudang,id',
            'unit_tujuan_id' => 'required_if:tipe_tujuan,RUANGAN|nullable|exists:unit,id',
            'quantity' => 'required|integer|min:1',
            'tanggal_pengalihan' => 'required|date',
            'alasan' => 'required|string|min:5|max:1000',
        ], [
            'selected_barang_id.required' => 'Komoditas barang wajib dipilih.',
            'selected_stok_batch_id.required_if' => 'Batch stok fisik asal wajib dipilih.',
            'alasan.required' => 'Alasan / dasar pengalihan wajib diisi.',
            'alasan.min' => 'Alasan minimal 5 karakter.',
        ]);

        if ($this->tipe_asal === 'GUDANG' && $this->tipe_tujuan === 'GUDANG' && (int) $this->gudang_asal_id === (int) $this->gudang_tujuan_id) {
            $this->addError('gudang_asal_id', 'Gudang asal dan gudang tujuan pengalihan tidak boleh sama.');
            return;
        }

        try {
            $service = app(PengalihanBarangService::class);
            $transfer = $service->ajukanPengalihan([
                'tipe_asal' => $this->tipe_asal,
                'gudang_asal_id' => $this->gudang_asal_id,
                'unit_asal_id' => $this->unit_asal_id,
                'tipe_tujuan' => $this->tipe_tujuan,
                'gudang_tujuan_id' => $this->gudang_tujuan_id,
                'unit_tujuan_id' => $this->unit_tujuan_id,
                'lokasi_gudang_tujuan_id' => $this->lokasi_gudang_tujuan_id,
                'tanggal_pengalihan' => $this->tanggal_pengalihan,
                'alasan' => $this->alasan,
                'catatan' => $this->catatan,
                'diajukan_oleh' => $user->id,
                'items' => [
                    [
                        'barang_id' => $this->selected_barang_id,
                        'stok_batch_id' => $this->selected_stok_batch_id,
                        'jumlah' => $this->quantity,
                    ],
                ],
            ]);

            $this->showCreateModal = false;
            $this->toast("Pengalihan barang {$transfer->kode} berhasil diajukan.", 'success');
            $this->reset(['selected_barang_id', 'selected_stok_batch_id', 'quantity', 'alasan', 'catatan']);
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->toast($e->getMessage(), 'danger');
        }
    }

    public function showDetail(int $id): void
    {
        $this->selectedTransfer = PengalihanBarang::with([
            'gudangAsal', 'unitAsal', 'gudangTujuan', 'unitTujuan', 'lokasiGudangTujuan',
            'pengaju', 'penyetuju', 'penerima',
            'detail.barang.satuan', 'detail.stokBatch',
        ])->findOrFail($id);

        $this->showDetailModal = true;
    }

    public function openApprovalModal(int $id, string $type): void
    {
        $this->actionTransferId = $id;
        $this->actionType = $type;
        $this->catatanApproval = '';
        $this->showApprovalModal = true;
    }

    public function executeApproval(): void
    {
        $user = auth()->user();
        abort_unless(
            $user?->can('update_gudang_stok::pengalihan::barang') ||
            $user?->hasRole(['super_admin', 'admin_logistik', 'kepala_gudang']),
            403
        );

        $service = app(PengalihanBarangService::class);
        try {
            if ($this->actionType === 'APPROVE') {
                $transfer = $service->setujuiPengalihan($this->actionTransferId, $user->id, $this->catatanApproval);
                $this->toast("Pengalihan {$transfer->kode} disetujui untuk serah terima.", 'success');
            } else {
                $transfer = $service->tolakPengalihan($this->actionTransferId, $user->id, $this->catatanApproval);
                $this->toast("Pengalihan {$transfer->kode} ditolak dan stok dikembalikan.", 'warning');
            }

            $this->showApprovalModal = false;
            $this->actionTransferId = null;
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->toast($e->getMessage(), 'danger');
        }
    }

    public function executeTerima(int $id): void
    {
        $user = auth()->user();
        abort_unless(
            $user?->can('update_gudang_stok::pengalihan::barang') ||
            $user?->hasRole(['super_admin', 'admin_logistik', 'kepala_gudang', 'petugas_gudang', 'kepala_unit', 'petugas_unit']),
            403
        );

        try {
            $service = app(PengalihanBarangService::class);
            $transfer = $service->eksekusiSerahTerima($id, $user->id);
            $this->toast("Serah terima {$transfer->kode} selesai! Stok resmi mengalir ke inventaris tujuan.", 'success');
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->toast($e->getMessage(), 'danger');
        }
    }

    public function cancelPengalihan(int $id): void
    {
        $user = auth()->user();
        try {
            $service = app(PengalihanBarangService::class);
            $transfer = $service->batalkanPengalihan($id, $user->id, 'Dibatalkan oleh pengaju');
            $this->toast("Pengalihan {$transfer->kode} dibatalkan dan stok dikembalikan.", 'info');
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->toast($e->getMessage(), 'danger');
        }
    }

    public function render()
    {
        $user = auth()->user();
        $isGlobalAdmin = $user && ($user->hasRole(['super_admin', 'admin_logistik']) || $user->can('page_Dashboard'));

        // Guard Utama: Unit Ruangan DIBLOKIR dari mengakses Pengalihan Barang
        $isWarehouseOrAdmin = $isGlobalAdmin || $user?->hasRole(['kepala_gudang', 'petugas_gudang']) || $user?->gudang_id;
        abort_unless($isWarehouseOrAdmin, 403, 'Akses ditolak: Hanya fasilitas gudang logistik yang berwenang mengakses modul Pengalihan Barang.');

        $query = PengalihanBarang::with([
            'gudangAsal', 'unitAsal', 'gudangTujuan', 'unitTujuan',
            'pengaju', 'penyetuju', 'penerima',
            'detail.barang.satuan',
        ]);

        // 1. Tab Aktif: Pengajuan yang diterbitkan oleh / ditujukan ke gudang pengguna
        if ($this->activeTab === 'aktif') {
            if (! $isGlobalAdmin && $user?->gudang_id) {
                $query->where(function ($q) use ($user) {
                    $q->where(fn ($sub) => $sub->where('tipe_tujuan', 'GUDANG')->where('gudang_tujuan_id', $user->gudang_id))
                      ->orWhere('diajukan_oleh', $user->id);
                });
            }
            $query->whereIn('status', ['DIAJUKAN', 'DISETUJUI']);
        }
        // 2. Tab Otorisasi: Pengajuan dari gudang/unit LAIN yang meminta stok dari gudang pengguna
        elseif ($this->activeTab === 'otorisasi') {
            if (! $isGlobalAdmin && $user?->gudang_id) {
                $query->where('tipe_asal', 'GUDANG')
                      ->where('gudang_asal_id', $user->gudang_id)
                      ->where(function ($sub) use ($user) {
                          $sub->where('tipe_tujuan', '!=', 'GUDANG')
                              ->orWhere('gudang_tujuan_id', '!=', $user->gudang_id);
                      });
            }
            $query->where('status', 'DIAJUKAN');
        }
        // 3. Tab Riwayat: Selesai, Ditolak, Dibatalkan
        else {
            if (! $isGlobalAdmin && $user?->gudang_id) {
                $query->where(function ($q) use ($user) {
                    $q->where(fn ($sub) => $sub->where('tipe_asal', 'GUDANG')->where('gudang_asal_id', $user->gudang_id))
                      ->orWhere(fn ($sub) => $sub->where('tipe_tujuan', 'GUDANG')->where('gudang_tujuan_id', $user->gudang_id));
                });
            }
            $query->whereIn('status', ['SELESAI', 'DITOLAK', 'DIBATALKAN']);
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('kode', 'ilike', "%{$this->search}%")
                  ->orWhere('alasan', 'ilike', "%{$this->search}%")
                  ->orWhereHas('gudangAsal', fn ($g) => $g->where('nama', 'ilike', "%{$this->search}%"))
                  ->orWhereHas('gudangTujuan', fn ($g) => $g->where('nama', 'ilike', "%{$this->search}%"))
                  ->orWhereHas('unitTujuan', fn ($u) => $u->where('nama', 'ilike', "%{$this->search}%"))
                  ->orWhereHas('detail.barang', fn ($b) => $b->where('nama', 'ilike', "%{$this->search}%"));
            });
        }

        $items = $query->orderBy($this->getValidatedSortColumn(['kode', 'status', 'tanggal_pengalihan']), $this->getValidatedSortDirection())->paginate($this->perPage);

        // Hitungan Tab & KPI yang disinkronkan ke gudang pengguna (mencegah kebocoran angka global)
        $countAktifQuery = PengalihanBarang::whereIn('status', ['DIAJUKAN', 'DISETUJUI']);
        if (! $isGlobalAdmin && $user?->gudang_id) {
            $countAktifQuery->where(function ($q) use ($user) {
                $q->where(fn ($sub) => $sub->where('tipe_tujuan', 'GUDANG')->where('gudang_tujuan_id', $user->gudang_id))
                  ->orWhere('diajukan_oleh', $user->id);
            });
        }
        $countAktif = $countAktifQuery->count();

        $countOtorisasiQuery = PengalihanBarang::where('status', 'DIAJUKAN');
        if (! $isGlobalAdmin && $user?->gudang_id) {
            $countOtorisasiQuery->where('tipe_asal', 'GUDANG')
                ->where('gudang_asal_id', $user->gudang_id)
                ->where(function ($sub) use ($user) {
                    $sub->where('tipe_tujuan', '!=', 'GUDANG')
                        ->orWhere('gudang_tujuan_id', '!=', $user->gudang_id);
                });
        }
        $countOtorisasi = $countOtorisasiQuery->count();

        $kpiBerjalan = $countAktif;
        $kpiMenungguTerima = (clone $countAktifQuery)->where('status', 'DISETUJUI')->count();

        $riwayatQuery = PengalihanBarang::where('status', 'SELESAI');
        if (! $isGlobalAdmin && $user?->gudang_id) {
            $riwayatQuery->where(function ($q) use ($user) {
                $q->where(fn ($sub) => $sub->where('tipe_asal', 'GUDANG')->where('gudang_asal_id', $user->gudang_id))
                  ->orWhere(fn ($sub) => $sub->where('tipe_tujuan', 'GUDANG')->where('gudang_tujuan_id', $user->gudang_id));
            });
        }
        $kpiSelesaiBulanIni = (clone $riwayatQuery)
            ->whereMonth('tanggal_serah_terima', now()->month)
            ->whereYear('tanggal_serah_terima', now()->year)
            ->count();

        $kpiTotalItem = \App\Models\PengalihanBarangDetail::whereHas('pengalihanBarang', function ($q) use ($isGlobalAdmin, $user) {
            $q->where('status', 'SELESAI');
            if (! $isGlobalAdmin && $user?->gudang_id) {
                $q->where(function ($sub) use ($user) {
                    $sub->where(fn ($s) => $s->where('tipe_asal', 'GUDANG')->where('gudang_asal_id', $user->gudang_id))
                        ->orWhere(fn ($s) => $s->where('tipe_tujuan', 'GUDANG')->where('gudang_tujuan_id', $user->gudang_id));
                });
            }
        })->sum('jumlah');
        $daftarBarang = Barang::where('status', 'AKTIF')
            ->where('tipe', '!=', 'ASET')
            ->orderBy('nama')
            ->get();

        $batchesAsal = collect();
        if ($this->selected_barang_id && $this->gudang_asal_id) {
            $batchesAsal = StokBatch::where('barang_id', $this->selected_barang_id)
                ->where('gudang_id', $this->gudang_asal_id)
                ->where('jumlah', '>', 0)
                ->where('status', 'AKTIF')
                ->orderBy('tanggal_kedaluwarsa', 'asc')
                ->get();
        }

        $gudangs = Gudang::where('status', 'AKTIF')->orderBy('nama')->get();
        $units = Unit::where('status', 'AKTIF')->orderBy('nama')->get();
        $raks = $this->gudang_tujuan_id ? LokasiGudang::where('gudang_id', $this->gudang_tujuan_id)->where('status', 'AKTIF')->orderBy('kode')->get() : collect();

        return view('livewire.persediaan.pengalihan-barang-index', [
            'items' => $items,
            'countAktif' => $countAktif,
            'countOtorisasi' => $countOtorisasi,
            'kpiBerjalan' => $kpiBerjalan,
            'kpiMenungguTerima' => $kpiMenungguTerima,
            'kpiSelesaiBulanIni' => $kpiSelesaiBulanIni,
            'kpiTotalItem' => $kpiTotalItem,
            'daftarBarang' => $daftarBarang,
            'batchesAsal' => $batchesAsal,
            'gudangs' => $gudangs,
            'units' => $units,
            'raks' => $raks,
            'isGlobalAdmin' => $isGlobalAdmin,
            'isDestinationLocked' => ! $isGlobalAdmin && ($user?->gudang_id || $user?->unit_id),
            'namaLokasiTujuanUser' => $user?->gudang_id
                ? ($user->gudang?->nama ?? 'Gudang Anda')
                : ($user?->unit_id ? ($user->unit?->nama ?? 'Ruangan Anda') : 'Lokasi Terdaftar'),
        ])->layout('layouts.portal', [
            'portal' => $this->getPortalName(),
            'title' => 'Pengalihan & Mutasi Barang',
        ]);
    }
}
