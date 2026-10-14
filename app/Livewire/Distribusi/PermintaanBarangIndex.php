<?php

namespace App\Livewire\Distribusi;

use App\Enums\Distribusi\StatusPermintaanDistribusi;
use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Livewire\Distribusi\Concerns\AuthorizesDistribusiScope;
use App\Livewire\Distribusi\Concerns\HasDraftAmprah;
use App\Models\Master\Barang;
use App\Models\Master\Gudang;
use App\Models\Distribusi\PermintaanDistribusi;
use App\Models\Distribusi\PermintaanDistribusiDetail;
use App\Models\Persediaan\StokBatch;
use App\Models\Master\Unit;
use App\Support\Generator\CodeGenerator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class PermintaanBarangIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal, AuthorizesDistribusiScope;
    use HasDraftAmprah;

    #[Url(history: true)]
    public string $filterStatus = '';

    #[Url(history: true)]
    public string $filterUrgensi = '';
    /**
     * Reset seluruh parameter filter dan pencarian ke kondisi default.
     */
    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterStatus = '';
        $this->filterUrgensi = '';
        $this->resetPage();
    }

    public bool $showModal = false;
    public bool $showDetailModal = false;
    public ?PermintaanDistribusi $selectedPermintaan = null;

    // Form fields
    public string $kode = '';
    public ?int $unit_id = null;
    public ?int $gudang_id = null;
    public string $tanggal_permintaan = '';
    public string $urgensi = 'REGULER';
    public string $catatan = '';
    public array $items = [];

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
     * Buka modal dialog formulir untuk penambahan data baru.
     */
    public function create(): void
    {
        abort_unless(auth()->user()?->can('create_layanan_unit::permintaan::barang'), 403);

        $user = auth()->user();
        $this->reset(['unit_id', 'catatan', 'items']);
        $this->kode = CodeGenerator::generate('REQ', PermintaanDistribusi::class, 'kode', 'Ym', 3);
        $this->tanggal_permintaan = date('Y-m-d');
        $this->urgensi = 'REGULER';
        $this->gudang_id = Gudang::where('status', 'AKTIF')->value('id');
        $this->items = [['barang_id' => null, 'jumlah' => 1, 'catatan' => '']];
        $this->unit_id = $user?->unit_id;
        $this->showModal = true;
    }

    /**
     * Validasi isian formulir dan simpan data ke database.
     */
    public function save(): void
    {
        abort_unless(auth()->user()?->can('create_layanan_unit::permintaan::barang'), 403);

        $user = auth()->user();

        // Non-global hanya boleh mengajukan untuk unitnya sendiri
        if (! $this->isGlobalOperator()) {
            abort_unless($user?->unit_id, 403, 'Akun Anda tidak terhubung dengan unit ruangan.');
            $this->unit_id = $user->unit_id;
        } elseif (! $this->unit_id && $user?->unit_id) {
            $this->unit_id = $user->unit_id;
        }

        $this->validate([
            'unit_id'            => 'required|exists:unit,id',
            'gudang_id'          => 'required|exists:gudang,id',
            'tanggal_permintaan' => 'required|date',
            'urgensi'            => 'required|in:REGULER,CITO',
            'catatan'            => 'nullable|string|max:1000',
            'items'              => 'required|array|min:1',
            'items.*.barang_id'  => 'required|exists:barang,id',
            'items.*.jumlah'     => 'required|integer|min:1',
            'items.*.catatan'    => 'nullable|string|max:255',
        ], [
            'unit_id.required'           => 'Unit pemohon wajib dipilih.',
            'gudang_id.required'         => 'Gudang tujuan wajib dipilih.',
            'tanggal_permintaan.required'=> 'Tanggal permintaan wajib diisi.',
            'urgensi.required'           => 'Tingkat urgensi wajib dipilih.',
            'items.required'             => 'Daftar barang tidak boleh kosong.',
            'items.min'                  => 'Minimal harus ada 1 barang yang diminta.',
            'items.*.barang_id.required' => 'Barang wajib dipilih.',
            'items.*.jumlah.required'    => 'Jumlah barang wajib diisi.',
            'items.*.jumlah.min'         => 'Jumlah minimal 1.',
        ]);

        // Cek duplikasi barang di form
        $barangIds = array_column($this->items, 'barang_id');
        if (count($barangIds) !== count(array_unique($barangIds))) {
            $this->addError('items', 'Terdapat barang yang sama lebih dari satu baris. Mohon gabungkan jumlahnya.');
            return;
        }

        // Komoditas dengan stok gudang kosong atau kurang tetap diizinkan diajukan
        // Selisih kebutuhan akan otomatis dicatat sebagai hutang gudang (backorder)
        // Hanya Kepala Unit (role + permission otorisasi karu) untuk unitnya sendiri yang melewati Meja 1
        $directToGudang = $user->hasRole('kepala_unit')
            && $user->can('update_layanan_unit::approval::karu')
            && $this->inUnitScope($this->unit_id);
        $initialStatus  = $directToGudang ? StatusPermintaanDistribusi::DisetujuiKaru->value : StatusPermintaanDistribusi::Diajukan->value;
        $disetujuiOleh  = $directToGudang ? $user->id : null;

        DB::transaction(function () use ($user, $initialStatus, $disetujuiOleh, $directToGudang) {
            $kodeFinal = $this->kode ?: CodeGenerator::generate('REQ', PermintaanDistribusi::class, 'kode', 'Ym', 3);
            if (PermintaanDistribusi::where('kode', $kodeFinal)->exists()) {
                $kodeFinal = CodeGenerator::generate('REQ', PermintaanDistribusi::class, 'kode', 'Ym', 3);
            }

            $permintaan = PermintaanDistribusi::create([
                'kode'               => $kodeFinal,
                'unit_id'            => $this->unit_id,
                'gudang_id'          => $this->gudang_id,
                'tanggal_permintaan' => $this->tanggal_permintaan ?: date('Y-m-d'),
                'urgensi'            => $this->urgensi,
                'status'             => $initialStatus,
                'catatan'            => $this->catatan ? trim($this->catatan) : null,
                'diajukan_oleh'      => $user->id,
                'disetujui_oleh'     => $disetujuiOleh,
            ]);

            foreach ($this->items as $item) {
                PermintaanDistribusiDetail::create([
                    'permintaan_distribusi_id' => $permintaan->id,
                    'barang_id'              => $item['barang_id'],
                    'jumlah'                 => $item['jumlah'],
                    'jumlah_disetujui'       => $directToGudang ? $item['jumlah'] : null,
                    'catatan'                => !empty($item['catatan']) ? trim($item['catatan']) : null,
                ]);
            }

            $this->kode = $kodeFinal;
        });

        $this->showModal = false;
        $toastMsg = $directToGudang
            ? "Permintaan amprah '{$this->kode}' berhasil diajukan dan langsung diteruskan ke Kepala Gudang (Status: DISETUJUI KARU)."
            : "Permintaan amprah '{$this->kode}' berhasil diajukan, menunggu otorisasi Kepala Ruangan.";
        $this->toast($toastMsg, 'success');
    }

    /**
     * Buka modal dialog dan muat data rincian record terpilih.
     */
    public function showDetail(int $id): void
    {
        $permintaan = PermintaanDistribusi::with([
            'unit',
            'gudang',
            'pengaju',
            'penyetuju',
            'detail.barang.satuan',
            'pengiriman.detail',
        ])->findOrFail($id);

        $this->authorizeView();
        $this->authorizeReadScoped($permintaan->unit_id, $permintaan->gudang_id);

        $this->selectedPermintaan = $permintaan;
        $this->showDetailModal = true;
    }

    /**
     * Tampilkan modal dialog konfirmasi pembatalan transaksi.
     */
    public function confirmBatal(int $id): void
    {
        $permintaan = PermintaanDistribusi::with('unit')->findOrFail($id);
        $this->authorizeRequesterAction($permintaan, 'Anda tidak memiliki hak akses untuk membatalkan permintaan dari unit ini.');

        // 'DIBATALKAN' bukan nilai status SPPB (yang dipakai BATAL); dipertahankan agar perilaku tidak berubah.
        $canBatal = in_array($permintaan->status, StatusPermintaanDistribusi::dapatDibatalkan())
            && ! \App\Models\Distribusi\PengirimanBarang::where('permintaan_distribusi_id', $permintaan->id)->where('status', '!=', 'DIBATALKAN')->exists();

        if (! $canBatal) {
            $this->toast('Permintaan amprah ini sudah diproses dan tidak dapat dibatalkan.', 'danger');
            return;
        }

        $this->askConfirmation(
            target: $id,
            action: 'batal',
            title: 'Batalkan Permintaan Amprah',
            description: "Apakah Anda yakin ingin membatalkan permohonan amprah {$permintaan->kode}?",
            buttonText: 'Ya, Batalkan',
            variant: 'danger'
        );
    }

    /**
     * Batalkan transaksi terpilih dan catat log perubahan.
     */
    public function batal(int $id): void
    {
        $permintaan = PermintaanDistribusi::findOrFail($id);
        $this->authorizeRequesterAction($permintaan, 'Anda tidak memiliki hak akses untuk membatalkan permintaan dari unit ini.');

        // 'DIBATALKAN' bukan nilai status SPPB (yang dipakai BATAL); dipertahankan agar perilaku tidak berubah.
        $canBatal = in_array($permintaan->status, StatusPermintaanDistribusi::dapatDibatalkan())
            && ! \App\Models\Distribusi\PengirimanBarang::where('permintaan_distribusi_id', $permintaan->id)->where('status', '!=', 'DIBATALKAN')->exists();

        if (! $canBatal) {
            $this->toast('Permintaan amprah ini sudah diproses dan tidak dapat dibatalkan.', 'danger');
            return;
        }

        $catatanAwal = $permintaan->catatan ? $permintaan->catatan . "\n" : '';
        $permintaan->update([
            'status' => StatusPermintaanDistribusi::Ditolak->value,
            'catatan' => $catatanAwal . '[Dibatalkan oleh pemohon]',
        ]);

        $this->toast("Permintaan amprah '{$permintaan->kode}' berhasil dibatalkan.", 'info');
    }

    /**
     * Tampilkan dialog konfirmasi penutupan sisa barang pada pesanan pembelian (PO).
     */
    public function confirmTutupSisaHutang(int $id): void
    {
        $permintaan = PermintaanDistribusi::findOrFail($id);
        $this->authorizeRequesterAction($permintaan, 'Anda tidak memiliki hak akses untuk menutup sisa permintaan dari unit ini.');

        if ($permintaan->status !== StatusPermintaanDistribusi::DikirimSebagian->value) {
            $this->toast('Hanya permintaan dengan status DIKIRIM_SEBAGIAN yang dapat ditutup sisa hutangnya.', 'warning');
            return;
        }

        $this->askConfirmation(
            target: $id,
            action: 'tutupSisaHutang',
            title: 'Tutup Sisa Hutang Amprah',
            description: "Apakah Anda yakin ingin menutup sisa barang yang belum terkirim pada permohonan {$permintaan->kode}? Sisa barang tidak akan dikirim lagi dan permohonan dinyatakan selesai.",
            buttonText: 'Ya, Tutup Sisa Hutang',
            variant: 'primary'
        );
    }

    /**
     * Tutup sisa kuantitas PO yang tidak akan dikirim oleh pemasok.
     */
    public function tutupSisaHutang(int $id): void
    {
        DB::transaction(function () use ($id) {
            $permintaan = PermintaanDistribusi::lockForUpdate()->findOrFail($id);
            $this->authorizeRequesterAction($permintaan, 'Anda tidak memiliki hak akses untuk menutup sisa permintaan dari unit ini.');

            if ($permintaan->status !== StatusPermintaanDistribusi::DikirimSebagian->value) {
                $this->toast('Hanya permintaan dengan status DIKIRIM_SEBAGIAN yang dapat ditutup sisa hutangnya.', 'warning');
                return;
            }

            $catatanAwal = $permintaan->catatan ? $permintaan->catatan . "\n" : '';
            $permintaan->update([
                'status'  => StatusPermintaanDistribusi::Selesai->value,
                'catatan' => $catatanAwal . '[Sisa hutang ditutup oleh pemohon pada ' . now()->format('d/m/Y H:i') . ']',
            ]);

            $this->toast("Sisa barang terhutang untuk permohonan '{$permintaan->kode}' telah ditutup resmi.", 'info');
        });
    }

    /**
     * Verifikasi wewenang pengguna untuk melihat data pada halaman ini.
     */
    protected function authorizeView(): void
    {
        abort_unless(
            auth()->user()?->canAny(['view_any_layanan_unit::permintaan::barang', 'view_layanan_unit::permintaan::barang']),
            403
        );
    }

    /**
     * Pembatalan/penutupan amprah oleh pemohon: permission pengajuan amprah dan unit milik sendiri.
     */
    protected function canRequesterAct(?int $unitId): bool
    {
        $user = auth()->user();

        return (bool) ($user
            && $user->canAny(['create_layanan_unit::permintaan::barang', 'update_layanan_unit::permintaan::barang'])
            && ($this->isGlobalOperator() || ($user->unit_id && (int) $user->unit_id === (int) $unitId)));
    }

    /**
     * Verifikasi wewenang pemohon sebelum mengubah atau membatalkan permintaan.
     */
    protected function authorizeRequesterAction(PermintaanDistribusi $permintaan, string $message): void
    {
        abort_unless($this->canRequesterAct($permintaan->unit_id), 403, $message);
    }

    /**
     * Render tampilan blade komponen Livewire.
     */
    public function render()
    {
        $this->authorizeView();

        $user = auth()->user();
        $isGlobalAdmin = $this->isGlobalOperator();

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

        $daftarBarang = Cache::remember('dropdown_barang_aktif', config('simlog.cache.dropdown_ttl'), fn () => Barang::where('status', 'AKTIF')->with('satuan')->orderBy('nama')->get());
        $daftarGudang = Cache::remember('dropdown_gudang_aktif', config('simlog.cache.dropdown_ttl'), fn () => Gudang::where('status', 'AKTIF')->orderBy('nama')->get());
        $daftarUnit   = Cache::remember('dropdown_unit_aktif', config('simlog.cache.dropdown_ttl'), fn () => Unit::where('status', 'AKTIF')->orderBy('nama')->get());
        $stokGudangMap = [];
        if ($this->gudang_id) {
            $stokGudangMap = StokBatch::where('gudang_id', $this->gudang_id)
                ->where('status', 'AKTIF')
                ->where('jumlah', '>', 0)
                ->where(function ($q) {
                    $q->whereNull('tanggal_kedaluwarsa')
                        ->orWhere('tanggal_kedaluwarsa', '>=', now()->toDateString());
                })
                ->groupBy('barang_id')
                ->selectRaw('barang_id, sum(jumlah) as total_stok')
                ->pluck('total_stok', 'barang_id')
                ->toArray();
        }

        return view('livewire.distribusi.permintaan-barang-index', [
            'daftarPermintaan' => $daftarPermintaan,
            'daftarBarang'     => $daftarBarang,
            'daftarGudang'     => $daftarGudang,
            'daftarUnit'       => $daftarUnit,
            'stokGudangMap'    => $stokGudangMap,
            'isGlobalAdmin'    => $isGlobalAdmin,
            'canRequesterAct'  => (bool) $user?->canAny(['create_layanan_unit::permintaan::barang', 'update_layanan_unit::permintaan::barang']),
            'userUnitId'       => $user?->unit_id,
            'items'            => $this->items,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Permintaan Barang Ruangan (Amprah)']);
    }
}
