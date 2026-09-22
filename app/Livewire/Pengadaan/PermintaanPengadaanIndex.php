<?php

namespace App\Livewire\Pengadaan;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Barang;
use App\Models\PermintaanPengadaan;
use App\Models\PermintaanPengadaanDetail;
use App\Models\Unit;
use App\Support\Generator\CodeGenerator;
use Livewire\Attributes\Url;
use Livewire\Component;

class PermintaanPengadaanIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal;

    public bool $showModal = false;
    public ?int $editingId = null;
    public bool $showDetailModal = false;
    public ?PermintaanPengadaan $selectedPr = null;

    #[Url(history: true)]
    public string $filterStatus = '';

    #[Url(history: true)]
    public string $filterUrgensi = '';

    // Form fields
    public string $kode = '';
    public ?int $unit_id = null;
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

    public function create()
    {
        abort_unless(auth()->user()?->can('create_pengadaan::permintaan::pengadaan'), 403);

        $this->reset(['editingId', 'unit_id', 'urgensi', 'catatan', 'items']);
        $this->kode = CodeGenerator::generatePr();
        $this->unit_id = auth()->user()->unit_id;
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
            'unit_id'            => 'required|exists:unit,id',
            'urgensi'            => 'required|in:REGULER,MENDESAK,CITO',
            'catatan'            => 'nullable|string|max:1000',
            'items'              => 'required|array|min:1',
            'items.*.barang_id'  => 'required|exists:barang,id',
            'items.*.jumlah'     => 'required|integer|min:1',
        ]);

        $kodeFinal = $this->kode ?: CodeGenerator::generatePr();

        $pr = PermintaanPengadaan::create([
            'kode'               => $kodeFinal,
            'unit_id'            => $this->unit_id,
            'diajukan_oleh'      => auth()->id(),
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi'            => $this->urgensi,
            'status'             => 'DIAJUKAN',
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
        $this->selectedPr = PermintaanPengadaan::with(['unit', 'pengaju', 'penyetuju', 'detail.barang'])->findOrFail($id);
        $this->showDetailModal = true;
    }

    public function confirmApprove(int $id): void
    {
        $pr = PermintaanPengadaan::with('unit')->findOrFail($id);
        $this->askConfirmation(
            target: $id,
            action: 'approve',
            title: 'Konfirmasi Persetujuan PR',
            description: "Setujui permohonan pengadaan {$pr->kode} dari {$pr->unit?->nama}?",
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

        if ($pr->status !== 'DIAJUKAN') {
            $this->toast('Hanya PR berstatus DIAJUKAN yang dapat disetujui.', 'danger');
            return;
        }

        $pr->update([
            'status'         => 'DISETUJUI',
            'disetujui_oleh' => auth()->id(),
        ]);

        // Default full approval for each detail
        $pr->detail()->update(['jumlah_disetujui' => \Illuminate\Support\Facades\DB::raw('jumlah')]);

        $this->toast("PR '{$pr->kode}' telah disetujui.", 'success');
    }

    public function reject(int $id)
    {
        abort_unless(auth()->user()?->can('update_pengadaan::permintaan::pengadaan'), 403);

        $pr = PermintaanPengadaan::findOrFail($id);

        if ($pr->status !== 'DIAJUKAN') {
            $this->toast('Hanya PR berstatus DIAJUKAN yang dapat ditolak.', 'danger');
            return;
        }

        $pr->update([
            'status'         => 'DITOLAK',
            'disetujui_oleh' => auth()->id(),
        ]);

        $this->toast("PR '{$pr->kode}' telah ditolak.", 'info');
    }

    public function teruskan(int $id)
    {
        abort_unless(auth()->user()?->can('update_pengadaan::permintaan::pengadaan'), 403);

        $pr = PermintaanPengadaan::findOrFail($id);

        if ($pr->status !== 'DISETUJUI') {
            $this->toast('Hanya PR berstatus DISETUJUI yang dapat diteruskan.', 'danger');
            return;
        }

        $pr->update(['status' => 'DITERUSKAN_KE_PENGADAAN']);

        $this->toast("PR '{$pr->kode}' diteruskan ke bagian pengadaan.", 'success');
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('view_any_pengadaan::permintaan::pengadaan') || auth()->user()?->can('view_pengadaan::permintaan::pengadaan'), 403);

        $user = auth()->user();
        $daftarBarang = Barang::where('status', 'AKTIF')->get();
        $daftarUnit = Unit::where('status', 'AKTIF')->get();

        $query = PermintaanPengadaan::with(['unit', 'pengaju', 'detail'])
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterUrgensi, fn ($q) => $q->where('urgensi', $this->filterUrgensi))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('kode', 'ilike', "%{$this->search}%")
                        ->orWhereHas('unit', fn ($u) => $u->where('nama', 'ilike', "%{$this->search}%"));
                });
            });

        // Portal-aware: scope to user's unit for non-admin roles
        if ($user->unit_id && !$user->can('view_any_pengadaan::permintaan::pengadaan')) {
            $query->where('unit_id', $user->unit_id);
        }

        $daftarPr = $query
            ->orderBy($this->sortBy ?: 'id', $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);

        return view('livewire.pengadaan.permintaan-pengadaan-index', [
            'daftarPr'     => $daftarPr,
            'daftarBarang' => $daftarBarang,
            'daftarUnit'   => $daftarUnit,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Permintaan Pengadaan (PR)']);
    }
}
