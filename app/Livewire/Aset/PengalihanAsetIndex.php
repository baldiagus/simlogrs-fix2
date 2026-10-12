<?php

namespace App\Livewire\Aset;

use App\Enums\StatusAset;
use App\Enums\StatusPeminjaman;
use App\Enums\StatusPengalihan;
use App\Livewire\Concerns\AuthorizesPengalihan;
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
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal, AuthorizesPengalihan;

    protected function entitasPengalihan(): string
    {
        return 'layanan_unit::pengalihan::aset';
    }

    #[Url(history: true)]
    public string $activeTab = 'aktif'; // 'aktif' | 'riwayat'

    #[Url(history: true)]
    public string $filterStatus = '';

    #[Url(history: true)]
    public string $filterTipe = 'semua';


    // Modal Create State
    public bool $showCreateModal = false;
    public string $searchAsetModal = '';
    #[Url(history: true)]
    public ?int $filterTujuanUnitId = null;

    // Alur Pemilihan Komoditas & Penyedia (Sesuai Peminjaman Aset, Tanpa Tanggal Kembali)
    public ?int $selected_barang_id = null;
    public ?string $selected_penyedia_key = null;
    public string $searchPenyedia = '';
    public ?int $selected_aset_id = null;
    public int $quantity = 1;
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
        $this->activeTab = in_array($tab, ['aktif', 'otorisasi', 'riwayat'], true) ? $tab : 'aktif';
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $user = auth()->user();
        $isGlobalAdmin = (bool) ($user && $user->hasRole(['super_admin', 'admin_logistik']));
        abort_unless($user?->can('create_layanan_unit::pengalihan::aset') || $isGlobalAdmin, 403, 'Anda tidak memiliki wewenang untuk mengajukan pengalihan aset.');

        $this->resetValidation();
        $this->tanggal_pengalihan = now()->toDateString();
        $this->selected_barang_id = null;
        $this->selected_penyedia_key = null;
        $this->selected_aset_id = null;
        $this->quantity = 1;
        $this->searchPenyedia = '';
        $this->selected_aset_ids = [];
        $this->alasan = '';
        $this->catatan = '';
        // Default lokasi tujuan adalah ruangan / gudang milik pengguna login
        if ($user?->unit_id) {
            $this->tipe_tujuan = 'RUANGAN';
            $this->unit_tujuan_id = $user->unit_id;
            $this->gudang_tujuan_id = null;
        } elseif ($user?->gudang_id) {
            $this->tipe_tujuan = 'GUDANG';
            $this->gudang_tujuan_id = $user->gudang_id;
            $this->unit_tujuan_id = null;
        }

        $this->showCreateModal = true;
    }

    public function updatedSelectedBarangId(): void
    {
        $this->selected_penyedia_key = null;
        $this->selected_aset_id = null;
        $this->quantity = 1;
        $this->searchPenyedia = '';
        $this->selected_aset_ids = [];
    }
    public function updatedSelectedPenyediaKey(): void
    {
        if (! $this->selected_penyedia_key) {
            $this->selected_aset_ids = [];
            $this->selected_aset_id = null;
            return;
        }

        $providers = $this->getPenyediaAsetList();
        $provider = collect($providers)->firstWhere('key', $this->selected_penyedia_key);
        if ($provider) {
            $maxAvailable = $provider['jumlah'];
            if ($this->quantity > $maxAvailable) {
                $this->quantity = max(1, $maxAvailable);
            }
            $this->tipe_asal = $provider['tipe'];
            if ($provider['tipe'] === 'RUANGAN') {
                $this->unit_asal_id = $provider['id'];
                $this->gudang_asal_id = null;
            } else {
                $this->gudang_asal_id = $provider['id'];
                $this->unit_asal_id = null;
            }
            $this->selected_aset_ids = array_slice($provider['aset_ids'], 0, max(1, (int) $this->quantity));
            $this->selected_aset_id = $provider['aset_ids'][0] ?? null;
        } else {
            $this->selected_aset_ids = [];
            $this->selected_aset_id = null;
        }
    }

    public function updatedQuantity($value): void
    {
        $max = $this->maxQuantity;
        if ((int) $value > $max) {
            $this->quantity = $max;
            $this->addError('quantity', "Jumlah unit melebihi stok tersedia (maksimal {$max} unit).");
        } elseif ((int) $value < 1) {
            $this->quantity = 1;
            $this->resetErrorBag('quantity');
        } else {
            $this->resetErrorBag('quantity');
        }

        if ($this->selected_penyedia_key) {
            $providers = $this->getPenyediaAsetList();
            $provider = collect($providers)->firstWhere('key', $this->selected_penyedia_key);
            if ($provider) {
                $qty = max(1, min((int) $this->quantity, count($provider['aset_ids'])));
                $this->selected_aset_ids = array_slice($provider['aset_ids'], 0, $qty);
            }
        }
    }

    public function getMaxQuantityProperty(): int
    {
        if (! $this->selected_penyedia_key) {
            return 1;
        }

        $providers = $this->getPenyediaAsetList();
        $provider = collect($providers)->firstWhere('key', $this->selected_penyedia_key);

        return $provider ? max(1, $provider['jumlah']) : 1;
    }

    public function getDaftarBarangAsetTersedia()
    {
        $user = auth()->user();
        $isGlobalAdmin = (bool) ($user && $user->hasRole(['super_admin', 'admin_logistik']));
        $ownUnitId = ! $isGlobalAdmin ? $user?->unit_id : null;
        $ownGudangId = ! $isGlobalAdmin ? $user?->gudang_id : null;

        $constraint = function ($q) use ($ownUnitId, $ownGudangId) {
            $q->whereIn('status', StatusAset::dapatDialihkan())
                ->whereNotIn('kondisi', ['RUSAK_BERAT', 'DIHAPUSKAN'])
                ->whereDoesntHave('permintaanMaintenance', function ($sub) {
                    $sub->whereNotIn('status', ['SELESAI', 'DIBATALKAN']);
                })
                ->whereDoesntHave('peminjamanDetail.permintaanPeminjaman', function ($sub) {
                    $sub->whereIn('status', StatusPeminjaman::aktif());
                })
                ->when($ownUnitId, fn ($sub) => $sub->where(fn ($sq) => $sq->where('unit_id', '!=', $ownUnitId)->orWhereNull('unit_id')))
                ->when($ownGudangId, fn ($sub) => $sub->where(fn ($sq) => $sq->where('gudang_id', '!=', $ownGudangId)->orWhereNull('gudang_id')));
        };

        return \App\Models\Barang::where('status', 'AKTIF')
            ->whereHas('aset', $constraint)
            ->withCount(['aset' => $constraint])
            ->with('satuan')
            ->orderBy('nama')
            ->get();
    }

    public function getPenyediaAsetList(): array
    {
        if (! $this->selected_barang_id) {
            return [];
        }

        $user = auth()->user();
        $isGlobalAdmin = (bool) ($user && $user->hasRole(['super_admin', 'admin_logistik']));
        $ownUnitId = ! $isGlobalAdmin ? $user?->unit_id : null;
        $ownGudangId = ! $isGlobalAdmin ? $user?->gudang_id : null;
        $userUnitId = $user?->unit_id;
        $userGudangId = $user?->gudang_id;

        $assets = Aset::where('barang_id', $this->selected_barang_id)
            ->whereIn('status', StatusAset::dapatDialihkan())
            ->whereNotIn('kondisi', ['RUSAK_BERAT', 'DIHAPUSKAN'])
            ->whereDoesntHave('permintaanMaintenance', function ($sub) {
                $sub->whereNotIn('status', ['SELESAI', 'DIBATALKAN']);
            })
            ->whereDoesntHave('peminjamanDetail.permintaanPeminjaman', function ($sub) {
                $sub->whereIn('status', StatusPeminjaman::aktif());
            })
            ->when($ownUnitId, fn ($sub) => $sub->where(fn ($sq) => $sq->where('unit_id', '!=', $ownUnitId)->orWhereNull('unit_id')))
            ->when($ownGudangId, fn ($sub) => $sub->where(fn ($sq) => $sq->where('gudang_id', '!=', $ownGudangId)->orWhereNull('gudang_id')))
            ->with(['unit', 'gudang'])
            ->get();
        $providers = [];

        // Kelompokkan penyedia ruangan unit
        $units = $assets->whereNotNull('unit_id')->groupBy('unit_id');
        foreach ($units as $unitId => $group) {
            $unit = $group->first()->unit;
            if (! $unit || $unit->status !== 'AKTIF') continue;

            $isOwnUnit = $userUnitId && (int) $userUnitId === (int) $unitId;

            $providers[] = [
                'key'        => "RUANGAN_{$unitId}",
                'tipe'       => 'RUANGAN',
                'id'         => $unitId,
                'nama'       => $unit->nama,
                'kode'       => $unit->kode,
                'jumlah'     => $group->count(),
                'is_own'     => $isOwnUnit,
                'aset_ids'   => $group->pluck('id')->toArray(),
                'kode_asets' => $group->pluck('kode_aset')->implode(', '),
            ];
        }

        // Kelompokkan penyedia fasilitas gudang
        $gudangs = $assets->whereNotNull('gudang_id')->groupBy('gudang_id');
        foreach ($gudangs as $gudangId => $group) {
            $gudang = $group->first()->gudang;
            if (! $gudang || $gudang->status !== 'AKTIF') continue;

            $isOwnGudang = $userGudangId && (int) $userGudangId === (int) $gudangId;

            $providers[] = [
                'key'        => "GUDANG_{$gudangId}",
                'tipe'       => 'GUDANG',
                'id'         => $gudangId,
                'nama'       => $gudang->nama,
                'kode'       => $gudang->kode,
                'jumlah'     => $group->count(),
                'is_own'     => $isOwnGudang,
                'aset_ids'   => $group->pluck('id')->toArray(),
                'kode_asets' => $group->pluck('kode_aset')->implode(', '),
            ];
        }
        if ($this->searchPenyedia) {
            $term = strtolower(trim($this->searchPenyedia));
            $providers = array_values(array_filter($providers, function ($p) use ($term) {
                return str_contains(strtolower($p['nama']), $term) ||
                       str_contains(strtolower($p['kode']), $term);
            }));
        }

        return $providers;
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
        $isGlobalAdmin = (bool) ($user && $user->hasRole(['super_admin', 'admin_logistik']));
        abort_unless($user?->can('create_layanan_unit::pengalihan::aset') || $isGlobalAdmin, 403);

        // Jika menggunakan alur pemilihan penyedia aset (seperti peminjaman aset)
        if ($this->selected_penyedia_key) {
            $providers = $this->getPenyediaAsetList();
            $provider = collect($providers)->firstWhere('key', $this->selected_penyedia_key);
            if (! $provider) {
                $this->addError('selected_penyedia_key', 'Lokasi penyedia aset tidak valid.');
                return;
            }

            $maxAvailable = count($provider['aset_ids']);
            $this->validate([
                'quantity' => "required|integer|min:1|max:{$maxAvailable}",
            ], [
                'quantity.required' => 'Jumlah unit aset yang dialihkan wajib diisi.',
                'quantity.min'      => 'Jumlah unit minimal 1 unit.',
                'quantity.max'      => "Jumlah unit melebihi stok tersedia (maksimal {$maxAvailable} unit).",
            ]);

            $this->selected_aset_ids = array_slice($provider['aset_ids'], 0, (int) $this->quantity);
            $this->tipe_asal = $provider['tipe'];
            if ($provider['tipe'] === 'RUANGAN') {
                $this->unit_asal_id = $provider['id'];
                $this->gudang_asal_id = null;
            } else {
                $this->gudang_asal_id = $provider['id'];
                $this->unit_asal_id = null;
            }

            if (! $isGlobalAdmin) {
                if ($user?->unit_id) {
                    $this->tipe_tujuan = 'RUANGAN';
                    $this->unit_tujuan_id = $user->unit_id;
                    $this->gudang_tujuan_id = null;
                } elseif ($user?->gudang_id) {
                    $this->tipe_tujuan = 'GUDANG';
                    $this->gudang_tujuan_id = $user->gudang_id;
                    $this->unit_tujuan_id = null;
                }
            }
        } else {
            // Proteksi fallback jika menggunakan form manual
            if (! $isGlobalAdmin) {
                if ($user?->unit_id) {
                    $this->tipe_asal = 'RUANGAN';
                    $this->unit_asal_id = $user->unit_id;
                    $this->gudang_asal_id = null;
                } elseif ($user?->gudang_id) {
                    $this->tipe_asal = 'GUDANG';
                    $this->gudang_asal_id = $user->gudang_id;
                    $this->unit_asal_id = null;
                }
            }
        }
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
        $transfer = PengalihanAset::findOrFail($this->actionTransferId);

        // 1. Anti Self-Approval Guard
        abort_if(
            (int) $transfer->diajukan_oleh === (int) $user?->id,
            403,
            'Konflik Kepentingan: Anda tidak dapat menyetujui atau menolak permohonan pengalihan yang Anda buat sendiri.'
        );

        // 2. Wewenang Fasilitas Asal Pemilik Aset
        $isGlobalAdmin = (bool) ($user && $user->hasRole(['super_admin', 'admin_logistik']));
        $isOriginOwner = ($transfer->tipe_asal === 'RUANGAN' && $user?->unit_id && (int) $user->unit_id === (int) $transfer->unit_asal_id && $user->hasRole('kepala_unit') && $user->can('update_layanan_unit::pengalihan::aset'))
            || ($transfer->tipe_asal === 'GUDANG' && $user?->gudang_id && (int) $user->gudang_id === (int) $transfer->gudang_asal_id && $user->hasRole('kepala_gudang') && $user->can('update_layanan_unit::pengalihan::aset'));

        abort_unless(
            $isGlobalAdmin || $isOriginOwner,
            403,
            'Hanya fasilitas asal pemilik aset yang berhak melakukan otorisasi pelepasan aset.'
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
        $this->authorizePengalihan($transfer, 'serah_terima', 'Hanya fasilitas tujuan yang berhak mengonfirmasi serah terima fisik aset.');

        abort_unless(
            $transfer->status === StatusPengalihan::Disetujui->value,
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
        $this->authorizePengalihan(PengalihanAset::findOrFail($this->transferTerima?->id), 'serah_terima', 'Hanya fasilitas tujuan yang berhak mengonfirmasi serah terima fisik aset.');

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
        $this->authorizePengalihan(PengalihanAset::findOrFail($id), 'batal', 'Hanya pengaju dengan izin pembatalan yang berhak membatalkan pengalihan aset ini.');

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
        $isGlobalAdmin = (bool) ($user && $user->hasRole(['super_admin', 'admin_logistik']));

        abort_unless(
            $user && (
                $user->can('view_any_layanan_unit::pengalihan::aset') ||
                $user->can('view_layanan_unit::pengalihan::aset') ||
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

        // 1. Tab Aktif: Pengajuan milik unit/gudang pengguna (pemohon / penerima)
        if ($this->activeTab === 'aktif') {
            if (! $isGlobalAdmin) {
                $query->where(function ($q) use ($user) {
                    if ($user?->unit_id) {
                        $q->where(fn ($sub) => $sub->where('tipe_tujuan', 'RUANGAN')->where('unit_tujuan_id', $user->unit_id))
                          ->orWhere('diajukan_oleh', $user->id);
                    } elseif ($user?->gudang_id) {
                        $q->where(fn ($sub) => $sub->where('tipe_tujuan', 'GUDANG')->where('gudang_tujuan_id', $user->gudang_id))
                          ->orWhere('diajukan_oleh', $user->id);
                    }
                });
            }
            $query->whereIn('status', StatusPengalihan::aktif());
        }
        // 2. Tab Otorisasi: Pengajuan dari unit/gudang LAIN yang meminta aset dari fasilitas pengguna
        elseif ($this->activeTab === 'otorisasi') {
            if (! $isGlobalAdmin) {
                $query->where(function ($q) use ($user) {
                    if ($user?->unit_id) {
                        $q->where('tipe_asal', 'RUANGAN')
                          ->where('unit_asal_id', $user->unit_id)
                          ->where(function ($sub) use ($user) {
                              $sub->where('tipe_tujuan', '!=', 'RUANGAN')
                                  ->orWhere('unit_tujuan_id', '!=', $user->unit_id);
                          });
                    } elseif ($user?->gudang_id) {
                        $q->where('tipe_asal', 'GUDANG')
                          ->where('gudang_asal_id', $user->gudang_id)
                          ->where(function ($sub) use ($user) {
                              $sub->where('tipe_tujuan', '!=', 'GUDANG')
                                  ->orWhere('gudang_tujuan_id', '!=', $user->gudang_id);
                          });
                    }
                });
            }
            $query->where('status', StatusPengalihan::Diajukan->value);
        }
        // 3. Tab Riwayat: Selesai, Ditolak, Dibatalkan
        else {
            if (! $isGlobalAdmin) {
                $query->where(function ($q) use ($user) {
                    if ($user?->unit_id) {
                        $q->where(fn ($sub) => $sub->where('tipe_asal', 'RUANGAN')->where('unit_asal_id', $user->unit_id))
                          ->orWhere(fn ($sub) => $sub->where('tipe_tujuan', 'RUANGAN')->where('unit_tujuan_id', $user->unit_id));
                    } elseif ($user?->gudang_id) {
                        $q->where(fn ($sub) => $sub->where('tipe_asal', 'GUDANG')->where('gudang_asal_id', $user->gudang_id))
                          ->orWhere(fn ($sub) => $sub->where('tipe_tujuan', 'GUDANG')->where('gudang_tujuan_id', $user->gudang_id));
                    }
                });
            }
            $query->whereIn('status', StatusPengalihan::riwayat());
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

        // Filter Tujuan di main toolbar
        if ($this->filterTujuanUnitId) {
            $query->where('tipe_tujuan', 'RUANGAN')->where('unit_tujuan_id', $this->filterTujuanUnitId);
        }

        $items = $query->orderBy($this->getValidatedSortColumn(['kode', 'status', 'tanggal_pengalihan']), $this->getValidatedSortDirection())->paginate($this->perPage);

        // Hitungan Tab & KPI yang disinkronkan ke fasilitas pengguna
        $countAktifQuery = PengalihanAset::whereIn('status', StatusPengalihan::aktif());
        if (! $isGlobalAdmin) {
            $countAktifQuery->where(function ($q) use ($user) {
                if ($user?->unit_id) {
                    $q->where(fn ($sub) => $sub->where('tipe_tujuan', 'RUANGAN')->where('unit_tujuan_id', $user->unit_id))
                      ->orWhere('diajukan_oleh', $user->id);
                } elseif ($user?->gudang_id) {
                    $q->where(fn ($sub) => $sub->where('tipe_tujuan', 'GUDANG')->where('gudang_tujuan_id', $user->gudang_id))
                      ->orWhere('diajukan_oleh', $user->id);
                }
            });
        }
        $countAktif = $countAktifQuery->count();

        $countOtorisasiQuery = PengalihanAset::where('status', StatusPengalihan::Diajukan->value);
        if (! $isGlobalAdmin) {
            $countOtorisasiQuery->where(function ($q) use ($user) {
                if ($user?->unit_id) {
                    $q->where('tipe_asal', 'RUANGAN')
                      ->where('unit_asal_id', $user->unit_id)
                      ->where(function ($sub) use ($user) {
                          $sub->where('tipe_tujuan', '!=', 'RUANGAN')
                              ->orWhere('unit_tujuan_id', '!=', $user->unit_id);
                      });
                } elseif ($user?->gudang_id) {
                    $q->where('tipe_asal', 'GUDANG')
                      ->where('gudang_asal_id', $user->gudang_id)
                      ->where(function ($sub) use ($user) {
                          $sub->where('tipe_tujuan', '!=', 'GUDANG')
                              ->orWhere('gudang_tujuan_id', '!=', $user->gudang_id);
                      });
                }
            });
        }
        $countOtorisasi = $countOtorisasiQuery->count();

        $kpiBerjalan = $countAktif;
        $kpiMenungguTerima = (clone $countAktifQuery)->where('status', 'DISETUJUI')->count();

        $riwayatQuery = PengalihanAset::where('status', 'SELESAI');
        if (! $isGlobalAdmin) {
            $riwayatQuery->where(function ($q) use ($user) {
                if ($user?->unit_id) {
                    $q->where(fn ($sub) => $sub->where('tipe_asal', 'RUANGAN')->where('unit_asal_id', $user->unit_id))
                      ->orWhere(fn ($sub) => $sub->where('tipe_tujuan', 'RUANGAN')->where('unit_tujuan_id', $user->unit_id));
                } elseif ($user?->gudang_id) {
                    $q->where(fn ($sub) => $sub->where('tipe_asal', 'GUDANG')->where('gudang_asal_id', $user->gudang_id))
                      ->orWhere(fn ($sub) => $sub->where('tipe_tujuan', 'GUDANG')->where('gudang_tujuan_id', $user->gudang_id));
                }
            });
        }
        $kpiSelesaiBulanIni = (clone $riwayatQuery)
            ->whereMonth('tanggal_serah_terima', now()->month)
            ->whereYear('tanggal_serah_terima', now()->year)
            ->count();

        $kpiTotalAset = \App\Models\PengalihanAsetDetail::whereHas('pengalihanAset', function ($q) use ($isGlobalAdmin, $user) {
            $q->where('status', 'SELESAI');
            if (! $isGlobalAdmin) {
                $q->where(function ($sub) use ($user) {
                    if ($user?->unit_id) {
                        $sub->where(fn ($s) => $s->where('tipe_asal', 'RUANGAN')->where('unit_asal_id', $user->unit_id))
                            ->orWhere(fn ($s) => $s->where('tipe_tujuan', 'RUANGAN')->where('unit_tujuan_id', $user->unit_id));
                    } elseif ($user?->gudang_id) {
                        $sub->where(fn ($s) => $s->where('tipe_asal', 'GUDANG')->where('gudang_asal_id', $user->gudang_id))
                            ->orWhere(fn ($s) => $s->where('tipe_tujuan', 'GUDANG')->where('gudang_tujuan_id', $user->gudang_id));
                    }
                });
            }
        })->count();
        $isOriginLocked = ! $isGlobalAdmin && ($user?->unit_id || $user?->gudang_id);
        $namaLokasiAsalUser = $user?->unit_id
            ? ($user->unit?->nama ?? 'Ruangan Anda')
            : ($user?->gudang_id ? ($user->gudang?->nama ?? 'Gudang Anda') : 'Lokasi Terdaftar');

        // Daftar Aset yang dapat dipilih untuk form create sesuai lokasi asal
        $asetTersedia = collect();
        if ($this->showCreateModal) {
            $asetQuery = Aset::with('barang')
                ->whereIn('status', StatusAset::dapatDialihkan())
                ->whereNotIn('kondisi', ['RUSAK_BERAT', 'DIHAPUSKAN']);

            if ($this->tipe_asal === 'RUANGAN' && $this->unit_asal_id) {
                $asetQuery->where('unit_id', $this->unit_asal_id);
            } elseif ($this->tipe_asal === 'GUDANG' && $this->gudang_asal_id) {
                $asetQuery->where('gudang_id', $this->gudang_asal_id);
            } else {
                $asetQuery->whereRaw('1=0');
            }

            if ($this->searchAsetModal) {
                $asetQuery->where(function ($q) {
                    $q->where('kode_aset', 'ilike', "%{$this->searchAsetModal}%")
                      ->orWhere('nomor_seri', 'ilike', "%{$this->searchAsetModal}%")
                      ->orWhereHas('barang', fn ($b) => $b->where('nama', 'ilike', "%{$this->searchAsetModal}%"));
                });
            }

            $asetTersedia = $asetQuery->orderBy('kode_aset')->get();
        }

        $units = Unit::where('status', 'AKTIF')->orderBy('nama')->get();
        $gudangs = Gudang::where('status', 'AKTIF')->orderBy('nama')->get();
        $raks = $this->gudang_tujuan_id ? LokasiGudang::where('gudang_id', $this->gudang_tujuan_id)->where('status', 'AKTIF')->orderBy('kode')->get() : collect();

        $daftarBarangAsetTersedia = $this->getDaftarBarangAsetTersedia();
        $daftarPenyediaAset = $this->getPenyediaAsetList();

        return view('livewire.aset.pengalihan-aset-index', [
            'items' => $items,
            'countAktif' => $countAktif,
            'countOtorisasi' => $countOtorisasi,
            'kpiBerjalan' => $kpiBerjalan,
            'kpiMenungguTerima' => $kpiMenungguTerima,
            'kpiSelesaiBulanIni' => $kpiSelesaiBulanIni,
            'kpiTotalAset' => $kpiTotalAset,
            'asetTersedia' => $asetTersedia,
            'daftarBarangAsetTersedia' => $daftarBarangAsetTersedia,
            'daftarPenyediaAset' => $daftarPenyediaAset,
            'units' => $units,
            'gudangs' => $gudangs,
            'raks' => $raks,
            'isGlobalAdmin' => $isGlobalAdmin,
            'izin' => $this->izinPengalihan($items),
            'isOriginLocked' => $isOriginLocked,
            'namaLokasiAsalUser' => $namaLokasiAsalUser,
        ])->layout('layouts.portal', [
            'portal' => $this->getPortalName(),
            'title' => 'Pengalihan & Mutasi Aset',
        ]);
    }
}
