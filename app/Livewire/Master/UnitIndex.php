<?php

namespace App\Livewire\Master;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\User;
use App\Models\Unit;
use Livewire\Attributes\Validate;
use Livewire\Component;

class UnitIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal;

    public bool $showModal = false;
    public ?int $editingId = null;
    public bool $showDetailModal = false;
    public ?Unit $selectedItem = null;


    #[Validate('required|string|max:50')]
    public string $kode = '';

    #[Validate('required|string|max:150')]
    public string $nama = '';

    #[Validate('required|in:PELAYANAN_MEDIS,PENUNJANG_MEDIS,ADMINISTRASI,OPERASIONAL')]
    public string $jenis = 'PELAYANAN_MEDIS';

    #[Validate('nullable|string|max:150')]
    public ?string $penanggung_jawab = null;

    #[Validate('nullable|exists:pengguna,id')]
    public string $kepala_unit_id = '';

    #[Validate('nullable|string|max:100')]
    public ?string $lokasi_gedung = null;

    #[Validate('required|in:AKTIF,NONAKTIF')]
    public string $status = 'AKTIF';

    public function showDetail(int $id): void
    {
        $this->selectedItem = Unit::withCount(['aset'])->with('kepalaUnit:id,nama,nip')->findOrFail($id);
        $this->showDetailModal = true;
    }

    public function create()
    {
        abort_unless(auth()->user()?->can('create_master::unit'), 403);

        $this->reset(['editingId', 'kode', 'nama', 'jenis', 'penanggung_jawab', 'lokasi_gedung', 'status', 'kepala_unit_id']);
        $this->jenis = 'PELAYANAN_MEDIS';
        $this->status = 'AKTIF';
        $this->kepala_unit_id = '';
        $this->showModal = true;
    }

    public function edit(int $id)
    {
        abort_unless(auth()->user()?->can('update_master::unit'), 403);

        $unit = Unit::findOrFail($id);
        $this->editingId = $unit->id;
        $this->kode = $unit->kode;
        $this->nama = $unit->nama;
        $this->jenis = $unit->jenis;
        $this->penanggung_jawab = $unit->penanggung_jawab;
        $matchedId = $unit->kepala_unit_id
            ?: ($unit->penanggung_jawab ? User::where('nama', $unit->penanggung_jawab)->value('id') : null)
            ?: User::where('unit_id', $unit->id)->whereHas('roles', fn ($q) => $q->whereIn('name', ['kepala_unit', 'manajemen', 'auditor', 'kepala_teknisi']))->value('id');
        $this->kepala_unit_id = $matchedId ? (string) $matchedId : '';
        $this->lokasi_gedung = $unit->lokasi_gedung;
        $this->status = $unit->status;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'kode' => 'required|string|max:50|unique:unit,kode,' . ($this->editingId ?? 'NULL') . ',id',
            'nama' => 'required|string|max:150',
            'jenis' => 'required|in:PELAYANAN_MEDIS,PENUNJANG_MEDIS,ADMINISTRASI,OPERASIONAL',
            'status' => 'required|in:AKTIF,NONAKTIF',
        ]);

        if ($this->editingId) {
            abort_unless(auth()->user()?->can('update_master::unit'), 403);
            $unit = Unit::findOrFail($this->editingId);
            $kepalaId = $this->kepala_unit_id !== '' ? (int) $this->kepala_unit_id : null;
            $namaKepala = $kepalaId ? User::where('id', $kepalaId)->value('nama') : $this->penanggung_jawab;
            $unit->update([
                'kode' => strtoupper($this->kode),
                'nama' => $this->nama,
                'jenis' => $this->jenis,
                'kepala_unit_id' => $kepalaId,
                'penanggung_jawab' => $namaKepala,
                'lokasi_gedung' => $this->lokasi_gedung,
                'status' => $this->status,
            ]);
            $this->toast("Unit '{$unit->nama}' berhasil diperbarui.", 'success');
        } else {
            abort_unless(auth()->user()?->can('create_master::unit'), 403);
            $kepalaId = $this->kepala_unit_id !== '' ? (int) $this->kepala_unit_id : null;
            $namaKepala = $kepalaId ? User::where('id', $kepalaId)->value('nama') : $this->penanggung_jawab;
            Unit::create([
                'kode' => strtoupper($this->kode),
                'nama' => $this->nama,
                'jenis' => $this->jenis,
                'kepala_unit_id' => $kepalaId,
                'penanggung_jawab' => $namaKepala,
                'lokasi_gedung' => $this->lokasi_gedung,
                'status' => $this->status,
            ]);
            $this->toast("Unit baru berhasil ditambahkan.", 'success');
        }

        $this->showModal = false;
    }
    public function confirmToggleStatus(int $id): void
    {
        $unit = Unit::findOrFail($id);
        $nextStatus = $unit->status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';
        $variant = $nextStatus === 'NONAKTIF' ? 'danger' : 'primary';

        $this->askConfirmation(
            target: $id,
            action: 'toggleStatus',
            title: 'Konfirmasi Ubah Status Unit',
            description: "Ubah status unit ruangan '{$unit->nama}' ({$unit->kode}) menjadi {$nextStatus}?",
            buttonText: "Ya, Ubah ke {$nextStatus}",
            variant: $variant
        );
    }

    public function toggleStatus(int $id)
    {
        abort_unless(auth()->user()?->can('update_master::unit'), 403);

        $unit = Unit::findOrFail($id);
        $newStatus = $unit->status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';
        $unit->update(['status' => $newStatus]);

        $this->toast("Status unit '{$unit->nama}' diubah menjadi {$newStatus}.", 'info');
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('view_any_master::unit') || auth()->user()?->can('view_master::unit'), 403);

        $items = Unit::query()
            ->with('kepalaUnit:id,nama')
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

        return view('livewire.master.unit-index', [
            'items' => $items,
            'daftarPengguna' => $daftarPengguna,
            'editingId' => $this->editingId,
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Unit Kerja & Ruangan']);
    }
}
