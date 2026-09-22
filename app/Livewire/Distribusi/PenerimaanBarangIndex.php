<?php

namespace App\Livewire\Distribusi;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\PenerimaanDistribusi;
use App\Models\PenerimaanDistribusiDetail;
use App\Models\PengirimanBarang;
use App\Models\StokRuangan;
use App\Support\Generator\CodeGenerator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class PenerimaanBarangIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal;

    #[Url(history: true)]
    public string $filterStatus = 'DIKIRIM';

    public bool $showDetailModal = false;
    public ?PengirimanBarang $selectedSppb = null;

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function showDetail(int $sppbId): void
    {
        $sppb = PengirimanBarang::with([
            'permintaanDistribusi.pengaju',
            'unit',
            'gudang',
            'pengirim',
            'detail.barang.satuan',
            'detail.stokBatch.lokasiGudang',
            'penerimaan.penerima',
        ])->findOrFail($sppbId);

        $user = auth()->user();
        $isGlobalAdmin = $user && ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard'));

        if (! $isGlobalAdmin && (int) $user?->unit_id !== (int) $sppb->unit_id) {
            abort(403, 'Anda tidak memiliki wewenang untuk melihat data pengiriman unit ini.');
        }

        $this->selectedSppb = $sppb;
        $this->showDetailModal = true;
    }

    public function confirmTerima(int $sppbId): void
    {
        $this->authorizePenerimaan();

        $sppb = PengirimanBarang::with(['permintaanDistribusi', 'unit', 'gudang'])->findOrFail($sppbId);

        $this->validateUnitAccess($sppb);

        if ($sppb->status !== 'DIKIRIM') {
            $this->toast('Hanya pengiriman dengan status DIKIRIM yang dapat dikonfirmasi penerimaannya.', 'danger');
            return;
        }

        $this->askConfirmation(
            target: $sppbId,
            action: 'terima',
            title: 'Konfirmasi Penerimaan Barang',
            description: "Apakah Anda yakin barang dari SPPB {$sppb->kode} (Gudang: {$sppb->gudang?->nama}) telah diterima fisik secara lengkap dan siap dimasukkan ke stok ruangan?",
            buttonText: 'Ya, Konfirmasi Terima',
            variant: 'primary'
        );
    }

    public function terima(int $sppbId): void
    {
        $this->authorizePenerimaan();

        $sppb = PengirimanBarang::with(['permintaanDistribusi', 'unit', 'gudang', 'detail.barang', 'detail.stokBatch'])
            ->findOrFail($sppbId);

        $this->validateUnitAccess($sppb);

        if ($sppb->status !== 'DIKIRIM') {
            $this->toast('Hanya pengiriman dengan status DIKIRIM yang dapat dikonfirmasi penerimaannya.', 'danger');
            return;
        }

        DB::transaction(function () use ($sppb) {
            // Create PenerimaanDistribusi (RCV-YYYYMM-XXX)
            $kodeRcv = CodeGenerator::generate('RCV', PenerimaanDistribusi::class, 'kode', 'Ym', 3);

            $penerimaan = PenerimaanDistribusi::create([
                'kode'                 => $kodeRcv,
                'pengiriman_barang_id' => $sppb->id,
                'unit_id'              => $sppb->unit_id,
                'tanggal_terima'       => now()->toDateString(),
                'status'               => 'SELESAI',
                'catatan'              => 'Penerimaan fisik barang dari SPPB ' . $sppb->kode,
                'diterima_oleh'        => auth()->id(),
            ]);

            // For each detail in $sppb->detail:
            foreach ($sppb->detail as $detail) {
                // Create PenerimaanDistribusiDetail
                PenerimaanDistribusiDetail::create([
                    'penerimaan_distribusi_id' => $penerimaan->id,
                    'barang_id'                => $detail->barang_id,
                    'jumlah_diterima'          => $detail->jumlah_dikirim,
                ]);

                // Add/Increment StokRuangan
                $stok = StokRuangan::firstOrCreate(
                    [
                        'unit_id'   => $sppb->unit_id,
                        'barang_id' => $detail->barang_id,
                    ],
                    [
                        'jumlah' => 0,
                    ]
                );

                $stok->increment('jumlah', $detail->jumlah_dikirim);
            }

            // Update status SPPB
            $sppb->update(['status' => 'DITERIMA']);

            // Update status PermintaanDistribusi
            if ($sppb->permintaanDistribusi) {
                $sppb->permintaanDistribusi->update(['status' => 'SELESAI']);
            }
        });

        $this->showDetailModal = false;
        $this->selectedSppb = null;

        $this->toast("Barang dari SPPB {$sppb->kode} berhasil diterima dan ditambahkan ke stok ruangan!", 'success');
    }

    protected function authorizePenerimaan(): void
    {
        $user = auth()->user();
        abort_unless(
            $user && (
                $user->can('update_layanan_unit::penerimaan::barang') ||
                $user->can('create_layanan_unit::penerimaan::barang') ||
                $user->can('page_PenerimaanBarangIndex') ||
                $user->can('page_Dashboard') ||
                $user->can('page_AdminLogistikDashboard')
            ),
            403,
            'Anda tidak memiliki wewenang untuk memproses penerimaan barang.'
        );
    }

    protected function validateUnitAccess(PengirimanBarang $sppb): void
    {
        $user = auth()->user();
        $isGlobalAdmin = $user && ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard'));

        if (! $isGlobalAdmin && (int) $user?->unit_id !== (int) $sppb->unit_id) {
            abort(403, 'Anda tidak memiliki wewenang untuk memproses pengiriman unit ini.');
        }
    }

    public function render()
    {
        abort_unless(
            auth()->user()?->can('page_PenerimaanBarangIndex') ||
            auth()->user()?->can('view_any_layanan_unit::penerimaan::barang') ||
            auth()->user()?->can('view_layanan_unit::penerimaan::barang') ||
            auth()->user()?->can('page_Dashboard') ||
            auth()->user()?->can('page_AdminLogistikDashboard'),
            403,
            'Anda tidak memiliki akses ke halaman konfirmasi penerimaan barang.'
        );

        $user = auth()->user();
        $isGlobalAdmin = $user && ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard'));

        $query = PengirimanBarang::with([
            'permintaanDistribusi',
            'unit',
            'gudang',
            'pengirim',
            'detail.barang.satuan',
            'detail.stokBatch',
        ])
        ->withCount('detail')
        ->when(! $isGlobalAdmin, fn ($q) => $q->where('unit_id', $user?->unit_id))
        ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
        ->when($this->search, function ($q) {
            $q->where(function ($sub) {
                $sub->where('kode', 'ilike', "%{$this->search}%")
                    ->orWhereHas('permintaanDistribusi', fn ($pd) => $pd->where('kode', 'ilike', "%{$this->search}%"))
                    ->orWhereHas('gudang', fn ($g) => $g->where('nama', 'ilike', "%{$this->search}%"))
                    ->orWhereHas('unit', fn ($u) => $u->where('nama', 'ilike', "%{$this->search}%"));
            });
        });

        $daftarPengiriman = $query
            ->orderBy($this->sortBy ?: 'id', $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);

        return view('livewire.distribusi.penerimaan-barang-index', [
            'daftarPengiriman' => $daftarPengiriman,
            'isGlobalAdmin'    => $isGlobalAdmin,
            'userUnitId'       => $user?->unit_id,
        ])->layout('layouts.portal', [
            'portal' => $this->getPortalName(),
            'title'  => 'Konfirmasi Penerimaan Distribusi Ruangan',
        ]);
    }
}
