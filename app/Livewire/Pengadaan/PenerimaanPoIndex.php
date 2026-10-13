<?php

namespace App\Livewire\Pengadaan;

use App\Enums\Pengadaan\StatusPesananPembelian;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Livewire\Pengadaan\Concerns\HasTolakKiriman;
use App\Models\Master\Barang;
use App\Models\Master\Gudang;
use App\Models\Master\LokasiGudang;
use App\Models\Pengadaan\PenerimaanBarang;
use App\Models\Pengadaan\PesananPembelian;
use App\Services\Pengadaan\PenerimaanPoService;
use App\Support\Generator\CodeGenerator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

class PenerimaanPoIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout;
    use HasTolakKiriman;

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

    /**
     * Bypass scope gudang penerima hanya untuk role global operasional.
     */
    private function isGlobalAdmin(): bool
    {
        return (bool) auth()->user()?->hasRole(['super_admin', 'admin_logistik']);
    }

    /**
     * Baca riwayat penerimaan lintas gudang: role global serta auditor/manajemen
     * (yang tetap wajib lolos permission view).
     */
    private function hasGlobalRead(): bool
    {
        return (bool) auth()->user()?->hasRole(['super_admin', 'admin_logistik', 'auditor', 'manajemen']);
    }

    private function ownsGudang(?int $gudangId): bool
    {
        $userGudangId = auth()->user()?->gudang_id;

        return $gudangId && $userGudangId && (int) $userGudangId === (int) $gudangId;
    }

    private function authorizeGudangPenerima(): void
    {
        abort_unless(
            $this->isGlobalAdmin() || $this->ownsGudang($this->gudang_id),
            403,
            'Anda hanya dapat mencatat penerimaan ke gudang Anda sendiri.'
        );
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
            ->whereIn('status', StatusPesananPembelian::bisaDiterima())
            ->findOrFail($poId);

        $this->poId = $po->id;
        $this->po = $po;
        $this->kode = CodeGenerator::generate('RCV', PenerimaanBarang::class, 'kode', 'Ym', 3);
        $this->nomor_surat_jalan = '';
        $this->catatan = '';
        $userGudangId = auth()->user()->gudang_id;
        if (! $this->isGlobalAdmin()) {
            abort_unless($userGudangId, 403, 'Akun Anda tidak terhubung ke gudang penerima.');
            $this->gudang_id = (int) $userGudangId;
        } else {
            $this->gudang_id = ($userGudangId && Gudang::where('id', $userGudangId)->where('status', 'AKTIF')->exists())
                ? $userGudangId
                : Gudang::where('status', 'AKTIF')->value('id');
        }

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
        $this->authorizeGudangPenerima();

        $hasReceived = false;
        $totalNetReceived = 0;
        $customErrors = [];

        foreach ($this->itemRows as $idx => $row) {
            $terima  = (int) ($row['jumlah_diterima'] ?? 0);
            $rusak   = (int) ($row['jumlah_rusak'] ?? 0);
            $sisa    = (int) ($row['sisa'] ?? 0);
            $dipesan = (int) ($row['jumlah_dipesan'] ?? 0);

            if ($terima > 0) {
                $totalNetReceived += max(0, $terima - $rusak);
            }

            if ($rusak < 0) {
                $customErrors["itemRows.{$idx}.jumlah_rusak"] = "Jumlah rusak tidak boleh kurang dari 0.";
            } elseif ($rusak > $dipesan) {
                $customErrors["itemRows.{$idx}.jumlah_rusak"] = "Jumlah rusak tidak boleh melebihi barang dipesan (maks. {$dipesan}).";
            }

            if ($terima > 0) {
                $hasReceived = true;

                if ($terima > $sisa) {
                    $customErrors["itemRows.{$idx}.jumlah_diterima"] = "Jumlah diterima melebihi sisa pesanan (maks. {$sisa}).";
                }
                if ($rusak > $terima && ! isset($customErrors["itemRows.{$idx}.jumlah_rusak"])) {
                    $customErrors["itemRows.{$idx}.jumlah_rusak"] = "Jumlah rusak tidak boleh melebihi jumlah diterima.";
                }
                if (empty(trim($row['nomor_batch'] ?? ''))) {
                    $customErrors["itemRows.{$idx}.nomor_batch"] = "Nomor batch wajib diisi untuk barang yang diterima.";
                }
                if (! empty($row['lokasi_gudang_id'])) {
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

        if ($totalNetReceived <= 0) {
            $this->toast('Total barang layak diterima (nett) adalah 0 unit. Seluruh barang rusak. Silakan gunakan tombol "Tolak Kiriman Vendor" jika kiriman fisik ditolak.', 'danger');
            $customErrors['transaksi_nett'] = 'Total barang layak diterima (nett) adalah 0 unit. Tidak ada stok fisik yang dapat dimasukkan ke gudang.';
        }

        $totalRusak = collect($this->itemRows)->sum(fn ($r) => (int) ($r['jumlah_rusak'] ?? 0));
        if ($totalRusak > 0 && empty(trim($this->catatan))) {
            $customErrors['catatan'] = "Terdapat barang rusak ({$totalRusak} unit). Catatan/berita acara kerusakan wajib diisi sebagai bukti klaim retur vendor.";
        }
        if (! empty($customErrors)) {
            $this->setErrorBag($customErrors);
            return;
        }

        $service = app(PenerimaanPoService::class);
        $diterima = $service->prosesPenerimaan(
            $this->poId,
            $this->gudang_id,
            $this->nomor_surat_jalan,
            $this->kode,
            $this->catatan ?: null,
            $this->itemRows,
            auth()->user()
        );
        if (is_string($diterima)) {
            $this->toast($diterima, 'danger');
            return;
        }

        if (! $diterima) {
            $this->showModal = false;
            $this->toast('Pesanan pembelian sudah tidak dalam status yang dapat diterima. Muat ulang data dan periksa status PO.', 'danger');
            return;
        }

        $this->showModal = false;
        $this->toast("Penerimaan barang {$this->kode} berhasil disimpan.", 'success');
    }

    public function showDetail(int $id): void
    {
        abort_unless(auth()->user()?->can('view_any_gudang_stok::penerimaan::po') || auth()->user()?->can('view_gudang_stok::penerimaan::po'), 403);

        $penerimaan = PenerimaanBarang::with(['pesananPembelian.pemasok', 'gudang', 'penerima', 'detail.barang', 'detail.lokasiGudang'])
            ->findOrFail($id);
        abort_unless(
            $this->hasGlobalRead() || $this->ownsGudang($penerimaan->gudang_id),
            403,
            'Anda tidak memiliki akses ke penerimaan gudang lain.'
        );

        $this->selectedPenerimaan = $penerimaan;
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

        $countTracking = PesananPembelian::whereIn('status', StatusPesananPembelian::aktif())->count();
        $scopeRiwayat = fn ($q) => $q->when(! $this->hasGlobalRead(), fn ($s) => $s->where('gudang_id', auth()->user()?->gudang_id));
        $countRiwayat  = $scopeRiwayat(PenerimaanBarang::query())->count();

        if ($this->activeTab === 'tracking') {
            $poSortColumns = ['kode', 'tanggal_po', 'total_biaya', 'status'];
            $sortColumn = in_array($this->sortBy, $poSortColumns, true) ? $this->sortBy : 'tanggal_po';
            $sortDirection = in_array(strtolower($this->sortDirection), ['asc', 'desc'], true) ? strtolower($this->sortDirection) : 'desc';

            $items = PesananPembelian::with(['pemasok', 'detail.barang'])
                ->whereIn('status', StatusPesananPembelian::aktif())
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

            $items = $scopeRiwayat(PenerimaanBarang::with(['pesananPembelian', 'gudang', 'penerima']))
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

        $daftarGudang = Gudang::where('status', 'AKTIF')
            ->when(! $this->isGlobalAdmin(), fn ($q) => $q->where('id', auth()->user()?->gudang_id))
            ->orderBy('nama')->get();
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
