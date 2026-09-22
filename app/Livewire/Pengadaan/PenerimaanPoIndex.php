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
use Livewire\Attributes\Url;
use Livewire\Component;

class PenerimaanPoIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout;

    public bool $showModal = false;
    public bool $showDetailModal = false;

    public ?object $selectedPenerimaan = null;

    #[Url(history: true)]
    public string $filterStatus = '';

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
        $this->gudang_id = auth()->user()->gudang_id;

        $this->itemRows = $po->detail->map(fn ($d) => [
            'po_detail_id'       => $d->id,
            'barang_id'          => $d->barang_id,
            'nama_barang'        => $d->barang->nama,
            'jumlah_dipesan'     => $d->jumlah_dipesan,
            'jumlah_sudah'       => $d->jumlah_diterima,
            'sisa'               => $d->jumlah_dipesan - $d->jumlah_diterima,
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
            'nomor_surat_jalan'              => 'required|string|max:100',
            'gudang_id'                      => 'required|exists:gudang,id',
            'catatan'                        => 'nullable|string|max:1000',
            'itemRows'                       => 'required|array|min:1',
            'itemRows.*.jumlah_diterima'     => 'required|integer|min:0',
            'itemRows.*.jumlah_rusak'        => 'required|integer|min:0',
            'itemRows.*.nomor_batch'         => 'required|string|max:100',
            'itemRows.*.tanggal_kedaluwarsa' => 'nullable|date',
            'itemRows.*.lokasi_gudang_id'    => 'required|exists:lokasi_gudang,id',
        ]);

        // At least one item must have jumlah_diterima > 0
        $hasReceived = collect($this->itemRows)->contains(fn ($r) => $r['jumlah_diterima'] > 0);
        if (! $hasReceived) {
            $this->toast('Minimal satu item harus memiliki jumlah diterima > 0.', 'danger');
            return;
        }

        DB::transaction(function () {
            $penerimaan = PenerimaanBarang::create([
                'kode'                  => $this->kode,
                'pesanan_pembelian_id'  => $this->poId,
                'gudang_id'             => $this->gudang_id,
                'nomor_surat_jalan'     => $this->nomor_surat_jalan,
                'tanggal_terima'        => now()->toDateString(),
                'status'                => 'LENGKAP',
                'catatan'               => $this->catatan ?: null,
                'diterima_oleh'         => auth()->id(),
            ]);

            $po = PesananPembelian::with('detail')->findOrFail($this->poId);

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

                // Create StokBatch for net good items
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

                // Update PO detail jumlah_diterima
                $poDetail = $po->detail->firstWhere('id', $row['po_detail_id']);
                if ($poDetail) {
                    $poDetail->increment('jumlah_diterima', $row['jumlah_diterima']);
                }

                // Update harga beli terakhir di master barang
                if ((float) $row['harga_satuan'] > 0) {
                    Barang::where('id', $row['barang_id'])->update([
                        'harga_beli_terakhir' => $row['harga_satuan'],
                    ]);
                }
            }

            // Refresh and check if all PO items fully received
            $po->refresh();
            $po->load('detail');
            $allReceived = $po->detail->every(fn ($d) => $d->jumlah_diterima >= $d->jumlah_dipesan);
            $po->update(['status' => $allReceived ? 'DITERIMA_LENGKAP' : 'DITERIMA_SEBAGIAN']);
        });

        $this->showModal = false;
        $this->toast("Penerimaan barang {$this->kode} berhasil disimpan.", 'success');
    }

    public function showDetail(int $id): void
    {
        abort_unless(auth()->user()?->can('view_any_gudang_stok::penerimaan::po') || auth()->user()?->can('view_gudang_stok::penerimaan::po'), 403);

        $this->selectedPenerimaan = PenerimaanBarang::with(['pesananPembelian.pemasok', 'gudang', 'penerima', 'detail.barang', 'detail.lokasiGudang'])
            ->findOrFail($id);
        $this->showDetailModal = true;
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('view_any_gudang_stok::penerimaan::po') || auth()->user()?->can('view_gudang_stok::penerimaan::po'), 403);

        $items = PenerimaanBarang::with(['pesananPembelian', 'gudang', 'penerima'])
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('kode', 'ilike', "%{$this->search}%")
                        ->orWhere('nomor_surat_jalan', 'ilike', "%{$this->search}%")
                        ->orWhereHas('pesananPembelian', fn ($r) => $r->where('kode', 'ilike', "%{$this->search}%"));
                });
            })
            ->orderBy($this->sortBy ?: 'id', $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);

        $daftarPo = PesananPembelian::whereIn('status', ['DIKIRIM_VENDOR', 'DITERIMA_SEBAGIAN'])->get();
        $daftarGudang = Gudang::all();
        $daftarLokasiGudang = $this->gudang_id
            ? LokasiGudang::where('gudang_id', $this->gudang_id)->get()
            : collect();

        return view('livewire.pengadaan.penerimaan-po-index', [
            'items'              => $items,
            'daftarPo'           => $daftarPo,
            'daftarGudang'       => $daftarGudang,
            'daftarLokasiGudang' => $daftarLokasiGudang,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Penerimaan Barang (Inbound)']);
    }
}
