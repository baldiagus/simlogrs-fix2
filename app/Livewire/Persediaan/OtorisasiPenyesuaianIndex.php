<?php

namespace App\Livewire\Persediaan;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Gudang;
use App\Models\PenyesuaianStok;
use App\Models\PergerakanStok;
use App\Models\StokBatch;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class OtorisasiPenyesuaianIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal;

    #[Url(history: true)]
    public string $filterStatus = 'MENUNGGU'; // 'MENUNGGU', 'DISETUJUI', 'DITOLAK', 'SEMUA'

    #[Url(history: true)]
    public ?int $filterGudangId = null;

    public bool $showDetailModal = false;
    public ?PenyesuaianStok $selectedPenyesuaian = null;

    public function showDetail(int $id): void
    {
        $this->selectedPenyesuaian = PenyesuaianStok::with([
            'stokOpname',
            'gudang',
            'pengaju',
            'penyetuju',
            'detail.barang.satuan',
            'detail.stokBatch.lokasiGudang',
        ])->findOrFail($id);

        $this->showDetailModal = true;
    }

    public function confirmApprove(int $id): void
    {
        abort_unless(
            auth()->user()?->can('update_gudang_stok::otorisasi::penyesuaian') ||
            auth()->user()?->can('page_KepalaGudangDashboard') ||
            auth()->user()?->can('page_Dashboard'),
            403
        );

        $adj = PenyesuaianStok::with(['gudang', 'detail.barang'])->findOrFail($id);

        $totalItem = $adj->detail->count();
        $this->askConfirmation(
            target: $id,
            action: 'approvePenyesuaian',
            title: 'Otorisasi Penyesuaian Stok',
            description: "Apakah Anda yakin ingin mengesahkan penyesuaian stok {$adj->kode} untuk fasilitas {$adj->gudang?->nama} ({$totalItem} item batch)? Saldo stok fisik akan disesuaikan secara permanen.",
            buttonText: 'Sahkan Penyesuaian Stok',
            variant: 'primary'
        );
    }

    public function approvePenyesuaian(int $id): void
    {
        abort_unless(
            auth()->user()?->can('update_gudang_stok::otorisasi::penyesuaian') ||
            auth()->user()?->can('page_KepalaGudangDashboard') ||
            auth()->user()?->can('page_Dashboard'),
            403
        );

        $adj = PenyesuaianStok::with(['stokOpname', 'detail.stokBatch', 'detail.barang'])->findOrFail($id);

        if ($adj->status !== 'MENUNGGU') {
            $this->toast("Dokumen penyesuaian telah diproses sebelumnya dengan status {$adj->status}.", 'danger');
            return;
        }

        $user = auth()->user();

        try {
            DB::transaction(function () use ($adj, $user) {
                foreach ($adj->detail as $detail) {
                    $batch = StokBatch::lockForUpdate()->findOrFail($detail->stok_batch_id);
                    $qty = (int) $detail->jumlah_penyesuaian;
                    $saldoSebelum = $batch->jumlah;

                    if ($detail->jenis_penyesuaian === 'KURANG') {
                        if ($saldoSebelum < $qty) {
                            throw new Exception("Saldo stok batch '{$batch->nomor_batch}' ({$detail->barang?->nama}) saat ini tidak mencukupi untuk dikurangi {$qty} unit (Saldo riil saat ini: {$saldoSebelum}).");
                        }

                        $saldoSesudah = $saldoSebelum - $qty;
                        $batch->update([
                            'jumlah' => $saldoSesudah,
                            'status' => $saldoSesudah == 0 ? 'HABIS' : 'AKTIF',
                        ]);
                    } else {
                        // TAMBAH
                        $saldoSesudah = $saldoSebelum + $qty;
                        $batch->update([
                            'jumlah' => $saldoSesudah,
                            'status' => 'AKTIF',
                        ]);
                    }

                    // Catat mutasi kartu stok jenis PENYESUAIAN
                    PergerakanStok::create([
                        'barang_id'        => $detail->barang_id,
                        'stok_batch_id'    => $batch->id,
                        'gudang_id'        => $batch->gudang_id,
                        'lokasi_gudang_id' => $batch->lokasi_gudang_id,
                        'jenis'            => 'PENYESUAIAN',
                        'jumlah'           => $qty,
                        'saldo_sebelum'    => $saldoSebelum,
                        'saldo_sesudah'    => $saldoSesudah,
                        'referensi_jenis'  => 'STOCK_OPNAME',
                        'referensi_id'     => $adj->id,
                        'keterangan'       => "Otorisasi penyesuaian opname: {$adj->kode} ({$detail->catatan})",
                        'dibuat_oleh'      => $user->id,
                    ]);
                }

                // Sahkan status penyesuaian dan sesi opname induk
                $adj->update([
                    'status'        => 'DISETUJUI',
                    'disetujui_oleh'=> $user->id,
                ]);

                if ($adj->stokOpname) {
                    $adj->stokOpname->update([
                        'status'        => 'DISETUJUI',
                        'disetujui_oleh'=> $user->id,
                    ]);
                }
            });

            if ($this->showDetailModal) {
                $this->showDetailModal = false;
            }
            $this->toast("Penyesuaian stok {$adj->kode} berhasil disahkan. Saldo fisik dan kartu stok telah terbarui secara atomik.", 'success');
        } catch (Exception $e) {
            $this->toast($e->getMessage(), 'danger');
        }
    }

    public function confirmReject(int $id): void
    {
        abort_unless(
            auth()->user()?->can('update_gudang_stok::otorisasi::penyesuaian') ||
            auth()->user()?->can('page_KepalaGudangDashboard') ||
            auth()->user()?->can('page_Dashboard'),
            403
        );

        $adj = PenyesuaianStok::findOrFail($id);

        $this->askConfirmation(
            target: $id,
            action: 'rejectPenyesuaian',
            title: 'Tolak Penyesuaian Stok',
            description: "Apakah Anda yakin ingin menolak pengajuan penyesuaian stok {$adj->kode}? Sesi opname akan dikembalikan untuk investigasi atau hitung ulang.",
            buttonText: 'Tolak Penyesuaian',
            variant: 'danger'
        );
    }

    public function rejectPenyesuaian(int $id): void
    {
        abort_unless(
            auth()->user()?->can('update_gudang_stok::otorisasi::penyesuaian') ||
            auth()->user()?->can('page_KepalaGudangDashboard') ||
            auth()->user()?->can('page_Dashboard'),
            403
        );

        $adj = PenyesuaianStok::with('stokOpname')->findOrFail($id);

        $user = auth()->user();
        DB::transaction(function () use ($adj, $user) {
            $adj->update([
                'status'        => 'DITOLAK',
                'disetujui_oleh'=> $user->id,
            ]);

            if ($adj->stokOpname) {
                $adj->stokOpname->update([
                    'status' => 'DITOLAK',
                ]);
            }
        });

        if ($this->showDetailModal) {
            $this->showDetailModal = false;
        }
        $this->toast("Penyesuaian stok {$adj->kode} telah ditolak.", 'info');
    }

    public function render()
    {
        abort_unless(
            auth()->user()?->can('view_any_gudang_stok::otorisasi::penyesuaian') ||
            auth()->user()?->can('view_any_audit_manajemen::selisih::opname') ||
            auth()->user()?->can('view_gudang_stok::otorisasi::penyesuaian'),
            403
        );

        $query = PenyesuaianStok::with(['gudang', 'pengaju', 'penyetuju', 'stokOpname', 'detail'])
            ->withCount('detail')
            ->when($this->filterStatus !== 'SEMUA', fn ($q) => $q->where('status', $this->filterStatus ?: 'MENUNGGU'))
            ->when($this->filterGudangId, fn ($q) => $q->where('gudang_id', $this->filterGudangId))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('kode', 'ilike', "%{$this->search}%")
                        ->orWhere('alasan', 'ilike', "%{$this->search}%")
                        ->orWhereHas('gudang', fn ($g) => $g->where('nama', 'ilike', "%{$this->search}%"));
                });
            });

        $items = $query->orderBy('id', 'desc')->paginate($this->perPage);

        $daftarGudang = Cache::remember('dropdown_gudang_aktif', 60, fn () => Gudang::where('status', 'AKTIF')->orderBy('nama')->get());

        return view('livewire.persediaan.otorisasi-penyesuaian-index', [
            'items'        => $items,
            'daftarGudang' => $daftarGudang,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Otorisasi Penyesuaian Stok Opname']);
    }
}
