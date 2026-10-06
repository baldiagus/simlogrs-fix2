<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Aset;
use App\Models\Barang;
use App\Models\LokasiGudang;
use App\Models\PenerimaanBarang;
use App\Models\PenerimaanBarangDetail;
use App\Models\PergerakanStok;
use App\Models\PermintaanDistribusi;
use App\Models\PesananPembelian;
use App\Models\StokBatch;
use App\Models\StokOpname;
use App\Models\StokOpnameDetail;
use App\Services\Distribusi\DistribusiService;
use App\Support\Generator\CodeGenerator;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
class GudangApiController extends ApiController
{
    /**
     * Daftar rak gudang penyimpanan.
     * GET /v1/gudang/rak
     */
    public function daftarRak(Request $request): JsonResponse
    {
        $user = $request->user();
        $isGlobal = $user && (
            $user->hasRole(['super_admin', 'admin_logistik', 'auditor', 'manajemen']) ||
            $user->can('view_any_master::lokasi::gudang') ||
            $user->can('page_AdminLogistikDashboard') ||
            $user->can('page_AuditorDashboard') ||
            $user->can('page_ManajemenDashboard')
        );

        $hasWarehouseAccess = $isGlobal || ($user && $user->gudang_id);
        if (! $hasWarehouseAccess) {
            return $this->jsonError('Akses ditolak: Anda tidak memiliki wewenang melihat denah rak gudang.', 403);
        }

        $cari = $request->query('cari');
        $gudangId = $isGlobal ? $request->query('gudang_id') : $user->gudang_id;

        $rakList = LokasiGudang::query()
            ->with('gudang:id,kode,nama')
            ->when($gudangId, fn ($q) => $q->where('gudang_id', $gudangId))
            ->when($cari, function ($q) use ($cari) {
                $q->where(function ($sub) use ($cari) {
                    $sub->where('kode', 'like', "%{$cari}%")
                        ->orWhere('nama', 'like', "%{$cari}%");
                });
            })
            ->withCount([
                'stokBatch as total_barang' => function ($q) {
                    $q->where('jumlah', '>', 0);
                },
                'aset as total_aset',
            ])
            ->orderBy('kode')
            ->get()
            ->map(fn ($rak) => [
                'id' => $rak->id,
                'kode' => $rak->kode,
                'nama' => $rak->nama,
                'baris' => $rak->baris,
                'kolom' => $rak->kolom,
                'level' => $rak->level,
                'status' => $rak->status,
                'gudang_id' => $rak->gudang_id,
                'nama_gudang' => $rak->gudang?->nama ?? '-',
                'total_barang' => (int) ($rak->total_barang ?? 0),
                'total_aset' => (int) ($rak->total_aset ?? 0),
            ]);

        return $this->jsonSuccess($rakList, 'Daftar rak gudang berhasil diambil.');
    }

