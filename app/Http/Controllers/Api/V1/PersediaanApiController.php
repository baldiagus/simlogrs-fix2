<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\PenyesuaianStok;
use App\Models\PenyesuaianStokDetail;
use App\Models\PergerakanStok;
use App\Models\StokBatch;
use App\Models\StokOpname;
use App\Models\StokOpnameDetail;
use App\Support\Generator\CodeGenerator;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PersediaanApiController extends ApiController
{
    /**
     * 1. Monitoring Stok FEFO (First-Expired, First-Out)
     * GET /v1/gudang/fefo/monitoring
     */
    public function monitoringFefo(Request $request): JsonResponse
    {
        $user = $request->user();
        $gudangId = $request->query('gudang_id', $user->gudang_id);
        $kategori = strtoupper($request->query('kategori', 'SEMUA'));
        $cari = $request->query('cari');

        $today = now()->toDateString();
        $date30 = now()->addDays(30)->toDateString();
        $date90 = now()->addDays(90)->toDateString();

        // Metrik Ringkasan FEFO
        $baseMetrics = StokBatch::where('status', 'AKTIF')
            ->where('jumlah', '>', 0)
            ->when($gudangId, fn ($q) => $q->where('gudang_id', $gudangId));

        $countExpired = (clone $baseMetrics)
            ->whereNotNull('tanggal_kedaluwarsa')
            ->where('tanggal_kedaluwarsa', '<', $today)
            ->count();

        $countKritis30 = (clone $baseMetrics)
            ->whereNotNull('tanggal_kedaluwarsa')
            ->whereBetween('tanggal_kedaluwarsa', [$today, $date30])
            ->count();

        $countPeringatan90 = (clone $baseMetrics)
            ->whereNotNull('tanggal_kedaluwarsa')
            ->whereBetween('tanggal_kedaluwarsa', [$date30, $date90])
            ->count();

        $countRop = Barang::where('status', 'AKTIF')
            ->where('titik_pesan', '>', 0)
            ->whereRaw('(SELECT COALESCE(SUM(jumlah), 0) FROM stok_batch WHERE stok_batch.barang_id = barang.id AND stok_batch.status = \'AKTIF\') <= barang.titik_pesan')
            ->count();

        // Query Daftar Batch FEFO
        $query = StokBatch::with([
            'barang.kategori:id,nama',
            'barang.satuan:id,nama,simbol',
            'gudang:id,kode,nama',
            'lokasiGudang:id,kode,nama',
        ])
        ->where('status', 'AKTIF')
        ->where('jumlah', '>', 0)
        ->when($gudangId, fn ($q) => $q->where('gudang_id', $gudangId));

        // Filter Berdasarkan Kategori FEFO
        if ($kategori === 'KEDALUWARSA') {
            $query->whereNotNull('tanggal_kedaluwarsa')->where('tanggal_kedaluwarsa', '<', $today);
        } elseif ($kategori === 'KRITIS') {
            $query->whereNotNull('tanggal_kedaluwarsa')->whereBetween('tanggal_kedaluwarsa', [$today, $date30]);
        } elseif ($kategori === 'PERINGATAN') {
            $query->whereNotNull('tanggal_kedaluwarsa')->whereBetween('tanggal_kedaluwarsa', [$date30, $date90]);
        } elseif ($kategori === 'ROP') {
            $query->whereHas('barang', function ($b) {
                $b->where('titik_pesan', '>', 0)
                  ->whereRaw('(SELECT COALESCE(SUM(jumlah), 0) FROM stok_batch WHERE stok_batch.barang_id = barang.id AND stok_batch.status = \'AKTIF\') <= barang.titik_pesan');
            });
        }

        // Pencarian Kata Kunci
        if ($cari) {
            $query->where(function ($sub) use ($cari) {
                $sub->where('nomor_batch', 'ilike', "%{$cari}%")
                    ->orWhereHas('barang', fn ($b) => $b->where('nama', 'ilike', "%{$cari}%")->orWhere('kode', 'ilike', "%{$cari}%"))
                    ->orWhereHas('lokasiGudang', fn ($lg) => $lg->where('nama', 'ilike', "%{$cari}%"));
            });
        }

        $batches = $query->orderByRaw('tanggal_kedaluwarsa ASC NULLS LAST')
            ->limit(100)
            ->get()
            ->map(function ($b) use ($today, $date30, $date90) {
                $ed = $b->tanggal_kedaluwarsa;
                $sisaHari = null;
                $statusFefo = 'AMAN'; // AMAN, PERINGATAN, KRITIS, KEDALUWARSA, TANPA_ED

                if ($ed) {
                    $sisaHari = Carbon::parse($today)->diffInDays(Carbon::parse($ed), false);
                    if ($sisaHari < 0) {
                        $statusFefo = 'KEDALUWARSA';
                    } elseif ($sisaHari <= 30) {
                        $statusFefo = 'KRITIS';
                    } elseif ($sisaHari <= 90) {
                        $statusFefo = 'PERINGATAN';
                    }
                } else {
                    $statusFefo = 'TANPA_ED';
                }

                $nilaiTotal = (float) $b->jumlah * (float) ($b->harga_satuan ?? 0);

                return [
                    'id'                  => $b->id,
                    'nomor_batch'         => $b->nomor_batch,
                    'barang_id'           => $b->barang_id,
                    'kode_barang'         => $b->barang?->kode ?? '-',
                    'nama_barang'         => $b->barang?->nama ?? 'Komoditas',
                    'kategori'            => $b->barang?->kategori?->nama ?? 'Umum',
                    'satuan'              => $b->barang?->satuan?->simbol ?? $b->barang?->satuan?->nama ?? 'Pcs',
                    'gudang'              => $b->gudang?->nama ?? 'Gudang Sentral',
                    'lokasi_rak'          => $b->lokasiGudang ? "{$b->lokasiGudang->nama} ({$b->lokasiGudang->kode})" : 'Tanpa Rak',
                    'tanggal_kedaluwarsa' => $ed?->format('Y-m-d'),
                    'sisa_hari'           => $sisaHari,
                    'jumlah'              => (int) $b->jumlah,
                    'harga_satuan'        => (float) ($b->harga_satuan ?? 0),
                    'nilai_total'         => $nilaiTotal,
                    'status_fefo'         => $statusFefo,
                ];
            });

        return $this->jsonSuccess([
            'metrik' => [
                'count_expired'       => $countExpired,
                'count_kritis_30'     => $countKritis30,
                'count_peringatan_90' => $countPeringatan90,
                'count_rop'           => $countRop,
                'total_batch_aktif'   => $baseMetrics->count(),
            ],
            'items'  => $batches,
        ], 'Data monitoring FEFO berhasil dimuat.');
    }

    /**
     * 2. Buku Mutasi Kartu Stok
     * GET /v1/gudang/mutasi/buku
     */
    public function bukuMutasi(Request $request): JsonResponse
    {
        $jenis = $request->query('jenis'); // MASUK, KELUAR, PENYESUAIAN, TRANSFER
        $gudangId = $request->query('gudang_id');
        $barangId = $request->query('barang_id');
        $tanggalMulai = $request->query('tanggal_mulai');
        $tanggalSelesai = $request->query('tanggal_selesai');
        $cari = $request->query('cari');

        $query = PergerakanStok::with([
            'barang.satuan:id,nama,simbol',
            'stokBatch:id,nomor_batch,tanggal_kedaluwarsa',
            'gudang:id,kode,nama',
            'lokasiGudang:id,kode,nama',
            'pembuatRecord:id,nama',
        ])
        ->when($jenis, fn ($q) => $q->where('jenis', $jenis))
        ->when($gudangId, fn ($q) => $q->where('gudang_id', $gudangId))
        ->when($barangId, fn ($q) => $q->where('barang_id', $barangId))
        ->when($tanggalMulai, fn ($q) => $q->whereDate('dibuat_pada', '>=', $tanggalMulai))
        ->when($tanggalSelesai, fn ($q) => $q->whereDate('dibuat_pada', '<=', $tanggalSelesai))
        ->when($cari, function ($q) use ($cari) {
            $q->where(function ($sub) use ($cari) {
                $sub->where('keterangan', 'ilike', "%{$cari}%")
                    ->orWhere('referensi_jenis', 'ilike', "%{$cari}%")
                    ->orWhereHas('barang', fn ($b) => $b->where('nama', 'ilike', "%{$cari}%")->orWhere('kode', 'ilike', "%{$cari}%"))
                    ->orWhereHas('stokBatch', fn ($sb) => $sb->where('nomor_batch', 'ilike', "%{$cari}%"));
            });
        });

        $mutasi = $query->orderBy('id', 'desc')
            ->limit(100)
            ->get()
            ->map(function ($m) {
                return [
                    'id'               => $m->id,
                    'tanggal'          => $m->dibuat_pada?->format('Y-m-d H:i') ?? '-',
                    'jenis'            => $m->jenis,
                    'barang_id'        => $m->barang_id,
                    'kode_barang'      => $m->barang?->kode ?? '-',
                    'nama_barang'      => $m->barang?->nama ?? 'Komoditas',
                    'satuan'           => $m->barang?->satuan?->simbol ?? 'Pcs',
                    'nomor_batch'      => $m->stokBatch?->nomor_batch ?? '-',
                    'gudang'           => $m->gudang?->nama ?? 'Gudang',
                    'lokasi_rak'       => $m->lokasiGudang?->nama ?? 'Tanpa Rak',
                    'jumlah'           => (int) $m->jumlah,
                    'saldo_sebelum'    => (int) $m->saldo_sebelum,
                    'saldo_sesudah'    => (int) $m->saldo_sesudah,
                    'referensi_jenis'  => $m->referensi_jenis,
                    'referensi_id'     => $m->referensi_id,
                    'keterangan'       => $m->keterangan ?? '-',
                    'petugas'          => $m->pembuatRecord?->nama ?? 'Sistem',
                ];
            });

        return $this->jsonSuccess($mutasi, 'Buku mutasi stok berhasil dimuat.');
    }

    /**
     * 3. Daftar Sesi Stock Opname
     * GET /v1/gudang/opname/sesi
     */
    public function daftarOpname(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $gudangId = $request->query('gudang_id');

        $query = StokOpname::with([
            'gudang:id,kode,nama',
            'pembuat:id,nama',
            'detail.barang.satuan',
            'detail.stokBatch.lokasiGudang',
        ])
        ->when($status, fn ($q) => $q->where('status', $status))
        ->when($gudangId, fn ($q) => $q->where('gudang_id', $gudangId));

        $opnames = $query->orderBy('id', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($op) {
                $totalItem = $op->detail->count();
                $totalTerhitung = $op->detail->whereNotNull('jumlah_fisik')->count();
                $totalSelisih = $op->detail->where('selisih', '!=', 0)->count();

                return [
                    'id'               => $op->id,
                    'kode'             => $op->kode,
                    'gudang_id'        => $op->gudang_id,
                    'gudang_nama'      => $op->gudang?->nama ?? 'Gudang',
                    'tanggal_opname'   => $op->tanggal_opname?->format('Y-m-d') ?? '-',
                    'status'           => $op->status,
                    'catatan'          => $op->catatan ?? '-',
                    'petugas_inisiasi' => $op->pembuat?->nama ?? 'Kepala Gudang',
                    'total_item'       => $totalItem,
                    'total_terhitung'  => $totalTerhitung,
                    'total_selisih'    => $totalSelisih,
                    'details'          => $op->detail->map(fn ($d) => [
                        'id'             => $d->id,
                        'stok_batch_id'  => $d->stok_batch_id,
                        'barang_id'      => $d->barang_id,
                        'kode_barang'    => $d->barang?->kode ?? '-',
                        'nama_barang'    => $d->barang?->nama ?? 'Barang',
                        'satuan'         => $d->barang?->satuan?->simbol ?? 'Pcs',
                        'nomor_batch'    => $d->stokBatch?->nomor_batch ?? '-',
                        'lokasi_rak'     => $d->stokBatch?->lokasiGudang?->nama ?? 'Tanpa Rak',
                        'jumlah_sistem'  => (int) $d->jumlah_sistem,
                        'jumlah_fisik'   => $d->jumlah_fisik !== null ? (int) $d->jumlah_fisik : null,
                        'selisih'        => (int) $d->selisih,
                        'catatan'        => $d->catatan ?? '',
                    ]),
                ];
            });

        return $this->jsonSuccess($opnames, 'Daftar sesi stock opname berhasil dimuat.');
    }

    /**
     * 4. Inisiasi Sesi Stock Opname Baru
     * POST /v1/gudang/opname/inisiasi
     */
    public function inisiasiOpname(Request $request): JsonResponse
    {
        $user = $request->user();
        $canInitiate = $user && (
            $user->can('create_gudang_stok::stok::opname') ||
            $user->hasRole(['kepala_gudang', 'admin_logistik', 'super_admin'])
        );

        if (! $canInitiate) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk menginisiasi stock opname.', 403);
        }

        $validator = Validator::make($request->all(), [
            'gudang_id'      => 'required|exists:gudang,id',
            'tanggal_opname' => 'required|date',
            'catatan'        => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->jsonError('Validasi inisiasi opname gagal.', 422, $validator->errors());
        }

        $gudang = Gudang::findOrFail($request->input('gudang_id'));
        if (! $user->hasRole(['super_admin', 'admin_logistik']) && $user->gudang_id && (int) $user->gudang_id !== (int) $gudang->id) {
            return $this->jsonError('Anda tidak memiliki wewenang menginisiasi opname di fasilitas gudang ini.', 403);
        }

        $batches = StokBatch::where('gudang_id', $gudang->id)
            ->where('status', 'AKTIF')
            ->get();

        if ($batches->isEmpty()) {
            return $this->jsonError("Tidak ada stok batch aktif di fasilitas {$gudang->nama} untuk diopname.", 422);
        }

        $kodeOpn = CodeGenerator::generateOpn();

        $opname = DB::transaction(function () use ($kodeOpn, $gudang, $user, $batches, $request) {
            $op = StokOpname::create([
                'kode'           => $kodeOpn,
                'gudang_id'      => $gudang->id,
                'tanggal_opname' => $request->input('tanggal_opname'),
                'status'         => 'BERJALAN',
                'catatan'        => $request->input('catatan'),
                'dibuat_oleh'    => $user->id,
            ]);

            foreach ($batches as $b) {
                StokOpnameDetail::create([
                    'stok_opname_id' => $op->id,
                    'barang_id'      => $b->barang_id,
                    'stok_batch_id'  => $b->id,
                    'jumlah_sistem'  => $b->jumlah,
                    'jumlah_fisik'   => 0,
                    'selisih'        => 0 - $b->jumlah,
                    'catatan'        => null,
                ]);
            }

            return $op;
        });

        return $this->jsonSuccess([
            'id'             => $opname->id,
            'kode'           => $opname->kode,
            'status'         => $opname->status,
            'total_batch'    => $batches->count(),
        ], "Sesi Stock Opname {$kodeOpn} berhasil diinisiasi.");
    }

    /**
     * 5. Simpan Hasil Hitung Fisik (Blind Count) & Finalisasi
     * POST /v1/gudang/opname/{id}/count
     */
    public function simpanHitungFisik(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $opname = StokOpname::with('detail.stokBatch')->find($id);

        if (! $opname) {
            return $this->jsonError('Sesi stock opname tidak ditemukan.', 404);
        }
        $canCount = $user && (
            $user->can('update_gudang_stok::stok::opname') ||
            $user->can('create_gudang_stok::stok::opname') ||
            $user->hasRole(['petugas_gudang', 'kepala_gudang', 'admin_logistik', 'super_admin'])
        );

        if (! $canCount) {
            return $this->jsonError('Anda tidak memiliki wewenang menginput hasil stock opname.', 403);
        }

        if (! $user->hasRole(['super_admin', 'admin_logistik']) && $user->gudang_id && (int) $user->gudang_id !== (int) $opname->gudang_id) {
            return $this->jsonError('Anda tidak memiliki wewenang memproses opname di fasilitas gudang ini.', 403);
        }

        if (! in_array($opname->status, ['BERJALAN', 'PROSES_COUNT', 'MENUNGGU_APPROVAL'])) {
            return $this->jsonError("Sesi opname telah ditutup dengan status {$opname->status}.", 422);
        }

        $validator = Validator::make($request->all(), [
            'items'                  => 'required|array|min:1',
            'items.*.detail_id'      => 'required|exists:stok_opname_detail,id',
            'items.*.jumlah_fisik'   => 'required|integer|min:0',
            'items.*.catatan'        => 'nullable|string|max:255',
            'finalisasi'             => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->jsonError('Validasi perhitungan fisik gagal.', 422, $validator->errors());
        }

        $finalisasi = $request->boolean('finalisasi', false);

        DB::transaction(function () use ($opname, $request, $user, $finalisasi) {
            $hasSelisih = false;
            $selisihRows = [];

            foreach ($request->input('items') as $row) {
                $detail = $opname->detail->firstWhere('id', $row['detail_id']);
                if (! $detail) continue;

                $fisik = (int) $row['jumlah_fisik'];
                $sistem = (int) $detail->jumlah_sistem;
                $selisih = $fisik - $sistem;

                $detail->update([
                    'jumlah_fisik' => $fisik,
                    'selisih'      => $selisih,
                    'catatan'      => $row['catatan'] ?? $detail->catatan,
                ]);

                if ($selisih !== 0) {
                    $hasSelisih = true;
                    $selisihRows[] = [
                        'barang_id'      => $detail->barang_id,
                        'stok_batch_id'  => $detail->stok_batch_id,
                        'jumlah'         => abs($selisih),
                        'jenis'          => $selisih > 0 ? 'TAMBAH' : 'KURANG',
                        'harga_satuan'   => $detail->stokBatch?->harga_satuan ?? 0,
                        'subtotal'       => abs($selisih) * (float) ($detail->stokBatch?->harga_satuan ?? 0),
                        'catatan'        => $row['catatan'] ?? 'Selisih opname fisik',
                    ];
                }
            }

            if ($finalisasi) {
                if ($hasSelisih) {
                    $opname->update(['status' => 'MENUNGGU_APPROVAL']);

                    // Buat dokumen PenyesuaianStok jika belum ada
                    $existingAdj = PenyesuaianStok::where('stok_opname_id', $opname->id)->first();
                    if (! $existingAdj) {
                        $kodeAdj = CodeGenerator::generateAdj();
                        $penyesuaian = PenyesuaianStok::create([
                            'kode'                => $kodeAdj,
                            'stok_opname_id'      => $opname->id,
                            'gudang_id'           => $opname->gudang_id,
                            'tanggal_penyesuaian' => now()->toDateString(),
                            'alasan'              => "Selisih perhitungan fisik Stock Opname {$opname->kode}",
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
                    $opname->update([
                        'status'         => 'DISETUJUI',
                        'disetujui_oleh' => $user->id,
                    ]);
                }
            } else {
                $opname->update(['status' => 'PROSES_COUNT']);
            }
        });

        return $this->jsonSuccess([
            'opname_id' => $opname->id,
            'status'    => $opname->fresh()->status,
        ], 'Perhitungan fisik stock opname berhasil dicatat.');
    }

    /**
     * 6. Daftar Otorisasi Penyesuaian Stok (Variance Authorization)
     * GET /v1/gudang/penyesuaian
     */
    public function daftarPenyesuaian(Request $request): JsonResponse
    {
        $user = $request->user();
        $canView = $user && (
            $user->can('view_any_gudang_stok::otorisasi::penyesuaian') ||
            $user->can('update_gudang_stok::otorisasi::penyesuaian') ||
            $user->hasRole(['kepala_gudang', 'admin_logistik', 'super_admin', 'auditor', 'manajemen'])
        );

        if (! $canView) {
            return $this->jsonError('Anda tidak memiliki wewenang melihat daftar otorisasi penyesuaian stok.', 403);
        }
        $status = $request->query('status', 'MENUNGGU');
        $gudangId = $request->query('gudang_id');

        $query = PenyesuaianStok::with([
            'stokOpname:id,kode,tanggal_opname',
            'gudang:id,kode,nama',
            'pengaju:id,nama',
            'penyetuju:id,nama',
            'detail.barang.satuan',
            'detail.stokBatch.lokasiGudang',
        ])
        ->when($status && $status !== 'SEMUA', fn ($q) => $q->where('status', $status))
        ->when($gudangId, fn ($q) => $q->where('gudang_id', $gudangId));

        $items = $query->orderBy('id', 'desc')->limit(50)->get()->map(function ($adj) {
            $totalNilai = (float) $adj->detail->sum('subtotal_nilai');

            return [
                'id'                  => $adj->id,
                'kode'                => $adj->kode,
                'kode_opname'         => $adj->stokOpname?->kode ?? '-',
                'gudang_id'           => $adj->gudang_id,
                'gudang_nama'         => $adj->gudang?->nama ?? 'Gudang Sentral',
                'tanggal_penyesuaian' => $adj->tanggal_penyesuaian?->format('Y-m-d') ?? '-',
                'alasan'              => $adj->alasan ?? 'Selisih opname fisik',
                'status'              => $adj->status,
                'total_item'          => $adj->detail->count(),
                'total_nilai_selisih' => $totalNilai,
                'pengaju'             => $adj->pengaju?->nama ?? 'Petugas Gudang',
                'penyetuju'           => $adj->penyetuju?->nama ?? '-',
                'items'               => $adj->detail->map(fn ($d) => [
                    'id'                 => $d->id,
                    'barang_id'          => $d->barang_id,
                    'kode_barang'        => $d->barang?->kode ?? '-',
                    'nama_barang'        => $d->barang?->nama ?? 'Barang',
                    'satuan'             => $d->barang?->satuan?->simbol ?? 'Pcs',
                    'nomor_batch'        => $d->stokBatch?->nomor_batch ?? '-',
                    'lokasi_rak'         => $d->stokBatch?->lokasiGudang?->nama ?? 'Tanpa Rak',
                    'jenis_penyesuaian'  => $d->jenis_penyesuaian, // TAMBAH / KURANG
                    'jumlah_penyesuaian' => (int) $d->jumlah_penyesuaian,
                    'harga_satuan'       => (float) $d->harga_satuan,
                    'subtotal_nilai'     => (float) $d->subtotal_nilai,
                    'catatan'            => $d->catatan ?? '-',
                ]),
            ];
        });

        return $this->jsonSuccess($items, 'Daftar dokumen penyesuaian stok berhasil dimuat.');
    }

    /**
     * 7. Sahkan Otorisasi Penyesuaian Stok (Approve)
     * POST /v1/gudang/penyesuaian/{id}/approve
     */
    public function approvePenyesuaian(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $adj = PenyesuaianStok::with(['stokOpname', 'detail.stokBatch', 'detail.barang'])->find($id);

        if (! $adj) {
            return $this->jsonError('Dokumen penyesuaian stok tidak ditemukan.', 404);
        }
        $canAuthorize = $user && (
            $user->can('update_gudang_stok::otorisasi::penyesuaian') ||
            $user->hasRole(['kepala_gudang', 'admin_logistik', 'super_admin'])
        );

        if (! $canAuthorize) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk mengesahkan penyesuaian stok.', 403);
        }

        if (! $user->hasRole(['super_admin', 'admin_logistik']) && $user->gudang_id && (int) $user->gudang_id !== (int) $adj->gudang_id) {
            return $this->jsonError('Anda tidak memiliki wewenang mengesahkan penyesuaian stok untuk fasilitas gudang ini.', 403);
        }

        if ($adj->status !== 'MENUNGGU') {
            return $this->jsonError("Dokumen telah diproses sebelumnya dengan status {$adj->status}.", 422);
        }

        try {
            DB::transaction(function () use ($adj, $user) {
                foreach ($adj->detail as $detail) {
                    $batch = StokBatch::lockForUpdate()->findOrFail($detail->stok_batch_id);
                    $qty = (int) $detail->jumlah_penyesuaian;
                    $saldoSebelum = $batch->jumlah;

                    if ($detail->jenis_penyesuaian === 'KURANG') {
                        if ($saldoSebelum < $qty) {
                            throw new Exception("Saldo stok batch '{$batch->nomor_batch}' ({$detail->barang?->nama}) saat ini tidak mencukupi untuk dikurangi {$qty} unit.");
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
                    'status'         => 'DISETUJUI',
                    'disetujui_oleh' => $user->id,
                ]);

                if ($adj->stokOpname) {
                    $adj->stokOpname->update([
                        'status'         => 'DISETUJUI',
                        'disetujui_oleh' => $user->id,
                    ]);
                }
            });

            return $this->jsonSuccess(null, "Otorisasi penyesuaian stok {$adj->kode} berhasil disahkan. Saldo batch fisik telah disinkronkan.");
        } catch (Exception $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    /**
     * 8. Tolak Otorisasi Penyesuaian Stok (Reject)
     * POST /v1/gudang/penyesuaian/{id}/reject
     */
    public function rejectPenyesuaian(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $adj = PenyesuaianStok::with('stokOpname')->find($id);

        if (! $adj) {
            return $this->jsonError('Dokumen penyesuaian stok tidak ditemukan.', 404);
        }
        $canAuthorize = $user && (
            $user->can('update_gudang_stok::otorisasi::penyesuaian') ||
            $user->hasRole(['kepala_gudang', 'admin_logistik', 'super_admin'])
        );

        if (! $canAuthorize) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk menolak otorisasi penyesuaian stok.', 403);
        }

        if (! $user->hasRole(['super_admin', 'admin_logistik']) && $user->gudang_id && (int) $user->gudang_id !== (int) $adj->gudang_id) {
            return $this->jsonError('Anda tidak memiliki wewenang menolak penyesuaian stok untuk fasilitas gudang ini.', 403);
        }

        if ($adj->status !== 'MENUNGGU') {
            return $this->jsonError("Dokumen telah diproses sebelumnya dengan status {$adj->status}.", 422);
        }

        $alasan = $request->input('alasan', 'Penyesuaian stok ditolak oleh Kepala Gudang.');

        DB::transaction(function () use ($adj, $user, $alasan) {
            $adj->update([
                'status'         => 'DITOLAK',
                'disetujui_oleh' => $user->id,
                'alasan'         => $adj->alasan . " [DITOLAK: {$alasan}]",
            ]);

            if ($adj->stokOpname) {
                $adj->stokOpname->update([
                    'status' => 'DIBATALKAN',
                ]);
            }
        });

        return $this->jsonSuccess(null, "Penyesuaian stok {$adj->kode} telah ditolak.");
    }
}
