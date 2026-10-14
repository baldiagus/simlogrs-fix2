<?php

namespace App\Livewire\Master;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Master\SatuanBarang;
use Livewire\Attributes\Validate;
use Livewire\Component;

class SatuanBarangIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal;

    public bool $showModal = false;
    public ?int $editingId = null;
    public bool $showDetailModal = false;
    public ?SatuanBarang $selectedItem = null;

    /**
     * Buka modal dialog dan muat data rincian record terpilih.
     */
    public function showDetail(int $id): void
    {
        abort_unless(auth()->user()?->canAny(['view_any_master::satuan::barang', 'view_master::satuan::barang']), 403);

        $this->selectedItem = SatuanBarang::withCount('barang')->findOrFail($id);
        $this->showDetailModal = true;
    }

    #[Validate('required|string|max:30')]
    public string $kode = '';

    #[Validate('required|string|max:50')]
    public string $nama = '';

    #[Validate('nullable|string|max:20')]
    public ?string $simbol = null;

    #[Validate('required|in:AKTIF,NONAKTIF')]
    public string $status = 'AKTIF';

    /**
     * Buka modal dialog formulir untuk penambahan data baru.
     */
    public function create()
    {
        abort_unless(auth()->user()?->can('create_master::satuan::barang'), 403);

        $this->reset(['editingId', 'kode', 'nama', 'simbol', 'status']);
        $this->status = 'AKTIF';
        $this->showModal = true;
    }

    /**
     * Buka modal dialog formulir dan muat data record yang akan disunting.
     */
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

    /**
     * Validasi isian formulir dan simpan data ke database.
     */
    public function save()
    {
        $this->validate([
            'kode' => 'required|string|max:30|unique:satuan_barang,kode,' . ($this->editingId ?? 'NULL') . ',id',
            'nama' => 'required|string|min:2|max:50',
            'status' => 'required|in:AKTIF,NONAKTIF',
        ], [
            'kode.required' => 'Kode satuan barang wajib diisi.',
            'kode.unique'   => 'Kode satuan sudah terdaftar.',
            'nama.required' => 'Nama satuan barang wajib diisi.',
            'status.required' => 'Status satuan wajib ditentukan.',
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
    /**
     * Tampilkan dialog konfirmasi untuk mengubah status aktif/nonaktif record.
     */
    public function confirmToggleStatus(int $id): void
    {
        $sat = SatuanBarang::findOrFail($id);
        $nextStatus = $sat->status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';
        $variant = $nextStatus === 'NONAKTIF' ? 'danger' : 'primary';

        $this->askConfirmation(
            target: $id,
            action: 'toggleStatus',
            title: 'Konfirmasi Ubah Status Satuan',
            description: "Ubah status satuan kemasan '{$sat->nama}' ({$sat->kode}) menjadi {$nextStatus}?",
            buttonText: "Ya, Ubah ke {$nextStatus}",
            variant: $variant
        );
    }

    /**
     * Ubah status aktif atau nonaktif record pada database.
     */
    public function toggleStatus(int $id)
    {
        abort_unless(auth()->user()?->can('update_master::satuan::barang'), 403);

        $sat = SatuanBarang::findOrFail($id);
        $newStatus = $sat->status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';
        $sat->update(['status' => $newStatus]);

        $this->toast("Status satuan '{$sat->nama}' diubah menjadi {$newStatus}.", 'info');
    }

    /**
     * Tampilkan modal dialog konfirmasi sebelum menghapus data.
     */
    public function confirmDelete(int $id): void
    {
        $sat = SatuanBarang::findOrFail($id);

        $this->askConfirmation(
            target: $id,
            action: 'delete',
            title: 'Konfirmasi Hapus Satuan',
            description: "Apakah Anda yakin ingin menghapus satuan kemasan '{$sat->nama}' ({$sat->kode})? Tindakan ini tidak dapat dibatalkan.",
            buttonText: 'Ya, Hapus Data',
            variant: 'danger'
        );
    }

    /**
     * Hapus record terpilih dari database.
     */
    public function delete(int $id)
    {
        abort_unless(auth()->user()?->can('delete_master::satuan::barang') || auth()->user()?->hasRole('super_admin'), 403, 'Anda tidak memiliki hak akses menghapus satuan barang.');

        $sat = SatuanBarang::findOrFail($id);

        if ($sat->barang()->exists()) {
            $this->toast("Satuan '{$sat->nama}' tidak dapat dihapus karena masih digunakan oleh data barang. Silakan nonaktifkan status satuan.", 'danger');
            return;
        }

        $namaSat = $sat->nama;
        $sat->delete();

        $this->toast("Satuan '{$namaSat}' berhasil dihapus.", 'success');
    }

    /**
     * Render tampilan blade komponen Livewire.
     */
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
