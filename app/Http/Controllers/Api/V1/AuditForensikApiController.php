<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StatusCapa;
use App\Http\Controllers\Api\ApiController;
use App\Models\LogAudit;
use App\Models\PenyesuaianStok;
use App\Models\PesananPembelian;
use App\Models\StokOpname;
use App\Models\TemuanAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditForensikApiController extends ApiController
{
    /**
     * Guard otorisasi akses data forensik & kepatuhan audit.
     */
    protected function authorizeForensicAccess(Request $request): ?JsonResponse
    {
        $user = $request->user();
        $canAccess = $user && (
            $user->hasRole(['super_admin', 'admin_logistik']) ||
            $user->can('view_any_audit_manajemen::audit::trail') ||
            $user->can('view_audit_manajemen::audit::trail')
        );

        if (! $canAccess) {
            return $this->jsonError('Akses ditolak: Data forensik hanya untuk Auditor Internal SPI & Manajemen.', 403);
        }

        return null;
    }

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
     * Digital Forensic Audit Trail.
     * GET /api/v1/audit/trail
     */
    public function auditTrail(Request $request): JsonResponse
    {
        if ($auth = $this->authorizeForensicAccess($request)) {
            return $auth;
        }
        $aksi = $request->query('aksi');
        $entitas = $request->query('entitas');
        $search = $request->query('search');

        $query = LogAudit::with(['pengguna:id,nama,nip', 'peran:id,name'])
            ->when($aksi, fn ($q) => $q->where('aksi', $aksi))
            ->when($entitas, fn ($q) => $q->where('referensi_jenis', $entitas))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('referensi_jenis', 'like', "%{$search}%")
                        ->orWhere('aksi', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhereHas('pengguna', fn ($u) => $u->where('nama', 'like', "%{$search}%"));
                });
            })
            ->latest('id');

        $paginated = $query->paginate(20);

        $items = $paginated->getCollection()->map(function ($log) {
            return [
                'id'              => $log->id,
                'pengguna_nama'   => $log->pengguna?->nama ?? 'Sistem / Anonim',
                'pengguna_nip'    => $log->pengguna?->nip ?? '-',
                'peran'           => $log->peran?->name ?? '-',
                'aksi'            => $log->aksi,
                'referensi_jenis' => class_basename($log->referensi_jenis),
                'referensi_id'    => (int) $log->referensi_id,
                'ip_address'      => $log->ip_address ?? '-',
                'device'          => $log->device ?? '-',
                'waktu'           => $log->dibuat_pada?->format('d/m/Y H:i:s') ?? '-',
                'timestamp'       => $log->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
                'has_data_sebelum'=> ! empty($log->data_sebelum),
                'has_data_sesudah'=> ! empty($log->data_sesudah),
            ];
        });

        return $this->jsonSuccess([
            'items'    => $items,
            'paginasi' => [
                'total'        => $paginated->total(),
                'per_halaman'  => $paginated->perPage(),
                'halaman_ini'  => $paginated->currentPage(),
                'total_halaman'=> $paginated->lastPage(),
            ],
        ], 'Daftar riwayat audit trail berhasil dimuat.');
    }

    /**
     * Detail forensik nilai data sebelum dan sesudah perubahan.
     * GET /api/v1/audit/trail/{id}
     */
    public function auditTrailDetail(Request $request, int $id): JsonResponse
    {
        if ($auth = $this->authorizeForensicAccess($request)) {
            return $auth;
        }
        $log = LogAudit::with(['pengguna:id,nama,nip', 'peran:id,name'])->find($id);

        if (! $log) {
            return $this->jsonError('Log audit tidak ditemukan.', 404);
        }

        return $this->jsonSuccess([
            'id'              => $log->id,
            'pengguna_nama'   => $log->pengguna?->nama ?? 'Sistem / Anonim',
            'pengguna_nip'    => $log->pengguna?->nip ?? '-',
            'peran'           => $log->peran?->name ?? '-',
            'aksi'            => $log->aksi,
            'referensi_jenis' => class_basename($log->referensi_jenis),
            'referensi_id'    => (int) $log->referensi_id,
            'ip_address'      => $log->ip_address ?? '-',
            'device'          => $log->device ?? '-',
            'waktu'           => $log->dibuat_pada?->format('d/m/Y H:i:s') ?? '-',
            'data_sebelum'    => $log->data_sebelum,
            'data_sesudah'    => $log->data_sesudah,
        ], 'Detail forensik log audit berhasil dimuat.');
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
        $filterAnomali = $request->query('anomali'); // 'TANPA_PR', 'TERLAMBAT', 'SELISIH_RCV'
        $search = $request->query('search');

        $today = now()->toDateString();

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

        $paginated = $query->latest('id')->paginate(15);

        $items = $paginated->getCollection()->map(function ($po) use ($today) {
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
        });

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

    /**
     * Pemeriksaan Selisih Opname Fisik vs Sistem.
     * GET /api/v1/audit/selisih-opname
     */
    public function selisihOpname(Request $request): JsonResponse
    {
        if ($auth = $this->authorizeForensicAccess($request)) {
            return $auth;
        }
        // 1. Sesi Opname dengan Selisih Kuantitas
        $sesiOpname = StokOpname::whereHas('detail', function ($q) {
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
        ->map(function ($op) {
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
        });

        // 2. Dokumen Penyesuaian Stok yang Diterbitkan
        $penyesuaian = PenyesuaianStok::with([
            'gudang:id,kode,nama',
            'pengaju:id,nama',
            'penyetuju:id,nama',
            'detail.barang.satuan',
        ])
        ->latest('id')
        ->limit(20)
        ->get()
        ->map(function ($p) {
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
        });

        return $this->jsonSuccess([
            'sesi_opname'  => $sesiOpname,
            'penyesuaian'  => $penyesuaian,
        ], 'Data audit selisih opname berhasil dimuat.');
    }
}
