<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Barang;
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

        $barang = Barang::query()
            ->with(['kategori:id,nama', 'satuan:id,nama', 'merek:id,nama'])
            ->when($cari, function ($q) use ($cari) {
                $q->where('nama', 'like', "%{$cari}%")
                  ->orWhere('kode', 'like', "%{$cari}%")
                  ->orWhere('spesifikasi', 'like', "%{$cari}%");
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
                    'spesifikasi'    => $b->spesifikasi,
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

        $pr = PermintaanPengadaan::query()
            ->when($user->unit_id && ! $user->can('page_AdminLogistikDashboard'), function ($q) use ($user) {
                $q->where('unit_id', $user->unit_id);
            })
            ->with(['unit:id,kode,nama', 'pengaju:id,nama', 'detail.barang:id,kode,nama,satuan_id', 'detail.barang.satuan:id,nama'])
            ->latest('dibuat_pada')
            ->limit(100)
            ->get()
            ->map(fn ($item) => [
                'id'                 => $item->id,
                'kode'               => $item->kode,
                'unit'               => $item->unit?->nama ?? 'Unit',
                'pengaju'            => $item->pengaju?->nama ?? 'Staf',
                'tanggal_permintaan' => $item->tanggal_permintaan?->format('Y-m-d') ?? '',
                'urgensi'            => $item->urgensi,
                'status'             => $item->status,
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
            ]);

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

        $validator = Validator::make($request->all(), [
            'unit_id'          => 'nullable|exists:unit,id',
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

        $unitId = $request->input('unit_id') ?? $user->unit_id;
        if (! $unitId) {
            return $this->jsonError('Unit kerja pemohon wajib terdaftar.', 422);
        }

        try {
            $pr = DB::transaction(function () use ($request, $user, $unitId) {
                $kodePr = CodeGenerator::generatePr();

                $prHeader = PermintaanPengadaan::create([
                    'kode'               => $kodePr,
                    'unit_id'            => $unitId,
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

                return $prHeader->load(['unit', 'pengaju', 'detail.barang']);
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
}
