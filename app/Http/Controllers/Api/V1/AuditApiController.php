<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StatusCapa;
use App\Http\Controllers\Api\ApiController;
use App\Models\LogAudit;
use App\Models\PenyesuaianStok;
use App\Models\PesananPembelian;
use App\Models\StokOpname;
use App\Models\TemuanAudit;
use App\Support\Generator\CodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuditApiController extends ApiController
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
     * Terbitkan temuan CAPA langsung dari anomali PO Pengadaan.
     * POST /api/v1/audit/kepatuhan-pengadaan/{po_id}/capa
     */
    public function buatTemuanKepatuhan(Request $request, int $po_id): JsonResponse
    {
        $user = $request->user();
        $canCreateTemuan = $user && (
            $user->hasRole(['super_admin', 'admin_logistik']) ||
            $user->can('create_audit_manajemen::temuan::capa')
        );
        if (! $canCreateTemuan) {
            return $this->jsonError('Hanya Auditor SPI yang berwenang menerbitkan temuan audit kepatuhan.', 403);
        }

        $po = PesananPembelian::with(['pemasok', 'permintaanPengadaan'])->find($po_id);
        if (! $po) {
            return $this->jsonError('Dokumen PO tidak ditemukan.', 404);
        }

        $validator = Validator::make($request->all(), [
            'tingkat_risiko' => 'required|in:Mayor,Moderat,Minor',
            'due_date_capa'  => 'required|date|after_or_equal:today',
            'ringkasan'      => 'nullable|string|min:10',
        ]);

        if ($validator->fails()) {
            return $this->jsonError('Validasi gagal.', 422, $validator->errors());
        }

        $tipe = $request->input('tipe_anomali', 'TANPA_PR');
        $vendor = $po->pemasok?->nama ?? 'Vendor';
        $objekAudit = "Pesanan Pembelian (PO) {$po->kode} - Rekanan: {$vendor}";

        if ($request->filled('ringkasan')) {
            $ringkasan = $request->input('ringkasan');
        } elseif ($tipe === 'TANPA_PR') {
            $ringkasan = "Ditemukan penerbitan Pesanan Pembelian (PO) nomor {$po->kode} senilai Rp " . number_format($po->total_biaya, 0, ',', '.') . " tanpa melalui dokumen Permintaan Pengadaan (PR) resmi dari unit pemohon.";
        } elseif ($tipe === 'TERLAMBAT') {
            $ringkasan = "Pengiriman barang PO nomor {$po->kode} dari vendor {$vendor} mengalami keterlambatan melebihi estimasi kirim ({$po->estimasi_tanggal_kirim?->format('d/m/Y')}).";
        } else {
            $ringkasan = "Terdapat selisih kuantitas barang yang diterima di gudang terhadap kuantitas yang dipesan pada dokumen PO {$po->kode}.";
        }

        $kodeAud = CodeGenerator::generateAud();

        $temuan = TemuanAudit::create([
            'kode'           => $kodeAud,
            'tanggal_audit'  => now()->toDateString(),
            'objek_audit'    => $objekAudit,
            'ringkasan'      => $ringkasan,
            'tingkat_risiko' => $request->input('tingkat_risiko', 'Mayor'),
            'due_date_capa'  => $request->input('due_date_capa'),
            'status_capa'    => StatusCapa::InProgress->value,
            'auditor_id'     => $user->id,
            'unit_id'        => $po->permintaanPengadaan?->unit_id,
        ]);

        return $this->jsonSuccess([
            'id'             => $temuan->id,
            'kode'           => $temuan->kode,
            'status_capa'    => $temuan->status_capa,
            'tingkat_risiko' => $temuan->tingkat_risiko,
        ], "Temuan audit kepatuhan '{$temuan->kode}' berhasil diterbitkan ke register CAPA.", 201);
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

    /**
     * Register Temuan Audit & CAPA.
     * GET /api/v1/audit/temuan-capa
     */
    public function registerTemuan(Request $request): JsonResponse
    {
        $status = $request->query('status'); // In Progress, In Review, Closed
        $risiko = $request->query('risiko'); // Mayor, Moderat, Minor
        $search = $request->query('search');

        $query = TemuanAudit::with([
            'auditor:id,nama',
            'evaluator:id,nama',
            'unit:id,nama',
            'gudang:id,nama',
        ])
        ->when($status, fn ($q) => $q->where('status_capa', $status))
        ->when($risiko, fn ($q) => $q->where('tingkat_risiko', $risiko))
        ->when($search, function ($q) use ($search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('kode', 'like', "%{$search}%")
                    ->orWhere('objek_audit', 'like', "%{$search}%")
                    ->orWhere('ringkasan', 'like', "%{$search}%");
            });
        })
        ->latest('id');
        $user = $request->user();
        $isGlobalAuditor = $user && (
            $user->hasRole(['super_admin', 'admin_logistik']) ||
            $user->can('view_any_audit_manajemen::temuan::capa')
        );

        if (! $isGlobalAuditor) {
            if ($user->unit_id) {
                $query->where('unit_id', $user->unit_id);
            } elseif ($user->gudang_id) {
                $query->where('gudang_id', $user->gudang_id);
            } else {
                return $this->jsonError('Anda tidak memiliki wewenang untuk mengakses data temuan audit.', 403);
            }
        }

        $paginated = $query->paginate(15);

        $today = now()->toDateString();

        $items = $paginated->getCollection()->map(function ($t) use ($today) {
            $isOverdue = $t->status_capa !== StatusCapa::Closed->value && $t->due_date_capa && $t->due_date_capa->format('Y-m-d') < $today;

            return [
                'id'                 => $t->id,
                'kode'               => $t->kode,
                'tanggal_audit'      => $t->tanggal_audit?->format('Y-m-d') ?? '-',
                'objek_audit'        => $t->objek_audit,
                'ringkasan'          => $t->ringkasan,
                'tingkat_risiko'     => $t->tingkat_risiko,
                'due_date_capa'      => $t->due_date_capa?->format('Y-m-d') ?? '-',
                'status_capa'        => $t->status_capa,
                'is_overdue'         => $isOverdue,
                'auditor'            => $t->auditor?->nama ?? 'Auditor SPI',
                'evaluator'          => $t->evaluator?->nama ?? '-',
                'unit'               => $t->unit?->nama ?? '-',
                'gudang'             => $t->gudang?->nama ?? '-',
                'lokasi_terperiksa'  => $t->unit?->nama ?? ($t->gudang?->nama ?? 'Sentral'),
                'analisis_penyebab'  => $t->analisis_penyebab,
                'tindakan_perbaikan' => $t->tindakan_perbaikan,
                'catatan_evaluasi'   => $t->catatan_evaluasi,
                'bukti_perbaikan'    => $t->bukti_perbaikan ? (is_array($t->bukti_perbaikan) ? $t->bukti_perbaikan : [$t->bukti_perbaikan]) : [],
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
        ], 'Daftar temuan audit & CAPA berhasil dimuat.');
    }

    /**
     * Penerbitan temuan audit baru secara mandiri oleh Auditor.
     * POST /api/v1/audit/temuan-capa
     */
    public function simpanTemuan(Request $request): JsonResponse
    {
        $user = $request->user();
        $canCreateTemuan = $user && (
            $user->hasRole(['super_admin', 'admin_logistik']) ||
            $user->can('create_audit_manajemen::temuan::capa')
        );
        if (! $canCreateTemuan) {
            return $this->jsonError('Hanya Auditor SPI yang berwenang menerbitkan temuan audit.', 403);
        }

        $validator = Validator::make($request->all(), [
            'objek_audit'    => 'required|string|min:5|max:150',
            'ringkasan'      => 'required|string|min:10',
            'tingkat_risiko' => 'required|in:Mayor,Moderat,Minor',
            'tanggal_audit'  => 'required|date',
            'due_date_capa'  => 'required|date|after_or_equal:tanggal_audit',
            'unit_id'        => 'nullable|exists:unit,id',
            'gudang_id'      => 'nullable|exists:gudang,id',
        ]);

        if ($validator->fails()) {
            return $this->jsonError('Validasi gagal.', 422, $validator->errors());
        }

        $kode = CodeGenerator::generateAud();

        $temuan = TemuanAudit::create([
            'kode'           => $kode,
            'tanggal_audit'  => $request->input('tanggal_audit'),
            'objek_audit'    => $request->input('objek_audit'),
            'ringkasan'      => $request->input('ringkasan'),
            'tingkat_risiko' => $request->input('tingkat_risiko'),
            'due_date_capa'  => $request->input('due_date_capa'),
            'status_capa'    => StatusCapa::InProgress->value,
            'auditor_id'     => $user->id,
            'unit_id'        => $request->input('unit_id'),
            'gudang_id'      => $request->input('gudang_id'),
        ]);

        return $this->jsonSuccess([
            'id'             => $temuan->id,
            'kode'           => $temuan->kode,
            'status_capa'    => $temuan->status_capa,
            'tingkat_risiko' => $temuan->tingkat_risiko,
        ], "Temuan audit '{$temuan->kode}' berhasil didaftarkan.", 201);
    }

    /**
     * Input tindakan perbaikan oleh Auditee Unit.
     * POST /api/v1/audit/temuan-capa/{id}/tindak-lanjut
     */
    public function tindakLanjutTemuan(Request $request, int $id): JsonResponse
    {
        $temuan = TemuanAudit::find($id);

        if (! $temuan) {
            return $this->jsonError('Temuan audit tidak ditemukan.', 404);
        }
        $user = $request->user();
        $isTargetAuditee = $user && (
            $user->hasRole(['super_admin', 'admin_logistik']) ||
            ($temuan->unit_id && (int) $user->unit_id === (int) $temuan->unit_id) ||
            ($temuan->gudang_id && (int) $user->gudang_id === (int) $temuan->gudang_id)
        );
        if (! $isTargetAuditee) {
            return $this->jsonError('Anda tidak memiliki wewenang mengirimkan tindak lanjut untuk temuan audit fasilitas/unit lain.', 403);
        }

        $validator = Validator::make($request->all(), [
            'analisis_penyebab'  => 'required|string|min:10',
            'tindakan_perbaikan' => 'required|string|min:10',
            'bukti_perbaikan'    => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->jsonError('Validasi gagal.', 422, $validator->errors());
        }

        $temuan->update([
            'analisis_penyebab'  => $request->input('analisis_penyebab'),
            'tindakan_perbaikan' => $request->input('tindakan_perbaikan'),
            'bukti_perbaikan'    => $request->input('bukti_perbaikan') ? [$request->input('bukti_perbaikan')] : $temuan->bukti_perbaikan,
            'status_capa'        => StatusCapa::InReview->value,
        ]);

        return $this->jsonSuccess([
            'id'          => $temuan->id,
            'kode'        => $temuan->kode,
            'status_capa' => $temuan->status_capa,
        ], "Tindak lanjut CAPA berhasil dikirimkan untuk evaluasi auditor.");
    }

    /**
     * Evaluasi penutupan (Closing) oleh Auditor SPI.
     * POST /api/v1/audit/temuan-capa/{id}/evaluasi
     */
    public function evaluasiTemuan(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $temuan = TemuanAudit::find($id);

        if (! $temuan) {
            return $this->jsonError('Temuan audit tidak ditemukan.', 404);
        }
        $canEvaluate = $user && (
            $user->hasRole(['super_admin', 'admin_logistik']) ||
            $user->can('update_audit_manajemen::temuan::capa')
        );
        if (! $canEvaluate) {
            return $this->jsonError('Hanya Auditor Internal SPI yang berwenang mengevaluasi dan menutup temuan CAPA.', 403);
        }

        $validator = Validator::make($request->all(), [
            'hasil_evaluasi'   => ['required', \Illuminate\Validation\Rule::in(StatusCapa::hasilEvaluasi())],
            'catatan_evaluasi' => 'required|string|min:5',
        ]);

        if ($validator->fails()) {
            return $this->jsonError('Validasi gagal.', 422, $validator->errors());
        }

        $temuan->update([
            'status_capa'      => $request->input('hasil_evaluasi'),
            'catatan_evaluasi' => $request->input('catatan_evaluasi'),
            'dievaluasi_oleh'  => $user->id,
        ]);

        return $this->jsonSuccess([
            'id'          => $temuan->id,
            'kode'        => $temuan->kode,
            'status_capa' => $temuan->status_capa,
        ], "Evaluasi temuan CAPA '{$temuan->kode}' berhasil disimpan sebagai {$temuan->status_capa}.");
    }
}