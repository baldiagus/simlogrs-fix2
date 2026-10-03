<?php

namespace App\Livewire\Master;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\MerekBarang;
use App\Support\Generator\CodeGenerator;
use Livewire\Attributes\Validate;
use Livewire\Component;

class MerekBarangIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal;

    public bool $showModal = false;
    public ?int $editingId = null;
    public bool $showDetailModal = false;
    public ?MerekBarang $selectedItem = null;

    public function showDetail(int $id): void
    {
        $this->selectedItem = MerekBarang::withCount('barang')->findOrFail($id);
        $this->showDetailModal = true;
    }

    #[Validate('required|string|max:50')]
    public string $kode = '';

    #[Validate('required|string|max:100')]
    public string $nama = '';

    #[Validate('required|in:AKTIF,NONAKTIF')]
    public string $status = 'AKTIF';

    public function create()
    {
        abort_unless(auth()->user()?->can('create_master::merek::barang'), 403);

        $this->reset(['editingId', 'nama', 'status']);
        $this->kode = CodeGenerator::generate('MRK', MerekBarang::class);
        $this->status = 'AKTIF';
        $this->showModal = true;
    }

    public function edit(int $id)
    {
        abort_unless(auth()->user()?->can('update_master::merek::barang'), 403);

        $merek = MerekBarang::findOrFail($id);
        $this->editingId = $merek->id;
        $this->kode = $merek->kode;
        $this->nama = $merek->nama;
        $this->status = $merek->status;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'nama' => 'required|string|min:2|max:100',
            'status' => 'required|in:AKTIF,NONAKTIF',
        ], [
            'nama.required' => 'Nama merek dagang wajib diisi.',
            'status.required' => 'Status merek wajib ditentukan.',
        ]);

        if ($this->editingId) {
            abort_unless(auth()->user()?->can('update_master::merek::barang'), 403);
            $merek = MerekBarang::findOrFail($this->editingId);
            $merek->update([
                'nama' => $this->nama,
                'status' => $this->status,
            ]);
            $this->toast("Merek '{$merek->nama}' berhasil diperbarui.", 'success');
        } else {
            abort_unless(auth()->user()?->can('create_master::merek::barang'), 403);
            $kodeFinal = $this->kode ?: CodeGenerator::generate('MRK', MerekBarang::class);
            MerekBarang::create([
                'kode' => $kodeFinal,
                'nama' => $this->nama,
                'status' => $this->status,
            ]);
            $this->toast("Merek '{$this->nama}' ({$kodeFinal}) berhasil ditambahkan.", 'success');
        }
        $this->showModal = false;
    }
    public function confirmToggleStatus(int $id): void
    {
        $merek = MerekBarang::findOrFail($id);
        $nextStatus = $merek->status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';
        $variant = $nextStatus === 'NONAKTIF' ? 'danger' : 'primary';

        $this->askConfirmation(
            target: $id,
            action: 'toggleStatus',
            title: 'Konfirmasi Ubah Status Merek',
            description: "Ubah status merek '{$merek->nama}' ({$merek->kode}) menjadi {$nextStatus}?",
            buttonText: "Ya, Ubah ke {$nextStatus}",
            variant: $variant
        );
    }

    public function toggleStatus(int $id)
    {
        abort_unless(auth()->user()?->can('update_master::merek::barang'), 403);

        $merek = MerekBarang::findOrFail($id);
        $newStatus = $merek->status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';
        $merek->update(['status' => $newStatus]);

        $this->toast("Status merek '{$merek->nama}' diubah menjadi {$newStatus}.", 'info');
    }

    public function confirmDelete(int $id): void
    {
        $merek = MerekBarang::findOrFail($id);

        $this->askConfirmation(
            target: $id,
            action: 'delete',
            title: 'Konfirmasi Hapus Merek',
            description: "Apakah Anda yakin ingin menghapus merek '{$merek->nama}' ({$merek->kode})? Tindakan ini tidak dapat dibatalkan.",
            buttonText: 'Ya, Hapus Data',
            variant: 'danger'
        );
    }

    public function delete(int $id)
    {
        abort_unless(auth()->user()?->can('delete_master::merek::barang') || auth()->user()?->hasRole('super_admin'), 403, 'Anda tidak memiliki hak akses menghapus merek barang.');

        $merek = MerekBarang::findOrFail($id);

        if ($merek->barang()->exists()) {
            $this->toast("Merek '{$merek->nama}' tidak dapat dihapus karena masih digunakan oleh data barang. Silakan nonaktifkan status merek.", 'danger');
            return;
        }

        $namaMerek = $merek->nama;
        $merek->delete();

        $this->toast("Merek '{$namaMerek}' berhasil dihapus.", 'success');
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('view_any_master::merek::barang') || auth()->user()?->can('view_master::merek::barang'), 403);

        $items = MerekBarang::query()
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('nama', 'ilike', "%{$this->search}%")
                        ->orWhere('kode', 'ilike', "%{$this->search}%");
                });
            })
            ->orderBy($this->sortBy ?: 'id', $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);

        return view('livewire.master.merek-barang-index', [
            'items' => $items,
            'editingId' => $this->editingId,
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Merek Barang']);
    }
}
