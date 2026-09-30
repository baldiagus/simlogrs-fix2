<?php

namespace App\Livewire\Master;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\User;
use App\Models\Gudang;
use Livewire\Attributes\Validate;
use Livewire\Component;

class GudangIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal;

    public bool $showModal = false;
    public ?int $editingId = null;
    public bool $showDetailModal = false;
    public ?\App\Models\Gudang $selectedItem = null;

    public function showDetail(int $id): void
    {
        $this->selectedItem = Gudang::withCount(['lokasiGudang', 'stokBatch'])->with('kepalaGudang:id,nama,nip')->findOrFail($id);
        $this->showDetailModal = true;
    }

    #[Validate('required|string|max:50')]
    public string $kode = '';

    #[Validate('required|string|max:150')]
    public string $nama = '';

    #[Validate('required|in:SENTRAL,TRANSIT,UNIT,DEPO')]
    public string $jenis = 'SENTRAL';

    #[Validate('nullable|string|max:150')]
    public ?string $penanggung_jawab = null;

    #[Validate('nullable|exists:pengguna,id')]
    public string $kepala_gudang_id = '';

    #[Validate('nullable|string|max:100')]
    public ?string $lokasi_gedung = null;

    #[Validate('required|in:AKTIF,NONAKTIF')]
    public string $status = 'AKTIF';

    public function create()
    {
        abort_unless(auth()->user()?->can('create_master::gudang'), 403);

        $this->reset(['editingId', 'kode', 'nama', 'jenis', 'penanggung_jawab', 'lokasi_gedung', 'status', 'kepala_gudang_id']);
        $this->jenis = 'SENTRAL';
        $this->status = 'AKTIF';
        $this->kepala_gudang_id = '';
        $this->showModal = true;
    }

    public function edit(int $id)
    {
        abort_unless(auth()->user()?->can('update_master::gudang'), 403);

        $gudang = Gudang::findOrFail($id);
        $this->editingId = $gudang->id;
        $this->kode = $gudang->kode;
        $this->nama = $gudang->nama;
        $this->jenis = $gudang->jenis;
        $this->penanggung_jawab = $gudang->penanggung_jawab;
        $matchedId = $gudang->kepala_gudang_id
            ?: ($gudang->penanggung_jawab ? User::where('nama', $gudang->penanggung_jawab)->value('id') : null)
            ?: User::where('gudang_id', $gudang->id)->whereHas('roles', fn ($q) => $q->where('name', 'kepala_gudang'))->value('id');
        $this->kepala_gudang_id = $matchedId ? (string) $matchedId : '';
        $this->lokasi_gedung = $gudang->lokasi_gedung;
        $this->status = $gudang->status;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'kode' => 'required|string|max:50|unique:gudang,kode,' . ($this->editingId ?? 'NULL') . ',id',
            'nama' => 'required|string|max:150',
            'jenis' => 'required|in:SENTRAL,TRANSIT,UNIT,DEPO',
            'status' => 'required|in:AKTIF,NONAKTIF',
        ]);

        if ($this->editingId) {
            abort_unless(auth()->user()?->can('update_master::gudang'), 403);
            $gudang = Gudang::findOrFail($this->editingId);
            $kepalaId = $this->kepala_gudang_id !== '' ? (int) $this->kepala_gudang_id : null;
            $namaKepala = $kepalaId ? User::where('id', $kepalaId)->value('nama') : $this->penanggung_jawab;
            $gudang->update([
                'kode' => strtoupper($this->kode),
                'nama' => $this->nama,
                'jenis' => $this->jenis,
                'kepala_gudang_id' => $kepalaId,
                'penanggung_jawab' => $namaKepala,
                'lokasi_gedung' => $this->lokasi_gedung,
                'status' => $this->status,
            ]);
            $this->toast("Gudang '{$gudang->nama}' berhasil diperbarui.", 'success');
        } else {
            abort_unless(auth()->user()?->can('create_master::gudang'), 403);
            $kepalaId = $this->kepala_gudang_id !== '' ? (int) $this->kepala_gudang_id : null;
            $namaKepala = $kepalaId ? User::where('id', $kepalaId)->value('nama') : $this->penanggung_jawab;
            Gudang::create([
                'kode' => strtoupper($this->kode),
                'nama' => $this->nama,
                'jenis' => $this->jenis,
                'kepala_gudang_id' => $kepalaId,
                'penanggung_jawab' => $namaKepala,
                'lokasi_gedung' => $this->lokasi_gedung,
                'status' => $this->status,
            ]);
            $this->toast("Gudang baru berhasil ditambahkan.", 'success');
        }

        $this->showModal = false;
    }
    public function confirmToggleStatus(int $id): void
    {
        $gudang = Gudang::findOrFail($id);
        $nextStatus = $gudang->status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';
        $variant = $nextStatus === 'NONAKTIF' ? 'danger' : 'primary';

        $this->askConfirmation(
            target: $id,
            action: 'toggleStatus',
            title: 'Konfirmasi Ubah Status Gudang',
            description: "Ubah status fasilitas gudang '{$gudang->nama}' ({$gudang->kode}) menjadi {$nextStatus}?",
            buttonText: "Ya, Ubah ke {$nextStatus}",
            variant: $variant
        );
    }

    public function toggleStatus(int $id)
    {
        abort_unless(auth()->user()?->can('update_master::gudang'), 403);

        $gudang = Gudang::findOrFail($id);
        $newStatus = $gudang->status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';
        $gudang->update(['status' => $newStatus]);

        $this->toast("Status gudang '{$gudang->nama}' diubah menjadi {$newStatus}.", 'info');
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('view_any_master::gudang') || auth()->user()?->can('view_master::gudang'), 403);

        $items = Gudang::query()
            ->with('kepalaGudang:id,nama')
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('nama', 'ilike', "%{$this->search}%")
                        ->orWhere('kode', 'ilike', "%{$this->search}%")
                        ->orWhere('penanggung_jawab', 'ilike', "%{$this->search}%");
                });
            })
            ->orderBy($this->sortBy ?: 'id', $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);

        $daftarPengguna = User::where('status', 'AKTIF')->orderBy('nama')->get(['id', 'nama', 'nip']);

        return view('livewire.master.gudang-index', [
            'items' => $items,
            'daftarPengguna' => $daftarPengguna,
            'editingId' => $this->editingId,
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Gudang Logistik']);
    }
}
