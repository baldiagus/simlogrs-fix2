<?php

namespace App\Livewire\Distribusi;

use App\Enums\StatusPermintaanDistribusi;
use App\Enums\StatusPengirimanBarang;
use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Livewire\Distribusi\Concerns\AuthorizesDistribusiScope;
use App\Models\PenerimaanDistribusi;
use App\Models\PenerimaanDistribusiDetail;
use App\Models\PengirimanBarang;
use App\Models\StokRuangan;
use App\Models\Aset;
use App\Support\Generator\CodeGenerator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class PenerimaanBarangIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal, AuthorizesDistribusiScope;

    #[Url(history: true)]
    public string $filterStatus = StatusPengirimanBarang::Dikirim->value;

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

        $this->authorizeView();
        $this->authorizeReadScoped($sppb->unit_id, $sppb->gudang_id);

        $this->selectedSppb = $sppb;
        $this->showDetailModal = true;
    }

    public function confirmTerima(int $sppbId): void
    {
        $this->authorizePenerimaan();

        $sppb = PengirimanBarang::with(['permintaanDistribusi', 'unit', 'gudang'])->findOrFail($sppbId);

        $this->validateUnitAccess($sppb);

        if ($sppb->status !== StatusPengirimanBarang::Dikirim->value) {
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

        if ($sppb->status !== StatusPengirimanBarang::Dikirim->value) {
            $this->toast('Hanya pengiriman dengan status DIKIRIM yang dapat dikonfirmasi penerimaannya.', 'danger');
            return;
        }

        try {
            DB::transaction(function () use ($sppbId, &$sppb) {
                $sppb = PengirimanBarang::lockForUpdate()->with(['permintaanDistribusi', 'unit', 'gudang', 'detail.barang', 'detail.stokBatch'])
                    ->findOrFail($sppbId);

                if ($sppb->status !== StatusPengirimanBarang::Dikirim->value) {
                    throw new \Exception("Pengiriman {$sppb->kode} telah diproses sebelumnya (Status: {$sppb->status}).");
                }

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

                $hasAssetItem = false;
                // For each detail in $sppb->detail:
                foreach ($sppb->detail as $detail) {
                    // Create PenerimaanDistribusiDetail
                    PenerimaanDistribusiDetail::create([
                        'penerimaan_distribusi_id' => $penerimaan->id,
                        'barang_id'                => $detail->barang_id,
                        'jumlah_diterima'          => $detail->jumlah_dikirim,
                    ]);

                    $barang = $detail->barang;
                    $isAset = $barang && ($barang->tipe === 'ASET' || str_contains(strtoupper($barang->kategori?->nama ?? ''), 'ASET'));
                    $qty = (int) $detail->jumlah_dikirim;

                    if ($isAset) {
                        $hasAssetItem = true;
                        // 1. Cek apakah ada unit fisik aset di gudang pengirim yang siap dialihkan
                        $existingGudangAsets = Aset::where('barang_id', $detail->barang_id)
                            ->where('gudang_id', $sppb->gudang_id)
                            ->whereNull('unit_id')
                            ->where('status', 'TERSEDIA')
                            ->limit($qty)
                            ->get();

                        $transferredCount = 0;
                        foreach ($existingGudangAsets as $asetGudang) {
                            $asetGudang->update([
                                'unit_id'          => $sppb->unit_id,
                                'gudang_id'        => null,
                                'lokasi_gudang_id' => null,
                                'status'           => 'TERSEDIA',
                                'kondisi'          => 'BAIK',
                            ]);
                            $transferredCount++;
                        }

                        // 2. Jika belum ada unit aset di gudang, daftarkan otomatis ke register aset ruangan
                        $remainingToCreate = $qty - $transferredCount;
                        for ($i = 0; $i < $remainingToCreate; $i++) {
                            $kodeAset = CodeGenerator::generate('AST', Aset::class, 'kode_aset');
                            while (Aset::where('kode_aset', $kodeAset)->exists()) {
                                $parts = explode('-', $kodeAset);
                                $seq = (int) end($parts) + 1;
                                $kodeAset = $parts[0] . '-' . $parts[1] . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
                            }

                            $batch = $detail->stokBatch;
                            $nilaiPerolehan = (float) ($batch?->harga_satuan ?: ($barang->harga_beli_terakhir ?: ($barang->harga_estimasi ?: 0)));
                            $nomorSeri = $batch?->nomor_batch
                                ? ($batch->nomor_batch . '-' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT))
                                : ('SN-' . $barang->kode . '-' . date('Ymd') . '-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT));
                            Aset::create([
                                'barang_id'         => $barang->id,
                                'unit_id'           => $sppb->unit_id,
                                'gudang_id'         => null,
                                'lokasi_gudang_id'  => null,
                                'kode_aset'         => $kodeAset,
                                'nomor_seri'        => $nomorSeri,
                                'tanggal_perolehan' => now()->toDateString(),
                                'nilai_perolehan'   => $nilaiPerolehan,
                                'kondisi'           => 'BAIK',
                                'status'            => 'TERSEDIA',
                            ]);
                        }
                    } else {
                        // Komoditas habis pakai atau sparepart: catat ke stok_ruangan
                        $stok = StokRuangan::firstOrCreate(
                            [
                                'unit_id'   => $sppb->unit_id,
                                'barang_id' => $detail->barang_id,
                            ],
                            [
                                'jumlah' => 0,
                            ]
                        );
                        $stok->increment('jumlah', $qty);
                    }
                }

                // Update status SPPB
                $sppb->update(['status' => StatusPengirimanBarang::Diterima->value]);

                // Evaluasi apakah permohonan amprah sudah selesai 100% atau masih ada sisa yang ditunggu
                if ($sppb->permintaanDistribusi) {
                    $permintaan = $sppb->permintaanDistribusi;
                    $permintaan->load('detail');

                    if ($permintaan->semuaBarangDiterima()) {
                        $permintaan->update(['status' => StatusPermintaanDistribusi::Selesai->value]);
                    } else {
                        $permintaan->update(['status' => StatusPermintaanDistribusi::DikirimSebagian->value]);
                    }
                }
            });
        } catch (\Throwable $e) {
            $this->toast($e->getMessage(), 'danger');
            return;
        }

        $this->showDetailModal = false;
        $this->selectedSppb = null;

        $msg = ($hasAssetItem ?? false)
            ? "Barang dari SPPB {$sppb->kode} berhasil diterima dan didaftarkan ke inventaris aset unit Anda!"
            : "Barang dari SPPB {$sppb->kode} berhasil diterima dan ditambahkan ke stok ruangan!";
        $this->toast($msg, 'success');
    }

    protected function authorizeView(): void
    {
        abort_unless(
            auth()->user()?->canAny(['view_any_layanan_unit::penerimaan::barang', 'view_layanan_unit::penerimaan::barang']),
            403,
            'Anda tidak memiliki akses ke halaman konfirmasi penerimaan barang.'
        );
    }

    protected function canTerima(): bool
    {
        return (bool) auth()->user()?->can('update_layanan_unit::penerimaan::barang');
    }

    protected function authorizePenerimaan(): void
    {
        abort_unless($this->canTerima(), 403, 'Anda tidak memiliki wewenang untuk memproses penerimaan barang.');
    }

    protected function validateUnitAccess(PengirimanBarang $sppb): void
    {
        $user = auth()->user();

        if (! $this->isGlobalOperator() && (! $user?->unit_id || (int) $user->unit_id !== (int) $sppb->unit_id)) {
            abort(403, 'Anda tidak memiliki wewenang untuk memproses pengiriman unit ini.');
        }
    }

    public function render()
    {
        $this->authorizeView();

        $user = auth()->user();
        $isGlobalAdmin = $this->isGlobalOperator();

        $query = PengirimanBarang::with([
            'permintaanDistribusi',
            'unit',
            'gudang',
            'pengirim',
            'detail.barang.satuan',
            'detail.stokBatch',
        ])
        ->withCount('detail')
        ->tap(fn ($q) => $this->applyReadScope($q))
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
            'canTerima'        => $this->canTerima(),
            'userUnitId'       => $user?->unit_id,
        ])->layout('layouts.portal', [
            'portal' => $this->getPortalName(),
            'title'  => 'Konfirmasi Penerimaan Distribusi Ruangan',
        ]);
    }
}
