<?php

namespace App\Livewire\Master;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Pemasok;
use App\Support\Generator\CodeGenerator;
use Livewire\Attributes\Validate;
use Livewire\Component;

class PemasokIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal;

    public bool $showModal = false;
    public ?int $editingId = null;

    #[Validate('required|string|max:50')]
    public string $kode = '';

    #[Validate('required|string|max:150')]
    public string $nama = '';

    #[Validate('nullable|string|max:50')]
    public ?string $npwp = null;

    #[Validate('nullable|string')]
    public ?string $alamat = null;

    #[Validate('nullable|string|max:100')]
    public ?string $kota = null;

    #[Validate('nullable|string|max:50')]
    public ?string $no_telepon = null;

    #[Validate('nullable|email|max:100')]
    public ?string $email = null;

    #[Validate('nullable|string|max:100')]
    public ?string $nama_kontak = null;

    #[Validate('required|in:AKTIF,NONAKTIF')]
    public string $status = 'AKTIF';

    public function create()
    {
        abort_unless(auth()->user()?->can('create_master::pemasok'), 403);

        $this->reset(['editingId', 'nama', 'npwp', 'alamat', 'kota', 'no_telepon', 'email', 'nama_kontak', 'status']);
        $this->kode = CodeGenerator::generate('VDR', Pemasok::class);
        $this->status = 'AKTIF';
        $this->showModal = true;
    }

    public function edit(int $id)
    {
        abort_unless(auth()->user()?->can('update_master::pemasok'), 403);

        $pemasok = Pemasok::findOrFail($id);
        $this->editingId = $pemasok->id;
        $this->kode = $pemasok->kode;
        $this->nama = $pemasok->nama;
        $this->npwp = $pemasok->npwp;
        $this->alamat = $pemasok->alamat;
        $this->kota = $pemasok->kota;
        $this->no_telepon = $pemasok->no_telepon;
        $this->email = $pemasok->email;
        $this->nama_kontak = $pemasok->nama_kontak;
        $this->status = $pemasok->status;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'kode' => 'required|string|max:50|unique:pemasok,kode,' . ($this->editingId ?? 'NULL') . ',id',
            'nama' => 'required|string|max:150',
            'email' => 'nullable|email|max:100',
            'status' => 'required|in:AKTIF,NONAKTIF',
        ]);

        $data = [
            'kode' => strtoupper($this->kode),
            'nama' => $this->nama,
            'npwp' => $this->npwp,
            'alamat' => $this->alamat,
            'kota' => $this->kota,
            'no_telepon' => $this->no_telepon,
            'email' => $this->email,
            'nama_kontak' => $this->nama_kontak,
            'status' => $this->status,
        ];

        if ($this->editingId) {
            abort_unless(auth()->user()?->can('update_master::pemasok'), 403);
            $pemasok = Pemasok::findOrFail($this->editingId);
            $pemasok->update($data);
            $this->toast("Pemasok '{$pemasok->nama}' berhasil diperbarui.", 'success');
        } else {
            abort_unless(auth()->user()?->can('create_master::pemasok'), 403);
            $data['kode'] = $this->kode ?: CodeGenerator::generate('VDR', Pemasok::class);
            Pemasok::create($data);
            $this->toast("Pemasok '{$this->nama}' ({$data['kode']}) berhasil ditambahkan.", 'success');
        }
        $this->showModal = false;
    }
    public function confirmToggleStatus(int $id): void
    {
        $pemasok = Pemasok::findOrFail($id);
        $nextStatus = $pemasok->status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';
        $variant = $nextStatus === 'NONAKTIF' ? 'danger' : 'primary';

        $this->askConfirmation(
            target: $id,
            action: 'toggleStatus',
            title: 'Konfirmasi Ubah Status Pemasok',
            description: "Ubah status perusahaan pemasok '{$pemasok->nama}' ({$pemasok->kode}) menjadi {$nextStatus}?",
            buttonText: "Ya, Ubah ke {$nextStatus}",
            variant: $variant
        );
    }

    public function toggleStatus(int $id)
    {
        abort_unless(auth()->user()?->can('update_master::pemasok'), 403);

        $pemasok = Pemasok::findOrFail($id);
        $newStatus = $pemasok->status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';
        $pemasok->update(['status' => $newStatus]);

        $this->toast("Status pemasok '{$pemasok->nama}' diubah menjadi {$newStatus}.", 'info');
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('view_any_master::pemasok') || auth()->user()?->can('view_master::pemasok'), 403);

        $items = Pemasok::query()
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('nama', 'ilike', "%{$this->search}%")
                        ->orWhere('kode', 'ilike', "%{$this->search}%")
                        ->orWhere('nama_kontak', 'ilike', "%{$this->search}%")
                        ->orWhere('kota', 'ilike', "%{$this->search}%");
                });
            })
            ->orderBy($this->sortBy ?: 'id', $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);

        return view('livewire.master.pemasok-index', [
            'items' => $items,
            'editingId' => $this->editingId,
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Pemasok & Rekanan Vendor']);
    }
}
