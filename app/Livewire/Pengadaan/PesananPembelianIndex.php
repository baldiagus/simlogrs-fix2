<?php

namespace App\Livewire\Pengadaan;

use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Pemasok;
use App\Models\PermintaanPengadaan;
use App\Models\PesananPembelian;
use App\Models\PesananPembelianDetail;
use App\Support\Generator\CodeGenerator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class PesananPembelianIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout;

    public bool $showModal = false;
    public bool $showDetailModal = false;
    public ?object $selectedPo = null;

    #[Url(history: true)]
    public string $filterStatus = '';

    // Konversi PO form fields
    public ?int $prId = null;
    public ?int $pemasok_id = null;
    public ?string $estimasi_tanggal_kirim = null;
    public string $syarat_pembayaran = 'TEMPO_30_HARI';
    public string $catatan = '';
    public string $kode = '';
    public array $itemRows = [];

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function konversiPo(int $prId): void
    {
        abort_unless(auth()->user()?->can('create_pengadaan::pesanan::pembelian'), 403);

        $pr = PermintaanPengadaan::with('detail.barang')->findOrFail($prId);

        $this->reset(['pemasok_id', 'estimasi_tanggal_kirim', 'catatan', 'itemRows']);
        $this->prId = $pr->id;
        $this->syarat_pembayaran = 'TEMPO_30_HARI';
        $this->kode = CodeGenerator::generatePo();

        $this->itemRows = $pr->detail->map(fn ($d) => [
            'barang_id'    => $d->barang_id,
            'nama_barang'  => $d->barang->nama ?? '-',
            'jumlah'       => $d->jumlah_disetujui ?: $d->jumlah,
            'harga_satuan' => 0,
            'subtotal'     => 0,
        ])->toArray();

        $this->showModal = true;
    }

    public function updatedItemRows(): void
    {
        foreach ($this->itemRows as $i => $row) {
            $harga = (float) ($row['harga_satuan'] ?? 0);
            $jumlah = (int) ($row['jumlah'] ?? 0);
            $this->itemRows[$i]['subtotal'] = $harga * $jumlah;
        }
    }

    public function savePo(): void
    {
        abort_unless(auth()->user()?->can('create_pengadaan::pesanan::pembelian'), 403);

        $this->validate([
            'pemasok_id'            => 'required|exists:pemasok,id',
            'estimasi_tanggal_kirim' => 'nullable|date',
            'syarat_pembayaran'     => 'required|in:TEMPO_30_HARI,TEMPO_60_HARI,TUNAI',
            'catatan'               => 'nullable|string|max:1000',
            'itemRows'              => 'required|array|min:1',
            'itemRows.*.harga_satuan' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () {
            $totalBiaya = collect($this->itemRows)->sum('subtotal');

            $po = PesananPembelian::create([
                'kode'                    => $this->kode ?: CodeGenerator::generatePo(),
                'pemasok_id'              => $this->pemasok_id,
                'permintaan_pengadaan_id' => $this->prId,
                'tanggal_po'              => now()->toDateString(),
                'estimasi_tanggal_kirim'  => $this->estimasi_tanggal_kirim,
                'total_biaya'             => $totalBiaya,
                'syarat_pembayaran'       => $this->syarat_pembayaran,
                'status'                  => 'DITERBITKAN',
                'catatan'                 => $this->catatan,
                'dibuat_oleh'             => auth()->id(),
            ]);

            foreach ($this->itemRows as $row) {
                PesananPembelianDetail::create([
                    'pesanan_pembelian_id' => $po->id,
                    'barang_id'            => $row['barang_id'],
                    'jumlah_dipesan'       => $row['jumlah'],
                    'harga_satuan'         => $row['harga_satuan'],
                    'subtotal'             => $row['subtotal'],
                ]);
            }
        });

        $this->showModal = false;
        $this->toast('Pesanan Pembelian berhasil dibuat.', 'success');
    }

    public function showDetail(int $id): void
    {
        abort_unless(auth()->user()?->can('view_pengadaan::pesanan::pembelian'), 403);

        $this->selectedPo = PesananPembelian::with(['pemasok', 'permintaanPengadaan', 'detail.barang', 'pembuat'])
            ->findOrFail($id);
        $this->showDetailModal = true;
    }

    public function updateStatusKirim(int $id): void
    {
        abort_unless(auth()->user()?->can('update_pengadaan::pesanan::pembelian'), 403);

        $po = PesananPembelian::findOrFail($id);

        if ($po->status !== 'DITERBITKAN') {
            $this->toast('Status PO tidak dapat diubah.', 'danger');
            return;
        }

        $po->update(['status' => 'DIKIRIM_VENDOR']);
        $this->toast("PO {$po->kode} telah diperbarui ke status DIKIRIM VENDOR.", 'success');
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('view_any_pengadaan::pesanan::pembelian') || auth()->user()?->can('view_pengadaan::pesanan::pembelian'), 403);

        $daftarPemasok = Pemasok::where('status', 'AKTIF')->get();

        $daftarPrSiapKonversi = PermintaanPengadaan::where('status', 'DITERUSKAN_KE_PENGADAAN')
            ->whereDoesntHave('pesananPembelian')
            ->get();

        $items = PesananPembelian::with(['pemasok', 'permintaanPengadaan', 'detail', 'pembuat'])
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('kode', 'ilike', "%{$this->search}%")
                        ->orWhereHas('pemasok', fn ($p) => $p->where('nama', 'ilike', "%{$this->search}%"));
                });
            })
            ->orderBy($this->sortBy ?: 'id', $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);

        return view('livewire.pengadaan.pesanan-pembelian-index', [
            'items'                 => $items,
            'daftarPemasok'         => $daftarPemasok,
            'daftarPrSiapKonversi'  => $daftarPrSiapKonversi,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Pesanan Pembelian (PO)']);
    }
}
