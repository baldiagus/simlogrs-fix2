<?php

namespace App\Livewire\Persediaan;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Gudang;
use App\Models\PenyesuaianStok;
use App\Models\PenyesuaianStokDetail;
use App\Models\StokBatch;
use App\Models\StokOpname;
use App\Models\StokOpnameDetail;
use App\Support\Generator\CodeGenerator;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Rap2hpoutre\FastExcel\FastExcel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class StockOpnameIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal, WithFileUploads;

    #[Url(history: true)]
    public string $filterStatus = '';

    #[Url(history: true)]
    public ?int $filterGudangId = null;

    // Modal Inisiasi Opname
    public bool $showInisiasiModal = false;

    #[Validate('required|exists:gudang,id')]
    public ?int $gudang_id = null;

    #[Validate('required|date')]
    public string $tanggal_opname = '';

    #[Validate('nullable|string')]
    public ?string $catatan = null;

    // Modal Input Blind Count
    public bool $showCountModal = false;
    public ?StokOpname $activeOpname = null;
    public array $countRows = [];

    // Modal QR Code Batch
    public bool $showQrModal = false;
    public ?StokBatch $selectedBatchQr = null;
    public ?string $qrCodeSvg = null;

    // Modal Detail Opname
    public bool $showDetailModal = false;
    public ?StokOpname $selectedOpname = null;

    // Excel Upload
    public $fileExcel = null;

    public function mount(): void
    {
        $this->tanggal_opname = now()->toDateString();
    }

    public function openInisiasi(): void
    {
        abort_unless(
            auth()->user()?->can('create_gudang_stok::stok::opname') ||
            auth()->user()?->can('page_KepalaGudangDashboard') ||
            auth()->user()?->can('page_Dashboard'),
            403
        );

        $this->reset(['gudang_id', 'catatan']);
        $user = auth()->user();
        if ($user?->gudang_id) {
            $this->gudang_id = $user->gudang_id;
        }
        $this->tanggal_opname = now()->toDateString();
        $this->showInisiasiModal = true;
    }

    public function inisiasiOpname(): void
    {
        abort_unless(
            auth()->user()?->can('create_gudang_stok::stok::opname') ||
            auth()->user()?->can('page_KepalaGudangDashboard') ||
            auth()->user()?->can('page_Dashboard'),
            403
        );

        $this->validate([
            'gudang_id'      => 'required|exists:gudang,id',
            'tanggal_opname' => 'required|date',
            'catatan'        => 'nullable|string',
        ], [
            'gudang_id.required'      => 'Fasilitas gudang yang diopname wajib dipilih.',
            'tanggal_opname.required' => 'Tanggal pelaksanaan opname fisik wajib diisi.',
        ]);

        $user = auth()->user();
        $gudang = Gudang::findOrFail($this->gudang_id);

        // Ambil seluruh batch aktif yang berada di gudang ini
        $batches = StokBatch::where('gudang_id', $gudang->id)
            ->where('status', 'AKTIF')
            ->get();

        if ($batches->isEmpty()) {
            $this->toast("Tidak ada stok batch aktif di gudang '{$gudang->nama}' untuk diopname.", 'danger');
            return;
        }

        $kodeOpn = CodeGenerator::generateOpn();

        DB::transaction(function () use ($kodeOpn, $gudang, $user, $batches) {
            $opname = StokOpname::create([
                'kode'           => $kodeOpn,
                'gudang_id'      => $gudang->id,
                'tanggal_opname' => $this->tanggal_opname,
                'status'         => 'BERJALAN',
                'catatan'        => $this->catatan,
                'dibuat_oleh'    => $user->id,
            ]);

            foreach ($batches as $b) {
                StokOpnameDetail::create([
                    'stok_opname_id' => $opname->id,
                    'barang_id'      => $b->barang_id,
                    'stok_batch_id'  => $b->id,
                    'jumlah_sistem'  => $b->jumlah,
                    'jumlah_fisik'   => 0,
                    'selisih'        => 0 - $b->jumlah,
                    'catatan'        => null,
                ]);
            }
        });

        $this->showInisiasiModal = false;
        $this->toast("Siklus Stock Opname '{$kodeOpn}' berhasil dibuka dengan status BERJALAN.", 'success');
    }

    public function openHitungFisik(int $opnameId): void
    {
        abort_unless(
            auth()->user()?->can('update_gudang_stok::stok::opname') ||
            auth()->user()?->can('create_gudang_stok::stok::opname') ||
            auth()->user()?->can('page_KepalaGudangDashboard') ||
            auth()->user()?->can('page_Dashboard'),
            403,
            'Anda tidak memiliki wewenang mengakses formulir hitung fisik.'
        );

        $this->activeOpname = StokOpname::with([
            'gudang',
            'detail.barang.satuan',
            'detail.stokBatch.lokasiGudang',
        ])->findOrFail($opnameId);

        $this->countRows = [];
        foreach ($this->activeOpname->detail as $det) {
            $this->countRows[$det->id] = [
                'id'            => $det->id,
                'barang_nama'   => $det->barang?->nama,
                'barang_kode'   => $det->barang?->kode,
                'nomor_batch'   => $det->stokBatch?->nomor_batch,
                'lokasi_rak'    => $det->stokBatch?->lokasiGudang?->nama ?? 'Tanpa Rak',
                'satuan'        => $det->barang?->satuan?->simbol ?? 'Unit',
                'jumlah_sistem' => $det->jumlah_sistem,
                'jumlah_fisik'  => $det->jumlah_fisik,
                'catatan'       => $det->catatan ?? '',
            ];
        }

        $this->showCountModal = true;
    }

    public function saveHitungFisik(): void
    {
        if (! $this->activeOpname) {
            return;
        }
        abort_unless(
            auth()->user()?->can('update_gudang_stok::stok::opname') ||
            auth()->user()?->can('create_gudang_stok::stok::opname') ||
            auth()->user()?->can('page_KepalaGudangDashboard') ||
            auth()->user()?->can('page_Dashboard'),
            403,
            'Anda tidak memiliki wewenang menginput hasil stock opname.'
        );

        $user = auth()->user();
        $isGlobalAdmin = $user && ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard'));
        if (! $isGlobalAdmin && $user?->gudang_id && (int) $user?->gudang_id !== (int) $this->activeOpname->gudang_id) {
            abort(403, 'Anda tidak memiliki wewenang memproses opname di fasilitas gudang ini.');
        }


        $user = auth()->user();

        DB::transaction(function () use ($user) {
            $hasDifference = false;
            $selisihRows = [];

            foreach ($this->countRows as $detId => $row) {
                $detail = StokOpnameDetail::with(['stokBatch', 'barang'])->findOrFail($detId);
                $fisik = max(0, (int) ($row['jumlah_fisik'] ?? 0));
                $selisih = $fisik - $detail->jumlah_sistem;

                $detail->update([
                    'jumlah_fisik' => $fisik,
                    'selisih'      => $selisih,
                    'catatan'      => $row['catatan'] ?: null,
                ]);

                if ($selisih != 0) {
                    $hasDifference = true;
                    $selisihRows[] = [
                        'barang_id'     => $detail->barang_id,
                        'stok_batch_id' => $detail->stok_batch_id,
                        'jumlah'        => abs($selisih),
                        'jenis'         => $selisih > 0 ? 'TAMBAH' : 'KURANG',
                        'harga_satuan'  => $detail->stokBatch?->harga_satuan ?? 0,
                        'subtotal'      => abs($selisih) * (float) ($detail->stokBatch?->harga_satuan ?? 0),
                        'catatan'       => $row['catatan'] ?: 'Selisih opname fisik',
                    ];
                }
            }

            if ($hasDifference) {
                // Ada selisih -> status MENUNGGU_APPROVAL & terbitkan dokumen PenyesuaianStok
                $this->activeOpname->update([
                    'status' => 'MENUNGGU_APPROVAL',
                ]);

                // Buat draft penyesuaian stok jika belum ada
                $existingAdj = PenyesuaianStok::where('stok_opname_id', $this->activeOpname->id)->first();
                if (! $existingAdj) {
                    $kodeAdj = CodeGenerator::generateAdj();
                    $penyesuaian = PenyesuaianStok::create([
                        'kode'                => $kodeAdj,
                        'stok_opname_id'      => $this->activeOpname->id,
                        'gudang_id'           => $this->activeOpname->gudang_id,
                        'tanggal_penyesuaian' => now()->toDateString(),
                        'alasan'              => "Selisih perhitungan fisik Stock Opname {$this->activeOpname->kode}",
                        'status'              => 'MENUNGGU',
                        'diajukan_oleh'       => $user->id,
                    ]);

                    foreach ($selisihRows as $sr) {
                        PenyesuaianStokDetail::create([
                            'penyesuaian_stok_id' => $penyesuaian->id,
                            'barang_id'           => $sr['barang_id'],
                            'stok_batch_id'       => $sr['stok_batch_id'],
                            'jumlah_penyesuaian'  => $sr['jumlah'],
                            'jenis_penyesuaian'   => $sr['jenis'],
                            'harga_satuan'        => $sr['harga_satuan'],
                            'subtotal_nilai'      => $sr['subtotal'],
                            'catatan'             => $sr['catatan'],
                        ]);
                    }
                }
            } else {
                // Selisih = 0 -> Selesai / DISETUJUI hanya jika pengguna punya wewenang otorisasi
                $canAuthorize = $user->can('update_gudang_stok::otorisasi::penyesuaian') ||
                    $user->hasRole(['kepala_gudang', 'admin_logistik', 'super_admin']);

                $this->activeOpname->update([
                    'status'         => $canAuthorize ? 'DISETUJUI' : 'MENUNGGU_APPROVAL',
                    'disetujui_oleh' => $canAuthorize ? $user->id : null,
                ]);
            }
        });

        $this->showCountModal = false;
        $this->toast("Perhitungan fisik opname {$this->activeOpname->kode} berhasil disimpan.", 'success');
    }

    public function exportExcel(int $opnameId)
    {
        $opname = StokOpname::with(['gudang', 'detail.barang.satuan', 'detail.stokBatch.lokasiGudang'])->findOrFail($opnameId);

        $rows = $opname->detail->map(fn ($d) => [
            'ID_Detail'     => $d->id,
            'Kode_Barang'   => $d->barang?->kode,
            'Nama_Barang'   => $d->barang?->nama,
            'Nomor_Batch'   => $d->stokBatch?->nomor_batch,
            'Lokasi_Rak'    => $d->stokBatch?->lokasiGudang?->nama ?? 'Tanpa Rak',
            'Satuan'        => $d->barang?->satuan?->simbol ?? 'Unit',
            'Jumlah_Fisik'  => $d->jumlah_fisik,
            'Catatan'       => $d->catatan ?? '',
        ]);

        $fileName = "Lembar_Opname_{$opname->kode}.xlsx";
        return (new FastExcel($rows))->download($fileName);
    }

    public function importExcel(): void
    {
        $this->validate([
            'fileExcel' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ], [
            'fileExcel.required' => 'Berkas Excel hasil opname fisik wajib diunggah.',
            'fileExcel.mimes'    => 'Format berkas harus berekstensi .xlsx, .xls, atau .csv.',
            'fileExcel.max'      => 'Ukuran berkas Excel maksimal 5 MB.',
        ]);

        if (! $this->activeOpname) {
            return;
        }
        abort_unless(
            auth()->user()?->can('update_gudang_stok::stok::opname') ||
            auth()->user()?->can('create_gudang_stok::stok::opname') ||
            auth()->user()?->can('page_KepalaGudangDashboard') ||
            auth()->user()?->can('page_Dashboard'),
            403,
            'Anda tidak memiliki wewenang mengimpor hasil stock opname.'
        );

        $user = auth()->user();
        $isGlobalAdmin = $user && ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard'));
        if (! $isGlobalAdmin && $user?->gudang_id && (int) $user?->gudang_id !== (int) $this->activeOpname->gudang_id) {
            abort(403, 'Anda tidak memiliki wewenang memproses opname di fasilitas gudang ini.');
        }


        try {
            $path = $this->fileExcel->getRealPath();
            $collection = (new FastExcel)->import($path);

            $updatedCount = 0;
            foreach ($collection as $row) {
                $detailId = $row['ID_Detail'] ?? null;
                $fisik = isset($row['Jumlah_Fisik']) ? (int) $row['Jumlah_Fisik'] : null;

                if ($detailId && $fisik !== null && isset($this->countRows[$detailId])) {
                    $this->countRows[$detailId]['jumlah_fisik'] = max(0, $fisik);
                    if (! empty($row['Catatan'])) {
                        $this->countRows[$detailId]['catatan'] = trim($row['Catatan']);
                    }
                    $updatedCount++;
                }
            }

            $this->reset('fileExcel');
            $this->toast("Berhasil mengimpor {$updatedCount} baris perhitungan fisik dari Excel.", 'success');
        } catch (Exception $e) {
            $this->toast("Gagal mengimpor file Excel: {$e->getMessage()}", 'danger');
        }
    }

    public function showQrBatch(int $batchId): void
    {
        $this->selectedBatchQr = StokBatch::with(['barang', 'gudang', 'lokasiGudang'])->findOrFail($batchId);
        $this->qrCodeSvg = QrCode::size(160)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($this->selectedBatchQr->nomor_batch);

        $this->showQrModal = true;
    }

    public function showDetail(int $opnameId): void
    {
        $this->selectedOpname = StokOpname::with([
            'gudang',
            'pembuat',
            'penyetuju',
            'detail.barang.satuan',
            'detail.stokBatch.lokasiGudang',
            'penyesuaian',
        ])->findOrFail($opnameId);

        $this->showDetailModal = true;
    }

    public function render()
    {
        abort_unless(
            auth()->user()?->can('view_any_gudang_stok::stok::opname') ||
            auth()->user()?->can('view_gudang_stok::stok::opname'),
            403
        );

        $user = auth()->user();
        $isGlobalAdmin = $user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard') || $user->can('page_KepalaGudangDashboard');

        $query = StokOpname::with(['gudang', 'pembuat', 'penyetuju', 'penyesuaian'])
            ->withCount('detail')
            ->when(! $isGlobalAdmin && $user?->gudang_id, fn ($q) => $q->where('gudang_id', $user->gudang_id))
            ->when($this->filterGudangId, fn ($q) => $q->where('gudang_id', $this->filterGudangId))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('kode', 'ilike', "%{$this->search}%")
                        ->orWhereHas('gudang', fn ($g) => $g->where('nama', 'ilike', "%{$this->search}%"));
                });
            });

        $items = $query->orderBy('id', 'desc')->paginate($this->perPage);

        $daftarGudang = Cache::remember('dropdown_gudang_aktif', 60, fn () => Gudang::where('status', 'AKTIF')->orderBy('nama')->get());

        return view('livewire.persediaan.stock-opname-index', [
            'items'        => $items,
            'daftarGudang' => $daftarGudang,
            'canManage'    => $isGlobalAdmin || $user?->can('create_gudang_stok::stok::opname'),
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Stock Opname Fisik (Blind Count)']);
    }
}
