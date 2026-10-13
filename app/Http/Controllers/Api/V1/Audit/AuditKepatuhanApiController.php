<?php

namespace App\Http\Controllers\Api\V1\Audit;

use App\Enums\Audit\StatusCapa;
use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesForensicAccess;
use App\Models\Audit\LogAudit;
use App\Models\Persediaan\PenyesuaianStok;
use App\Models\Pengadaan\PesananPembelian;
use App\Models\Persediaan\StokOpname;
use App\Models\Audit\TemuanAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ringkasan audit, kepatuhan pengadaan & selisih opname.
 */
class AuditKepatuhanApiController extends ApiController
{
    use AuthorizesForensicAccess;

    /**
     * Ringkasan metrik eksekutif Audit Internal SPI & Tata Kelola.
     * GET /api/v1/audit/summary
     */
    public function summary(Request $request): JsonResponse
    {
        if ($auth = $this->authorizeForensicAccess($request)) {
            return $auth;
        }
        $today = now()->toDateString();

        $totalTemuan = TemuanAudit::count();
        $totalClosed = TemuanAudit::where('status_capa', StatusCapa::Closed->value)->count();
        $totalMayorOpen = TemuanAudit::where('tingkat_risiko', 'Mayor')->where('status_capa', '!=', StatusCapa::Closed->value)->count();
        $totalOverdue = TemuanAudit::where('status_capa', '!=', StatusCapa::Closed->value)->where('due_date_capa', '<', $today)->count();
        $closingRate = $totalTemuan > 0 ? round(($totalClosed / $totalTemuan) * 100, 1) : 100.0;

        // Anomali Kepatuhan Pengadaan
        $poTanpaPr = PesananPembelian::whereNull('permintaan_pengadaan_id')->count();
        $poTerlambat = PesananPembelian::whereNotNull('estimasi_tanggal_kirim')
            ->where('estimasi_tanggal_kirim', '<', $today)
            ->where('status', '!=', 'DITERIMA_LENGKAP')
            ->count();
        $poSelisihRcv = PesananPembelian::whereHas('detail', function ($q) {
            $q->whereColumn('jumlah_diterima', '<', 'jumlah_dipesan');
        })->where('status', 'DITERIMA_SEBAGIAN')->count();

        // Selisih Opname
        $opnameDenganSelisih = StokOpname::whereHas('detail', function ($q) {
            $q->where('selisih', '!=', 0);
        })->count();
        $penyesuaianMenunggu = PenyesuaianStok::where('status', 'MENUNGGU')->count();

        // Total log forensik
        $totalLogAudit = LogAudit::count();

        return $this->jsonSuccess([
            'capa' => [
                'total_temuan'      => $totalTemuan,
                'total_closed'      => $totalClosed,
                'total_in_progress' => TemuanAudit::where('status_capa', StatusCapa::InProgress->value)->count(),
                'total_in_review'   => TemuanAudit::where('status_capa', StatusCapa::InReview->value)->count(),
                'mayor_open'        => $totalMayorOpen,
                'overdue'           => $totalOverdue,
                'closing_rate'      => $closingRate,
            ],
            'kepatuhan_po' => [
                'tanpa_pr'    => $poTanpaPr,
                'terlambat'   => $poTerlambat,
                'selisih_rcv' => $poSelisihRcv,
                'total_anomali' => $poTanpaPr + $poTerlambat + $poSelisihRcv,
            ],
            'selisih_opname' => [
                'sesi_berselisih'     => $opnameDenganSelisih,
                'penyesuaian_menunggu' => $penyesuaianMenunggu,
            ],
            'total_log_audit' => $totalLogAudit,
        ], 'Ringkasan metrik audit SPI berhasil dimuat.');
    }

    /**
     * Audit Kepatuhan Pengadaan (PO vs PR).
     * GET /api/v1/audit/kepatuhan-pengadaan
     */
    public function kepatuhanPengadaan(Request $request): JsonResponse
    {
        if ($auth = $this->authorizeForensicAccess($request)) {
            return $auth;
        }
        $today = now()->toDateString();

        $paginated = $this->queryKepatuhanPengadaan(
            $request->query('anomali'), // 'TANPA_PR', 'TERLAMBAT', 'SELISIH_RCV'
            $request->query('search'),
            $today
        )->latest('id')->paginate(15);

        $items = $paginated->getCollection()->map(fn ($po) => $this->formatKepatuhanPengadaan($po, $today));

        return $this->jsonSuccess([
            'items'    => $items,
            'paginasi' => [
                'total'        => $paginated->total(),
                'per_halaman'  => $paginated->perPage(),
                'halaman_ini'  => $paginated->currentPage(),
                'total_halaman'=> $paginated->lastPage(),
            ],
        ], 'Data audit kepatuhan pengadaan berhasil dimuat.');
    }

