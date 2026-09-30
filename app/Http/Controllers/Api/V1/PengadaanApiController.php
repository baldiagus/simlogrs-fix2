<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Barang;
use App\Models\Pemasok;
use App\Models\PesananPembelian;
use App\Models\PesananPembelianDetail;
use App\Models\PermintaanPengadaan;
use App\Models\PermintaanPengadaanDetail;
use App\Support\Generator\CodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PengadaanApiController extends ApiController
{
    /**
     * Katalog pencarian master barang untuk pengajuan pengadaan/amprah.
     * GET /v1/master/barang
     */
    public function katalogBarang(Request $request): JsonResponse
    {
        $cari = $request->query('cari');
        $tipe = $request->query('tipe');

        $barang = Barang::query()
            ->where('status', 'AKTIF')
            ->with(['kategori:id,nama', 'satuan:id,nama', 'merek:id,nama'])
            ->when($tipe, fn ($q) => $q->where('tipe', $tipe))
            ->when($cari, function ($q) use ($cari) {
                $q->where(function ($sub) use ($cari) {
                    $sub->where('nama', 'like', "%{$cari}%")
                        ->orWhere('kode', 'like', "%{$cari}%");
                });
            })
            ->limit(50)
            ->get()
            ->map(function ($b) {
                $tipe = $b->tipe ?? 'HABIS_PAKAI';
                $tipeLabel = match ($tipe) {
                    'ASET'      => 'Aktiva / Aset Inventaris',
                    'SPAREPART' => 'Suku Cadang IPSRS',
                    default     => 'Habis Pakai (ATK/ART)',
                };
                return [
                    'id'             => $b->id,
                    'kode'           => $b->kode,
                    'nama'           => $b->nama,
                    'spesifikasi'    => $b->nama,
                    'tipe'           => $tipe,
                    'tipe_label'     => $tipeLabel,
                    'kategori'       => $b->kategori?->nama ?? 'Umum',
                    'satuan'         => $b->satuan?->nama ?? 'Pcs',
                    'merek'          => $b->merek?->nama ?? '-',
                    'harga_estimasi' => (float) ($b->harga_estimasi ?? 0),
                ];
            });

        return $this->jsonSuccess($barang, 'Katalog barang berhasil diambil.');
    }

    /**
     * Riwayat daftar Permintaan Pengadaan (PR) milik unit pemohon.
     * GET /v1/pengadaan/pr
     */
    public function daftarPr(Request $request): JsonResponse
    {
        $user = $request->user();

        $isCrossUnitViewer = $user && (
            $user->can('page_PengadaanDashboard') ||
            $user->can('page_AdminLogistikDashboard') ||
            $user->can('page_Dashboard') ||
            $user->can('page_ManajemenDashboard') ||
            $user->can('page_AuditorDashboard')
        );

        $pr = PermintaanPengadaan::query()
            ->when(! $isCrossUnitViewer, function ($q) use ($user) {
                if ($user->unit_id && $user->gudang_id) {
                    $q->where(function ($sub) use ($user) {
                        $sub->where('unit_id', $user->unit_id)
                            ->orWhere('gudang_id', $user->gudang_id);
                    });
                } elseif ($user->unit_id) {
                    $q->where('unit_id', $user->unit_id);
                } elseif ($user->gudang_id) {
                    $q->where('gudang_id', $user->gudang_id);
                } else {
                    $q->where('diajukan_oleh', $user->id);
                }
            })
            ->with(['unit:id,kode,nama', 'gudang:id,kode,nama', 'pengaju:id,nama', 'detail.barang:id,kode,nama,satuan_id', 'detail.barang.satuan:id,nama', 'pesananPembelian:id,permintaan_pengadaan_id,kode,status'])
            ->latest('dibuat_pada')
            ->limit(100)
            ->get()
            ->map(function ($item) {
                $status = $item->status;
                if ($status === 'DIPROSES' && $item->pesananPembelian->isNotEmpty()) {
                    $allPoReceived = $item->pesananPembelian->every(fn ($po) => $po->status === 'DITERIMA_LENGKAP');
                    if ($allPoReceived) {
                        $status = 'SELESAI';
                        $item->update(['status' => 'SELESAI']);
                    }
                }

                return [
                    'id'                 => $item->id,
                    'kode'               => $item->kode,
                    'unit'               => $item->nama_pemohon,
                    'tipe_pemohon'       => $item->tipe_pemohon,
                    'pengaju'            => $item->pengaju?->nama ?? 'Staf',
                    'tanggal_permintaan' => $item->tanggal_permintaan?->format('Y-m-d') ?? '',
                    'urgensi'            => $item->urgensi,
                    'status'             => $status,
                    'nomor_po'           => $item->pesananPembelian->first()?->kode,
                    'status_po'          => $item->pesananPembelian->first()?->status,
                    'catatan'            => $item->catatan,
                    'jumlah_item'        => $item->detail->count(),
                'items'              => $item->detail->map(fn ($d) => [
                    'id'          => $d->id,
                    'barang_id'   => $d->barang_id,
                    'nama_barang' => $d->barang?->nama ?? 'Barang',
                    'kode_barang' => $d->barang?->kode,
                    'satuan'      => $d->barang?->satuan?->nama ?? 'Pcs',
                    'jumlah'      => $d->jumlah,
                    'catatan'     => $d->catatan,
                ]),
                ];
            });

        return $this->jsonSuccess($pr, 'Daftar permintaan pengadaan berhasil dimuat.');
    }

    /**
     * Pengajuan dokumen Permintaan Pengadaan (PR) baru dari gawai mobile.
     * POST /v1/pengadaan/pr
     */
    public function simpanPr(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->can('create_pengadaan::permintaan::pengadaan') &&
            ! $user->hasRole('petugas_unit') &&
            ! $user->hasRole('kepala_unit') &&
            ! $user->hasRole('super_admin')) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk mengajukan permintaan pengadaan.', 403);
        }

        $unitId = $request->input('unit_id') ?: $user->unit_id;
        $gudangId = $request->input('gudang_id') ?: ($user->unit_id ? null : $user->gudang_id);

        if (! $unitId && ! $gudangId && ($user->hasRole('super_admin') || $user->can('create_pengadaan::permintaan::pengadaan'))) {
            $firstUnit = \App\Models\Unit::where('status', 'AKTIF')->first();
            $unitId = $firstUnit?->id;
        }

        $request->merge([
            'unit_id' => $unitId,
            'gudang_id' => $unitId ? null : $gudangId,
        ]);

        $validator = Validator::make($request->all(), [
            'unit_id'          => 'nullable|required_without:gudang_id|exists:unit,id',
            'gudang_id'        => 'nullable|required_without:unit_id|exists:gudang,id',
            'urgensi'          => 'required|in:REGULER,MENDESAK,CITO',
            'catatan'          => 'nullable|string|max:1000',
            'items'            => 'required|array|min:1',
            'items.*.barang_id'=> 'required|exists:barang,id',
            'items.*.jumlah'   => 'required|integer|min:1',
            'items.*.catatan'  => 'nullable|string|max:500',
        ], [
            'items.required'            => 'Minimal 1 barang harus dimasukkan ke dalam permohonan pengadaan.',
            'items.min'                 => 'Minimal 1 barang harus dimasukkan ke dalam permohonan pengadaan.',
            'items.*.barang_id.required'=> 'Barang wajib dipilih.',
            'items.*.jumlah.min'        => 'Jumlah barang minimal 1 unit.',
        ]);

        if ($validator->fails()) {
            return $this->jsonError('Validasi data gagal.', 422, $validator->errors());
        }

        $unitId = $request->input('unit_id');
        $gudangId = $request->input('gudang_id');

        if (! $unitId && ! $gudangId) {
            $unitId = $user->unit_id;
            $gudangId = $user->unit_id ? null : $user->gudang_id;
        }

        if (! $unitId && ! $gudangId) {
            return $this->jsonError('Pilih salah satu entitas pemohon: Unit Ruangan atau Gudang.', 422);
        }

        // Mutual exclusion: jika unit dipilih, gudang dibersihkan, dan sebaliknya
        if ($unitId) {
            $gudangId = null;
        }

        try {
            $pr = DB::transaction(function () use ($request, $user, $unitId, $gudangId) {
                $kodePr = CodeGenerator::generatePr();

                $prHeader = PermintaanPengadaan::create([
                    'kode'               => $kodePr,
                    'unit_id'            => $unitId,
                    'gudang_id'          => $gudangId,
                    'diajukan_oleh'      => $user->id,
                    'tanggal_permintaan' => now()->toDateString(),
                    'urgensi'            => $request->input('urgensi'),
                    'status'             => 'DIAJUKAN',
                    'catatan'            => $request->input('catatan'),
                ]);
                foreach ($request->input('items') as $row) {
                    PermintaanPengadaanDetail::create([
                        'permintaan_pengadaan_id' => $prHeader->id,
                        'barang_id'               => $row['barang_id'],
                        'jumlah'                  => $row['jumlah'],
                        'catatan'                 => $row['catatan'] ?? null,
                    ]);
                }

                return $prHeader->load(['unit', 'gudang', 'pengaju', 'detail.barang']);
            });

            return $this->jsonSuccess([
                'id'      => $pr->id,
                'kode'    => $pr->kode,
                'status'  => $pr->status,
                'urgensi' => $pr->urgensi,
            ], "Permintaan pengadaan {$pr->kode} berhasil diajukan.", 201);
        } catch (\Throwable $e) {
            return $this->jsonError('Gagal menyimpan permintaan pengadaan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Daftar pemasok/rekanan aktif untuk dropdown penerbitan PO.
     * GET /v1/pengadaan/pemasok
     */
    public function daftarPemasok(Request $request): JsonResponse
    {
        $pemasok = Pemasok::where('status', 'AKTIF')
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama', 'kota', 'no_telepon', 'alamat']);

        return $this->jsonSuccess($pemasok, 'Daftar rekanan pemasok berhasil dimuat.');
    }

    /**
     * Daftar seluruh dokumen Pesanan Pembelian (PO).
     * GET /v1/pengadaan/po
     */
    public function daftarPo(Request $request): JsonResponse
    {
        $user = $request->user();
        $isGlobal = $user && (
            $user->hasRole(['pengadaan', 'super_admin', 'admin_logistik', 'manajemen', 'auditor', 'kepala_gudang', 'petugas_gudang']) ||
            $user->can('view_any_pengadaan::pesanan::pembelian') ||
            $user->can('view_any_pengadaan::penerimaan::po') ||
            $user->can('page_PesananPembelianIndex') ||
            $user->can('page_PenerimaanPoIndex') ||
            $user->can('page_PengadaanDashboard') ||
            $user->can('page_ManajemenDashboard') ||
            $user->can('page_AuditorDashboard')
        );

        $hasUnitAccess = $user && ($user->unit_id || $user->hasRole(['kepala_unit', 'petugas_unit']));

        if (! $isGlobal && ! $hasUnitAccess) {
            return $this->jsonError('Akses ditolak: Anda tidak memiliki wewenang melihat daftar PO.', 403);
        }
        $status = $request->query('status');
        $search = $request->query('search');

        $query = PesananPembelian::with([
            'pemasok:id,kode,nama,kota,no_telepon',
            'permintaanPengadaan.unit:id,nama',
            'pembuat:id,nama',
            'detail.barang.satuan',
        ])

        ->when($status, fn ($q) => $q->where('status', $status))
        ->when($search, function ($q) use ($search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('kode', 'like', "%{$search}%")
                    ->orWhereHas('pemasok', fn ($p) => $p->where('nama', 'like', "%{$search}%"));
            });
        });

        if (! $isGlobal && $user->unit_id) {
            $query->whereHas('permintaanPengadaan', fn ($q) => $q->where('unit_id', $user->unit_id));
        }

        $pos = $query->limit(100)->get()->map(function ($po) {
            return [
                'id'                      => $po->id,
                'kode'                    => $po->kode,
                'pemasok_id'              => $po->pemasok_id,
                'nama_pemasok'            => $po->pemasok?->nama ?? 'Vendor',
                'kode_pemasok'            => $po->pemasok?->kode ?? '-',
                'permintaan_pengadaan_id' => $po->permintaan_pengadaan_id,
                'pr_kode'                 => $po->permintaanPengadaan?->kode,
                'pr_unit'                 => $po->permintaanPengadaan?->unit?->nama ?? 'Unit',
                'tanggal_po'              => $po->tanggal_po?->format('Y-m-d') ?? '-',
                'estimasi_tanggal_kirim'  => $po->estimasi_tanggal_kirim?->format('Y-m-d') ?? '-',
                'total_biaya'             => (float) ($po->total_biaya ?? 0),
                'syarat_pembayaran'       => $po->syarat_pembayaran,
                'status'                  => $po->status,
                'catatan'                 => $po->catatan,
                'pembuat'                 => $po->pembuat?->nama ?? 'Staf Pengadaan',
                'total_item'              => $po->detail->count(),
                'items'                   => $po->detail->map(fn ($d) => [
                    'id'               => $d->id,
                    'barang_id'        => $d->barang_id,
                    'nama_barang'      => $d->barang?->nama ?? 'Barang',
                    'satuan'           => $d->barang?->satuan?->nama ?? 'Pcs',
                    'jumlah_dipesan'   => (int) $d->jumlah_dipesan,
                    'jumlah_diterima'  => (int) ($d->jumlah_diterima ?? 0),
                    'harga_satuan'     => (float) ($d->harga_satuan ?? 0),
                    'subtotal'         => (float) ($d->subtotal ?? 0),
                ]),
            ];
        });

        return $this->jsonSuccess($pos, 'Daftar pesanan pembelian (PO) berhasil dimuat.');
    }

    /**
     * Detail lengkap satu dokumen PO.
     * GET /v1/pengadaan/po/{id}
     */
    public function detailPo(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $hasPoAccess = $user && (
            $user->hasRole(['pengadaan', 'super_admin', 'admin_logistik', 'manajemen', 'auditor', 'kepala_gudang', 'petugas_gudang']) ||
            $user->can('view_any_pengadaan::pesanan::pembelian') ||
            $user->can('view_any_pengadaan::penerimaan::po') ||
            $user->can('page_PesananPembelianIndex') ||
            $user->can('page_PenerimaanPoIndex') ||
            $user->can('page_PengadaanDashboard') ||
            $user->can('page_ManajemenDashboard') ||
            $user->can('page_AuditorDashboard')
        );

        if (! $hasPoAccess) {
            return $this->jsonError('Akses ditolak: Anda tidak memiliki wewenang melihat dokumen PO ini.', 403);
        }

        $po = PesananPembelian::with([
            'pemasok',
            'permintaanPengadaan.unit',
            'pembuat',
            'detail.barang.satuan',
        ])->find($id);

        if (! $po) {
            return $this->jsonError('Dokumen PO tidak ditemukan.', 404);
        }


        return $this->jsonSuccess([
            'id'                      => $po->id,
            'kode'                    => $po->kode,
            'pemasok'                 => $po->pemasok ? [
                'id'       => $po->pemasok->id,
                'kode'     => $po->pemasok->kode,
                'nama'     => $po->pemasok->nama,
                'kota' => $po->pemasok->kota,
                'telepon'  => $po->pemasok->no_telepon,
                'alamat'   => $po->pemasok->alamat,
            ] : null,
            'pr_kode'                 => $po->permintaanPengadaan?->kode,
            'pr_unit'                 => $po->permintaanPengadaan?->unit?->nama ?? '-',
            'tanggal_po'              => $po->tanggal_po?->format('Y-m-d') ?? '-',
            'estimasi_tanggal_kirim'  => $po->estimasi_tanggal_kirim?->format('Y-m-d') ?? '-',
            'total_biaya'             => (float) ($po->total_biaya ?? 0),
            'syarat_pembayaran'       => $po->syarat_pembayaran,
            'status'                  => $po->status,
            'catatan'                 => $po->catatan,
            'pembuat'                 => $po->pembuat?->nama ?? '-',
            'items'                   => $po->detail->map(fn ($d) => [
                'id'               => $d->id,
                'barang_id'        => $d->barang_id,
                'nama_barang'      => $d->barang?->nama ?? 'Barang',
                'satuan'           => $d->barang?->satuan?->nama ?? 'Pcs',
                'jumlah_dipesan'   => (int) $d->jumlah_dipesan,
                'jumlah_diterima'  => (int) ($d->jumlah_diterima ?? 0),
                'harga_satuan'     => (float) ($d->harga_satuan ?? 0),
                'subtotal'         => (float) ($d->subtotal ?? 0),
            ]),
        ], 'Detail PO berhasil dimuat.');
    }

    /**
     * Penerbitan dokumen PO baru dari PR yang statusnya sudah diserahkan ke pengadaan (DISETUJUI).
     * POST /v1/pengadaan/po
     */
    public function terbitkanPo(Request $request): JsonResponse
    {
        $user = $request->user();

        $canManage = $user && (
            $user->hasRole('pengadaan') ||
            $user->hasRole('admin_logistik') ||
            $user->hasRole('super_admin') ||
            $user->can('create_pengadaan::pesanan::pembelian')
        );

        if (! $canManage) {
            return $this->jsonError('Hanya bagian pengadaan dan admin logistik yang berwenang menerbitkan PO.', 403);
        }

        $validator = Validator::make($request->all(), [
            'permintaan_pengadaan_id' => 'required|exists:permintaan_pengadaan,id',
            'pemasok_id'              => 'required|exists:pemasok,id',
            'estimasi_tanggal_kirim'  => 'nullable|date',
            'syarat_pembayaran'       => 'required|in:TEMPO_30_HARI,TEMPO_60_HARI,TUNAI',
            'catatan'                 => 'nullable|string|max:1000',
            'items'                   => 'required|array|min:1',
            'items.*.barang_id'       => 'required|exists:barang,id',
            'items.*.jumlah'          => 'required|integer|min:1',
            'items.*.harga_satuan'    => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->jsonError('Validasi penerbitan PO gagal.', 422, $validator->errors());
        }

        $pr = PermintaanPengadaan::with('detail')->find($request->input('permintaan_pengadaan_id'));
        if (! $pr) {
            return $this->jsonError('Dokumen PR tidak ditemukan.', 404);
        }

        if (! in_array($pr->status, [PermintaanPengadaan::STATUS_DITERUSKAN_KE_PENGADAAN, PermintaanPengadaan::STATUS_DIPROSES, 'DISETUJUI'])) {
            return $this->jsonError("Hanya PR yang telah diserahkan ke pengadaan yang dapat diterbitkan PO (saat ini: {$pr->status}).", 422);
        }

        $prDetailsByBarang = $pr->detail->keyBy('barang_id');
        foreach ($request->input('items') as $row) {
            $barangId = $row['barang_id'];
            $qty = (int) $row['jumlah'];

            $prDetail = $prDetailsByBarang->get($barangId);
            if (! $prDetail) {
                return $this->jsonError("Barang dengan ID {$barangId} tidak terdaftar pada dokumen PR {$pr->kode}.", 422);
            }

            $maxAllowed = (int) ($prDetail->jumlah_disetujui ?? $prDetail->jumlah);
            if ($qty > $maxAllowed) {
                return $this->jsonError("Jumlah barang ({$qty}) melebihi kuantitas yang disetujui pada PR ({$maxAllowed}).", 422);
            }
        }

        try {
            $po = DB::transaction(function () use ($request, $user, $pr) {
                $totalBiaya = 0;
                foreach ($request->input('items') as $row) {
                    $totalBiaya += ((int) $row['jumlah']) * ((float) $row['harga_satuan']);
                }

                $kodePo = CodeGenerator::generatePo();

                $poHeader = PesananPembelian::create([
                    'kode'                    => $kodePo,
                    'pemasok_id'              => $request->input('pemasok_id'),
                    'permintaan_pengadaan_id' => $pr->id,
                    'tanggal_po'              => now()->toDateString(),
                    'estimasi_tanggal_kirim'  => $request->input('estimasi_tanggal_kirim'),
                    'total_biaya'             => $totalBiaya,
                    'syarat_pembayaran'       => $request->input('syarat_pembayaran'),
                    'status'                  => 'DITERBITKAN',
                    'catatan'                 => $request->input('catatan'),
                    'dibuat_oleh'             => $user->id,
                ]);

                foreach ($request->input('items') as $row) {
                    $subtotal = ((int) $row['jumlah']) * ((float) $row['harga_satuan']);

                    PesananPembelianDetail::create([
                        'pesanan_pembelian_id' => $poHeader->id,
                        'barang_id'            => $row['barang_id'],
                        'jumlah_dipesan'       => $row['jumlah'],
                        'jumlah_diterima'      => 0,
                        'harga_satuan'         => $row['harga_satuan'],
                        'subtotal'             => $subtotal,
                    ]);
                }

                // Update status PR menjadi DIPROSES
                $pr->update(['status' => PermintaanPengadaan::STATUS_DIPROSES]);

                return $poHeader;
            });

            return $this->jsonSuccess([
                'id'          => $po->id,
                'kode'        => $po->kode,
                'status'      => $po->status,
                'total_biaya' => (float) $po->total_biaya,
            ], "Pesanan Pembelian {$po->kode} berhasil diterbitkan.", 201);
        } catch (\Throwable $e) {
            return $this->jsonError('Gagal menerbitkan PO: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update status PO yang sudah diterbitkan menjadi DIKIRIM_VENDOR.
     * POST /v1/pengadaan/po/{id}/kirim
     */
    public function updateStatusKirim(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $canManage = $user && (
            $user->hasRole('pengadaan') ||
            $user->hasRole('admin_logistik') ||
            $user->hasRole('super_admin') ||
            $user->can('update_pengadaan::pesanan::pembelian')
        );

        if (! $canManage) {
            return $this->jsonError('Hanya bagian pengadaan dan admin logistik yang berwenang memperbarui status pengiriman PO.', 403);
        }

        $po = PesananPembelian::with('pemasok')->find($id);
        if (! $po) {
            return $this->jsonError('Dokumen PO tidak ditemukan.', 404);
        }

        if ($po->status !== 'DITERBITKAN') {
            return $this->jsonError("Hanya PO dengan status DITERBITKAN yang dapat diubah menjadi DIKIRIM_VENDOR (saat ini: {$po->status}).", 422);
        }

        $estimasiBaru = $request->input('estimasi_tanggal_kirim', $po->estimasi_tanggal_kirim);

        $po->update([
            'status'                 => 'DIKIRIM_VENDOR',
            'estimasi_tanggal_kirim' => $estimasiBaru,
        ]);

        return $this->jsonSuccess([
            'id'                     => $po->id,
            'kode'                   => $po->kode,
            'status'                 => $po->status,
            'estimasi_tanggal_kirim' => $po->estimasi_tanggal_kirim?->format('Y-m-d'),
        ], "Status PO {$po->kode} berhasil diubah menjadi DIKIRIM_VENDOR (dalam pengiriman rekanan).");
    }

}