    /**
     * Detail rak gudang: daftar stok batch barang dan aset fisik.
     * GET /v1/gudang/rak/{id}
     */
    public function detailRak(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $isGlobal = $user && (
            $user->hasRole(['super_admin', 'admin_logistik', 'auditor', 'manajemen']) ||
            $user->can('view_any_master::lokasi::gudang') ||
            $user->can('page_AdminLogistikDashboard') ||
            $user->can('page_AuditorDashboard') ||
            $user->can('page_ManajemenDashboard')
        );

        $rak = LokasiGudang::with(['gudang:id,kode,nama'])->find($id);

        if (! $rak) {
            return $this->jsonError('Data rak gudang tidak ditemukan.', 404);
        }

        if (! $isGlobal) {
            if (! $user?->gudang_id || (int) $user->gudang_id !== (int) $rak->gudang_id) {
                return $this->jsonError('Akses ditolak: Anda tidak memiliki wewenang melihat detail rak fasilitas gudang ini.', 403);
            }
        }

        // 1. Stok Batch Barang di Rak ini
        $barangList = StokBatch::query()
            ->where('lokasi_gudang_id', $id)
            ->where('jumlah', '>', 0)
            ->with(['barang.kategori:id,nama', 'barang.satuan:id,nama', 'barang.merek:id,nama'])
            ->get()
            ->map(function ($sb) {
                $exp = $sb->tanggal_kedaluwarsa;
                $statusKedaluwarsa = 'AMAN';
                if ($exp) {
                    $diffDays = now()->diffInDays($exp, false);
                    if ($diffDays < 0) {
                        $statusKedaluwarsa = 'KADALUWARSA';
                    } elseif ($diffDays <= 30) {
                        $statusKedaluwarsa = 'MENDEKATI_KADALUWARSA';
                    }
                }

                $tipe = $sb->barang?->tipe ?? 'HABIS_PAKAI';
                $tipeLabel = match ($tipe) {
                    'ASET'      => 'Aktiva / Aset Inventaris',
                    'SPAREPART' => 'Suku Cadang IPSRS',
                    default     => 'Habis Pakai (ATK/ART)',
                };

                return [
                    'id'                 => $sb->id,
                    'barang_id'          => $sb->barang_id,
                    'nama_barang'        => $sb->barang?->nama ?? 'Barang',
                    'kode_barang'        => $sb->barang?->kode ?? '-',
                    'tipe'               => $tipe,
                    'tipe_label'         => $tipeLabel,
                    'kategori'           => $sb->barang?->kategori?->nama ?? 'Umum',
                    'satuan'             => $sb->barang?->satuan?->nama ?? 'Pcs',
                    'merek'              => $sb->barang?->merek?->nama ?? '-',
                    'nomor_batch'        => $sb->nomor_batch,
                    'tanggal_kedaluwarsa'=> $sb->tanggal_kedaluwarsa?->format('Y-m-d'),
                    'status_kedaluwarsa' => $statusKedaluwarsa,
                    'kuantitas_stok'     => (int) $sb->jumlah,
                ];
            });

        // 2. Aset Fisik di Rak ini
        $asetList = Aset::query()
            ->where('lokasi_gudang_id', $id)
            ->with(['barang.merek:id,nama', 'barang.kategori:id,nama'])
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'kode_aset' => $a->kode_aset,
                'nama_aset'          => $a->barang?->nama ?? 'Aset Fisik',
                'tipe'               => $a->barang?->tipe ?? 'ASET',
                'tipe_label'         => 'Aktiva / Aset Inventaris',
                'merk'               => $a->barang?->merek?->nama ?? '-',
                'model'              => $a->barang?->spesifikasi ?? '-',
                'nomor_seri' => $a->nomor_seri ?? '-',
                'status_ketersediaan' => $a->status,
                'kondisi_fisik' => $a->kondisi,
            ]);

        $data = [
            'id' => $rak->id,
            'kode' => $rak->kode,
            'nama' => $rak->nama,
            'baris' => $rak->baris,
            'kolom' => $rak->kolom,
            'level' => $rak->level,
            'status' => $rak->status,
            'gudang_id' => $rak->gudang_id,
            'nama_gudang' => $rak->gudang?->nama ?? '-',
            'total_barang' => $barangList->count(),
            'total_aset' => $asetList->count(),
            'barang' => $barangList,
            'aset' => $asetList,
        ];

        return $this->jsonSuccess($data, 'Detail rak gudang berhasil diambil.');
    }

    /**
     * Verifikasi barcode/QR batch & eksekusi picking FEFO via mobile scanner.
     */
    public function scanBatch(Request $request, DistribusiService $service): JsonResponse
    {
        $user = $request->user();

        if (! $user->can('create_gudang_stok::picking::distribusi') && ! $user->can('page_AdminLogistikDashboard')) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk eksekusi picking FEFO.', 403);
        }

        $request->validate([
            'permintaan_id' => 'nullable|integer',
            'nomor_batch'   => 'nullable|string',
            'gudang_id'     => 'nullable|exists:gudang,id',
        ]);
        $barcode = trim($request->input('nomor_batch', $request->input('barcode', '')));
        $permintaanId = $request->input('permintaan_id');
        $permintaan = null;

        if ($permintaanId) {
            $permintaan = PermintaanDistribusi::with(['detail.barang', 'unit'])->find($permintaanId);
        }

        if (! $permintaan) {
            $permintaan = PermintaanDistribusi::with(['detail.barang', 'unit'])
                ->whereIn('status', ['DISETUJUI_GUDANG', 'DIKIRIM_SEBAGIAN', 'DISETUJUI_KARU'])
                ->latest('id')
                ->first();
        }

        // Jika ada permohonan distribusi yang siap dipicking, jalankan picking FEFO
        if ($permintaan && in_array($permintaan->status, ['DISETUJUI_GUDANG', 'DIKIRIM_SEBAGIAN', 'DISETUJUI_KARU'], true)) {
            try {
                $gudangId = $request->input('gudang_id') ?: $permintaan->gudang_id;
                $sppb = $service->eksekusiPickingFefo($permintaan, $user, $gudangId);

                $sppb->load(['detail.barang', 'detail.stokBatch', 'unit']);

                return $this->jsonSuccess([
                    'sppb_kode'      => $sppb->kode,
                    'permintaan_id'  => $permintaan->id,
                    'unit_tujuan'    => $sppb->unit?->nama,
                    'tanggal_kirim'  => $sppb->tanggal_kirim?->format('Y-m-d'),
                    'status'         => $sppb->status,
                    'alokasi_batch'  => $sppb->detail->map(fn ($d) => [
                        'barang'      => $d->barang?->nama,
                        'nomor_batch' => $d->stokBatch?->nomor_batch,
                        'expired'     => $d->stokBatch?->tanggal_kedaluwarsa?->format('Y-m-d'),
                        'qty_dikirim' => $d->jumlah_dikirim,
                    ]),
                ], "Picking FEFO berhasil. SPPB {$sppb->kode} telah diterbitkan.");
            } catch (Exception $e) {
                return $this->jsonError($e->getMessage(), 422);
            }
        }

        // Jika tidak ada antrean dokumen distribusi, jalankan verifikasi batch langsung di rak
        if ($barcode !== '') {
            $batch = StokBatch::with(['barang', 'lokasiGudang'])
                ->whereRaw('LOWER(nomor_batch) = ?', [strtolower($barcode)])
                ->orWhereHas('barang', fn ($q) => $q->whereRaw('LOWER(kode) = ?', [strtolower($barcode)]))
                ->first();

            if ($batch) {
                $earliest = StokBatch::where('barang_id', $batch->barang_id)
                    ->where('status', 'AKTIF')
                    ->where('jumlah', '>', 0)
                    ->orderByRaw('tanggal_kedaluwarsa ASC NULLS LAST')
                    ->first();

                $isValid = ($earliest && $earliest->id === $batch->id);

                return $this->jsonSuccess([
                    'sppb_kode'      => 'VERIFIKASI-FEFO',
                    'permintaan_id'  => 0,
                    'unit_tujuan'    => 'Pemeriksaan Rak Gudang',
                    'tanggal_kirim'  => now()->toDateString(),
                    'status'         => $isValid ? 'VALID' : 'NON_FEFO',
                    'alokasi_batch'  => [
                        [
                            'barang'      => $batch->barang?->nama,
                            'nomor_batch' => $batch->nomor_batch,
                            'expired'     => $batch->tanggal_kedaluwarsa?->format('Y-m-d'),
                            'qty_dikirim' => $batch->jumlah,
                        ],
                    ],
                ], $isValid ? "Batch '{$batch->nomor_batch}' valid sesuai urutan FEFO." : 'Peringatan: Bukan batch terdekat.');
            }

            return $this->jsonError("Nomor batch atau barcode '{$barcode}' tidak ditemukan di gudang.", 422);
        }

        return $this->jsonError('Belum ada dokumen permohonan distribusi yang siap dipicking.', 422);
    }

    /**
     * Input perhitungan fisik blind count per barcode/nomor batch di depan rak via mobile scanner.
     * POST /v1/gudang/opname-count
     */
    public function opnameCount(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->can('create_gudang_stok::stok::opname') && ! $user->can('update_gudang_stok::stok::opname') && ! $user->can('page_AdminLogistikDashboard')) {
            return $this->jsonError('Anda tidak memiliki wewenang menginput hasil stock opname.', 403);
        }

        $validated = $request->validate([
            'stok_opname_id' => 'required|exists:stok_opname,id',
            'stok_batch_id'  => 'nullable|exists:stok_batch,id',
            'nomor_batch'    => 'nullable|string',
            'jumlah_fisik'   => 'required|integer|min:0',
            'catatan'        => 'nullable|string|max:500',
        ]);

        $opname = StokOpname::find($validated['stok_opname_id']);
        if (! $opname) {
            return $this->jsonError('Sesi stock opname tidak ditemukan.', 404);
        }

        $isGlobalAdmin = $user && (
            $user->hasRole(['super_admin', 'admin_logistik']) ||
            $user->can('page_AdminLogistikDashboard')
        );

        if (! $isGlobalAdmin && (int) $user?->gudang_id !== (int) $opname->gudang_id) {
            return $this->jsonError('Anda tidak memiliki wewenang menginput stock opname untuk fasilitas gudang lain.', 403);
        }

        if ($opname->status !== 'BERJALAN') {
            return $this->jsonError("Sesi stock opname {$opname->kode} tidak sedang dalam status BERJALAN (status: {$opname->status}).", 422);
        }

        // Resolusi baris detail
        $detail = null;
        if (! empty($validated['stok_batch_id'])) {
            $detail = StokOpnameDetail::with(['barang', 'stokBatch'])
                ->where('stok_opname_id', $opname->id)
                ->where('stok_batch_id', $validated['stok_batch_id'])
                ->first();
        } elseif (! empty($validated['nomor_batch'])) {
            $batch = StokBatch::where('gudang_id', $opname->gudang_id)
                ->where('nomor_batch', trim($validated['nomor_batch']))
                ->first();

            if ($batch) {
                $detail = StokOpnameDetail::with(['barang', 'stokBatch'])
                    ->where('stok_opname_id', $opname->id)
                    ->where('stok_batch_id', $batch->id)
                    ->first();
            }
        }

        if (! $detail) {
            return $this->jsonError('Item batch tidak terdaftar pada sesi stock opname ini.', 404);
        }

        $fisik = (int) $validated['jumlah_fisik'];
        $selisih = $fisik - $detail->jumlah_sistem;

        $detail->update([
            'jumlah_fisik' => $fisik,
            'selisih'      => $selisih,
            'catatan'      => $validated['catatan'] ?? $detail->catatan,
        ]);

        return $this->jsonSuccess([
            'opname_kode'   => $opname->kode,
            'detail_id'     => $detail->id,
            'barang'        => $detail->barang?->nama,
            'nomor_batch'   => $detail->stokBatch?->nomor_batch,
            'jumlah_fisik'  => $detail->jumlah_fisik,
            'catatan'       => $detail->catatan,
        ], 'Hasil perhitungan fisik berhasil direkam.');
    }

    /**
     * Daftar PO siap diterima di gudang (Inbound Pending).
     * GET /v1/gudang/po-inbound
     */
    public function poInbound(Request $request): JsonResponse
    {
        $user = $request->user();

        $canView = $user->can('create_gudang_stok::penerimaan::po') ||
            $user->can('view_any_gudang_stok::penerimaan::po') ||
            $user->can('view_gudang_stok::penerimaan::po') ||
            $user->hasRole('petugas_gudang') ||
            $user->hasRole('kepala_gudang') ||
            $user->hasRole('admin_logistik') ||
            $user->hasRole('super_admin');

        if (! $canView) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk melihat daftar penerimaan PO.', 403);
        }

        $pos = PesananPembelian::query()
            ->whereIn('status', ['DIKIRIM_VENDOR', 'DITERBITKAN', 'DITERIMA_SEBAGIAN'])
            ->with([
                'pemasok:id,kode,nama',
                'detail.barang:id,kode,nama,satuan_id,kategori_id',
                'detail.barang.satuan:id,nama',
            ])
            ->latest('id')
            ->get()
            ->map(function ($po) {
                return [
                    'id'                     => $po->id,
                    'kode_po'                => $po->kode,
                    'pemasok'                => $po->pemasok?->nama ?? '-',
                    'tanggal_po'             => $po->tanggal_po?->format('Y-m-d') ?? '',
                    'estimasi_tanggal_kirim' => $po->estimasi_tanggal_kirim?->format('Y-m-d'),
                    'status'                 => $po->status,
                    'total_item'             => $po->detail->count(),
                    'items'                  => $po->detail->map(function ($d) {
                        $sisa = max(0, (int) $d->jumlah_dipesan - (int) $d->jumlah_diterima);
                        return [
                            'id'                   => $d->id,
                            'barang_id'            => $d->barang_id,
                            'kode_barang'          => $d->barang?->kode ?? '-',
                            'nama_barang'          => $d->barang?->nama ?? 'Barang',
                            'satuan'               => $d->barang?->satuan?->nama ?? 'Pcs',
                            'jumlah_dipesan'       => (int) $d->jumlah_dipesan,
                            'jumlah_sudah_diterima'=> (int) $d->jumlah_diterima,
                            'sisa_kebutuhan'       => $sisa,
                            'harga_satuan'         => (float) $d->harga_satuan,
                        ];
                    }),
                ];
            });

        return $this->jsonSuccess($pos, 'Daftar PO inbound siap terima berhasil dimuat.');
    }

    /**
     * Konfirmasi penerimaan fisik barang dari vendor dan alokasi ke rak gudang.
     * POST /v1/gudang/penerimaan-po
     */
    public function simpanPenerimaanPo(Request $request): JsonResponse
    {
        $user = $request->user();

        $canReceive = $user->can('create_gudang_stok::penerimaan::po') ||
            $user->hasRole('petugas_gudang') ||
            $user->hasRole('kepala_gudang') ||
            $user->hasRole('super_admin');

        if (! $canReceive) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk melakukan penerimaan PO gudang.', 403);
        }

        $validator = Validator::make($request->all(), [
            'pesanan_pembelian_id'              => 'required|exists:pesanan_pembelian,id',
            'nomor_surat_jalan'                 => 'required|string|max:100',
            'catatan'                           => 'nullable|string|max:1000',
            'items'                             => 'required|array|min:1',
            'items.*.barang_id'                 => 'required|exists:barang,id',
            'items.*.lokasi_gudang_id'          => 'required|exists:lokasi_gudang,id',
            'items.*.nomor_batch'               => 'required|string|max:100',
            'items.*.tanggal_kedaluwarsa'       => 'nullable|date',
            'items.*.jumlah_diterima'           => 'required|integer|min:0',
            'items.*.jumlah_rusak'              => 'nullable|integer|min:0',
        ], [
            'nomor_surat_jalan.required'        => 'Nomor surat jalan vendor wajib diisi.',
            'items.required'                    => 'Minimal satu barang harus dicatat penerimaannya.',
            'items.*.lokasi_gudang_id.required' => 'Rak penyimpanan fisik wajib dipilih.',
            'items.*.nomor_batch.required'      => 'Nomor batch barang wajib diisi.',
        ]);

        if ($validator->fails()) {
            return $this->jsonError('Validasi data penerimaan gagal.', 422, $validator->errors());
        }

        $po = PesananPembelian::with('detail')->find($request->input('pesanan_pembelian_id'));
        if (! $po) {
            return $this->jsonError('Data PO tidak ditemukan.', 404);
        }

        if (! in_array($po->status, ['DIKIRIM_VENDOR', 'DITERIMA_SEBAGIAN'])) {
            return $this->jsonError("PO {$po->kode} belum dikirim oleh vendor atau tidak dalam status dapat diterima (saat ini: {$po->status}).", 422);
        }
        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('admin_logistik');
        $gudangId = $user->gudang_id;

        if (! $gudangId) {
            if ($isGlobalAdmin) {
                $firstRackId = $request->input('items')[0]['lokasi_gudang_id'] ?? null;
                $gudangId = $request->input('gudang_id')
                    ?? ($firstRackId ? \App\Models\LokasiGudang::where('id', $firstRackId)->value('gudang_id') : null)
                    ?? \App\Models\Gudang::where('status', 'AKTIF')->value('id');
            } else {
                return $this->jsonError('Akun Anda belum memiliki penugasan fasilitas gudang penerima resmi.', 403);
            }
        }

        if (! $gudangId) {
            return $this->jsonError('Fasilitas gudang penerima tidak valid atau belum ditentukan.', 422);
        }

        // M-04: Validasi bahwa setiap lokasi_gudang_id (rak) benar-benar berada di dalam $gudangId
        $itemRackIds = collect($request->input('items'))->pluck('lokasi_gudang_id')->filter()->unique()->toArray();
        $invalidRacks = \App\Models\LokasiGudang::whereIn('id', $itemRackIds)
            ->where('gudang_id', '!=', $gudangId)
            ->with('gudang:id,nama')
            ->get();

        if ($invalidRacks->isNotEmpty()) {
            $daftarRakInvalid = $invalidRacks->map(fn ($r) => "{$r->kode} ({$r->nama}) di {$r->gudang?->nama}")->implode(', ');

            return $this->jsonError("Alokasi rak tidak valid: Rak [{$daftarRakInvalid}] bukan bagian dari fasilitas gudang penerima yang ditugaskan.", 422, [
                'lokasi_gudang_id' => ['Rak penyimpanan fisik wajib berada di dalam fasilitas gudang penerima yang sama.'],
            ]);
        }
        $hasReceived = false;
        foreach ($request->input('items') as $row) {
            if ((int) $row['jumlah_diterima'] > 0) {
                $hasReceived = true;
                break;
            }
        }

        if (! $hasReceived) {
            return $this->jsonError('Minimal satu komoditas barang harus memiliki jumlah diterima > 0.', 422);
        }

        try {
            $penerimaan = DB::transaction(function () use ($request, $user, $po, $gudangId) {
                $kodeRcv = CodeGenerator::generate('RCV', PenerimaanBarang::class, 'kode', 'Ym', 3);

                // 1. Update jumlah diterima di pesanan_pembelian_detail
                foreach ($request->input('items') as $row) {
                    $qty = (int) $row['jumlah_diterima'];
                    if ($qty <= 0) continue;

                    $poDetail = $po->detail->firstWhere('barang_id', $row['barang_id']);
                    if ($poDetail) {
                        $poDetail->increment('jumlah_diterima', $qty);

                        if ((float) $poDetail->harga_satuan > 0) {
                            Barang::where('id', $row['barang_id'])->update([
                                'harga_beli_terakhir' => $poDetail->harga_satuan,
                            ]);
                        }
                    }
                }

                // 2. Evaluasi status PO
                $po->refresh();
                $po->load('detail');
                $allReceived = $po->detail->every(fn ($d) => (int) $d->jumlah_diterima >= (int) $d->jumlah_dipesan);
                $statusRcv = $allReceived ? 'LENGKAP' : 'SEBAGIAN';
                $statusPo  = $allReceived ? 'DITERIMA_LENGKAP' : 'DITERIMA_SEBAGIAN';

                // 3. Buat dokumen penerimaan barang
                $rcv = PenerimaanBarang::create([
                    'kode'                 => $kodeRcv,
                    'pesanan_pembelian_id' => $po->id,
                    'gudang_id'            => $gudangId,
                    'nomor_surat_jalan'    => $request->input('nomor_surat_jalan'),
                    'tanggal_terima'       => now()->toDateString(),
                    'status'               => $statusRcv,
                    'catatan'              => $request->input('catatan'),
                    'diterima_oleh'        => $user->id,
                ]);

                // 4. Catat detail & stok batch
                foreach ($request->input('items') as $row) {
                    $qty = (int) $row['jumlah_diterima'];
                    $rusak = (int) ($row['jumlah_rusak'] ?? 0);
                    $net = max(0, $qty - $rusak);

                    if ($qty <= 0) continue;

                    $poDetail = $po->detail->firstWhere('barang_id', $row['barang_id']);
                    $hargaSatuan = $poDetail ? $poDetail->harga_satuan : 0;

                    PenerimaanBarangDetail::create([
                        'penerimaan_barang_id' => $rcv->id,
                        'barang_id'            => $row['barang_id'],
                        'lokasi_gudang_id'     => $row['lokasi_gudang_id'],
                        'nomor_batch'          => $row['nomor_batch'],
                        'tanggal_kedaluwarsa'  => $row['tanggal_kedaluwarsa'] ?? null,
                        'jumlah_diterima'      => $qty,
                        'jumlah_rusak'         => $rusak,
                        'harga_satuan'         => $hargaSatuan,
                    ]);

                    if ($net > 0) {
                        $batch = StokBatch::create([
                            'barang_id'           => $row['barang_id'],
                            'gudang_id'           => $gudangId,
                            'lokasi_gudang_id'    => $row['lokasi_gudang_id'],
                            'nomor_batch'         => $row['nomor_batch'],
                            'tanggal_kedaluwarsa' => $row['tanggal_kedaluwarsa'] ?? null,
                            'jumlah'              => $net,
                            'harga_satuan'        => $hargaSatuan,
                            'status'              => 'AKTIF',
                        ]);

                        PergerakanStok::create([
                            'barang_id'        => $row['barang_id'],
                            'stok_batch_id'    => $batch->id,
                            'gudang_id'        => $gudangId,
                            'lokasi_gudang_id' => $row['lokasi_gudang_id'],
                            'jenis'            => 'MASUK',
                            'jumlah'           => $net,
                            'saldo_sebelum'    => 0,
                            'saldo_sesudah'    => $net,
                            'referensi_jenis'  => 'PENERIMAAN_PO',
                            'referensi_id'     => $rcv->id,
                            'keterangan'       => "Penerimaan PO {$po->kode} - Batch {$row['nomor_batch']}",
                            'dibuat_oleh'      => $user->id,
                        ]);
                    }
                }

                // 5. Update status PO
                $po->update(['status' => $statusPo]);

                // 6. Jika seluruh barang PO telah diterima lengkap, update status PR induk menjadi SELESAI
                if ($statusPo === 'DITERIMA_LENGKAP' && $po->permintaan_pengadaan_id) {
                    $po->permintaanPengadaan()->update(['status' => 'SELESAI']);
                }
                return $rcv;
            });

            return $this->jsonSuccess([
                'id'       => $penerimaan->id,
                'kode_rcv' => $penerimaan->kode,
                'status'   => $penerimaan->status,
            ], "Penerimaan fisik barang {$penerimaan->kode} berhasil disimpan ke gudang.", 201);
        } catch (\Throwable $e) {
            return $this->jsonError('Gagal memproses penerimaan barang: ' . $e->getMessage(), 500);
        }
    }
}
