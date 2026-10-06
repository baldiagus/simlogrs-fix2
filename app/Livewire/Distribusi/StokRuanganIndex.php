<?php

namespace App\Livewire\Distribusi;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Aset;
use App\Models\StokRuangan;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

class StokRuanganIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal, WithFileUploads;

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

    public function openUbahStatusAset(int $asetId): void
    {
        $aset = Aset::with(['barang', 'unit'])->findOrFail($asetId);
        $user = auth()->user();

        $canUpdate = $user && (
            $user->can('update_layanan_unit::stok::ruangan') ||
            $user->can('page_Dashboard') ||
            $user->can('page_AdminLogistikDashboard') ||
            $user->hasRole(['super_admin', 'admin_logistik', 'kepala_unit', 'kepala_gudang', 'petugas_gudang'])
        );
        abort_unless($canUpdate, 403, 'Anda tidak memiliki wewenang mengubah status aset operasional.');

        $isGlobalAdmin = $user && ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard') || $user->hasRole(['super_admin', 'admin_logistik']));
        $isOwner = ($user?->unit_id && (int) $user->unit_id === (int) $aset->unit_id)
                || ($user?->gudang_id && (int) $user->gudang_id === (int) $aset->gudang_id);

        if (! $isGlobalAdmin && ! $isOwner) {
            abort(403, 'Anda tidak berwenang mengubah aset milik fasilitas lain.');
        }
        if (in_array($aset->status, ['DIPINJAM', 'DALAM_MAINTENANCE', 'DIHAPUSKAN'])) {
            abort(403, "Status aset '{$aset->kode_aset}' ({$aset->status}) tidak dapat diubah secara manual di ruangan.");
        }

        $this->selectedAsetId = $aset->id;
        $this->selectedAset = $aset;
        $this->statusAsetBaru = in_array($aset->status, ['DIGUNAKAN', 'TERSEDIA']) ? $aset->status : 'DIGUNAKAN';
        $this->kondisiAsetBaru = $aset->kondisi;
        $this->showUbahStatusAsetModal = true;
    }

    public function simpanStatusAset(): void
    {
        $this->validate([
            'statusAsetBaru'  => 'required|in:DIGUNAKAN,TERSEDIA',
            'kondisiAsetBaru' => 'required|in:BAIK,RUSAK_RINGAN,RUSAK_BERAT',
        ], [
            'statusAsetBaru.required'  => 'Status aset operasional wajib ditentukan.',
            'kondisiAsetBaru.required' => 'Kondisi fisik aset wajib dinilai.',
        ]);

        if (! $this->selectedAsetId) {
            return;
        }

        $user = auth()->user();
        $isGlobalAdmin = $user && ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard') || $user->hasRole(['super_admin', 'admin_logistik']));

        $aset = Aset::findOrFail($this->selectedAsetId);
        $isOwner = ($user?->unit_id && (int) $user->unit_id === (int) $aset->unit_id)
                || ($user?->gudang_id && (int) $user->gudang_id === (int) $aset->gudang_id);

        if (! $isGlobalAdmin && ! $isOwner) {
            abort(403, 'Anda tidak berwenang mengubah aset milik fasilitas lain.');
        }
        if (in_array($aset->status, ['DIPINJAM', 'DALAM_MAINTENANCE', 'DIHAPUSKAN'])) {
            abort(403, "Status aset '{$aset->kode_aset}' ({$aset->status}) tidak dapat diubah secara manual di ruangan.");
        }

        $aset->update([
            'status'  => $this->statusAsetBaru,
            'kondisi' => $this->kondisiAsetBaru,
        ]);

        $this->toast("Status aset '{$aset->kode_aset}' berhasil diperbarui menjadi {$this->statusAsetBaru}.", 'success');

        $this->showUbahStatusAsetModal = false;
        $this->selectedAsetId = null;
        $this->selectedAset = null;
    }

    public function openLaporServis(int $asetId): void
    {
        $user = auth()->user();
        abort_unless(
            $user && (
                $user->can('create_layanan_unit::lapor::servis') ||
                $user->can('update_layanan_unit::stok::ruangan') ||
                $user->can('view_any_layanan_unit::stok::ruangan') ||
                $user->can('page_Dashboard') ||
                $user->can('page_AdminLogistikDashboard') ||
                $user->hasRole(['super_admin', 'admin_logistik', 'kepala_unit', 'petugas_unit', 'kepala_gudang', 'petugas_gudang'])
            ),
            403,
            'Anda tidak memiliki wewenang untuk melaporkan kerusakan sarana.'
        );

        $aset = Aset::with(['barang', 'unit', 'gudang'])->findOrFail($asetId);

        $isGlobalAdmin = $user && ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard') || $user->hasRole(['super_admin', 'admin_logistik']));
        $isOwner = ($user?->unit_id && (int) $user->unit_id === (int) $aset->unit_id)
                || ($user?->gudang_id && (int) $user->gudang_id === (int) $aset->gudang_id);

        if (! $isGlobalAdmin && ! $isOwner) {
            abort(403, 'Anda tidak berwenang melaporkan kerusakan aset milik fasilitas lain.');
        }

        $tiketAktif = \App\Models\PermintaanMaintenance::where('aset_id', $aset->id)
            ->whereIn('status', ['DILAPORKAN', 'DISETUJUI', 'DALAM_PENGERJAAN'])
            ->latest('id')
            ->first();

        if (in_array($aset->status, ['DALAM_MAINTENANCE', 'DIHAPUSKAN']) || $tiketAktif) {
            $kodeTiket = $tiketAktif?->kode ?? 'aktif';
            $this->toast("Aset '{$aset->kode_aset}' saat ini sudah dalam penanganan perbaikan teknisi IPSRS (Status: {$aset->status}, Tiket: {$kodeTiket}).", 'warning');
            return;
        }

        $this->servisAsetId = $aset->id;
        $this->selectedAsetServis = $aset;
        $this->servisPrioritas = 'SEDANG';
        $this->servisDeskripsi = '';
        $this->servisFoto = null;
        $this->showLaporServisModal = true;
    }

    public function simpanLaporServis(): void
    {
        $this->validate([
            'servisPrioritas' => 'required|in:RENDAH,SEDANG,TINGGI,CITO',
            'servisDeskripsi' => 'required|string|min:5',
            'servisFoto'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
        ], [
            'servisPrioritas.required' => 'Prioritas penanganan wajib dipilih.',
            'servisDeskripsi.required' => 'Deskripsi keluhan atau kerusakan sarana wajib dijelaskan.',
            'servisDeskripsi.min'      => 'Deskripsi kerusakan minimal 5 karakter.',
            'servisFoto.image'         => 'Berkas harus berupa foto gambar.',
            'servisFoto.max'           => 'Ukuran foto maksimal adalah 10 MB.',
        ]);

        if (! $this->servisAsetId) {
            return;
        }

        $user = auth()->user();
        $aset = Aset::with(['barang', 'unit'])->findOrFail($this->servisAsetId);

        $isGlobalAdmin = $user && ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard') || $user->hasRole(['super_admin', 'admin_logistik']));
        if (! $isGlobalAdmin && (int) $user?->unit_id !== (int) $aset->unit_id) {
            abort(403, 'Anda tidak berwenang melaporkan kerusakan aset milik unit lain.');
        }

        $fotoPath = null;
        if ($this->servisFoto) {
            $opt = \App\Support\File\FileOptimizer::optimizeAndStore($this->servisFoto, 'maintenance/kerusakan');
            $fotoPath = $opt['path'];
        }

        $kode = \App\Support\Generator\CodeGenerator::generateMnt();

        \Illuminate\Support\Facades\DB::transaction(function () use ($kode, $aset, $user, $fotoPath) {
            \App\Models\PermintaanMaintenance::create([
                'kode'              => $kode,
                'aset_id'           => $aset->id,
                'unit_id'           => $aset->unit_id ?: $user->unit_id,
                'dilaporkan_oleh'   => $user->id,
                'deskripsi_masalah' => $this->servisDeskripsi,
                'foto_kerusakan'    => $fotoPath,
                'prioritas'         => $this->servisPrioritas,
                'status'            => 'DILAPORKAN',
            ]);

            $kondisiAset = match ($this->servisPrioritas) {
                'CITO'   => 'RUSAK_BERAT',
                'TINGGI' => 'RUSAK_RINGAN',
                default  => in_array($aset->kondisi, ['RUSAK_RINGAN', 'RUSAK_BERAT']) ? $aset->kondisi : 'RUSAK_RINGAN',
            };

            $aset->update([
                'status'  => 'DALAM_MAINTENANCE',
                'kondisi' => $kondisiAset,
            ]);
        });

        $this->showLaporServisModal = false;
        $this->reset(['servisAsetId', 'selectedAsetServis', 'servisPrioritas', 'servisDeskripsi', 'servisFoto']);
        $this->toast("Laporan kerusakan '{$kode}' untuk {$aset->kode_aset} berhasil dikirim ke IPSRS.", 'success');
    }

    public function batalLaporServis(): void
    {
        $this->showLaporServisModal = false;
        $this->reset(['servisAsetId', 'selectedAsetServis', 'servisPrioritas', 'servisDeskripsi', 'servisFoto']);
    }

    public function hapusFotoServis(): void
    {
        $this->servisFoto = null;
    }

    public function openCatatPemakaian(int $id): void
    {
        $stok = StokRuangan::with(['barang.satuan', 'unit'])->findOrFail($id);

        $user = auth()->user();
        $isGlobalAdmin = $user && ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard'));

        if (! $isGlobalAdmin && (int) $user?->unit_id !== (int) $stok->unit_id) {
            abort(403, 'Anda tidak memiliki wewenang mencatat pemakaian persediaan unit lain.');
        }

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
        $isGlobalAdmin = $user && ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard'));

        try {
            DB::transaction(function () use ($user, $isGlobalAdmin) {
                $stok = StokRuangan::lockForUpdate()->with(['barang.satuan', 'unit'])->findOrFail($this->selectedStokId);

                if (! $isGlobalAdmin && (int) $user?->unit_id !== (int) $stok->unit_id) {
                    throw new \Exception('Anda tidak berwenang mencatat pemakaian persediaan ruangan lain.');
                }

                if ($this->jumlah_pakai > $stok->jumlah) {
                    throw new \Exception("Kuantitas pakai ({$this->jumlah_pakai}) melebihi sisa fisik di lemari ({$stok->jumlah}).");
                }

                $sisaBaru = max(0, $stok->jumlah - $this->jumlah_pakai);
                $stok->update(['jumlah' => $sisaBaru]);

                \App\Models\LogAudit::create([
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

    public function render()
    {
        abort_unless(
            auth()->user()?->can('page_StokRuanganIndex') ||
            auth()->user()?->can('view_any_layanan_unit::stok::ruangan') ||
            auth()->user()?->can('view_layanan_unit::stok::ruangan') ||
            auth()->user()?->can('view_any_gudang_stok::stok::batch') ||
            auth()->user()?->can('page_Dashboard') ||
            auth()->user()?->can('page_AdminLogistikDashboard') ||
            auth()->user()?->hasRole(['super_admin', 'admin_logistik', 'kepala_gudang', 'petugas_gudang']),
            403,
            'Anda tidak memiliki akses ke halaman persediaan lokal ruangan.'
        );

        $user = auth()->user();
        $isGlobalAdmin = $user && ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard'));
        $isWarehouseUser = ! $isGlobalAdmin && (bool) $user?->gudang_id;
        $isRoomUser = ! $isGlobalAdmin && (bool) $user?->unit_id;

        // Target unit query
        $effectiveUnitId = $isGlobalAdmin ? $this->filterUnitId : $user?->unit_id;

        // 1. Kueri Persediaan Barang
        if ($isWarehouseUser) {
            $baseQuery = \App\Models\StokBatch::query()
                ->where('gudang_id', $user->gudang_id);
        } elseif ($isRoomUser) {
            $baseQuery = StokRuangan::query()
                ->where('unit_id', $user->unit_id);
        } elseif ($isGlobalAdmin) {
            $baseQuery = StokRuangan::query()
                ->when($this->filterUnitId, fn ($q) => $q->where('unit_id', $this->filterUnitId));
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
        $baseAsetQuery = Aset::query();
        if ($isWarehouseUser) {
            $baseAsetQuery->where('gudang_id', $user->gudang_id);
        } elseif ($isRoomUser) {
            $baseAsetQuery->where('unit_id', $user->unit_id);
        } elseif ($isGlobalAdmin) {
            $baseAsetQuery->when($this->filterUnitId, fn ($q) => $q->where('unit_id', $this->filterUnitId));
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

        $daftarUnit = $isGlobalAdmin ? Unit::where('status', 'AKTIF')->orderBy('nama')->get(['id', 'nama', 'kode']) : collect();

        $pageTitle = match (true) {
            $isWarehouseUser => "Persediaan Fasilitas Gudang (" . ($user->gudang?->nama ?? 'Gudang Anda') . ")",
            $isRoomUser => "Persediaan Lokal Ruangan (" . ($user->unit?->nama ?? 'Ruangan Anda') . ")",
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
