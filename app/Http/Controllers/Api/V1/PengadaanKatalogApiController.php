<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Barang;
use App\Models\StokBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Katalog master barang untuk pengajuan pengadaan/amprah.
 */
class PengadaanKatalogApiController extends ApiController
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
                $stokGudang = (int) StokBatch::where('barang_id', $b->id)
                    ->where('status', 'AKTIF')
                    ->where('jumlah', '>', 0)
                    ->sum('jumlah');

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
                    'stok'           => $stokGudang,
                ];
            });

        return $this->jsonSuccess($barang, 'Katalog barang berhasil diambil.');
    }
}
