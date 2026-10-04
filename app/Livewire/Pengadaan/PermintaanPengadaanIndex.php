<?php

namespace App\Livewire\Pengadaan;

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

        $pr = PermintaanPengadaan::create([
            'kode'               => $kodeFinal,
            'unit_id'            => $this->unit_id,
            'gudang_id'          => $this->gudang_id,
            'diajukan_oleh'      => auth()->id(),
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi'            => $this->urgensi,
            'status'             => PermintaanPengadaan::STATUS_DIAJUKAN,
            'catatan'            => $this->catatan ?: null,
        ]);

        foreach ($this->items as $item) {
            PermintaanPengadaanDetail::create([
                'permintaan_pengadaan_id' => $pr->id,
                'barang_id'              => $item['barang_id'],
                'jumlah'                 => $item['jumlah'],
            ]);
        }

        $this->showModal = false;
        $this->toast("Permintaan Pengadaan '{$kodeFinal}' berhasil diajukan.", 'success');
    }

    public function showDetail(int $id)
    {
        $this->selectedPr = PermintaanPengadaan::with(['unit', 'gudang', 'pengaju', 'penyetuju', 'detail.barang'])->findOrFail($id);
        $this->showDetailModal = true;
    }

    public function canUserApprovePr(PermintaanPengadaan $item): bool
    {
        $user = auth()->user();
        if (! $user || ! $user->can('update_pengadaan::permintaan::pengadaan')) {
            return false;
        }

        // Pengguna dengan akses admin global dapat mengotorisasi seluruh PR
        if ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard')) {
            return true;
        }

        // Kepala Ruangan / Unit hanya boleh otorisasi unit miliknya
        if ($user->unit_id && $item->unit_id && (int) $user->unit_id !== (int) $item->unit_id) {
            return false;
        }

        // Kepala Gudang hanya boleh otorisasi gudang miliknya jika PR diajukan untuk gudang
        if ($user->gudang_id && $item->gudang_id && (int) $user->gudang_id !== (int) $item->gudang_id) {
            return false;
        }

        return true;
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

    public function confirmTeruskan(int $id): void
    {
        $pr = PermintaanPengadaan::findOrFail($id);
        $this->askConfirmation(
            target: $id,
            action: 'teruskan',
            title: 'Konfirmasi Teruskan ke Pengadaan',
            description: "Teruskan permohonan {$pr->kode} ke Bagian Pengadaan untuk penerbitan PO?",
            buttonText: 'Ya, Teruskan',
            variant: 'primary'
        );
    }

    public function approve(int $id)
    {
        abort_unless(auth()->user()?->can('update_pengadaan::permintaan::pengadaan'), 403);

        $pr = PermintaanPengadaan::findOrFail($id);

        if (! $this->canUserApprovePr($pr)) {
            abort(403, 'Anda tidak memiliki wewenang mengesahkan permohonan dari unit ini.');
        }

        if ($pr->status !== PermintaanPengadaan::STATUS_DIAJUKAN) {
            $this->toast('Hanya PR berstatus DIAJUKAN yang dapat disetujui.', 'danger');
            return;
        }
        $pr->update([
            'status'         => PermintaanPengadaan::STATUS_DITERUSKAN_KE_PENGADAAN,
            'disetujui_oleh' => auth()->id(),
        ]);

        // Default full approval for each detail
        $pr->detail()->update(['jumlah_disetujui' => \Illuminate\Support\Facades\DB::raw('jumlah')]);

        $this->toast("PR '{$pr->kode}' telah disetujui dan diteruskan ke bagian pengadaan.", 'success');
    }

    public function reject(int $id)
    {
        abort_unless(auth()->user()?->can('update_pengadaan::permintaan::pengadaan'), 403);

        $pr = PermintaanPengadaan::findOrFail($id);

        if (! $this->canUserApprovePr($pr)) {
            abort(403, 'Anda tidak memiliki wewenang menolak permohonan dari unit ini.');
        }

        if ($pr->status !== PermintaanPengadaan::STATUS_DIAJUKAN) {
            $this->toast('Hanya PR berstatus DIAJUKAN yang dapat ditolak.', 'danger');
            return;
        }
        $pr->update([
            'status'         => PermintaanPengadaan::STATUS_DITOLAK,
            'disetujui_oleh' => auth()->id(),
        ]);

        $this->toast("PR '{$pr->kode}' telah ditolak.", 'info');
    }

    public function teruskan(int $id)
    {
        abort_unless(auth()->user()?->can('update_pengadaan::permintaan::pengadaan'), 403);

        $pr = PermintaanPengadaan::findOrFail($id);

        if (! $this->canUserApprovePr($pr)) {
            abort(403, 'Anda tidak memiliki wewenang meneruskan permohonan dari unit ini.');
        }

        if ($pr->status !== PermintaanPengadaan::STATUS_DITERUSKAN_KE_PENGADAAN && $pr->status !== 'DISETUJUI') {
            $this->toast('Permohonan tidak dapat diteruskan.', 'danger');
            return;
        }
        $pr->update(['status' => PermintaanPengadaan::STATUS_DITERUSKAN_KE_PENGADAAN]);

        $this->toast("PR '{$pr->kode}' diteruskan ke bagian pengadaan.", 'success');
    }
    public function openRejectPengadaan(int $id): void
    {
        abort_unless(auth()->user()?->can('create_pengadaan::pesanan::pembelian'), 403);
        $pr = PermintaanPengadaan::findOrFail($id);

        if ($pr->status !== PermintaanPengadaan::STATUS_DITERUSKAN_KE_PENGADAAN) {
            $this->toast('Hanya PR berstatus DITERUSKAN KE PENGADAAN yang dapat ditolak oleh bagian pengadaan.', 'danger');
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
        $catatanAwal = $pr->catatan ? $pr->catatan . "\n" : '';
        $pr->update([
            'status' => PermintaanPengadaan::STATUS_DITOLAK,
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
        $daftarBarang = Barang::where('status', 'AKTIF')->get();
        $daftarUnit   = Unit::where('status', 'AKTIF')->orderBy('nama')->get();
        $daftarGudang = Gudang::where('status', 'AKTIF')->orderBy('nama')->get();

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

        $isCrossUnitViewer = $user && (
            $user->can('page_PengadaanDashboard') ||
            $user->can('page_AdminLogistikDashboard') ||
            $user->can('page_Dashboard') ||
            $user->can('page_ManajemenDashboard') ||
            $user->can('page_AuditorDashboard')
        );

        // Batasi data hanya untuk unit atau gudang dinas milik pengguna (kecuali tim pengadaan/admin global)
        if (! $isCrossUnitViewer) {
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
            'isGlobalApprover' => $isCrossUnitViewer,
            'userUnitId'       => $user?->unit_id,
            'userGudangId'     => $user?->gudang_id,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Permintaan Pengadaan (PR)']);
    }
}
