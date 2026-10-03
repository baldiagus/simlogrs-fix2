<?php

namespace App\Livewire\Master;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\User;
use App\Models\Gudang;
use Illuminate\Support\Facades\Cache;
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
    public ?int $kepala_gudang_id = null;

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
        $this->kepala_gudang_id = null;
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
        $this->kepala_gudang_id = $matchedId ? (int) $matchedId : null;
        $this->lokasi_gedung = $gudang->lokasi_gedung;
        $this->status = $gudang->status;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'kode' => 'required|string|max:50|unique:gudang,kode,' . ($this->editingId ?? 'NULL') . ',id',
            'nama' => 'required|string|min:3|max:150',
            'jenis' => 'required|in:SENTRAL,TRANSIT,UNIT,DEPO',
            'status' => 'required|in:AKTIF,NONAKTIF',
        ], [
            'kode.required' => 'Kode fasilitas gudang wajib diisi.',
            'kode.unique'   => 'Kode gudang sudah terdaftar.',
            'nama.required' => 'Nama fasilitas gudang wajib diisi.',
            'nama.min'      => 'Nama fasilitas gudang minimal 3 karakter.',
            'jenis.required' => 'Jenis klasifikasi gudang wajib dipilih.',
            'status.required' => 'Status gudang wajib ditentukan.',
        ]);

        if ($this->editingId) {
            abort_unless(auth()->user()?->can('update_master::gudang'), 403);
            $gudang = Gudang::findOrFail($this->editingId);
            $kepalaId = $this->kepala_gudang_id ? (int) $this->kepala_gudang_id : null;
            $namaKepala = $kepalaId ? User::where('id', $kepalaId)->value('nama') : null;
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
            $kepalaId = $this->kepala_gudang_id ? (int) $this->kepala_gudang_id : null;
            $namaKepala = $kepalaId ? User::where('id', $kepalaId)->value('nama') : null;
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

        Cache::forget('dropdown_gudang_aktif');
        Cache::forget('dropdown_gudang_aktif_audit');

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

        Cache::forget('dropdown_gudang_aktif');
        Cache::forget('dropdown_gudang_aktif_audit');
        $this->toast("Status gudang '{$gudang->nama}' diubah menjadi {$newStatus}.", 'info');
    }

    public function confirmDelete(int $id): void
    {
        $gudang = Gudang::findOrFail($id);

        $this->askConfirmation(
            target: $id,
            action: 'delete',
            title: 'Konfirmasi Hapus Gudang',
            description: "Apakah Anda yakin ingin menghapus fasilitas gudang '{$gudang->nama}' ({$gudang->kode})? Tindakan ini tidak dapat dibatalkan.",
            buttonText: 'Ya, Hapus Data',
            variant: 'danger'
        );
    }

    public function delete(int $id)
    {
        abort_unless(auth()->user()?->can('delete_master::gudang') || auth()->user()?->hasRole('super_admin'), 403, 'Anda tidak memiliki hak akses menghapus fasilitas gudang.');

        $gudang = Gudang::findOrFail($id);

        $hasDependencies = $gudang->lokasiGudang()->exists()
            || \App\Models\StokBatch::where('gudang_id', $id)->exists()
            || \App\Models\PenerimaanBarang::where('gudang_id', $id)->exists()
            || \App\Models\PermintaanDistribusi::where('gudang_id', $id)->exists()
            || \App\Models\User::where('gudang_id', $id)->exists();

        if ($hasDependencies) {
            $this->toast("Fasilitas gudang '{$gudang->nama}' tidak dapat dihapus karena masih memiliki rak, stok fisik, atau petugas penugasan. Silakan nonaktifkan status gudang.", 'danger');
            return;
        }

        $namaGudang = $gudang->nama;
        $gudang->delete();

        Cache::forget('dropdown_gudang_aktif');
        Cache::forget('dropdown_gudang_aktif_audit');
        $this->toast("Fasilitas gudang '{$namaGudang}' berhasil dihapus.", 'success');
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
