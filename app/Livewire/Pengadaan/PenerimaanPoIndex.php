<?php

namespace App\Livewire\Pengadaan;

use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\LokasiGudang;
use App\Models\PenerimaanBarang;
use App\Models\PenerimaanBarangDetail;
use App\Models\PergerakanStok;
use App\Models\PesananPembelian;
use App\Models\StokBatch;
use App\Support\Generator\CodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

class PenerimaanPoIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout;

    #[Url(history: true)]
    public string $activeTab = 'tracking'; // 'tracking' | 'riwayat'

    public bool $showModal = false;
    public bool $showDetailModal = false;
    public bool $showDetailPoModal = false;
    public bool $showRejectModal = false;
    public string $alasan_penolakan = '';
    public ?object $selectedPenerimaan = null;
    public ?PesananPembelian $selectedPo = null;

    #[Url(history: true)]
    public string $filterStatus = '';

    public function switchTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['tracking', 'riwayat'], true) ? $tab : 'tracking';
        $this->filterStatus = '';
        $this->search = '';
        $this->sortBy = '';
        $this->sortDirection = 'desc';
        $this->resetPage();
    }
    // Form fields
    public ?int $poId = null;
    public ?object $po = null;
    public string $kode = '';
    public string $nomor_surat_jalan = '';
    public string $catatan = '';
    public ?int $gudang_id = null;
    public array $itemRows = [];

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedGudangId(): void
    {
        foreach ($this->itemRows as $idx => $row) {
            $this->itemRows[$idx]['lokasi_gudang_id'] = null;
        }
    }

    public function terima(int $poId): void
    {
        abort_unless(auth()->user()?->can('create_gudang_stok::penerimaan::po'), 403);

        $po = PesananPembelian::with(['detail.barang', 'pemasok'])
            ->whereIn('status', ['DIKIRIM_VENDOR', 'DITERIMA_SEBAGIAN'])
            ->findOrFail($poId);

        $this->poId = $po->id;
        $this->po = $po;
        $this->kode = CodeGenerator::generate('RCV', PenerimaanBarang::class, 'kode', 'Ym', 3);
        $this->nomor_surat_jalan = '';
        $this->catatan = '';
        $userGudangId = auth()->user()->gudang_id;
        $this->gudang_id = ($userGudangId && Gudang::where('id', $userGudangId)->where('status', 'AKTIF')->exists())
            ? $userGudangId
            : Gudang::where('status', 'AKTIF')->value('id');

        $this->itemRows = $po->detail->map(fn ($d) => [
            'po_detail_id'       => $d->id,
            'barang_id'          => $d->barang_id,
            'nama_barang'        => $d->barang->nama,
            'jumlah_dipesan'     => $d->jumlah_dipesan,
            'jumlah_sudah'       => $d->jumlah_diterima,
            'sisa'               => max(0, $d->jumlah_dipesan - $d->jumlah_diterima),
            'harga_satuan'       => (float) $d->harga_satuan,
            'jumlah_diterima'    => 0,
            'jumlah_rusak'       => 0,
            'nomor_batch'        => '',
            'tanggal_kedaluwarsa' => '',
            'lokasi_gudang_id'   => null,
        ])->toArray();

        $this->showModal = true;
    }

    public function savePenerimaan(): void
    {
        abort_unless(auth()->user()?->can('create_gudang_stok::penerimaan::po'), 403);

        $this->validate([
            'nomor_surat_jalan' => 'required|string|min:3|max:100',
            'gudang_id'         => ['required', Rule::exists('gudang', 'id')->where('status', 'AKTIF')],
            'catatan'           => 'nullable|string|max:1000',
            'itemRows'          => 'required|array|min:1',
        ], [
            'nomor_surat_jalan.required' => 'Nomor surat jalan pengiriman dari vendor wajib diisi.',
            'nomor_surat_jalan.min'      => 'Nomor surat jalan minimal 3 karakter.',
            'gudang_id.required'         => 'Gudang penerima logistik wajib dipilih.',
        ]);

        $hasReceived = false;
        $customErrors = [];

        foreach ($this->itemRows as $idx => $row) {
            $terima = (int) ($row['jumlah_diterima'] ?? 0);
            $rusak  = (int) ($row['jumlah_rusak'] ?? 0);
            $sisa   = (int) ($row['sisa'] ?? 0);

            if ($terima > 0) {
                $hasReceived = true;

                if ($terima > $sisa) {
                    $customErrors["itemRows.{$idx}.jumlah_diterima"] = "Jumlah diterima melebihi sisa pesanan (maks. {$sisa}).";
                }
                if ($rusak > $terima) {
                    $customErrors["itemRows.{$idx}.jumlah_rusak"] = "Jumlah rusak tidak boleh melebihi jumlah diterima.";
                }
                if (empty(trim($row['nomor_batch'] ?? ''))) {
                    $customErrors["itemRows.{$idx}.nomor_batch"] = "Nomor batch wajib diisi untuk barang yang diterima.";
                }
                if (empty($row['lokasi_gudang_id'])) {
                    $customErrors["itemRows.{$idx}.lokasi_gudang_id"] = "Lokasi rak simpan wajib dipilih.";
                } else {
                    $isRackValid = LokasiGudang::where('id', $row['lokasi_gudang_id'])
                        ->where('gudang_id', $this->gudang_id)
                        ->where('status', 'AKTIF')
                        ->exists();

                    if (! $isRackValid) {
                        $customErrors["itemRows.{$idx}.lokasi_gudang_id"] = "Rak yang dipilih bukan bagian dari fasilitas gudang penerima ini atau sedang nonaktif.";
                    }
                }
            }
        }

        if (! $hasReceived) {
            $this->toast('Minimal satu komoditas harus memiliki jumlah diterima > 0.', 'danger');
            return;
        }

        if (! empty($customErrors)) {
            $this->setErrorBag($customErrors);
            return;
        }

        DB::transaction(function () {
            $po = PesananPembelian::with('detail')->findOrFail($this->poId);

            // 1. Update jumlah_diterima pada detail PO terlebih dahulu
            foreach ($this->itemRows as $row) {
                if ($row['jumlah_diterima'] <= 0) {
                    continue;
                }

                $poDetail = $po->detail->firstWhere('id', $row['po_detail_id']);
                if ($poDetail) {
                    $poDetail->increment('jumlah_diterima', $row['jumlah_diterima']);
                }

                if ((float) $row['harga_satuan'] > 0) {
                    Barang::where('id', $row['barang_id'])->update([
                        'harga_beli_terakhir' => $row['harga_satuan'],
                    ]);
                }
            }

            // 2. Evaluasi status pemenuhan PO secara dinamis
            $po->refresh();
            $po->load('detail');
            $allReceived = $po->detail->every(fn ($d) => (int) $d->jumlah_diterima >= (int) $d->jumlah_dipesan);
            $statusRcv = $allReceived ? 'LENGKAP' : 'SEBAGIAN';
            $statusPo  = $allReceived ? 'DITERIMA_LENGKAP' : 'DITERIMA_SEBAGIAN';

            // 3. Buat dokumen penerimaan dengan status dinamis
            $penerimaan = PenerimaanBarang::create([
                'kode'                  => $this->kode,
                'pesanan_pembelian_id'  => $this->poId,
                'gudang_id'             => $this->gudang_id,
                'nomor_surat_jalan'     => $this->nomor_surat_jalan,
                'tanggal_terima'        => now()->toDateString(),
                'status'                => $statusRcv,
                'catatan'               => $this->catatan ?: null,
                'diterima_oleh'         => auth()->id(),
            ]);

            // 4. Catat rincian penerimaan dan kartu stok batch
            foreach ($this->itemRows as $row) {
                if ($row['jumlah_diterima'] <= 0) {
                    continue;
                }

                $netReceived = $row['jumlah_diterima'] - $row['jumlah_rusak'];

                PenerimaanBarangDetail::create([
                    'penerimaan_barang_id' => $penerimaan->id,
                    'barang_id'            => $row['barang_id'],
                    'lokasi_gudang_id'     => $row['lokasi_gudang_id'],
                    'nomor_batch'          => $row['nomor_batch'],
                    'tanggal_kedaluwarsa'  => $row['tanggal_kedaluwarsa'] ?: null,
                    'jumlah_diterima'      => $row['jumlah_diterima'],
                    'jumlah_rusak'         => $row['jumlah_rusak'],
                    'harga_satuan'         => $row['harga_satuan'],
                ]);

                if ($netReceived > 0) {
                    $batch = StokBatch::create([
                        'barang_id'           => $row['barang_id'],
                        'gudang_id'           => $this->gudang_id,
                        'lokasi_gudang_id'    => $row['lokasi_gudang_id'],
                        'nomor_batch'         => $row['nomor_batch'],
                        'tanggal_kedaluwarsa' => $row['tanggal_kedaluwarsa'] ?: null,
                        'jumlah'              => $netReceived,
                        'harga_satuan'        => $row['harga_satuan'],
                        'status'              => 'AKTIF',
                    ]);

                    PergerakanStok::create([
                        'barang_id'        => $row['barang_id'],
                        'stok_batch_id'    => $batch->id,
                        'gudang_id'        => $this->gudang_id,
                        'lokasi_gudang_id' => $row['lokasi_gudang_id'],
                        'jenis'            => 'MASUK',
                        'jumlah'           => $netReceived,
                        'saldo_sebelum'    => 0,
                        'saldo_sesudah'    => $netReceived,
                        'referensi_jenis'  => 'PENERIMAAN_PO',
                        'referensi_id'     => $penerimaan->id,
                        'keterangan'       => "Penerimaan PO {$po->kode} - Batch {$row['nomor_batch']}",
                        'dibuat_oleh'      => auth()->id(),
                    ]);
                }
            }

            // 5. Update status PO resmi
            $po->update(['status' => $statusPo]);

            // 6. Jika seluruh barang PO telah diterima lengkap, update status PR induk menjadi SELESAI
            if ($statusPo === 'DITERIMA_LENGKAP' && $po->permintaan_pengadaan_id) {
                $po->permintaanPengadaan()->update(['status' => 'SELESAI']);
            }
        });

        $this->showModal = false;
        $this->toast("Penerimaan barang {$this->kode} berhasil disimpan.", 'success');
    }

    public function openRejectModal(): void
    {
        abort_unless(auth()->user()?->can('create_gudang_stok::penerimaan::po'), 403);

        $this->validate([
            'nomor_surat_jalan' => 'required|string|min:3|max:100',
            'gudang_id'         => ['required', Rule::exists('gudang', 'id')->where('status', 'AKTIF')],
        ], [
            'nomor_surat_jalan.required' => 'Nomor surat jalan vendor wajib diisi sebelum menolak kiriman.',
            'nomor_surat_jalan.min'      => 'Nomor surat jalan minimal 3 karakter.',
            'gudang_id.required'         => 'Gudang penerima logistik wajib dipilih.',
        ]);

        $this->alasan_penolakan = '';
        $this->showModal = false;
        $this->showRejectModal = true;
    }

    public function cancelReject(): void
    {
        $this->showRejectModal = false;
        $this->showModal = true;
    }

    public function executeTolakKiriman(): void
    {
        abort_unless(auth()->user()?->can('create_gudang_stok::penerimaan::po'), 403);

        $this->validate([
            'nomor_surat_jalan' => 'required|string|min:3|max:100',
            'gudang_id'         => ['required', Rule::exists('gudang', 'id')->where('status', 'AKTIF')],
            'alasan_penolakan'  => 'required|string|min:5|max:1000',
        ], [
            'nomor_surat_jalan.required' => 'Nomor surat jalan vendor wajib diisi.',
            'gudang_id.required'         => 'Gudang penerima logistik wajib dipilih.',
            'alasan_penolakan.required'  => 'Alasan penolakan barang wajib dijelaskan.',
            'alasan_penolakan.min'       => 'Alasan penolakan minimal 5 karakter.',
        ]);

        $rcvKode = CodeGenerator::generate('RCV', PenerimaanBarang::class, 'kode', 'Ym', 3);

        DB::transaction(function () use ($rcvKode) {
            $po = PesananPembelian::findOrFail($this->poId);

            // Buat dokumen penerimaan berstatus DITOLAK
            $penerimaan = PenerimaanBarang::create([
                'kode'                 => $rcvKode,
                'pesanan_pembelian_id' => $this->poId,
                'gudang_id'            => $this->gudang_id,
                'nomor_surat_jalan'    => $this->nomor_surat_jalan,
                'tanggal_terima'       => now()->toDateString(),
                'status'               => 'DITOLAK',
                'catatan'              => '[DITOLAK PETUGAS GUDANG]: ' . trim($this->alasan_penolakan),
                'diterima_oleh'        => auth()->id(),
            ]);

            $defaultRackId = LokasiGudang::where('gudang_id', $this->gudang_id)->where('status', 'AKTIF')->value('id')
                ?: LokasiGudang::where('status', 'AKTIF')->value('id');

            // Catat detail komoditas yang ditolak (kuantitas diterima = 0)
            foreach ($this->itemRows as $row) {
                PenerimaanBarangDetail::create([
                    'penerimaan_barang_id' => $penerimaan->id,
                    'barang_id'            => $row['barang_id'],
                    'lokasi_gudang_id'     => ! empty($row['lokasi_gudang_id']) ? $row['lokasi_gudang_id'] : $defaultRackId,
                    'nomor_batch'          => 'REJECTED',
                    'tanggal_kedaluwarsa'  => null,
                    'jumlah_diterima'      => 0,
                    'jumlah_rusak'         => (int) ($row['sisa'] ?? $row['jumlah_dipesan']),
                    'harga_satuan'         => $row['harga_satuan'],
                ]);
            }

            // PO status tetap dipertahankan pada status aktifnya (DIKIRIM_VENDOR atau DITERIMA_SEBAGIAN)
            // agar vendor tetap berkewajiban mengirimkan barang pengganti
        });

        $poKode = $this->po?->kode ?? 'PO';
        $this->showRejectModal = false;
        $this->showModal = false;
        $this->activeTab = 'riwayat';
        $this->filterStatus = '';
        $this->search = '';
        $this->resetPage();
        $this->toast("Kiriman fisik {$poKode} berhasil ditolak. Berita acara penolakan diterbitkan dengan nomor {$rcvKode}.", 'info');
    }
    public function showDetail(int $id): void
    {
        abort_unless(auth()->user()?->can('view_any_gudang_stok::penerimaan::po') || auth()->user()?->can('view_gudang_stok::penerimaan::po'), 403);

        $this->selectedPenerimaan = PenerimaanBarang::with(['pesananPembelian.pemasok', 'gudang', 'penerima', 'detail.barang', 'detail.lokasiGudang'])
            ->findOrFail($id);
        $this->showDetailModal = true;
    }

    public function showDetailPo(int $id): void
    {
        abort_unless(auth()->user()?->can('view_any_gudang_stok::penerimaan::po') || auth()->user()?->can('view_gudang_stok::penerimaan::po') || auth()->user()?->can('create_gudang_stok::penerimaan::po'), 403);

        $this->selectedPo = PesananPembelian::with(['pemasok', 'detail.barang', 'permintaanPengadaan', 'pembuat', 'penerimaanBarang'])
            ->findOrFail($id);
        $this->showDetailPoModal = true;
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('view_any_gudang_stok::penerimaan::po') || auth()->user()?->can('view_gudang_stok::penerimaan::po'), 403);

        $countTracking = PesananPembelian::whereIn('status', ['DITERBITKAN', 'DIKIRIM_VENDOR', 'DITERIMA_SEBAGIAN'])->count();
        $countRiwayat  = PenerimaanBarang::count();

        if ($this->activeTab === 'tracking') {
            $poSortColumns = ['kode', 'tanggal_po', 'total_biaya', 'status'];
            $sortColumn = in_array($this->sortBy, $poSortColumns, true) ? $this->sortBy : 'tanggal_po';
            $sortDirection = in_array(strtolower($this->sortDirection), ['asc', 'desc'], true) ? strtolower($this->sortDirection) : 'desc';

            $items = PesananPembelian::with(['pemasok', 'detail.barang'])
                ->whereIn('status', ['DITERBITKAN', 'DIKIRIM_VENDOR', 'DITERIMA_SEBAGIAN'])
                ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
                ->when($this->search, function ($q) {
                    $q->where(function ($sub) {
                        $sub->where('kode', 'ilike', "%{$this->search}%")
                            ->orWhereHas('pemasok', fn ($p) => $p->where('nama', 'ilike', "%{$this->search}%"));
                    });
                })
                ->orderBy($sortColumn, $sortDirection)
                ->paginate($this->perPage);
        } else {
            $rcvSortColumns = ['kode', 'tanggal_terima', 'status', 'nomor_surat_jalan'];
            $sortColumn = in_array($this->sortBy, $rcvSortColumns, true) ? $this->sortBy : 'tanggal_terima';
            $sortDirection = in_array(strtolower($this->sortDirection), ['asc', 'desc'], true) ? strtolower($this->sortDirection) : 'desc';

            $items = PenerimaanBarang::with(['pesananPembelian', 'gudang', 'penerima'])
                ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
                ->when($this->search, function ($q) {
                    $q->where(function ($sub) {
                        $sub->where('kode', 'ilike', "%{$this->search}%")
                            ->orWhere('nomor_surat_jalan', 'ilike', "%{$this->search}%")
                            ->orWhereHas('pesananPembelian', fn ($r) => $r->where('kode', 'ilike', "%{$this->search}%"));
                    });
                })
                ->orderBy($sortColumn, $sortDirection)
                ->paginate($this->perPage);
        }

        $daftarGudang = Gudang::where('status', 'AKTIF')->orderBy('nama')->get();
        $daftarLokasiGudang = $this->gudang_id
            ? LokasiGudang::where('gudang_id', $this->gudang_id)->where('status', 'AKTIF')->orderBy('nama')->get()
            : collect();

        return view('livewire.pengadaan.penerimaan-po-index', [
            'items'              => $items,
            'countTracking'      => $countTracking,
            'countRiwayat'       => $countRiwayat,
            'daftarGudang'       => $daftarGudang,
            'daftarLokasiGudang' => $daftarLokasiGudang,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Penerimaan Barang (Inbound)']);
    }
}
