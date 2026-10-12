<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StatusAset;
use App\Enums\StatusPeminjaman;
use App\Http\Controllers\Api\ApiController;
use App\Models\Aset;
use App\Models\PengalihanAset;
use App\Models\Unit;
use App\Support\Auth\PengalihanAccess;
use App\Services\Aset\PengalihanAsetService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AsetPengalihanApiController extends ApiController
{
    /**
     * Daftar dokumen pengalihan / mutasi aset.
     * GET /v1/aset/pengalihan?tab=aktif|otorisasi|riwayat
     */
    public function daftarPengalihan(Request $request): JsonResponse
    {
        $user = $request->user();
        $isGlobalAdmin = (bool) ($user && $user->hasRole(['super_admin', 'admin_logistik']));
        $tab = $request->query('tab', 'aktif');

        $query = PengalihanAset::with([
            'unitAsal', 'unitTujuan', 'gudangAsal', 'gudangTujuan', 'lokasiGudangTujuan',
            'pengaju', 'penyetuju', 'penerima',
            'detail.aset.barang.satuan',
        ])->latest('id');

        $this->filterDaftarPengalihan($query, $user, $isGlobalAdmin, $tab);

        $list = $query->get()->map(fn ($item) => $this->formatItemPengalihan($item, $user, $isGlobalAdmin));

        return $this->jsonSuccess($list, 'Daftar pengalihan aset berhasil dimuat.');
    }

    protected function filterDaftarPengalihan($query, $user, bool $isGlobalAdmin, string $tab): void
    {
        if (! $isGlobalAdmin) {
            $userUnitId = $user->unit_id;
            $userGudangId = $user->gudang_id;
            $userId = $user->id;

            if ($tab === 'otorisasi') {
                $query->where('status', 'DIAJUKAN')
                    ->where(function ($q) use ($userUnitId, $userGudangId, $userId) {
                        if ($userUnitId) {
                            $q->orWhere(function ($sub) use ($userUnitId, $userId) {
                                $sub->where('tipe_asal', 'RUANGAN')
                                    ->where('unit_asal_id', $userUnitId)
                                    ->where(function ($nest) use ($userUnitId, $userId) {
                                        $nest->where('unit_tujuan_id', '!=', $userUnitId)
                                             ->orWhere('diajukan_oleh', '!=', $userId);
                                    });
                            });
                        }
                        if ($userGudangId) {
                            $q->orWhere(function ($sub) use ($userGudangId, $userId) {
                                $sub->where('tipe_asal', 'GUDANG')
                                    ->where('gudang_asal_id', $userGudangId)
                                    ->where(function ($nest) use ($userGudangId, $userId) {
                                        $nest->where('gudang_tujuan_id', '!=', $userGudangId)
                                             ->orWhere('diajukan_oleh', '!=', $userId);
                                    });
                            });
                        }
                    });
            } elseif ($tab === 'aktif') {
                $query->whereIn('status', ['DIAJUKAN', 'DISETUJUI'])
                    ->where(function ($q) use ($userUnitId, $userGudangId, $userId) {
                        $q->where('diajukan_oleh', $userId);
                        if ($userUnitId) {
                            $q->orWhere(function ($sub) use ($userUnitId) {
                                $sub->where('tipe_tujuan', 'RUANGAN')
                                    ->where('unit_tujuan_id', $userUnitId);
                            });
                        }
                        if ($userGudangId) {
                            $q->orWhere(function ($sub) use ($userGudangId) {
                                $sub->where('tipe_tujuan', 'GUDANG')
                                    ->where('gudang_tujuan_id', $userGudangId);
                            });
                        }
                    });
            } else {
                $query->whereIn('status', ['SELESAI', 'DITOLAK', 'DIBATALKAN'])
                    ->where(function ($q) use ($userUnitId, $userGudangId, $userId) {
                        $q->where('diajukan_oleh', $userId);
                        if ($userUnitId) {
                            $q->orWhere('unit_asal_id', $userUnitId)
                              ->orWhere('unit_tujuan_id', $userUnitId);
                        }
                        if ($userGudangId) {
                            $q->orWhere('gudang_asal_id', $userGudangId)
                              ->orWhere('gudang_tujuan_id', $userGudangId);
                        }
                    });
            }
        } else {
            if ($tab === 'aktif') {
                $query->whereIn('status', ['DIAJUKAN', 'DISETUJUI']);
            } elseif ($tab === 'otorisasi') {
                $query->where('status', 'DIAJUKAN');
            } elseif ($tab === 'riwayat') {
                $query->whereIn('status', ['SELESAI', 'DITOLAK', 'DIBATALKAN']);
            }
        }
    }

    protected function formatItemPengalihan($item, $user, bool $isGlobalAdmin): array
    {
        $namaItem = $item->detail->first()?->aset?->barang?->nama
            ?? $item->detail->first()?->aset?->nama_aset
            ?? 'Aset Inventaris';

        $canCancel = $item->status === 'DIAJUKAN' && (
            $isGlobalAdmin ||
            (int) $item->diajukan_oleh === (int) $user->id ||
            ($user->unit_id && (int) $user->unit_id === (int) $item->unit_asal_id && $user->hasRole('kepala_unit'))
        );

        $canApprove = $item->status === 'DIAJUKAN' && (
            $isGlobalAdmin ||
            ($user->unit_id && (int) $user->unit_id === (int) $item->unit_asal_id && $user->hasRole(['kepala_unit', 'super_admin', 'admin_logistik'])) ||
            ($user->gudang_id && (int) $user->gudang_id === (int) $item->gudang_asal_id && $user->hasRole(['kepala_gudang', 'super_admin', 'admin_logistik']))
        );

        $canReceive = $item->status === 'DISETUJUI' && (
            $isGlobalAdmin ||
            ($user->unit_id && (int) $user->unit_id === (int) $item->unit_tujuan_id) ||
            ($user->gudang_id && (int) $user->gudang_id === (int) $item->gudang_tujuan_id)
        );

        return [
            'id'                 => $item->id,
            'kode'               => $item->kode,
            'tipe'               => 'ASET',
            'tipe_asal'          => $item->tipe_asal,
            'asal_nama'          => $item->nama_lokasi_asal,
            'unit_asal_id'       => $item->unit_asal_id,
            'gudang_asal_id'     => $item->gudang_asal_id,
            'tipe_tujuan'        => $item->tipe_tujuan,
            'tujuan_nama'        => $item->nama_lokasi_tujuan,
            'unit_tujuan_id'     => $item->unit_tujuan_id,
            'gudang_tujuan_id'   => $item->gudang_tujuan_id,
            'status'             => $item->status,
            'tanggal_pengalihan' => $item->tanggal_pengalihan?->format('Y-m-d') ?? '',
            'nama_item'          => $namaItem,
            'jumlah'             => $item->detail->count(),
            'alasan'             => $item->alasan,
            'catatan'            => $item->catatan,
            'pengaju'            => $item->pengaju?->nama ?? 'Staf',
            'penyetuju'          => $item->penyetuju?->nama,
            'penerima'           => $item->penerima?->nama,
            'can_cancel'         => $canCancel,
            'can_approve'        => $canApprove,
            'can_receive'        => $canReceive,
            'items'              => $item->detail->map(fn ($d) => [
                'id'                   => $d->id,
                'aset_id'              => $d->aset_id,
                'nama_barang'          => $d->aset?->barang?->nama ?? 'Aset',
                'kode_aset'            => $d->aset?->kode_aset ?? '-',
                'nomor_seri'           => $d->aset?->nomor_seri ?? '-',
                'kondisi_sebelum'      => $d->kondisi_sebelum ?? 'BAIK',
                'kondisi_saat_diterima'=> $d->kondisi_saat_diterima,
            ]),
        ];
    }

    /**
     * Daftar komoditas dan fasilitas penyedia aset yang siap dialihkan (inbound).
     * GET /v1/aset/pengalihan/komoditas-tersedia
     */
    public function komoditasAsetTersedia(Request $request): JsonResponse
    {
        $user = $request->user();
        $isGlobalAdmin = (bool) ($user && $user->hasRole(['super_admin', 'admin_logistik']));
        $ownUnitId = ! $isGlobalAdmin ? $user?->unit_id : null;
        $ownGudangId = ! $isGlobalAdmin ? $user?->gudang_id : null;

        $assets = Aset::with(['barang.kategori', 'barang.satuan', 'unit', 'gudang'])
            ->whereIn('status', StatusAset::dapatDialihkan())
            ->whereNotIn('kondisi', ['RUSAK_BERAT', 'DIHAPUSKAN'])
            ->whereDoesntHave('permintaanMaintenance', function ($sub) {
                $sub->whereNotIn('status', ['SELESAI', 'DITOLAK', 'DIBATALKAN']);
            })
            ->whereDoesntHave('peminjamanDetail.permintaanPeminjaman', function ($sub) {
                $sub->whereIn('status', StatusPeminjaman::aktif());
            })
            ->when($ownUnitId, fn ($sub) => $sub->where(fn ($sq) => $sq->where('unit_id', '!=', $ownUnitId)->orWhereNull('unit_id')))
            ->when($ownGudangId, fn ($sub) => $sub->where(fn ($sq) => $sq->where('gudang_id', '!=', $ownGudangId)->orWhereNull('gudang_id')))
            ->orderBy('kode_aset')
            ->get();

        $grouped = $assets->groupBy('barang_id')->map(function ($items, $barangId) {
            $first = $items->first();
            $barang = $first->barang;

            $penyediaList = [];
            foreach ($items->groupBy(fn ($a) => $a->unit_id ? "RUANGAN_{$a->unit_id}" : "GUDANG_{$a->gudang_id}") as $key => $subGroup) {
                $firstSub = $subGroup->first();
                $isRuangan = $firstSub->unit_id !== null;
                $penyediaList[] = [
                    'key'        => $key,
                    'tipe'       => $isRuangan ? 'RUANGAN' : 'GUDANG',
                    'id'         => $isRuangan ? $firstSub->unit_id : $firstSub->gudang_id,
                    'nama'       => $isRuangan ? ($firstSub->unit?->nama ?? 'Ruangan') : ($firstSub->gudang?->nama ?? 'Gudang'),
                    'kode'       => $isRuangan ? ($firstSub->unit?->kode ?? '-') : ($firstSub->gudang?->kode ?? '-'),
                    'jumlah'     => $subGroup->count(),
                    'aset_ids'   => $subGroup->pluck('id')->values()->toArray(),
                    'kode_asets' => $subGroup->pluck('kode_aset')->implode(', '),
                ];
            }

            return [
                'barang_id'      => (int) $barangId,
                'nama_komoditas' => $barang?->nama ?? 'Aset Fisik',
                'kategori'       => $barang?->kategori?->nama ?? 'Umum',
                'satuan'         => $barang?->satuan?->nama ?? 'Unit',
                'total_tersedia' => $items->count(),
                'penyedia'       => $penyediaList,
            ];
        })->values();

        return $this->jsonSuccess($grouped, 'Daftar komoditas aset tersedia untuk pengalihan berhasil diambil.');
    }

    /**
     * Daftar inventaris aset fisik milik unit atau gudang pengguna login yang siap dialihkan.
     * GET /v1/aset/pengalihan/inventaris-asal
     */
    public function inventarisAsal(Request $request): JsonResponse
    {
        $user = $request->user();
        $isGlobalAdmin = (bool) ($user && $user->hasRole(['super_admin', 'admin_logistik']));

        $query = Aset::with(['barang.kategori', 'barang.satuan', 'barang.merek', 'unit', 'gudang'])
            ->whereIn('status', StatusAset::dapatDialihkan())
            ->whereNotIn('kondisi', ['RUSAK_BERAT', 'DIHAPUSKAN'])
            ->whereDoesntHave('permintaanMaintenance', function ($sub) {
                $sub->whereNotIn('status', ['SELESAI', 'DITOLAK', 'DIBATALKAN']);
            })
            ->whereDoesntHave('peminjamanDetail.permintaanPeminjaman', function ($sub) {
                $sub->whereIn('status', StatusPeminjaman::aktif());
            });

        if (! $isGlobalAdmin) {
            $query->where(function ($q) use ($user) {
                if ($user->unit_id) {
                    $q->where('unit_id', $user->unit_id);
                } elseif ($user->gudang_id) {
                    $q->where('gudang_id', $user->gudang_id);
                }
            });
        }

        $items = $query->orderBy('kode_aset')->get()->map(function ($a) {
            $isRuangan = $a->unit_id !== null;
            return [
                'id'             => $a->id,
                'kode_aset'      => $a->kode_aset,
                'nomor_seri'     => $a->nomor_seri ?? '-',
                'nama_komoditas' => $a->barang?->nama ?? 'Aset Fisik',
                'spesifikasi'    => $a->barang?->spesifikasi ?? '-',
                'kategori'       => $a->barang?->kategori?->nama ?? 'Umum',
                'satuan'         => $a->barang?->satuan?->nama ?? 'Unit',
                'merek'          => $a->barang?->merek?->nama ?? '-',
                'kondisi'        => $a->kondisi,
                'tipe_penyedia'  => $isRuangan ? 'RUANGAN' : 'GUDANG',
                'penyedia_id'    => $isRuangan ? $a->unit_id : $a->gudang_id,
                'nama_penyedia'  => $isRuangan ? ($a->unit?->nama ?? 'Ruangan') : ($a->gudang?->nama ?? 'Gudang'),
                'lokasi_lengkap' => $a->lokasiTercatat ?? ($isRuangan ? $a->unit?->nama : $a->gudang?->nama),
            ];
        });

        return $this->jsonSuccess($items, 'Daftar inventaris aset fisik asal berhasil dimuat.');
    }

    /**
     * Simpan pengajuan pengalihan aset baru.
     * POST /v1/aset/pengalihan
     */
    public function simpanPengalihan(Request $request, PengalihanAsetService $service): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'tipe_tujuan'        => 'required|in:RUANGAN,GUDANG',
            'unit_tujuan_id'     => 'required_if:tipe_tujuan,RUANGAN|nullable|exists:unit,id',
            'gudang_tujuan_id'   => 'required_if:tipe_tujuan,GUDANG|nullable|exists:gudang,id',
            'aset_ids'           => 'required|array|min:1',
            'aset_ids.*'         => 'exists:aset,id',
            'tanggal_pengalihan' => 'required|date',
            'alasan'             => 'required|string|min:5|max:1000',
            'catatan'            => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->jsonError('Validasi data gagal.', 422, $validator->errors());
        }

        $isGlobalAdmin = (bool) ($user && ($user->hasRole(['super_admin', 'admin_logistik'])));

        // Di sistem SIMLOGRS, pengalihan aset adalah INBOUND (meminta aset dari fasilitas lain masuk ke fasilitas pemohon)
        if (! $isGlobalAdmin) {
            $tipeTujuan = $user->unit_id ? 'RUANGAN' : ($user->gudang_id ? 'GUDANG' : 'RUANGAN');
            $unitTujuanId = $user->unit_id;
            $gudangTujuanId = $user->gudang_id;
        } else {
            $tipeTujuan = $request->input('tipe_tujuan', 'RUANGAN');
            $unitTujuanId = $request->input('unit_tujuan_id');
            $gudangTujuanId = $request->input('gudang_tujuan_id');
        }

        // Resolusi lokasi asal dari aset fisik yang dipilih
        $firstAsetId = $request->input('aset_ids')[0] ?? null;
        $firstAset = $firstAsetId ? Aset::find($firstAsetId) : null;

        $tipeAsal = $request->input('tipe_asal') ?? ($firstAset?->unit_id ? 'RUANGAN' : ($firstAset?->gudang_id ? 'GUDANG' : 'RUANGAN'));
        $unitAsalId = $request->input('unit_asal_id') ?? $firstAset?->unit_id;
        $gudangAsalId = $request->input('gudang_asal_id') ?? $firstAset?->gudang_id;

        // Anti self-transfer guard
        if ($tipeAsal === 'RUANGAN' && $tipeTujuan === 'RUANGAN' && (int) $unitAsalId === (int) $unitTujuanId) {
            return $this->jsonError('Unit asal dan unit tujuan pengalihan tidak boleh sama. Aset sudah berada di unit Anda.', 422);
        }
        if ($tipeAsal === 'GUDANG' && $tipeTujuan === 'GUDANG' && (int) $gudangAsalId === (int) $gudangTujuanId) {
            return $this->jsonError('Gudang asal dan gudang tujuan pengalihan tidak boleh sama.', 422);
        }

        try {
            $pengalihan = $service->ajukanPengalihan([
                'aset_ids'                => $request->input('aset_ids'),
                'tipe_asal'               => $tipeAsal,
                'unit_asal_id'            => $unitAsalId,
                'gudang_asal_id'          => $gudangAsalId,
                'tipe_tujuan'             => $tipeTujuan,
                'unit_tujuan_id'          => $unitTujuanId,
                'gudang_tujuan_id'        => $gudangTujuanId,
                'lokasi_gudang_tujuan_id' => $request->input('lokasi_gudang_tujuan_id'),
                'tanggal_pengalihan'      => $request->input('tanggal_pengalihan'),
                'alasan'                  => $request->input('alasan'),
                'catatan'                 => $request->input('catatan'),
                'diajukan_oleh'           => $user->id,
            ]);

            return $this->jsonSuccess([
                'id'     => $pengalihan->id,
                'kode'   => $pengalihan->kode,
                'status' => $pengalihan->status,
            ], "Pengalihan aset {$pengalihan->kode} berhasil diajukan.");
        } catch (Exception $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    /**
     * Otorisasi pengalihan aset (APPROVE / REJECT).
     * POST /v1/aset/pengalihan/{id}/otorisasi
     */
    public function otorisasiPengalihan(Request $request, int $id, PengalihanAsetService $service): JsonResponse
    {
        $user = $request->user();
        $dokumen = PengalihanAset::find($id);
        if (! $dokumen) {
            return $this->jsonError('Dokumen pengalihan tidak ditemukan.', 404);
        }
        if (! PengalihanAccess::boleh($user, $dokumen, 'otorisasi', PengalihanAccess::ENTITAS_ASET)) {
            return $this->jsonError('Anda tidak berwenang mengotorisasi pengalihan aset ini.', 403);
        }
        $aksi = strtoupper($request->input('aksi', 'APPROVE'));
        $catatan = $request->input('catatan');

        try {
            if ($aksi === 'REJECT') {
                $pengalihan = $service->tolakPengalihan($id, $user->id, $catatan);
                return $this->jsonSuccess(['id' => $pengalihan->id, 'status' => 'DITOLAK'], "Pengalihan aset {$pengalihan->kode} berhasil ditolak.");
            } else {
                $pengalihan = $service->setujuiPengalihan($id, $user->id, $catatan);
                return $this->jsonSuccess(['id' => $pengalihan->id, 'status' => 'DISETUJUI'], "Pengalihan aset {$pengalihan->kode} berhasil disetujui.");
            }
        } catch (Exception $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    /**
     * Konfirmasi BAST serah terima fisik aset.
     * POST /v1/aset/pengalihan/{id}/serah-terima
     */
    public function serahTerimaPengalihan(Request $request, int $id, PengalihanAsetService $service): JsonResponse
    {
        $user = $request->user();
        $dokumen = PengalihanAset::find($id);
        if (! $dokumen) {
            return $this->jsonError('Dokumen pengalihan tidak ditemukan.', 404);
        }
        if (! PengalihanAccess::boleh($user, $dokumen, 'serah_terima', PengalihanAccess::ENTITAS_ASET)) {
            return $this->jsonError('Anda tidak berwenang mengonfirmasi serah terima pengalihan aset ini.', 403);
        }

        try {
            $pengalihan = $service->eksekusiSerahTerima($id, $user->id);
            return $this->jsonSuccess(['id' => $pengalihan->id, 'status' => 'SELESAI'], "Serah terima fisik aset {$pengalihan->kode} berhasil. Status: SELESAI.");
        } catch (Exception $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    /**
     * Batalkan pengalihan aset.
     * POST /v1/aset/pengalihan/{id}/batal
     */
    public function batalPengalihan(Request $request, int $id, PengalihanAsetService $service): JsonResponse
    {
        $user = $request->user();
        $dokumen = PengalihanAset::find($id);
        if (! $dokumen) {
            return $this->jsonError('Dokumen pengalihan tidak ditemukan.', 404);
        }
        if (! PengalihanAccess::boleh($user, $dokumen, 'batal', PengalihanAccess::ENTITAS_ASET)) {
            return $this->jsonError('Anda tidak berwenang membatalkan pengalihan aset ini.', 403);
        }
        $alasan = $request->input('alasan', 'Dibatalkan oleh pemohon');

        try {
            $pengalihan = $service->batalkanPengalihan($id, $user->id, $alasan);
            return $this->jsonSuccess(['id' => $pengalihan->id, 'status' => 'DIBATALKAN'], "Pengalihan aset {$pengalihan->kode} berhasil dibatalkan.");
        } catch (Exception $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

}
