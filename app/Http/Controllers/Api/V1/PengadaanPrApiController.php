<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StatusPermintaanPengadaan;
use App\Enums\StatusPesananPembelian;
use App\Http\Controllers\Api\ApiController;
use App\Models\Barang;
use App\Models\PermintaanPengadaan;
use App\Models\PermintaanPengadaanDetail;
use App\Support\Generator\CodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Daftar & pengajuan Purchase Request (PR) ruangan.
 */
class PengadaanPrApiController extends ApiController
{
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
            ->with(['unit:id,kode,nama', 'gudang:id,kode,nama', 'pengaju:id,nama', 'detail.barang:id,kode,nama,satuan_id,harga_beli_terakhir,harga_estimasi', 'detail.barang.satuan:id,nama', 'pesananPembelian:id,permintaan_pengadaan_id,kode,status'])
            ->latest('dibuat_pada')
            ->limit(100)
            ->get()
            ->map(function ($item) {
                $status = $item->status;
                if ($status === StatusPermintaanPengadaan::Diproses->value && $item->pesananPembelian->isNotEmpty()) {
                    $allPoReceived = $item->pesananPembelian->every(fn ($po) => $po->status === StatusPesananPembelian::DiterimaLengkap->value);
                    if ($allPoReceived) {
                        // GET idempotent: status disajikan selesai tanpa mutasi DB di dalam permintaan baca.
                        $status = StatusPermintaanPengadaan::Selesai->value;
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
                    'id'                  => $d->id,
                    'barang_id'           => $d->barang_id,
                    'nama_barang'         => $d->barang?->nama ?? 'Barang',
                    'kode_barang'         => $d->barang?->kode,
                    'satuan'              => $d->barang?->satuan?->nama ?? 'Pcs',
                    'jumlah'              => $d->jumlah,
                    'catatan'             => $d->catatan,
                    'harga_beli_terakhir' => (float) ($d->barang?->harga_beli_terakhir ?? 0),
                    'harga_estimasi'      => (float) ($d->barang?->harga_estimasi ?? 0),
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

        if (! $user->can('create_pengadaan::permintaan::pengadaan') && ! $user->hasRole(['super_admin', 'admin_logistik'])) {
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

        $validator = $this->validatorPr($request);

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
            $pr = $this->buatPr($request, $user, $unitId, $gudangId);

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

    private function validatorPr(Request $request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
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
    }

    private function buatPr(Request $request, $user, $unitId, $gudangId): PermintaanPengadaan
    {
        return DB::transaction(function () use ($request, $user, $unitId, $gudangId) {
            $kodePr = CodeGenerator::generatePr();

            $prHeader = PermintaanPengadaan::create([
                'kode'               => $kodePr,
                'unit_id'            => $unitId,
                'gudang_id'          => $gudangId,
                'diajukan_oleh'      => $user->id,
                'tanggal_permintaan' => now()->toDateString(),
                'urgensi'            => $request->input('urgensi'),
                'status'             => StatusPermintaanPengadaan::Diajukan->value,
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
    }
}
