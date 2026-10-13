<?php

namespace App\Livewire\Distribusi;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Livewire\Distribusi\Concerns\AuthorizesDistribusiScope;
use App\Livewire\Distribusi\Concerns\HasAksiAsetRuangan;
use App\Models\Aset\Aset;
use App\Models\Distribusi\StokRuangan;
use App\Models\Master\Unit;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

class StokRuanganIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal, WithFileUploads, AuthorizesDistribusiScope;
    use HasAksiAsetRuangan;

    #[Url(history: true)]
    public string $activeTab = 'bhp'; // 'bhp' atau 'aset'

    #[Url(history: true)]
    public string $filterKondisiAset = ''; // '', 'BAIK', 'RUSAK_RINGAN', 'RUSAK_BERAT'

    #[Url(history: true)]
    public string $filterStatusAset = ''; // '', 'DIGUNAKAN', 'TERSEDIA', 'DIPINJAM'

    public bool $showUbahStatusAsetModal = false;
    public ?int $selectedAsetId = null;
    public ?Aset $selectedAset = null;
    public string $statusAsetBaru = 'DIGUNAKAN';
    public string $kondisiAsetBaru = 'BAIK';

    // Modal Lapor Servis IPSRS langsung dari ruangan
    public bool $showLaporServisModal = false;
    public ?int $servisAsetId = null;
    public ?Aset $selectedAsetServis = null;
    public string $servisPrioritas = 'SEDANG';
    public string $servisDeskripsi = '';
    public $servisFoto = null;
    #[Url(history: true)]
    public string $filterKondisi = ''; // '', 'TERSEDIA', 'HABIS'

    #[Url(history: true)]
    public ?int $filterUnitId = null;

    public bool $showPakaiModal = false;
    public ?int $selectedStokId = null;
    public ?StokRuangan $selectedStok = null;

    public int $jumlah_pakai = 1;
    public string $keterangan = '';

    public function mount(): void
    {
        $user = auth()->user();
        if ($user?->unit_id) {
            $this->filterUnitId = $user->unit_id;
        } elseif (! $this->filterUnitId && ! (bool) $user?->gudang_id) {
            $this->filterUnitId = Unit::where('status', 'AKTIF')->orderBy('nama')->first()?->id;
        }
    }

    public function updatedFilterKondisi(): void
    {
        $this->resetPage();
    }

    public function updatedFilterUnitId(): void
    {
        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function updatedFilterKondisiAset(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatusAset(): void
    {
        $this->resetPage();
    }

    public function openCatatPemakaian(int $id): void
    {
        $stok = StokRuangan::with(['barang.satuan', 'unit'])->findOrFail($id);

        abort_unless($this->canUpdateStok(), 403, 'Anda tidak memiliki wewenang mencatat pemakaian persediaan ruangan.');
        abort_unless($this->ownsUnitStok($stok->unit_id), 403, 'Anda tidak memiliki wewenang mencatat pemakaian persediaan unit lain.');

        if ($stok->jumlah <= 0) {
            $this->toast("Persediaan barang '{$stok->barang?->nama}' sudah habis di ruangan.", 'warning');
            return;
        }

        $this->selectedStokId = $stok->id;
        $this->selectedStok = $stok;
        $this->jumlah_pakai = 1;
        $this->keterangan = '';
        $this->showPakaiModal = true;
    }

    public function simpanPemakaian(): void
    {
        $this->validate([
            'jumlah_pakai' => 'required|integer|min:1',
            'keterangan'   => 'required|string|max:500',
        ], [
            'jumlah_pakai.required' => 'Kuantitas pemakaian wajib diisi.',
            'jumlah_pakai.integer'  => 'Kuantitas harus berupa angka bulat.',
            'jumlah_pakai.min'      => 'Kuantitas pemakaian minimal 1.',
            'keterangan.required'   => 'Keterangan atau keperluan pemakaian wajib diisi.',
        ]);

        if (! $this->selectedStokId) {
            return;
        }

        $user = auth()->user();
        abort_unless($this->canUpdateStok(), 403, 'Anda tidak memiliki wewenang mencatat pemakaian persediaan ruangan.');

        try {
            DB::transaction(function () use ($user) {
                $stok = StokRuangan::lockForUpdate()->with(['barang.satuan', 'unit'])->findOrFail($this->selectedStokId);

                if (! $this->ownsUnitStok($stok->unit_id)) {
                    throw new \Exception('Anda tidak berwenang mencatat pemakaian persediaan ruangan lain.');
                }

                if ($this->jumlah_pakai > $stok->jumlah) {
                    throw new \Exception("Kuantitas pakai ({$this->jumlah_pakai}) melebihi sisa fisik di lemari ({$stok->jumlah}).");
                }

                $sisaBaru = max(0, $stok->jumlah - $this->jumlah_pakai);
                $stok->update(['jumlah' => $sisaBaru]);

                \App\Models\Audit\LogAudit::create([
                    'pengguna_id'     => $user?->id,
                    'peran_id'        => $user?->roles?->first()?->id,
                    'referensi_jenis' => 'PemakaianStokRuangan',
                    'referensi_id'    => $stok->id,
                    'aksi'            => 'PEMAKAIAN',
                    'data_sebelum'    => [
                        'jumlah' => $stok->jumlah,
                        'unit'   => $stok->unit?->nama,
                        'barang' => $stok->barang?->nama,
                    ],
                    'data_sesudah'    => [
                        'jumlah_pakai' => $this->jumlah_pakai,
                        'sisa'         => $sisaBaru,
                        'keterangan'   => trim($this->keterangan),
                        'unit'         => $stok->unit?->nama,
                        'barang'       => $stok->barang?->nama,
                    ],
                    'ip_address'      => request()?->ip() ?: '127.0.0.1',
                    'device'          => substr(request()?->userAgent() ?? 'Web Browser', 0, 255),
                    'dibuat_pada'     => now(),
                ]);
            });

            $namaBarang = $this->selectedStok?->barang?->nama ?? 'Barang';
            $satuan = $this->selectedStok?->barang?->satuan?->simbol ?? 'unit';
            $this->toast("Pemakaian {$this->jumlah_pakai} {$satuan} '{$namaBarang}' berhasil dicatat.", 'success');
        } catch (\Throwable $e) {
            $this->toast($e->getMessage(), 'danger');
            return;
        }

        $this->showPakaiModal = false;
        $this->selectedStokId = null;
        $this->selectedStok = null;
    }

    protected function canUpdateStok(): bool
    {
        return (bool) auth()->user()?->can('update_layanan_unit::stok::ruangan');
    }

    protected function canLaporServis(): bool
    {
        return (bool) auth()->user()?->can('create_layanan_unit::lapor::servis');
    }

    protected function ownsAset(Aset $aset): bool
    {
        $user = auth()->user();

        return $this->isGlobalOperator()
            || ($user?->unit_id && $aset->unit_id && (int) $user->unit_id === (int) $aset->unit_id)
            || ($user?->gudang_id && $aset->gudang_id && (int) $user->gudang_id === (int) $aset->gudang_id);
    }

    protected function ownsUnitStok(?int $unitId): bool
    {
        $user = auth()->user();

        return $this->isGlobalOperator() || ($user?->unit_id && (int) $user->unit_id === (int) $unitId);
    }

    public function render()
    {
        $user = auth()->user();
        $canViewRuangan = (bool) $user?->canAny(['view_any_layanan_unit::stok::ruangan', 'view_layanan_unit::stok::ruangan']);
        $canViewStokGudang = (bool) $user?->canAny(['view_any_gudang_stok::stok::batch', 'view_gudang_stok::stok::batch']);

        abort_unless(
            $canViewRuangan || ($canViewStokGudang && $user?->gudang_id),
            403,
            'Anda tidak memiliki akses ke halaman persediaan lokal ruangan.'
        );

        $isGlobalAdmin = $this->isGlobalOperator();
        $isWarehouseUser = ! $isGlobalAdmin && (bool) $user?->gudang_id;
        $isRoomUser = ! $isGlobalAdmin && (bool) $user?->unit_id;
        // Pembaca tanpa penempatan (auditor/manajemen) boleh memilih unit lewat permission view*
        $canPickUnit = $isGlobalAdmin || (! $isWarehouseUser && ! $isRoomUser);

        // Target unit query: selalu mengarah ke unit tertentu, tidak mencampur seluruh RS
        $effectiveUnitId = $isRoomUser
            ? $user->unit_id
            : ($this->filterUnitId ?: ($user?->unit_id ?: Unit::where('status', 'AKTIF')->orderBy('nama')->first()?->id));

        if (! $this->filterUnitId && ! $isWarehouseUser && $effectiveUnitId) {
            $this->filterUnitId = $effectiveUnitId;
        }

        // 1. Kueri Persediaan Barang
        if ($isWarehouseUser) {
            $baseQuery = \App\Models\Persediaan\StokBatch::query()
                ->where('gudang_id', $user->gudang_id);
        } elseif ($effectiveUnitId) {
            $baseQuery = StokRuangan::query()
                ->where('unit_id', $effectiveUnitId);
        } else {
            $baseQuery = StokRuangan::query()->whereRaw('1 = 0');
        }

        // Metrik Ringkasan
        $countTotalItem = (clone $baseQuery)->count();
        $countTersedia = (clone $baseQuery)->where('jumlah', '>', 0)->count();
        $countHabis = (clone $baseQuery)->where('jumlah', '<=', 0)->count();

        // Data List Barang
        $query = (clone $baseQuery)
            ->with($isWarehouseUser ? ['barang.satuan', 'barang.kategori', 'gudang'] : ['barang.satuan', 'barang.kategori', 'unit'])
            ->when($this->filterKondisi === 'TERSEDIA', fn ($q) => $q->where('jumlah', '>', 0))
            ->when($this->filterKondisi === 'HABIS', fn ($q) => $q->where('jumlah', '<=', 0))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->whereHas('barang', function ($b) {
                        $b->where('nama', 'ilike', "%{$this->search}%")
                            ->orWhere('kode', 'ilike', "%{$this->search}%");
                    });
                });
            });
        $daftarStok = $query
            ->orderBy($this->sortBy ?: ($isWarehouseUser ? 'barang_id' : 'id'), $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);

        // 2. Query Aset Fisik Ruangan / Fasilitas Gudang
        $baseAsetQuery = Aset::query()->where('status', '!=', 'DIHAPUSKAN');
        if ($isWarehouseUser) {
            $baseAsetQuery->where('gudang_id', $user->gudang_id)->whereNull('unit_id');
        } elseif ($effectiveUnitId) {
            $baseAsetQuery->where('unit_id', $effectiveUnitId)->whereNull('gudang_id');
        } else {
            $baseAsetQuery->whereRaw('1 = 0');
        }

        $countTotalAset = (clone $baseAsetQuery)->count();
        $countAsetBaik = (clone $baseAsetQuery)->where('kondisi', 'BAIK')->count();

        $asetQuery = (clone $baseAsetQuery)
            ->with(['barang.kategori', 'barang.satuan', 'unit', 'gudang'])
            ->when($this->filterKondisiAset, fn ($q) => $q->where('kondisi', $this->filterKondisiAset))
            ->when($this->filterStatusAset, fn ($q) => $q->where('status', $this->filterStatusAset))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('kode_aset', 'ilike', "%{$this->search}%")
                        ->orWhere('nomor_seri', 'ilike', "%{$this->search}%")
                        ->orWhereHas('barang', fn ($b) => $b->where('nama', 'ilike', "%{$this->search}%"));
                });
            });

        $daftarAset = $asetQuery
            ->orderBy($this->sortBy ?: 'kode_aset', $this->sortDirection === 'desc' ? 'desc' : 'asc')
            ->paginate($this->perPage);

        $daftarUnit = $canPickUnit ? Unit::where('status', 'AKTIF')->orderBy('nama')->get(['id', 'nama', 'kode']) : collect();

        $pageTitle = match (true) {
            $isWarehouseUser => "Persediaan Fasilitas Gudang (" . ($user->gudang?->nama ?? 'Gudang Anda') . ")",
            $isRoomUser => "Persediaan Lokal Ruangan (" . ($user->unit?->nama ?? 'Ruangan Anda') . ")",
            $canPickUnit && $this->filterUnitId => "Persediaan Ruangan (" . ($daftarUnit->firstWhere('id', $this->filterUnitId)?->nama ?? 'Unit Terpilih') . ")",
            default => 'Persediaan Fasilitas & Ruangan',
        };

        $pageSubtitle = $isWarehouseUser
            ? 'Monitoring fisik persediaan barang dan sarana inventaris yang berada di fasilitas gudang ini.'
            : 'Monitoring sisa fisik persediaan BHP dan sarana inventaris yang ditempatkan di ruangan.';

        return view('livewire.distribusi.stok-ruangan-index', [
            'activeTab'           => $this->activeTab,
            'daftarStok'          => $daftarStok,
            'daftarAset'          => $daftarAset,
            'daftarUnit'          => $daftarUnit,
            'isGlobalAdmin'       => $isGlobalAdmin,
            'canPickUnit'         => $canPickUnit,
            'canCatatPakai'       => $this->canUpdateStok() && ($isGlobalAdmin || $isRoomUser),
            'canUpdateStok'       => $this->canUpdateStok(),
            'canLaporServis'      => $this->canLaporServis(),
            'isWarehouseUser'     => $isWarehouseUser,
            'isRoomUser'          => $isRoomUser,
            'userUnitId'          => $user?->unit_id,
            'userGudangId'        => $user?->gudang_id,
            'countTotalItem'      => $countTotalItem,
            'countTersedia'       => $countTersedia,
            'countHabis'          => $countHabis,
            'countTotalAset'      => $countTotalAset,
            'countAsetBaik'       => $countAsetBaik,
            'sortBy'              => $this->sortBy,
            'sortDirection'       => $this->sortDirection,
            'selectedStok'        => $this->selectedStok,
            'selectedAset'        => $this->selectedAset,
            'pageTitle'           => $pageTitle,
            'pageSubtitle'        => $pageSubtitle,
        ])->layout('layouts.portal', [
            'portal' => $this->getPortalName(),
            'title'  => $pageTitle,
        ]);
    }
}
