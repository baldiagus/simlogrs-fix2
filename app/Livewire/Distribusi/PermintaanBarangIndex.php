<?php

namespace App\Livewire\Distribusi;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\PermintaanDistribusi;
use App\Models\PermintaanDistribusiDetail;
use App\Models\StokBatch;
use App\Models\Unit;
use App\Support\Generator\CodeGenerator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class PermintaanBarangIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal;

    #[Url(history: true)]
    public string $filterStatus = '';

    #[Url(history: true)]
    public string $filterUrgensi = '';

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

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterUrgensi(): void
    {
        $this->resetPage();
    }

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

    public function addItem(): void
    {
        $this->items[] = ['barang_id' => null, 'jumlah' => 1, 'catatan' => ''];
    }

    public function removeItem(int $index): void
    {
        if (count($this->items) > 1) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
        }
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('create_layanan_unit::permintaan::barang'), 403);

        $user = auth()->user();

        // Auto-fill unit_id if user has unit_id and property was empty
        if (! $this->unit_id && $user?->unit_id) {
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

        // Cek ketersediaan stok fisik gudang non-negatif
        foreach ($this->items as $index => $item) {
            $barangId = (int) ($item['barang_id'] ?? 0);
            $jumlahDiminta = (int) ($item['jumlah'] ?? 0);
            $stokTersedia = $this->getStokTersedia($barangId, (int) $this->gudang_id);
            $barang = Barang::with('satuan')->find($barangId);
            $namaBarang = $barang?->nama ?? "Barang #{$barangId}";
            $satuan = $barang?->satuan?->simbol ?? 'unit';

            if ($stokTersedia <= 0) {
                $this->addError("items.{$index}.barang_id", "Komoditas '{$namaBarang}' di gudang tujuan saat ini kosong (0 {$satuan}). Tidak dapat diajukan amprah.");
                return;
            }

            if ($jumlahDiminta > $stokTersedia) {
                $this->addError("items.{$index}.jumlah", "Jumlah diminta ({$jumlahDiminta} {$satuan}) melebihi stok gudang yang tersedia ({$stokTersedia} {$satuan}).");
                return;
            }
        }
        // Pengguna selain role 'petugas_unit' (Kepala Unit, Teknisi, Admin, dll.) langsung bypass Meja 1 ke Kepala Gudang
        $isPetugasUnitMurni = $user->hasRole('petugas_unit')
            && ! $user->hasRole('kepala_unit')
            && ! $user->can('update_layanan_unit::approval::karu');

        $directToGudang = ! $isPetugasUnitMurni;
        $initialStatus  = $directToGudang ? 'DISETUJUI_KARU' : 'DIAJUKAN';
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

    public function showDetail(int $id): void
    {
        $permintaan = PermintaanDistribusi::with([
            'unit',
            'gudang',
            'pengaju',
            'penyetuju',
            'detail.barang.satuan',
        ])->findOrFail($id);

        $user = auth()->user();
        $isGlobalAdmin = $user && ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard'));

        if (! $isGlobalAdmin && $user?->unit_id && (int) $user->unit_id !== (int) $permintaan->unit_id) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat dokumen dari unit ini.');
        }

        $this->selectedPermintaan = $permintaan;
        $this->showDetailModal = true;
    }

    public function confirmBatal(int $id): void
    {
        $permintaan = PermintaanDistribusi::with('unit')->findOrFail($id);
        $user = auth()->user();
        $isGlobalAdmin = $user && ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard'));

        if (! $isGlobalAdmin && $user?->unit_id && (int) $user->unit_id !== (int) $permintaan->unit_id) {
            abort(403, 'Anda tidak memiliki hak akses untuk membatalkan permintaan dari unit ini.');
        }

        if ($permintaan->status !== 'DIAJUKAN') {
            $this->toast('Hanya permintaan dengan status DIAJUKAN yang dapat dibatalkan.', 'danger');
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
     * Menghitung saldo stok fisik aktif dan belum kedaluwarsa di gudang terkait.
     */
    public function getStokTersedia(int $barangId, ?int $gudangId = null): int
    {
        $gid = $gudangId ?: (int) $this->gudang_id;
        if (! $gid || ! $barangId) {
            return 0;
        }

        return (int) StokBatch::where('gudang_id', $gid)
            ->where('barang_id', $barangId)
            ->where('status', 'AKTIF')
            ->where('jumlah', '>', 0)
            ->where(function ($q) {
                $q->whereNull('tanggal_kedaluwarsa')
                    ->orWhere('tanggal_kedaluwarsa', '>=', now()->toDateString());
            })
            ->sum('jumlah');
    }

    public function batal(int $id): void
    {
        $permintaan = PermintaanDistribusi::findOrFail($id);
        $user = auth()->user();
        $isGlobalAdmin = $user && ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard'));

        if (! $isGlobalAdmin && $user?->unit_id && (int) $user->unit_id !== (int) $permintaan->unit_id) {
            abort(403, 'Anda tidak memiliki hak akses untuk membatalkan permintaan dari unit ini.');
        }

        if ($permintaan->status !== 'DIAJUKAN') {
            $this->toast('Hanya permintaan dengan status DIAJUKAN yang dapat dibatalkan.', 'danger');
            return;
        }

        $catatanAwal = $permintaan->catatan ? $permintaan->catatan . "\n" : '';
        $permintaan->update([
            'status' => 'DITOLAK',
            'catatan' => $catatanAwal . '[Dibatalkan oleh pemohon]',
        ]);

        $this->toast("Permintaan amprah '{$permintaan->kode}' berhasil dibatalkan.", 'info');
    }

    public function render()
    {
        $user = auth()->user();
        abort_unless(
            $user && (
                $user->can('view_any_layanan_unit::permintaan::barang') ||
                $user->can('view_layanan_unit::permintaan::barang') ||
                $user->can('page_PermintaanBarangIndex') ||
                $user->can('page_Dashboard') ||
                $user->can('page_AdminLogistikDashboard')
            ),
            403
        );

        $isGlobalAdmin = $user && ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard'));

        $query = PermintaanDistribusi::with(['unit', 'gudang', 'pengaju', 'penyetuju', 'detail.barang.satuan'])
            ->withCount('detail')
            ->when(! $isGlobalAdmin && $user?->unit_id, fn ($q) => $q->where('unit_id', $user->unit_id))
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

        $daftarBarang = Cache::remember('dropdown_barang_aktif', 60, fn () => Barang::where('status', 'AKTIF')->with('satuan')->orderBy('nama')->get());
        $daftarGudang = Cache::remember('dropdown_gudang_aktif', 60, fn () => Gudang::where('status', 'AKTIF')->orderBy('nama')->get());
        $daftarUnit   = Cache::remember('dropdown_unit_aktif', 60, fn () => Unit::where('status', 'AKTIF')->orderBy('nama')->get());
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
            'userUnitId'       => $user?->unit_id,
            'items'            => $this->items,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Permintaan Barang Ruangan (Amprah)']);
    }
}
