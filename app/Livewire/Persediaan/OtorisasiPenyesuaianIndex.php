<?php

namespace App\Livewire\Persediaan;

use App\Enums\Persediaan\StatusPenyesuaianStok;
use App\Enums\Persediaan\StatusStokOpname;
use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Master\Gudang;
use App\Models\Persediaan\PenyesuaianStok;
use App\Models\Persediaan\PergerakanStok;
use App\Models\Persediaan\StokBatch;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class OtorisasiPenyesuaianIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal;

    #[Url(history: true)]
    public string $filterStatus = StatusPenyesuaianStok::Menunggu->value; // nilai StatusPenyesuaianStok atau 'SEMUA'

    #[Url(history: true)]
    public ?int $filterGudangId = null;

    public bool $showDetailModal = false;
    public ?PenyesuaianStok $selectedPenyesuaian = null;

    /**
     * Buka modal dialog dan muat data rincian record terpilih.
     */
    public function showDetail(int $id): void
    {
        $this->authorizeView();

        $this->selectedPenyesuaian = PenyesuaianStok::with([
            'stokOpname',
            'gudang',
            'pengaju',
            'penyetuju',
            'detail.barang.satuan',
            'detail.stokBatch.lokasiGudang',
        ])->findOrFail($id);
        abort_unless($this->canViewGudang((int) $this->selectedPenyesuaian->gudang_id), 403);

        $this->showDetailModal = true;
    }

    /**
     * Tampilkan modal konfirmasi persetujuan transaksi.
     */
    public function confirmApprove(int $id): void
    {
        $adj = PenyesuaianStok::with(['gudang', 'detail.barang'])->findOrFail($id);
        $this->authorizeOtorisasi($adj);

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

    /**
     * Eksekusi persetujuan penyesuaian stok dan mutasikan kuantitas batch gudang.
     */
    public function approvePenyesuaian(int $id): void
    {
        $adj = PenyesuaianStok::with(['stokOpname', 'detail.stokBatch', 'detail.barang'])->findOrFail($id);
        $this->authorizeOtorisasi($adj);

        if (! StatusPenyesuaianStok::tryFrom((string) $adj->status)?->bolehKe(StatusPenyesuaianStok::Disetujui)) {
            $this->toast("Dokumen penyesuaian telah diproses sebelumnya dengan status {$adj->status}.", 'danger');
            return;
        }

        $user = auth()->user();

        try {
            DB::transaction(function () use ($adj, $user) {
                // Kunci dokumen lalu cek ulang status: dua pengesahan bersamaan tidak boleh sama-sama memutasi stok.
                $adj = PenyesuaianStok::with(['stokOpname', 'detail.stokBatch', 'detail.barang'])->lockForUpdate()->findOrFail($adj->id);
                if (! StatusPenyesuaianStok::tryFrom((string) $adj->status)?->bolehKe(StatusPenyesuaianStok::Disetujui)) {
                    throw new Exception("Dokumen penyesuaian telah diproses sebelumnya dengan status {$adj->status}.");
                }

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
                    'status'        => StatusPenyesuaianStok::Disetujui->value,
                    'disetujui_oleh'=> $user->id,
                ]);

                if ($adj->stokOpname) {
                    $adj->stokOpname->update([
                        'status'        => StatusStokOpname::Disetujui->value,
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

    /**
     * Tampilkan modal konfirmasi penolakan transaksi.
     */
    public function confirmReject(int $id): void
    {
        $adj = PenyesuaianStok::findOrFail($id);
        $this->authorizeOtorisasi($adj);

        $this->askConfirmation(
            target: $id,
            action: 'rejectPenyesuaian',
            title: 'Tolak Penyesuaian Stok',
            description: "Apakah Anda yakin ingin menolak pengajuan penyesuaian stok {$adj->kode}? Sesi opname akan dikembalikan untuk investigasi atau hitung ulang.",
            buttonText: 'Tolak Penyesuaian',
            variant: 'danger'
        );
    }

    /**
     * Eksekusi penolakan usulan penyesuaian stok beserta alasan penolakan.
     */
    public function rejectPenyesuaian(int $id): void
    {
        $adj = PenyesuaianStok::with('stokOpname')->findOrFail($id);
        $this->authorizeOtorisasi($adj);

        if (! StatusPenyesuaianStok::tryFrom((string) $adj->status)?->bolehKe(StatusPenyesuaianStok::Ditolak)) {
            $this->toast("Dokumen penyesuaian telah diproses sebelumnya dengan status {$adj->status}.", 'danger');
            return;
        }

        $user = auth()->user();
        DB::transaction(function () use ($adj, $user) {
            $adj->update([
                'status'        => StatusPenyesuaianStok::Ditolak->value,
                'disetujui_oleh'=> $user->id,
            ]);

            if ($adj->stokOpname) {
                $adj->stokOpname->update([
                    'status' => StatusStokOpname::Ditolak->value,
                ]);
            }
        });

        if ($this->showDetailModal) {
            $this->showDetailModal = false;
        }
        $this->toast("Penyesuaian stok {$adj->kode} telah ditolak.", 'info');
    }

    /**
     * Otorisasi penyesuaian adalah tahap workflow kepala gudang: butuh permission aksi
     * dan role kepala_gudang pada gudangnya sendiri; hanya role global yang lintas gudang.
     */
    private function authorizeOtorisasi(PenyesuaianStok $adj): void
    {
        abort_unless($this->canOtorisasi($adj), 403);
    }

    /**
     * Periksa apakah pengguna memiliki hak otorisasi penyesuaian stok.
     */
    public function canOtorisasi(PenyesuaianStok $adj): bool
    {
        $user = auth()->user();
        $hasPermission = (bool) $user?->can('update_gudang_stok::otorisasi::penyesuaian');
        $isGlobalAdmin = (bool) $user?->hasRole(['super_admin', 'admin_logistik']);
        $isKepalaGudangPemilik = (bool) ($user?->hasRole('kepala_gudang')
            && $user->gudang_id
            && (int) $user->gudang_id === (int) $adj->gudang_id);

        return $hasPermission && ($isGlobalAdmin || $isKepalaGudangPemilik);
    }

    /**
     * Verifikasi wewenang pengguna untuk melihat data pada halaman ini.
     */
    private function authorizeView(): void
    {
        abort_unless(
            auth()->user()?->can('view_any_gudang_stok::otorisasi::penyesuaian') ||
            auth()->user()?->can('view_any_audit_manajemen::selisih::opname') ||
            auth()->user()?->can('view_gudang_stok::otorisasi::penyesuaian'),
            403
        );
    }

    /**
     * Periksa apakah pengguna memiliki hak akses baca secara global.
     */
    private function hasGlobalRead(): bool
    {
        // Auditor/manajemen membaca global hanya lewat permission view yang sudah dicek di render().
        return (bool) auth()->user()?->hasRole(['super_admin', 'admin_logistik', 'auditor', 'manajemen']);
    }

    /**
     * Periksa wewenang pengguna untuk melihat informasi gudang terpilih.
     */
    private function canViewGudang(int $gudangId): bool
    {
        $user = auth()->user();

        return $this->hasGlobalRead() || ($user?->gudang_id && (int) $user->gudang_id === $gudangId);
    }

    /**
     * Render tampilan blade komponen Livewire.
     */
    public function render()
    {
        $this->authorizeView();

        $query = PenyesuaianStok::with(['gudang', 'pengaju', 'penyetuju', 'stokOpname', 'detail'])
            ->withCount('detail')
            ->when($this->filterStatus !== 'SEMUA', fn ($q) => $q->where('status', $this->filterStatus ?: StatusPenyesuaianStok::Menunggu->value))
            ->when(! $this->hasGlobalRead(), fn ($q) => $q->where('gudang_id', auth()->user()?->gudang_id))
            ->when($this->filterGudangId, fn ($q) => $q->where('gudang_id', $this->filterGudangId))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('kode', 'ilike', "%{$this->search}%")
                        ->orWhere('alasan', 'ilike', "%{$this->search}%")
                        ->orWhereHas('gudang', fn ($g) => $g->where('nama', 'ilike', "%{$this->search}%"));
                });
            });

        $items = $query->orderBy('id', 'desc')->paginate($this->perPage);

        $daftarGudang = Cache::remember('dropdown_gudang_aktif', config('simlog.cache.dropdown_ttl'), fn () => Gudang::where('status', 'AKTIF')->orderBy('nama')->get());

        return view('livewire.persediaan.otorisasi-penyesuaian-index', [
            'items'        => $items,
            'daftarGudang' => $daftarGudang,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Otorisasi Penyesuaian Stok Opname']);
    }
}
