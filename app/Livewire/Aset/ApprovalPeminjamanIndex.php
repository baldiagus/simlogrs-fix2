<?php

namespace App\Livewire\Aset;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\PermintaanPeminjaman;
use App\Services\Aset\PeminjamanAsetService;
use Livewire\Attributes\Url;
use Livewire\Component;

class ApprovalPeminjamanIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal;

    #[Url(history: true)]
    public string $filterStatus = '';

    public bool $showDetailModal = false;
    public ?PermintaanPeminjaman $selectedPinjam = null;

    public function mount(): void
    {
        $this->checkAccess();
    }

    /**
     * Memeriksa otorisasi halaman berbasis permission dinamis Spatie.
     */
    protected function checkAccess(): void
    {
        $user = auth()->user();
        abort_unless(
            $user && (
                $user->can('page_ApprovalPeminjamanIndex') ||
                $user->can('layanan_unit::approval::karu') ||
                $user->can('view_any_layanan_unit::approval::karu') ||
                $user->can('view_layanan_unit::approval::karu') ||
                $user->can('layanan_unit::peminjaman::aset') ||
                $user->can('view_layanan_unit::peminjaman::aset') ||
                $user->can('view_any_layanan_unit::peminjaman::aset') ||
                $user->can('page_Dashboard') ||
                $user->can('page_AdminLogistikDashboard') ||
                $user->can('page_SuperAdminDashboard')
            ),
            403,
            'Anda tidak memiliki wewenang untuk mengakses halaman Otorisasi Peminjaman Aset.'
        );
    }

    /**
     * Cek apakah pengguna memiliki hak akses admin global.
     */
    protected function isGlobalAdmin(): bool
    {
        $user = auth()->user();

        return (bool) ($user && (
            $user->can('page_Dashboard') ||
            $user->can('page_AdminLogistikDashboard') ||
            $user->can('page_SuperAdminDashboard') ||
            $user->can('manage_all')
        ));
    }

    /**
     * Terapkan cakupan data berbasis unit ruangan atau gudang penyedia.
     */
    protected function applyScope($query)
    {
        if ($this->isGlobalAdmin()) {
            return $query;
        }

        $user = auth()->user();

        return $query->where(function ($q) use ($user) {
            $hasScope = false;

            if ($user?->unit_id) {
                $hasScope = true;
                $q->where(function ($sub) use ($user) {
                    $sub->where('tipe_penyedia', 'RUANGAN')
                        ->where('unit_penyedia_id', $user->unit_id);
                });
            }

            if ($user?->gudang_id) {
                if ($hasScope) {
                    $q->orWhere(function ($sub) use ($user) {
                        $sub->where('tipe_penyedia', 'GUDANG')
                            ->where('gudang_penyedia_id', $user->gudang_id);
                    });
                } else {
                    $hasScope = true;
                    $q->where(function ($sub) use ($user) {
                        $sub->where('tipe_penyedia', 'GUDANG')
                            ->where('gudang_penyedia_id', $user->gudang_id);
                    });
                }
            }

            if (! $hasScope) {
                $q->whereRaw('1 = 0');
            }
        });
    }

    /**
     * Validasi wewenang eksekusi tindakan pada dokumen peminjaman tertentu.
     */
    protected function authorizeAction(PermintaanPeminjaman $pinjam): void
    {
        $this->checkAccess();

        if ($this->isGlobalAdmin()) {
            return;
        }

        $user = auth()->user();

        if ($pinjam->tipe_penyedia === 'RUANGAN') {
            if (! $user?->unit_id || (int) $user->unit_id !== (int) $pinjam->unit_penyedia_id) {
                abort(403, 'Anda tidak memiliki wewenang untuk memproses permintaan dari ruangan penyedia ini.');
            }
        } elseif ($pinjam->tipe_penyedia === 'GUDANG') {
            if (! $user?->gudang_id || (int) $user->gudang_id !== (int) $pinjam->gudang_penyedia_id) {
                abort(403, 'Anda tidak memiliki wewenang untuk memproses permintaan dari gudang penyedia ini.');
            }
        } else {
            abort(403, 'Tipe penyedia peminjaman tidak valid.');
        }
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    /**
     * Tampilkan rincian dokumen dan aset yang diajukan peminjaman.
     */
    public function showDetail(int $id): void
    {
        $pinjam = PermintaanPeminjaman::with([
            'unitPeminjam',
            'unitPenyedia',
            'gudangPenyedia',
            'pengaju',
            'penyetuju',
            'detail.aset.barang',
        ])->findOrFail($id);

        $this->authorizeAction($pinjam);

        $this->selectedPinjam = $pinjam;
        $this->showDetailModal = true;
    }

    /**
     * Tutup modal rincian peminjaman.
     */
    public function closeDetail(): void
    {
        $this->showDetailModal = false;
        $this->selectedPinjam = null;
    }

    /**
     * Minta konfirmasi persetujuan peminjaman.
     */
    public function confirmApprove(int $id): void
    {
        $pinjam = PermintaanPeminjaman::with('unitPeminjam')->findOrFail($id);
        $this->authorizeAction($pinjam);

        if ($pinjam->status !== 'DIAJUKAN') {
            $this->toast('Hanya permintaan berstatus DIAJUKAN yang dapat disetujui.', 'danger');
            return;
        }

        $this->showDetailModal = false;

        $this->askConfirmation(
            target: $id,
            action: 'approve',
            title: 'Konfirmasi Persetujuan Peminjaman',
            description: "Setujui permintaan peminjaman {$pinjam->kode} dari {$pinjam->unitPeminjam?->nama}?",
            buttonText: 'Ya, Setujui',
            variant: 'primary'
        );
    }

    /**
     * Eksekusi persetujuan peminjaman aset.
     */
    public function approve(int $id): void
    {
        $pinjam = PermintaanPeminjaman::findOrFail($id);
        $this->authorizeAction($pinjam);

        if ($pinjam->status !== 'DIAJUKAN') {
            $this->toast('Hanya permintaan berstatus DIAJUKAN yang dapat disetujui.', 'danger');
            return;
        }

        // Kunci integritas: periksa apakah ada aset di permohonan ini yang sudah disetujui/dipinjam pada transaksi lain
        $asetIds = $pinjam->detail->pluck('aset_id')->filter();
        $konflik = PermintaanPeminjaman::whereIn('status', ['DISETUJUI', 'DIPINJAM'])
            ->where('id', '!=', $pinjam->id)
            ->whereHas('detail', fn ($d) => $d->whereIn('aset_id', $asetIds))
            ->first();

        if ($konflik) {
            $this->toast("Aset pada permohonan ini telah disetujui atau sedang aktif dipinjam pada transaksi {$konflik->kode}.", 'danger');
            return;
        }

        $pinjam->update([
            'status'         => 'DISETUJUI',
            'disetujui_oleh' => auth()->id(),
        ]);

        $this->toast("Permintaan pinjam {$pinjam->kode} telah disetujui, siap serah terima fisik.", 'success');
    }

    /**
     * Minta konfirmasi penolakan peminjaman.
     */
    public function confirmReject(int $id): void
    {
        $pinjam = PermintaanPeminjaman::with('unitPeminjam')->findOrFail($id);
        $this->authorizeAction($pinjam);

        if ($pinjam->status !== 'DIAJUKAN') {
            $this->toast('Hanya permintaan berstatus DIAJUKAN yang dapat ditolak.', 'danger');
            return;
        }

        $this->showDetailModal = false;

        $this->askConfirmation(
            target: $id,
            action: 'reject',
            title: 'Konfirmasi Penolakan Peminjaman',
            description: "Tolak permohonan peminjaman aset {$pinjam->kode} dari {$pinjam->unitPeminjam?->nama}?",
            buttonText: 'Ya, Tolak',
            variant: 'danger'
        );
    }

    /**
     * Eksekusi penolakan peminjaman aset.
     */
    public function reject(int $id): void
    {
        $pinjam = PermintaanPeminjaman::findOrFail($id);
        $this->authorizeAction($pinjam);

        if ($pinjam->status !== 'DIAJUKAN') {
            $this->toast('Hanya permintaan berstatus DIAJUKAN yang dapat ditolak.', 'danger');
            return;
        }

        $pinjam->update([
            'status'         => 'DITOLAK',
            'disetujui_oleh' => auth()->id(),
        ]);

        $this->toast("Permintaan pinjam {$pinjam->kode} ditolak.", 'info');
    }

    /**
     * Minta konfirmasi serah terima fisik aset inventaris.
     */
    public function confirmSerahTerima(int $id): void
    {
        $pinjam = PermintaanPeminjaman::with('unitPeminjam')->findOrFail($id);
        $this->authorizeAction($pinjam);

        if ($pinjam->status !== 'DISETUJUI') {
            $this->toast('Hanya peminjaman berstatus DISETUJUI yang dapat diserahterimakan.', 'danger');
            return;
        }

        $this->showDetailModal = false;

        $this->askConfirmation(
            target: $id,
            action: 'serahTerima',
            title: 'Konfirmasi Serah Terima Fisik Aset',
            description: "Lakukan serah terima fisik aset untuk {$pinjam->kode} ke unit {$pinjam->unitPeminjam?->nama}? Status aset akan menjadi DIPINJAM.",
            buttonText: 'Ya, Serah Terima',
            variant: 'primary'
        );
    }

    /**
     * Eksekusi mutasi kepemilikan operasional dan serah terima fisik aset.
     */
    public function serahTerima(int $id): void
    {
        $pinjam = PermintaanPeminjaman::with('detail.aset')->findOrFail($id);
        $this->authorizeAction($pinjam);

        if ($pinjam->status !== 'DISETUJUI') {
            $this->toast('Hanya peminjaman berstatus DISETUJUI yang dapat diserahterimakan.', 'danger');
            return;
        }

        try {
            app(PeminjamanAsetService::class)->serahTerimaFisik($pinjam, auth()->user());
            $this->toast('Serah terima fisik berhasil. Aset resmi berstatus DIPINJAM dan berpindah ke unit peminjam.', 'success');
        } catch (\Throwable $e) {
            $this->toast($e->getMessage(), 'danger');
        }
    }

    /**
     * Judul halaman dinamis menyesuaikan portal dan peran pengguna.
     */
    public function getPageTitle(): string
    {
        $portal = $this->getPortalName();

        return match ($portal) {
            'kepala-gudang'  => 'Otorisasi Peminjaman Aset (Kepala Gudang)',
            'kepala-unit'    => 'Otorisasi Peminjaman Aset (Kepala Ruangan)',
            'admin-logistik' => 'Otorisasi Peminjaman Aset (Admin Logistik)',
            default => (function () {
                $role = auth()->user()?->roles?->first();
                $roleName = $role?->nama ?? $role?->name ?? 'Pimpinan';
                return "Otorisasi Peminjaman Aset ({$roleName})";
            })(),
        };
    }

    public function render()
    {
        $this->checkAccess();

        $isGlobalAdmin = $this->isGlobalAdmin();
        $baseCountQuery = PermintaanPeminjaman::query();
        $this->applyScope($baseCountQuery);

        $countMenunggu = (clone $baseCountQuery)->where('status', 'DIAJUKAN')->count();
        $countSiapSerahTerima = (clone $baseCountQuery)->where('status', 'DISETUJUI')->count();
        $countDipinjam = (clone $baseCountQuery)->where('status', 'DIPINJAM')->count();

        $query = PermintaanPeminjaman::with([
            'unitPeminjam',
            'unitPenyedia',
            'gudangPenyedia',
            'pengaju',
            'penyetuju',
            'detail.aset.barang',
        ])->withCount('detail');

        $this->applyScope($query);
        $query->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('kode', 'ilike', "%{$this->search}%")
                        ->orWhere('keperluan', 'ilike', "%{$this->search}%")
                        ->orWhereHas('unitPeminjam', fn ($u) => $u->where('nama', 'ilike', "%{$this->search}%"))
                        ->orWhereHas('detail.aset', fn ($a) => $a->where('kode_aset', 'ilike', "%{$this->search}%"))
                        ->orWhereHas('detail.aset.barang', fn ($b) => $b->where('nama', 'ilike', "%{$this->search}%"));
                });
            });

        $sortColumn = in_array($this->sortBy, ['id', 'kode', 'tanggal_pinjam', 'tanggal_kembali_rencana', 'status'], true)
            ? $this->sortBy
            : 'id';

        $daftarPermintaan = $query
            ->orderBy($sortColumn, $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);

        $pageTitle = $this->getPageTitle();

        return view('livewire.aset.approval-peminjaman-index', [
            'daftarPermintaan'     => $daftarPermintaan,
            'pageTitle'            => $pageTitle,
            'isGlobalAdmin'        => $isGlobalAdmin,
            'countMenunggu'        => $countMenunggu,
            'countSiapSerahTerima' => $countSiapSerahTerima,
            'countDipinjam'        => $countDipinjam,
            'filterStatus'         => $this->filterStatus,
        ])->layout('layouts.portal', [
            'portal' => $this->getPortalName(),
            'title'  => $pageTitle,
        ]);
    }
}
