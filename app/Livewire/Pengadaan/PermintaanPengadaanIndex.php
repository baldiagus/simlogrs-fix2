<?php

namespace App\Livewire\Pengadaan;

use App\Enums\StatusPermintaanPengadaan;
use App\Enums\StatusPesananPembelian;
use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\PermintaanPengadaan;
use App\Models\PermintaanPengadaanDetail;
use App\Models\Unit;
use App\Support\Generator\CodeGenerator;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class PermintaanPengadaanIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal;

    public bool $showModal = false;
    public ?int $editingId = null;
    public bool $showDetailModal = false;
    public ?PermintaanPengadaan $selectedPr = null;
    public bool $showRejectPengadaanModal = false;
    public ?int $rejectPrId = null;
    public string $alasanPenolakanPengadaan = '';

    #[Url(history: true)]
    public string $filterStatus = '';

    #[Url(history: true)]
    public string $filterUrgensi = '';

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterStatus = '';
        $this->filterUrgensi = '';
        $this->resetPage();
    }
    // Form fields
    public string $kode = '';
    public ?int $unit_id = null;
    public ?int $gudang_id = null;
    public string $urgensi = 'REGULER';
    public string $catatan = '';
    public array $items = [];

    public function updatedUnitId($value): void
    {
        if (! empty($value)) {
            $this->gudang_id = null;
        }
    }

    public function updatedGudangId($value): void
    {
        if (! empty($value)) {
            $this->unit_id = null;
        }
    }

    public function resetPemohon(): void
    {
        $this->unit_id = null;
        $this->gudang_id = null;
    }

    /**
     * Bypass scope operasional (buat/otorisasi lintas unit & gudang) hanya untuk role global.
     */
    private function isGlobalAdmin(): bool
    {
        return (bool) auth()->user()?->hasRole(['super_admin', 'admin_logistik']);
    }

    /**
     * Baca lintas unit: role global, pembaca global (auditor/manajemen), dan bagian pengadaan
     * yang memproses PR terusan seluruh unit. Permission view tetap dicek terpisah.
     */
    private function hasGlobalRead(): bool
    {
        return (bool) auth()->user()?->hasRole(['super_admin', 'admin_logistik', 'auditor', 'manajemen', 'pengadaan']);
    }

    private function ownsScope(?int $unitId, ?int $gudangId): bool
    {
        $user = auth()->user();

        return ($unitId && $user?->unit_id && (int) $user->unit_id === (int) $unitId)
            || ($gudangId && $user?->gudang_id && (int) $user->gudang_id === (int) $gudangId);
    }

    private function canReadPr(PermintaanPengadaan $pr): bool
    {
        return $this->hasGlobalRead()
            || $this->ownsScope($pr->unit_id, $pr->gudang_id)
            || (int) $pr->diajukan_oleh === (int) auth()->id();
    }

    /**
     * Tahap otorisasi kepala: role jabatan yang sesuai pada unit/gudang pemohon miliknya.
     */
    private function isWorkflowLeaderFor(?int $unitId, ?int $gudangId): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($gudangId) {
            return $user->hasRole('kepala_gudang') && $user->gudang_id && (int) $user->gudang_id === (int) $gudangId;
        }

        if ($unitId) {
            return $user->hasRole(['kepala_unit', 'kepala_teknisi']) && $user->unit_id && (int) $user->unit_id === (int) $unitId;
        }

        return false;
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterUrgensi(): void
    {
        $this->resetPage();
    }

    public function create()
    {
        abort_unless(auth()->user()?->can('create_pengadaan::permintaan::pengadaan'), 403);

        $user = auth()->user();
        $this->reset(['editingId', 'unit_id', 'gudang_id', 'urgensi', 'catatan', 'items']);
        $this->kode = CodeGenerator::generatePr();
        $this->unit_id = $user->unit_id;
        $this->gudang_id = $user->unit_id ? null : $user->gudang_id;
        $this->urgensi = 'REGULER';
        $this->items = [['barang_id' => null, 'jumlah' => 1]];
        $this->showModal = true;
    }

    public function addItem()
    {
        $this->items[] = ['barang_id' => null, 'jumlah' => 1];
    }

    public function removeItem(int $index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save()
    {
        abort_unless(auth()->user()?->can('create_pengadaan::permintaan::pengadaan'), 403);

        $this->validate([
            'unit_id'            => 'nullable|required_without:gudang_id|exists:unit,id',
            'gudang_id'          => 'nullable|required_without:unit_id|exists:gudang,id',
            'urgensi'            => 'required|in:REGULER,MENDESAK,CITO',
            'catatan'            => 'nullable|string|max:1000',
            'items'              => 'required|array|min:1',
            'items.*.barang_id'  => 'required|exists:barang,id',
            'items.*.jumlah'     => 'required|integer|min:1',
        ], [
            'unit_id.required_without'   => 'Pilih salah satu pemohon: Ruangan Unit atau Gudang Logistik.',
            'gudang_id.required_without' => 'Pilih salah satu pemohon: Ruangan Unit atau Gudang Logistik.',
            'urgensi.required'           => 'Tingkat urgensi pengadaan wajib ditentukan.',
            'items.required'             => 'Minimal 1 baris komoditas barang wajib ditambahkan.',
            'items.min'                  => 'Minimal 1 baris komoditas barang wajib ditambahkan.',
            'items.*.barang_id.required' => 'Komoditas barang wajib dipilih.',
            'items.*.jumlah.required'    => 'Kuantitas barang wajib diisi.',
            'items.*.jumlah.min'         => 'Kuantitas barang minimal 1 unit.',
        ]);

        $kodeFinal = $this->kode ?: CodeGenerator::generatePr();

        $user = auth()->user();
        $isGlobalAdmin = $this->isGlobalAdmin();

        // Pemohon non-global hanya boleh mengajukan atas nama unit/gudang miliknya sendiri.
        abort_unless(
            $isGlobalAdmin || $this->ownsScope($this->unit_id, $this->gudang_id),
            403,
            'Anda hanya dapat mengajukan permintaan pengadaan untuk unit/gudang Anda sendiri.'
        );

        // Auto-otorisasi tahap kepala membutuhkan permission otorisasi DAN role jabatan yang sesuai.
        $isLeader = $user->can('update_pengadaan::permintaan::pengadaan')
            && ($isGlobalAdmin || $this->isWorkflowLeaderFor($this->unit_id, $this->gudang_id));

        $initialStatus = $isLeader ? StatusPermintaanPengadaan::DiteruskanKePengadaan->value : StatusPermintaanPengadaan::Diajukan->value;
        $disetujuiOleh = $isLeader ? $user->id : null;

        // Header dan detail PR satu transaksi (ADR 0002 B).
        DB::transaction(function () use ($kodeFinal, $user, $disetujuiOleh, $initialStatus, $isLeader) {
            $pr = PermintaanPengadaan::create([
                'kode'               => $kodeFinal,
                'unit_id'            => $this->unit_id,
                'gudang_id'          => $this->gudang_id,
                'diajukan_oleh'      => $user->id,
                'disetujui_oleh'     => $disetujuiOleh,
                'tanggal_permintaan' => now()->toDateString(),
                'urgensi'            => $this->urgensi,
                'status'             => $initialStatus,
                'catatan'            => $this->catatan ?: null,
            ]);

            foreach ($this->items as $item) {
                PermintaanPengadaanDetail::create([
                    'permintaan_pengadaan_id' => $pr->id,
                    'barang_id'              => $item['barang_id'],
                    'jumlah'                 => $item['jumlah'],
                    'jumlah_disetujui'       => $isLeader ? $item['jumlah'] : null,
                ]);
            }
        });

        $this->showModal = false;
        $toastMsg = $isLeader
            ? "Permintaan Pengadaan '{$kodeFinal}' berhasil diajukan dan langsung diteruskan ke bagian pengadaan."
            : "Permintaan Pengadaan '{$kodeFinal}' berhasil diajukan.";
        $this->toast($toastMsg, 'success');
    }

    public function showDetail(int $id)
    {
        abort_unless(auth()->user()?->can('view_any_pengadaan::permintaan::pengadaan') || auth()->user()?->can('view_pengadaan::permintaan::pengadaan'), 403);

        $pr = PermintaanPengadaan::with(['unit', 'gudang', 'pengaju', 'penyetuju', 'detail.barang'])->findOrFail($id);
        abort_unless($this->canReadPr($pr), 403, 'Anda tidak memiliki akses ke permintaan pengadaan unit lain.');

        $this->selectedPr = $pr;
        $this->showDetailModal = true;
    }

    public function canUserApprovePr(PermintaanPengadaan $item): bool
    {
        $user = auth()->user();
        if (! $user || ! $user->can('update_pengadaan::permintaan::pengadaan')) {
            return false;
        }

        // Role global (super_admin/admin_logistik) dapat mengotorisasi PR seluruh unit;
        // selain itu wajib kepala unit/gudang pemohon (role jabatan + unit/gudang cocok).
        return $this->isGlobalAdmin() || $this->isWorkflowLeaderFor($item->unit_id, $item->gudang_id);
    }

    public function confirmApprove(int $id): void
    {
        $pr = PermintaanPengadaan::with(['unit', 'gudang'])->findOrFail($id);
        $this->askConfirmation(
            target: $id,
            action: 'approve',
            title: 'Konfirmasi Persetujuan PR',
            description: "Setujui permohonan pengadaan {$pr->kode} dari {$pr->nama_pemohon}?",
            buttonText: 'Ya, Setujui',
            variant: 'primary'
        );
    }

    public function confirmReject(int $id): void
    {
        $pr = PermintaanPengadaan::findOrFail($id);
        $this->askConfirmation(
            target: $id,
            action: 'reject',
            title: 'Konfirmasi Penolakan PR',
            description: "Tolak permohonan pengadaan {$pr->kode}? Permintaan yang ditolak tidak dapat diproses lebih lanjut.",
            buttonText: 'Ya, Tolak Permintaan',
            variant: 'danger'
        );
    }


    public function approve(int $id)
    {
        abort_unless(auth()->user()?->can('update_pengadaan::permintaan::pengadaan'), 403);

        $pr = PermintaanPengadaan::findOrFail($id);

        if (! $this->canUserApprovePr($pr)) {
            abort(403, 'Anda tidak memiliki wewenang mengesahkan permohonan dari unit ini.');
        }

        if ($pr->status !== StatusPermintaanPengadaan::Diajukan->value) {
            $this->toast('Hanya PR berstatus DIAJUKAN yang dapat disetujui.', 'danger');
            return;
        }
        DB::transaction(function () use ($pr) {
            $pr->update([
                'status'         => StatusPermintaanPengadaan::DiteruskanKePengadaan->value,
                'disetujui_oleh' => auth()->id(),
            ]);

            // Default full approval for each detail
            $pr->detail()->update(['jumlah_disetujui' => DB::raw('jumlah')]);
        });

        $this->toast("PR '{$pr->kode}' telah disetujui dan diteruskan ke bagian pengadaan.", 'success');
    }

    public function reject(int $id)
    {
        abort_unless(auth()->user()?->can('update_pengadaan::permintaan::pengadaan'), 403);

        $pr = PermintaanPengadaan::findOrFail($id);

        if (! $this->canUserApprovePr($pr)) {
            abort(403, 'Anda tidak memiliki wewenang menolak permohonan dari unit ini.');
        }

        if ($pr->status !== StatusPermintaanPengadaan::Diajukan->value) {
            $this->toast('Hanya PR berstatus DIAJUKAN yang dapat ditolak.', 'danger');
            return;
        }
        $pr->update([
            'status'         => StatusPermintaanPengadaan::Ditolak->value,
            'disetujui_oleh' => auth()->id(),
        ]);

        $this->toast("PR '{$pr->kode}' telah ditolak.", 'info');
    }

    public function openRejectPengadaan(int $id): void
    {
        abort_unless(auth()->user()?->can('create_pengadaan::pesanan::pembelian'), 403);
        $pr = PermintaanPengadaan::findOrFail($id);

        if ($pr->status !== StatusPermintaanPengadaan::DiteruskanKePengadaan->value) {
            $this->toast('Hanya PR berstatus DITERUSKAN KE PENGADAAN yang dapat ditolak oleh bagian pengadaan.', 'danger');
            return;
        }

        $hasPo = \App\Models\PesananPembelian::where('permintaan_pengadaan_id', $id)
            ->where('status', '!=', StatusPesananPembelian::Dibatalkan->value)
            ->exists();
        if ($hasPo) {
            $this->toast('PR ini sudah diproses ke Pesanan Pembelian (PO) aktif dan tidak dapat ditolak.', 'danger');
            return;
        }

        $this->rejectPrId = $id;
        $this->alasanPenolakanPengadaan = '';
        $this->showRejectPengadaanModal = true;
    }

    public function submitRejectPengadaan(): void
    {
        abort_unless(auth()->user()?->can('create_pengadaan::pesanan::pembelian'), 403);

        $this->validate([
            'alasanPenolakanPengadaan' => 'required|string|min:5|max:500',
        ], [
            'alasanPenolakanPengadaan.required' => 'Alasan penolakan dari bagian pengadaan wajib diisi.',
            'alasanPenolakanPengadaan.min' => 'Alasan penolakan minimal 5 karakter.',
        ]);

        $pr = PermintaanPengadaan::findOrFail($this->rejectPrId);
        if ($pr->status !== StatusPermintaanPengadaan::DiteruskanKePengadaan->value) {
            $this->toast('Hanya PR berstatus DITERUSKAN KE PENGADAAN yang dapat ditolak oleh bagian pengadaan.', 'danger');
            return;
        }

        $catatanAwal = $pr->catatan ? $pr->catatan . "\n" : '';
        $pr->update([
            'status' => StatusPermintaanPengadaan::Ditolak->value,
            'catatan' => $catatanAwal . '[Ditolak Pengadaan]: ' . trim($this->alasanPenolakanPengadaan),
            'disetujui_oleh' => auth()->id(),
        ]);

        $this->showRejectPengadaanModal = false;
        $this->toast("PR '{$pr->kode}' berhasil ditolak di meja pengadaan.", 'info');
    }


    public function render()
    {
        abort_unless(auth()->user()?->can('view_any_pengadaan::permintaan::pengadaan') || auth()->user()?->can('view_pengadaan::permintaan::pengadaan'), 403);

        $user = auth()->user();
        $isGlobalAdmin = $this->isGlobalAdmin();
        $daftarBarang = Barang::where('status', 'AKTIF')->get();
        // Pilihan pemohon non-global dibatasi ke unit/gudang miliknya sendiri.
        $daftarUnit   = Unit::where('status', 'AKTIF')
            ->when(! $isGlobalAdmin, fn ($q) => $q->where('id', $user?->unit_id))
            ->orderBy('nama')->get();
        $daftarGudang = Gudang::where('status', 'AKTIF')
            ->when(! $isGlobalAdmin, fn ($q) => $q->where('id', $user?->gudang_id))
            ->orderBy('nama')->get();

        $query = PermintaanPengadaan::with(['unit', 'gudang', 'pengaju', 'detail'])
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterUrgensi, fn ($q) => $q->where('urgensi', $this->filterUrgensi))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('kode', 'ilike', "%{$this->search}%")
                        ->orWhereHas('unit', fn ($u) => $u->where('nama', 'ilike', "%{$this->search}%"))
                        ->orWhereHas('gudang', fn ($g) => $g->where('nama', 'ilike', "%{$this->search}%"));
                });
            });

        // Batasi data hanya untuk unit atau gudang dinas milik pengguna (kecuali pembaca global)
        if (! $this->hasGlobalRead()) {
            $query->where(function ($q) use ($user) {
                if ($user->unit_id && $user->gudang_id) {
                    $q->where('unit_id', $user->unit_id)
                      ->orWhere('gudang_id', $user->gudang_id);
                } elseif ($user->unit_id) {
                    $q->where('unit_id', $user->unit_id);
                } elseif ($user->gudang_id) {
                    $q->where('gudang_id', $user->gudang_id);
                } else {
                    $q->where('diajukan_oleh', $user->id);
                }
            });
        }

        $daftarPr = $query
            ->orderBy($this->sortBy ?: 'id', $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);

        return view('livewire.pengadaan.permintaan-pengadaan-index', [
            'daftarPr'         => $daftarPr,
            'daftarBarang'     => $daftarBarang,
            'daftarUnit'       => $daftarUnit,
            'daftarGudang'     => $daftarGudang,
            'isGlobalApprover' => $isGlobalAdmin,
            'userUnitId'       => $user?->unit_id,
            'userGudangId'     => $user?->gudang_id,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Permintaan Pengadaan (PR)']);
    }
}
