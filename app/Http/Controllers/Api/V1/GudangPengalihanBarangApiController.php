<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StatusPengalihan;
use App\Http\Controllers\Api\ApiController;
use App\Models\Barang;
use App\Models\PengalihanBarang;
use App\Models\StokBatch;
use App\Services\Persediaan\PengalihanBarangService;
use App\Support\Auth\PengalihanAccess;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GudangPengalihanBarangApiController extends ApiController
{
    /**
     * Daftar dokumen pengalihan barang antar fasilitas gudang.
     * GET /v1/gudang/pengalihan-barang?tab=aktif|otorisasi|riwayat
     */
    public function daftarPengalihanBarang(Request $request): JsonResponse
    {
        $user = $request->user();
        $isGlobalAdmin = (bool) ($user && ($user->hasRole(['super_admin', 'admin_logistik', 'manajemen'])));
        $tab = $request->query('tab', 'aktif');

        $query = PengalihanBarang::with([
            'gudangAsal', 'unitAsal', 'gudangTujuan', 'unitTujuan',
            'pengaju', 'penyetuju', 'penerima',
            'detail.barang.satuan',
        ])->latest('id');

        if (! $isGlobalAdmin) {
            $userGudangId = $user->gudang_id;
            $userId = $user->id;

            if ($tab === 'otorisasi') {
                $query->where('status', StatusPengalihan::Diajukan->value)
                    ->where('tipe_asal', 'GUDANG')
                    ->where('gudang_asal_id', $userGudangId)
                    ->where(function ($sub) use ($userGudangId, $userId) {
                        $sub->where('gudang_tujuan_id', '!=', $userGudangId)
                            ->orWhere('diajukan_oleh', '!=', $userId);
                    });
            } elseif ($tab === 'aktif') {
                $query->whereIn('status', [StatusPengalihan::Diajukan->value, StatusPengalihan::Disetujui->value])
                    ->where(function ($q) use ($userGudangId, $userId) {
                        $q->where('diajukan_oleh', $userId);
                        if ($userGudangId) {
                            $q->orWhere(function ($sub) use ($userGudangId) {
                                $sub->where('tipe_tujuan', 'GUDANG')
                                    ->where('gudang_tujuan_id', $userGudangId);
                            });
                        }
                    });
            } else {
                $query->whereIn('status', [StatusPengalihan::Selesai->value, StatusPengalihan::Ditolak->value, StatusPengalihan::Dibatalkan->value])
                    ->where(function ($q) use ($userGudangId, $userId) {
                        $q->where('diajukan_oleh', $userId);
                        if ($userGudangId) {
                            $q->orWhere('gudang_asal_id', $userGudangId)
                              ->orWhere('gudang_tujuan_id', $userGudangId);
                        }
                    });
            }
        } else {
            if ($tab === 'aktif') {
                $query->whereIn('status', [StatusPengalihan::Diajukan->value, StatusPengalihan::Disetujui->value]);
            } elseif ($tab === 'otorisasi') {
                $query->where('status', StatusPengalihan::Diajukan->value);
            } elseif ($tab === 'riwayat') {
                $query->whereIn('status', [StatusPengalihan::Selesai->value, StatusPengalihan::Ditolak->value, StatusPengalihan::Dibatalkan->value]);
            }
        }
        $list = $query->get()->map(function ($item) use ($user, $isGlobalAdmin) {
            $namaItem = $item->detail->first()?->barang?->nama ?? 'Komoditas Logistik';

            $canCancel = $item->status === StatusPengalihan::Diajukan->value && (
                $isGlobalAdmin ||
                (int) $item->diajukan_oleh === (int) $user->id
            );

            $canApprove = $item->status === StatusPengalihan::Diajukan->value && (
                $isGlobalAdmin ||
                ($user->gudang_id && (int) $user->gudang_id === (int) $item->gudang_asal_id && $user->hasRole(['kepala_gudang', 'super_admin', 'admin_logistik']))
            );

            $canReceive = $item->status === StatusPengalihan::Disetujui->value && (
                $isGlobalAdmin ||
                ($user->gudang_id && (int) $user->gudang_id === (int) $item->gudang_tujuan_id)
            );
            return [
                'id'                 => $item->id,
                'kode'               => $item->kode,
                'tipe'               => 'BARANG',
                'tipe_asal'          => $item->tipe_asal,
                'asal_nama'          => $item->gudangAsal?->nama ?? $item->unitAsal?->nama ?? '-',
                'gudang_asal_id'     => $item->gudang_asal_id,
                'tipe_tujuan'        => $item->tipe_tujuan,
                'tujuan_nama'        => $item->gudangTujuan?->nama ?? $item->unitTujuan?->nama ?? '-',
                'gudang_tujuan_id'   => $item->gudang_tujuan_id,
                'status'             => $item->status,
                'tanggal_pengalihan' => $item->tanggal_pengalihan?->format('Y-m-d') ?? '',
                'nama_item'          => $namaItem,
                'jumlah'             => (int) $item->detail->sum('jumlah'),
                'alasan'             => $item->alasan,
                'catatan'            => $item->catatan,
                'pengaju'            => $item->pengaju?->nama ?? 'Staf',
                'penyetuju'          => $item->penyetuju?->nama,
                'penerima'           => $item->penerima?->nama,
                'can_cancel'         => $canCancel,
                'can_approve'        => $canApprove,
                'can_receive'        => $canReceive,
            ];
        });

        return $this->jsonSuccess($list, 'Daftar pengalihan barang berhasil dimuat.');
    }

    /**
     * Simpan pengajuan pengalihan barang baru.
     * POST /v1/gudang/pengalihan-barang
     */
    public function simpanPengalihanBarang(Request $request, PengalihanBarangService $service): JsonResponse
    {
        $user = $request->user();

        $canCreate = $user && (
            $user->can('create_gudang_stok::pengalihan::barang') ||
            $user->hasRole(['super_admin', 'admin_logistik'])
        );

        if (! $canCreate) {
            return $this->jsonError('Akses ditolak: Hanya fasilitas gudang logistik yang berwenang mengajukan pengalihan barang.', 403);
        }

        $validator = Validator::make($request->all(), [
            'barang_id'          => 'required|exists:barang,id',
            'gudang_asal_id'     => 'required|exists:gudang,id',
            'gudang_tujuan_id'   => 'nullable|exists:gudang,id',
            'jumlah'             => 'required|integer|min:1',
            'tanggal_pengalihan' => 'required|date',
            'alasan'             => 'required|string|min:5|max:1000',
            'catatan'            => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->jsonError('Validasi data gagal.', 422, $validator->errors());
        }

        $isGlobalAdmin = (bool) ($user && ($user->hasRole(['super_admin', 'admin_logistik'])));
        $gudangTujuanId = ! $isGlobalAdmin ? $user->gudang_id : ($request->input('gudang_tujuan_id') ?? $user->gudang_id);
        $gudangAsalId = (int) $request->input('gudang_asal_id');

        if ($gudangTujuanId && $gudangAsalId === (int) $gudangTujuanId) {
            return $this->jsonError('Gudang asal dan tujuan pengalihan tidak boleh sama.', 422);
        }

        $barangId = (int) $request->input('barang_id');
        $jumlah = (int) $request->input('jumlah');

        // Resolve stok batch jika tidak dikirim dari klien
        $stokBatchId = $request->input('stok_batch_id');
        if (! $stokBatchId) {
            $batch = StokBatch::where('barang_id', $barangId)
                ->where('gudang_id', $gudangAsalId)
                ->where('jumlah', '>=', $jumlah)
                ->where('status', 'AKTIF')
                ->first();

            if (! $batch) {
                return $this->jsonError('Stok barang pada gudang asal tidak mencukupi untuk dialihkan.', 422);
            }
            $stokBatchId = $batch->id;
        }

        try {
            $pengalihan = $service->ajukanPengalihan([
                'tipe_asal'               => 'GUDANG',
                'gudang_asal_id'          => $gudangAsalId,
                'unit_asal_id'            => null,
                'tipe_tujuan'             => 'GUDANG',
                'gudang_tujuan_id'        => $gudangTujuanId,
                'unit_tujuan_id'          => null,
                'lokasi_gudang_tujuan_id' => $request->input('lokasi_gudang_tujuan_id'),
                'tanggal_pengalihan'      => $request->input('tanggal_pengalihan'),
                'alasan'                  => $request->input('alasan'),
                'catatan'                 => $request->input('catatan'),
                'diajukan_oleh'           => $user->id,
                'items'                   => [
                    [
                        'barang_id'     => $barangId,
                        'stok_batch_id' => $stokBatchId,
                        'jumlah'        => $jumlah,
                    ],
                ],
            ]);

            return $this->jsonSuccess([
                'id'     => $pengalihan->id,
                'kode'   => $pengalihan->kode,
                'status' => $pengalihan->status,
            ], "Pengalihan barang {$pengalihan->kode} berhasil diajukan.");
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    /**
     * Cek ketersediaan stok fisik aktif komoditas barang pada gudang tertentu.
     * GET /v1/gudang/stok-tersedia?barang_id={id}&gudang_id={id}
     */
    public function stokTersedia(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'barang_id' => 'required|exists:barang,id',
            'gudang_id' => 'required|exists:gudang,id',
        ]);

        if ($validator->fails()) {
            return $this->jsonError('Parameter tidak valid.', 422, $validator->errors());
        }

        $barangId = (int) $request->query('barang_id');
        $gudangId = (int) $request->query('gudang_id');

        $barang = Barang::with('satuan')->find($barangId);
        $totalStok = (int) StokBatch::where('barang_id', $barangId)
            ->where('gudang_id', $gudangId)
            ->where('status', 'AKTIF')
            ->sum('jumlah');

        return $this->jsonSuccess([
            'barang_id'     => $barangId,
            'gudang_id'     => $gudangId,
            'stok_tersedia' => $totalStok,
            'satuan'        => $barang?->satuan?->nama ?? 'Pcs',
        ], 'Informasi stok berhasil diambil.');
    }

    /**
     * Otorisasi pengalihan barang (APPROVE / REJECT).
     * POST /v1/gudang/pengalihan-barang/{id}/otorisasi
     */
    public function otorisasiPengalihanBarang(Request $request, int $id, PengalihanBarangService $service): JsonResponse
    {
        $user = $request->user();
        $dokumen = PengalihanBarang::find($id);
        if (! $dokumen) {
            return $this->jsonError('Dokumen pengalihan tidak ditemukan.', 404);
        }
        if (! PengalihanAccess::boleh($user, $dokumen, 'otorisasi', PengalihanAccess::ENTITAS_BARANG)) {
            return $this->jsonError('Anda tidak berwenang mengotorisasi pengalihan barang ini.', 403);
        }
        $aksi = strtoupper($request->input('aksi', 'APPROVE'));
        $catatan = $request->input('catatan');

        try {
            if ($aksi === 'REJECT') {
                $pengalihan = $service->tolakPengalihan($id, $user->id, $catatan);
                return $this->jsonSuccess(['id' => $pengalihan->id, 'status' => StatusPengalihan::Ditolak->value], "Pengalihan barang {$pengalihan->kode} berhasil ditolak.");
            } else {
                $pengalihan = $service->setujuiPengalihan($id, $user->id, $catatan);
                return $this->jsonSuccess(['id' => $pengalihan->id, 'status' => StatusPengalihan::Disetujui->value], "Pengalihan barang {$pengalihan->kode} berhasil disetujui.");
            }
        } catch (Exception $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    /**
     * Konfirmasi serah terima fisik barang.
     * POST /v1/gudang/pengalihan-barang/{id}/serah-terima
     */
    public function serahTerimaPengalihanBarang(Request $request, int $id, PengalihanBarangService $service): JsonResponse
    {
        $user = $request->user();
        $dokumen = PengalihanBarang::find($id);
        if (! $dokumen) {
            return $this->jsonError('Dokumen pengalihan tidak ditemukan.', 404);
        }
        if (! PengalihanAccess::boleh($user, $dokumen, 'serah_terima', PengalihanAccess::ENTITAS_BARANG)) {
            return $this->jsonError('Anda tidak berwenang mengonfirmasi serah terima pengalihan barang ini.', 403);
        }

        try {
            $pengalihan = $service->eksekusiSerahTerima($id, $user->id);
            return $this->jsonSuccess(['id' => $pengalihan->id, 'status' => StatusPengalihan::Selesai->value], "Serah terima fisik barang {$pengalihan->kode} berhasil selesai.");
        } catch (Exception $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    /**
     * Batalkan pengalihan barang.
     * POST /v1/gudang/pengalihan-barang/{id}/batal
     */
    public function batalPengalihanBarang(Request $request, int $id, PengalihanBarangService $service): JsonResponse
    {
        $user = $request->user();
        $dokumen = PengalihanBarang::find($id);
        if (! $dokumen) {
            return $this->jsonError('Dokumen pengalihan tidak ditemukan.', 404);
        }
        if (! PengalihanAccess::boleh($user, $dokumen, 'batal', PengalihanAccess::ENTITAS_BARANG)) {
            return $this->jsonError('Anda tidak berwenang membatalkan pengalihan barang ini.', 403);
        }
        $alasan = $request->input('alasan', 'Dibatalkan oleh pemohon');

        try {
            $pengalihan = $service->batalkanPengalihan($id, $user->id, $alasan);
            return $this->jsonSuccess(['id' => $pengalihan->id, 'status' => StatusPengalihan::Dibatalkan->value], "Pengalihan barang {$pengalihan->kode} berhasil dibatalkan.");
        } catch (Exception $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

}
