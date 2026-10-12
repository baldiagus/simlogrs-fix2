<?php

namespace App\Livewire\Audit;

use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Models\LogAudit;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;
use Livewire\Component;

class AuditTrailIndex extends Component
{
    use HasTableFilters, HasPortalLayout;

    #[Url(history: true)]
    public string $filterAksi = '';

    #[Url(history: true)]
    public string $filterEntitas = '';

    #[Url(history: true)]
    public ?int $filterPenggunaId = null;

    #[Url(history: true)]
    public ?string $tanggalMulai = null;

    #[Url(history: true)]
    public ?string $tanggalSelesai = null;

    public bool $showDetailModal = false;
    public ?LogAudit $selectedLog = null;

    public function showDetail(int $id): void
    {
        $this->selectedLog = LogAudit::with(['pengguna', 'peran'])->findOrFail($id);
        $this->showDetailModal = true;
    }

    public function resetFilters(): void
    {
        $this->reset(['filterAksi', 'filterEntitas', 'filterPenggunaId', 'tanggalMulai', 'tanggalSelesai', 'search']);
        $this->resetPage();
    }

    public function render()
    {
        abort_unless(
            auth()->user()?->can('view_any_audit_manajemen::audit::trail') ||
            auth()->user()?->can('view_audit_manajemen::audit::trail'),
            403
        );

        $query = LogAudit::with(['pengguna', 'peran'])
            ->when($this->filterAksi, fn ($q) => $q->where('aksi', $this->filterAksi))
            ->when($this->filterEntitas, fn ($q) => $q->where('referensi_jenis', $this->filterEntitas))
            ->when($this->filterPenggunaId, fn ($q) => $q->where('pengguna_id', $this->filterPenggunaId))
            ->when($this->tanggalMulai, fn ($q) => $q->whereDate('dibuat_pada', '>=', $this->tanggalMulai))
            ->when($this->tanggalSelesai, fn ($q) => $q->whereDate('dibuat_pada', '<=', $this->tanggalSelesai))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('referensi_jenis', 'ilike', "%{$this->search}%")
                        ->orWhere('ip_address', 'ilike', "%{$this->search}%")
                        ->orWhereHas('pengguna', fn ($u) => $u->where('nama', 'ilike', "%{$this->search}%"));
                });
            });

        $items = $query->orderBy('id', 'desc')->paginate($this->perPage);

        // Daftar entitas unik untuk filter
        $daftarEntitas = Cache::remember('audit_daftar_entitas', config('simlog.cache.dropdown_ttl'), function () {
            return LogAudit::select('referensi_jenis')->distinct()->pluck('referensi_jenis')->toArray();
        });

        // Daftar pengguna untuk filter
        $daftarPengguna = Cache::remember('audit_daftar_pengguna', config('simlog.cache.dropdown_ttl'), function () {
            return User::select('id', 'nama')->where('status', 'AKTIF')->orderBy('nama')->get();
        });

        return view('livewire.audit.audit-trail-index', [
            'items'          => $items,
            'daftarEntitas'  => $daftarEntitas,
            'daftarPengguna' => $daftarPengguna,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Digital Forensic Audit Trail']);
    }
}