    private function queryKepatuhanPengadaan($filterAnomali, $search, string $today)
    {
        $query = PesananPembelian::with([
            'pemasok:id,nama,kode',
            'permintaanPengadaan.unit:id,nama',
            'detail.barang.satuan',
        ])
        ->when($search, function ($q) use ($search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('kode', 'like', "%{$search}%")
                    ->orWhereHas('pemasok', fn ($p) => $p->where('nama', 'like', "%{$search}%"));
            });
        });

        if ($filterAnomali === 'TANPA_PR') {
            $query->whereNull('permintaan_pengadaan_id');
        } elseif ($filterAnomali === 'TERLAMBAT') {
            $query->whereNotNull('estimasi_tanggal_kirim')
                ->where('estimasi_tanggal_kirim', '<', $today)
                ->where('status', '!=', 'DITERIMA_LENGKAP');
        } elseif ($filterAnomali === 'SELISIH_RCV') {
            $query->whereHas('detail', function ($q) {
                $q->whereColumn('jumlah_diterima', '<', 'jumlah_dipesan');
            })->where('status', 'DITERIMA_SEBAGIAN');
        }

        return $query;
    }

    private function formatKepatuhanPengadaan($po, string $today): array
    {
        $isTanpaPr = empty($po->permintaan_pengadaan_id);
        $isTerlambat = ! empty($po->estimasi_tanggal_kirim)
            && $po->estimasi_tanggal_kirim->format('Y-m-d') < $today
            && $po->status !== 'DITERIMA_LENGKAP';
        $hasSelisihRcv = $po->status === 'DITERIMA_SEBAGIAN';

        $anomaliTags = [];
        if ($isTanpaPr) $anomaliTags[] = 'TANPA_PR';
        if ($isTerlambat) $anomaliTags[] = 'TERLAMBAT';
        if ($hasSelisihRcv) $anomaliTags[] = 'SELISIH_RCV';

        return [
            'id'                      => $po->id,
            'kode'                    => $po->kode,
            'pemasok'                 => $po->pemasok?->nama ?? 'Vendor',
            'kode_pemasok'            => $po->pemasok?->kode ?? '-',
            'tanggal_pesan'           => $po->tanggal_pesanan?->format('Y-m-d') ?? '-',
            'estimasi_tanggal_kirim'  => $po->estimasi_tanggal_kirim?->format('Y-m-d') ?? '-',
            'status'                  => $po->status,
            'total_biaya'             => (float) ($po->total_biaya ?? 0),
            'pr_kode'                 => $po->permintaanPengadaan?->kode,
            'pr_unit'                 => $po->permintaanPengadaan?->unit?->nama,
            'anomali_tags'            => $anomaliTags,
            'is_compliant'            => empty($anomaliTags),
            'total_item'              => $po->detail->count(),
            'items'                   => $po->detail->map(fn ($d) => [
                'id'               => $d->id,
                'nama_barang'      => $d->barang?->nama ?? 'Barang',
                'satuan'           => $d->barang?->satuan?->nama ?? 'Pcs',
                'jumlah_dipesan'   => (int) $d->jumlah_dipesan,
                'jumlah_diterima'  => (int) ($d->jumlah_diterima ?? 0),
                'harga_satuan'     => (float) ($d->harga_satuan ?? 0),
                'subtotal'         => (float) ($d->subtotal ?? 0),
            ]),
        ];
    }

    /**
     * Pemeriksaan Selisih Opname Fisik vs Sistem.
     * GET /api/v1/audit/selisih-opname
     */
    public function selisihOpname(Request $request): JsonResponse
    {
        if ($auth = $this->authorizeForensicAccess($request)) {
            return $auth;
        }
        return $this->jsonSuccess([
            'sesi_opname'  => $this->sesiOpnameSelisih(),
            'penyesuaian'  => $this->penyesuaianTerbaru(),
        ], 'Data audit selisih opname berhasil dimuat.');
    }

    /**
     * Sesi Opname dengan Selisih Kuantitas.
     */
    private function sesiOpnameSelisih()
    {
        return StokOpname::whereHas('detail', function ($q) {
            $q->where('selisih', '!=', 0);
        })
        ->with([
            'gudang:id,kode,nama',
            'pembuat:id,nama',
            'detail' => function ($q) {
                $q->where('selisih', '!=', 0)
                  ->with(['barang:id,nama,kode,satuan_id', 'barang.satuan:id,nama', 'stokBatch:id,nomor_batch,harga_satuan']);
            },
        ])
        ->latest('id')
        ->limit(20)
        ->get()
        ->map(fn ($op) => $this->formatSesiOpname($op));
    }

    private function formatSesiOpname($op): array
    {
        $selisihDetails = $op->detail->map(function ($d) {
            $sistem = (int) $d->jumlah_sistem;
            $fisik = (int) ($d->jumlah_fisik ?? 0);
            $selisih = (int) $d->selisih;
            $harga = (float) ($d->stokBatch?->harga_satuan ?? 0);
            $nilaiSelisih = abs($selisih) * $harga;

            return [
                'id'            => $d->id,
                'nama_barang'   => $d->barang?->nama ?? 'Barang',
                'kode_barang'   => $d->barang?->kode ?? '-',
                'satuan'        => $d->barang?->satuan?->nama ?? 'Pcs',
                'nomor_batch'   => $d->stokBatch?->nomor_batch ?? '-',
                'jumlah_sistem' => $sistem,
                'jumlah_fisik'  => $fisik,
                'selisih'       => $selisih,
                'jenis'         => $selisih > 0 ? 'LEBIH' : 'KURANG',
                'harga_satuan'  => $harga,
                'nilai_selisih' => $nilaiSelisih,
                'catatan'       => $d->catatan ?? '-',
            ];
        });

        $totalNilaiSelisih = $selisihDetails->sum('nilai_selisih');

        return [
            'id'                 => $op->id,
            'kode'               => $op->kode,
            'gudang'             => $op->gudang?->nama ?? 'Gudang',
            'petugas'            => $op->pembuat?->nama ?? 'Petugas Gudang',
            'tanggal_opname'     => $op->tanggal_opname?->format('Y-m-d') ?? '-',
            'status'             => $op->status,
            'total_batch_selisih'=> $selisihDetails->count(),
            'total_nilai_selisih'=> $totalNilaiSelisih,
            'details'            => $selisihDetails,
        ];
    }

    /**
     * Dokumen Penyesuaian Stok yang Diterbitkan.
     */
    private function penyesuaianTerbaru()
    {
        return PenyesuaianStok::with([
            'gudang:id,kode,nama',
            'pengaju:id,nama',
            'penyetuju:id,nama',
            'detail.barang.satuan',
        ])
        ->latest('id')
        ->limit(20)
        ->get()
        ->map(fn ($p) => $this->formatPenyesuaian($p));
    }

    private function formatPenyesuaian($p): array
    {
        $totalNilai = $p->detail->sum(fn ($d) => (float) ($d->subtotal ?? 0));

        return [
            'id'              => $p->id,
            'kode'            => $p->kode,
            'gudang'          => $p->gudang?->nama ?? 'Gudang',
            'status'          => $p->status,
            'alasan'          => $p->alasan,
            'pengaju'         => $p->pengaju?->nama ?? 'Petugas',
            'penyetuju'       => $p->penyetuju?->nama ?? '-',
            'tanggal_pengajuan'=> $p->dibuat_pada?->format('Y-m-d H:i') ?? '-',
            'total_item'      => $p->detail->count(),
            'total_nilai'     => $totalNilai,
            'items'           => $p->detail->map(fn ($d) => [
                'nama_barang' => $d->barang?->nama ?? 'Barang',
                'satuan'      => $d->barang?->satuan?->nama ?? 'Pcs',
                'jumlah'      => (int) $d->jumlah,
                'jenis'       => $d->jenis,
                'subtotal'    => (float) ($d->subtotal ?? 0),
            ]),
        ];
    }
}
