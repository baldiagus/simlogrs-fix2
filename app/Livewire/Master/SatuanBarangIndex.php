<?php

namespace App\Livewire\Master;

use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\SatuanBarang;
use Livewire\Attributes\Validate;
use Livewire\Component;

class SatuanBarangIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout;

    public bool $showModal = false;
    public ?int $editingId = null;

    #[Validate('required|string|max:30')]
    public string $kode = '';

    #[Validate('required|string|max:50')]
    public string $nama = '';

    #[Validate('nullable|string|max:20')]
    public ?string $simbol = null;

    #[Validate('required|in:AKTIF,NONAKTIF')]
    public string $status = 'AKTIF';

    public function create()
    {
        abort_unless(auth()->user()?->can('create_master::satuan::barang'), 403);

        $this->reset(['editingId', 'kode', 'nama', 'simbol', 'status']);
        $this->status = 'AKTIF';
        $this->showModal = true;
    }

    public function edit(int $id)
    {
        abort_unless(auth()->user()?->can('update_master::satuan::barang'), 403);

        $sat = SatuanBarang::findOrFail($id);
        $this->editingId = $sat->id;
        $this->kode = $sat->kode;
        $this->nama = $sat->nama;
        $this->simbol = $sat->simbol;
        $this->status = $sat->status;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'kode' => 'required|string|max:30|unique:satuan_barang,kode,' . ($this->editingId ?? 'NULL') . ',id',
            'nama' => 'required|string|max:50',
            'status' => 'required|in:AKTIF,NONAKTIF',
        ]);

        $data = [
            'kode' => strtoupper($this->kode),
            'nama' => $this->nama,
            'simbol' => $this->simbol,
            'status' => $this->status,
        ];

        if ($this->editingId) {
            abort_unless(auth()->user()?->can('update_master::satuan::barang'), 403);
            $sat = SatuanBarang::findOrFail($this->editingId);
            $sat->update($data);
            $this->toast("Satuan '{$sat->nama}' berhasil diperbarui.", 'success');
        } else {
            abort_unless(auth()->user()?->can('create_master::satuan::barang'), 403);
            SatuanBarang::create($data);
            $this->toast("Satuan baru berhasil ditambahkan.", 'success');
        }

        $this->showModal = false;
    }

    public function toggleStatus(int $id)
    {
        abort_unless(auth()->user()?->can('update_master::satuan::barang'), 403);

        $sat = SatuanBarang::findOrFail($id);
        $newStatus = $sat->status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';
        $sat->update(['status' => $newStatus]);

        $this->toast("Status satuan '{$sat->nama}' diubah menjadi {$newStatus}.", 'info');
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('view_any_master::satuan::barang') || auth()->user()?->can('view_master::satuan::barang'), 403);

        $items = SatuanBarang::query()
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('nama', 'ilike', "%{$this->search}%")
                        ->orWhere('kode', 'ilike', "%{$this->search}%");
                });
            })
            ->orderBy($this->sortBy ?: 'id', $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);

        return view('livewire.master.satuan-barang-index', [
            'items' => $items,
            'editingId' => $this->editingId,
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Satuan Barang']);
    }
}
