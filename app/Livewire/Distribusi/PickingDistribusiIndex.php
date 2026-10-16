<?php

namespace App\Livewire\Distribusi;

use App\Enums\Distribusi\StatusPermintaanDistribusi;
use App\Enums\Distribusi\StatusPengirimanBarang;
use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Livewire\Distribusi\Concerns\AuthorizesDistribusiScope;
use App\Models\Master\Gudang;
use App\Models\Distribusi\PengirimanBarang;
use App\Models\Distribusi\PermintaanDistribusi;
use App\Services\Distribusi\DistribusiService;
use App\Services\Distribusi\PickingScope;
use Livewire\Attributes\Url;
use Livewire\Component;

class PickingDistribusiIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal, AuthorizesDistribusiScope;

    #[Url(history: true)]
    public string $activeTab = 'siap-picking'; // 'siap-picking' | 'riwayat-sppb'

    #[Url(history: true)]
    public string $filterUrgensi = '';

    public bool $showDetailModal = false;
    public ?PengirimanBarang $selectedSppb = null;

    public bool $showPermintaanDetailModal = false;
    public ?PermintaanDistribusi $selectedPermintaanDetail = null;
    public array $ringkasanPermintaanDetail = [];
    /**
     * Ubah tab navigasi yang sedang aktif.
     */
    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    /**
     * Hook Livewire saat properti active tab diperbarui.
     */
    public function updatedActiveTab(): void
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
     * Tampilkan dialog konfirmasi pengambilan (picking) barang gudang.
     */
    public function confirmPicking(int $permintaanId): void
    {
        abort_unless($this->canPick(), 403, 'Anda tidak memiliki wewenang untuk melakukan picking FEFO.');

        $permintaan = PermintaanDistribusi::with(['unit', 'detail'])->find($permintaanId);
        if (! $permintaan) {
            $this->toast('Data permintaan amprah tidak ditemukan.', 'danger');
            return;
        }
        $this->authorizePickingScope($permintaan);

        if (! in_array($permintaan->status, StatusPermintaanDistribusi::siapPicking())) {
            $this->toast("Permintaan {$permintaan->kode} belum disetujui gudang atau sudah selesai diproses.", 'warning');
            return;
        }

        $unitNama = $permintaan->unit?->nama ?? 'Unit';
        $itemCount = $permintaan->detail->count();

        $this->askConfirmation(
            target: $permintaanId,
            action: 'executePicking',
            title: 'Konfirmasi Picking FEFO',
            description: "Eksekusi alokasi stok FEFO untuk unit {$unitNama} ({$itemCount} item barang)? Sistem akan mengalokasikan batch sesuai kedaluwarsa terdekat, memotong saldo stok gudang secara otomatis, dan menerbitkan SPPB.",
            buttonText: 'Eksekusi Picking FEFO',
            variant: 'primary'
        );
    }

    /**
     * Eksekusi pengambilan barang (picking) dari stok gudang berdasarkan FEFO.
     */
    public function executePicking(int $permintaanId): void
    {
        $user = auth()->user();
        abort_unless($this->canPick(), 403, 'Anda tidak memiliki wewenang untuk mengeksekusi picking FEFO.');

        $permintaan = PermintaanDistribusi::with(['detail.barang', 'unit'])->find($permintaanId);
        if (! $permintaan) {
            $this->toast('Data permintaan amprah tidak ditemukan.', 'danger');
            return;
        }
        $this->authorizePickingScope($permintaan);

        // Picking hanya setelah otorisasi gudang (Meja 2); DISETUJUI_KARU belum boleh dieksekusi
        if (! in_array($permintaan->status, StatusPermintaanDistribusi::siapPicking(), true)) {
            $this->toast("Status permintaan {$permintaan->kode} ({$permintaan->status}) tidak valid untuk picking.", 'warning');
            return;
        }

        // Pastikan gudang_id terisi jika belum ada
        if (! $permintaan->gudang_id) {
            $gudangId = $user->gudang_id ?: ($this->ownGudangIds()[0] ?? null) ?: Gudang::where('status', 'AKTIF')->first()?->id ?: 1;
            $permintaan->update(['gudang_id' => $gudangId]);
            $permintaan->refresh();
        }

        try {
            $sppb = app(DistribusiService::class)->eksekusiPickingFefo($permintaan, $user);

            $this->toast("SPPB {$sppb->kode} berhasil diterbitkan dengan alokasi FEFO!", 'success');
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->toast($e->getMessage(), 'danger', 'Gagal Picking FEFO');
        }
    }

    /**
     * Buka modal rincian Surat Perintah Pengiriman Barang (SPPB).
     */
    public function showSppbDetail(int $id): void
    {
        $this->checkAccess();

        $sppb = PengirimanBarang::with([
            'permintaanDistribusi',
            'unit',
            'gudang',
            'pengirim',
            'detail.barang.satuan',
            'detail.stokBatch.lokasiGudang',
        ])->findOrFail($id);
        $this->authorizeReadScoped($sppb->unit_id, $sppb->gudang_id);

        $this->selectedSppb = $sppb;
        $this->showDetailModal = true;
    }

    /**
     * Buka modal rincian data permintaan distribusi barang.
     */
    public function showPermintaanDetail(int $id): void
    {
        $this->checkAccess();

        $permintaan = PermintaanDistribusi::with([
            'unit',
            'gudang',
            'pengaju',
            'penyetuju',
            'detail.barang.satuan',
        ])->findOrFail($id);
        abort_unless($this->canReadPermintaan($permintaan), 403, 'Anda tidak memiliki wewenang untuk melihat permohonan di luar gudang Anda.');
        $this->selectedPermintaanDetail = $permintaan;

        $gudangId = $this->selectedPermintaanDetail->gudang_id;

        $existingShipments = PengirimanBarang::where('permintaan_distribusi_id', $this->selectedPermintaanDetail->id)
            ->where('status', '!=', StatusPengirimanBarang::Batal->value)
            ->pluck('id');

        $alreadySentMap = \App\Models\Distribusi\PengirimanBarangDetail::whereIn('pengiriman_barang_id', $existingShipments)
            ->groupBy('barang_id')
            ->selectRaw('barang_id, sum(jumlah_dikirim) as total_sent')
            ->pluck('total_sent', 'barang_id')
            ->toArray();

        $this->ringkasanPermintaanDetail = [];

        foreach ($this->selectedPermintaanDetail->detail as $det) {
            $diminta = (int) $det->jumlah;
            $disetujui = (int) ($det->jumlah_disetujui !== null ? $det->jumlah_disetujui : $diminta);
            $terkirim = (int) ($alreadySentMap[$det->barang_id] ?? 0);
            $sisaPicking = max(0, $disetujui - $terkirim);

            $stokGudang = (int) \App\Models\Persediaan\StokBatch::where('barang_id', $det->barang_id)
                ->where('status', 'AKTIF')
                ->where('jumlah', '>', 0)
                ->when($gudangId, fn ($q) => $q->where('gudang_id', $gudangId))
                ->where(function ($q) {
                    $q->whereNull('tanggal_kedaluwarsa')
                        ->orWhere('tanggal_kedaluwarsa', '>=', now()->toDateString());
                })
                ->sum('jumlah');

            $this->ringkasanPermintaanDetail[$det->id] = [
                'diminta'       => $diminta,
                'disetujui'     => $disetujui,
                'terkirim'      => $terkirim,
                'sisa_picking'  => $sisaPicking,
                'stok_gudang'   => $stokGudang,
            ];
        }

        $this->showPermintaanDetailModal = true;
    }

    /**
     * Tutup modal rincian data permintaan distribusi.
     */
    public function closePermintaanDetailModal(): void
    {
        $this->showPermintaanDetailModal = false;
        $this->selectedPermintaanDetail = null;
        $this->ringkasanPermintaanDetail = [];
    }

    /**
     * Tampilkan konfirmasi proses picking langsung dari modal detail.
     */
    public function confirmPickingFromDetail(): void
    {
        if (! $this->selectedPermintaanDetail) {
            return;
        }

        $id = $this->selectedPermintaanDetail->id;
        $this->showPermintaanDetailModal = false;
        $this->confirmPicking($id);
    }

    /**
     * Validasi izin dan hak akses pengguna saat ini.
     */
    protected function checkAccess(): void
    {
        abort_unless(
            auth()->user()?->canAny(['view_any_gudang_stok::picking::distribusi', 'view_gudang_stok::picking::distribusi']),
            403,
            'Anda tidak memiliki wewenang untuk mengakses halaman Picking FEFO & SPPB.'
        );
    }

    /**
     * Periksa apakah pengguna berwenang melakukan picking barang di gudang.
     */
    protected function canPick(): bool
    {
        return (bool) auth()->user()?->can('create_gudang_stok::picking::distribusi');
    }

    /**
     * Picking hanya untuk gudang milik pengguna; amprah tanpa gudang boleh diambil gudang pengguna.
     */
    protected function authorizePickingScope(PermintaanDistribusi $permintaan): void
    {
        PickingScope::authorize(auth()->user(), $permintaan);
    }

    /**
     * Periksa hak akses baca petugas terhadap dokumen permintaan distribusi.
     */
    protected function canReadPermintaan(PermintaanDistribusi $permintaan): bool
    {
        if (! $permintaan->gudang_id && ! $this->isGlobalOperator() && $this->ownGudangIds()) {
            return true;
        }

        return $this->canReadScoped($permintaan->unit_id, $permintaan->gudang_id, false, true);
    }

    /**
     * Antrean picking: gudang milik pengguna (termasuk amprah tanpa gudang); tanpa penempatan = pembaca global.
     */
    protected function applyPickingQueueScope($query)
    {
        if ($this->isGlobalOperator()) {
            return $query;
        }

        $gudangIds = $this->ownGudangIds();
        if ($gudangIds) {
            return $query->where(fn ($sub) => $sub->whereIn('gudang_id', $gudangIds)->orWhereNull('gudang_id'));
        }

        return $this->applyReadScope($query, null, 'gudang_id');
    }

    /**
     * Render tampilan blade komponen Livewire.
     */
    public function render()
    {
        $this->checkAccess();

        $user = auth()->user();

        // Badge count untuk antrean Siap Picking
        $siapPickingCount = PermintaanDistribusi::whereIn('status', StatusPermintaanDistribusi::siapPicking())
            ->tap(fn ($q) => $this->applyPickingQueueScope($q))
            ->count();

        if ($this->activeTab === 'siap-picking') {
            $query = PermintaanDistribusi::with(['unit', 'gudang', 'pengaju', 'detail.barang.satuan'])
                ->withCount('detail')
                ->whereIn('status', StatusPermintaanDistribusi::siapPicking())
                ->tap(fn ($q) => $this->applyPickingQueueScope($q))
                ->when($this->filterUrgensi, fn ($q) => $q->where('urgensi', $this->filterUrgensi))
                ->when($this->search, function ($q) {
                    $q->where(function ($sub) {
                        $sub->where('kode', 'ilike', "%{$this->search}%")
                            ->orWhereHas('unit', fn ($u) => $u->where('nama', 'ilike', "%{$this->search}%"))
                            ->orWhereHas('detail.barang', fn ($b) => $b->where('nama', 'ilike', "%{$this->search}%"));
                    });
                });

            if ($this->sortBy === 'id' || ! $this->sortBy) {
                $query->orderByRaw("CASE WHEN urgensi = 'CITO' THEN 0 ELSE 1 END")
                    ->orderBy('tanggal_permintaan', 'asc')
                    ->orderBy('id', 'asc');
            } else {
                $sortCol = in_array($this->sortBy, ['kode', 'tanggal_permintaan', 'urgensi', 'status', 'id']) ? $this->sortBy : 'id';
                $query->orderBy($sortCol, $this->sortDirection === 'asc' ? 'asc' : 'desc');
            }

            $items = $query->paginate($this->perPage);
        } else {
            $query = PengirimanBarang::with([
                'permintaanDistribusi',
                'unit',
                'gudang',
                'pengirim',
                'detail.barang.satuan',
                'detail.stokBatch.lokasiGudang',
            ])
                ->tap(fn ($q) => $this->applyReadScope($q, null, 'gudang_id'))
                ->when($this->search, function ($q) {
                    $q->where(function ($sub) {
                        $sub->where('kode', 'ilike', "%{$this->search}%")
                            ->orWhereHas('permintaanDistribusi', fn ($p) => $p->where('kode', 'ilike', "%{$this->search}%"))
                            ->orWhereHas('unit', fn ($u) => $u->where('nama', 'ilike', "%{$this->search}%"))
                            ->orWhereHas('detail.barang', fn ($b) => $b->where('nama', 'ilike', "%{$this->search}%"))
                            ->orWhereHas('detail.stokBatch', fn ($sb) => $sb->where('nomor_batch', 'ilike', "%{$this->search}%"));
                    });
                });

            $sortCol = in_array($this->sortBy, ['kode', 'tanggal_kirim', 'status', 'id']) ? $this->sortBy : 'id';
            $query->orderBy($sortCol, $this->sortDirection === 'asc' ? 'asc' : 'desc');

            $items = $query->paginate($this->perPage);
        }

        return view('livewire.distribusi.picking-distribusi-index', [
            'items'            => $items,
            'siapPickingCount' => $siapPickingCount,
        ])->layout('layouts.portal', [
            'portal' => $this->getPortalName(),
            'title'  => 'Picking FEFO & Pengeluaran Barang (SPPB)',
        ]);
    }
}
