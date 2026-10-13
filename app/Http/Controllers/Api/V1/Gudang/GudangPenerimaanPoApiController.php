<?php

namespace App\Http\Controllers\Api\V1\Gudang;

use App\Enums\Pengadaan\StatusPenerimaanPo;
use App\Enums\Pengadaan\StatusPermintaanPengadaan;
use App\Enums\Pengadaan\StatusPesananPembelian;
use App\Http\Controllers\Api\ApiController;
use App\Models\Master\Barang;
use App\Models\Pengadaan\PenerimaanBarang;
use App\Models\Pengadaan\PenerimaanBarangDetail;
use App\Models\Persediaan\PergerakanStok;
use App\Models\Pengadaan\PesananPembelian;
use App\Models\Persediaan\StokBatch;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class GudangPenerimaanPoApiController extends ApiController
{
    /**
     * Daftar PO siap diterima di gudang (Inbound Pending).
     * GET /v1/gudang/po-inbound
     */
    public function poInbound(Request $request): JsonResponse
    {
        $user = $request->user();

        $canView = $user->can('view_any_gudang_stok::penerimaan::po') ||
            $user->can('view_gudang_stok::penerimaan::po') ||
            $user->hasRole(['super_admin', 'admin_logistik']);

        if (! $canView) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk melihat daftar penerimaan PO.', 403);
        }

        $pos = PesananPembelian::query()
            ->whereIn('status', StatusPesananPembelian::aktif())
            ->with([
                'pemasok:id,kode,nama',
                'detail.barang:id,kode,nama,satuan_id,kategori_id',
                'detail.barang.satuan:id,nama',
            ])
            ->latest('id')
            ->get()
            ->map(function ($po) {
                return [
                    'id'                     => $po->id,
                    'kode_po'                => $po->kode,
                    'pemasok'                => $po->pemasok?->nama ?? '-',
                    'tanggal_po'             => $po->tanggal_po?->format('Y-m-d') ?? '',
                    'estimasi_tanggal_kirim' => $po->estimasi_tanggal_kirim?->format('Y-m-d'),
                    'status'                 => $po->status,
                    'total_item'             => $po->detail->count(),
                    'items'                  => $po->detail->map(function ($d) {
                        $sisa = max(0, (int) $d->jumlah_dipesan - (int) $d->jumlah_diterima);
                        return [
                            'id'                   => $d->id,
                            'barang_id'            => $d->barang_id,
                            'kode_barang'          => $d->barang?->kode ?? '-',
                            'nama_barang'          => $d->barang?->nama ?? 'Barang',
                            'satuan'               => $d->barang?->satuan?->nama ?? 'Pcs',
                            'jumlah_dipesan'       => (int) $d->jumlah_dipesan,
                            'jumlah_sudah_diterima'=> (int) $d->jumlah_diterima,
                            'sisa_kebutuhan'       => $sisa,
                            'harga_satuan'         => (float) $d->harga_satuan,
                        ];
                    }),
                ];
            });

        return $this->jsonSuccess($pos, 'Daftar PO inbound siap terima berhasil dimuat.');
    }

    /**
     * Konfirmasi penerimaan fisik barang dari vendor dan alokasi ke rak gudang.
     * POST /v1/gudang/penerimaan-po
     */
    public function simpanPenerimaanPo(Request $request): JsonResponse
    {
        $user = $request->user();

        $canReceive = $user->can('create_gudang_stok::penerimaan::po') ||
            $user->hasRole(['super_admin', 'admin_logistik']);

        if (! $canReceive) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk melakukan penerimaan PO gudang.', 403);
        }

        $validator = $this->validatorPenerimaanPo($request);

        if ($validator->fails()) {
            return $this->jsonError('Validasi data penerimaan gagal.', 422, $validator->errors());
        }

        $po = PesananPembelian::with('detail')->find($request->input('pesanan_pembelian_id'));
        if (! $po) {
            return $this->jsonError('Data PO tidak ditemukan.', 404);
        }

        if (! in_array($po->status, StatusPesananPembelian::bisaDiterima())) {
            return $this->jsonError("PO {$po->kode} belum dikirim oleh vendor atau tidak dalam status dapat diterima (saat ini: {$po->status}).", 422);
        }
        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('admin_logistik');
        $gudangId = $user->gudang_id;

        if (! $gudangId) {
            if ($isGlobalAdmin) {
                $firstRackId = $request->input('items')[0]['lokasi_gudang_id'] ?? null;
                $gudangId = $request->input('gudang_id')
                    ?? ($firstRackId ? \App\Models\Master\LokasiGudang::where('id', $firstRackId)->value('gudang_id') : null)
                    ?? \App\Models\Master\Gudang::where('status', 'AKTIF')->value('id');
            } else {
                return $this->jsonError('Akun Anda belum memiliki penugasan fasilitas gudang penerima resmi.', 403);
            }
        }

        if (! $gudangId) {
            return $this->jsonError('Fasilitas gudang penerima tidak valid atau belum ditentukan.', 422);
        }

        // M-04: Validasi rak berada di dalam gudang penerima
        $errorRak = $this->validasiRakDalamGudang($request, $gudangId);
        if ($errorRak) {
            return $errorRak;
        }
        $hasReceived = false;
        foreach ($request->input('items') as $row) {
            if ((int) $row['jumlah_diterima'] > 0) {
                $hasReceived = true;
                break;
            }
        }

        if (! $hasReceived) {
            return $this->jsonError('Minimal satu komoditas barang harus memiliki jumlah diterima > 0.', 422);
        }

        try {
            $penerimaan = $this->eksekusiPenerimaanPo($request, $user, $po, $gudangId);

            return $this->jsonSuccess([
                'id'       => $penerimaan->id,
                'kode_rcv' => $penerimaan->kode,
                'status'   => $penerimaan->status,
            ], "Penerimaan fisik barang {$penerimaan->kode} berhasil disimpan ke gudang.", 201);
        } catch (\Throwable $e) {
            return $this->jsonError('Gagal memproses penerimaan barang: ' . $e->getMessage(), 500);
        }
    }

    protected function eksekusiPenerimaanPo(Request $request, User $user, PesananPembelian $po, int $gudangId): PenerimaanBarang
    {
        return DB::transaction(function () use ($request, $user, $po, $gudangId) {
            $kodeRcv = CodeGenerator::generate('RCV', PenerimaanBarang::class, 'kode', 'Ym', 3);

            // 1. Update jumlah diterima di pesanan_pembelian_detail
            $this->tambahJumlahDiterimaPoDetail($request, $po);

            // 2. Evaluasi status PO
            $po->refresh();
            $po->load('detail');
            $allReceived = $po->detail->every(fn ($d) => (int) $d->jumlah_diterima >= (int) $d->jumlah_dipesan);
            $statusRcv = $allReceived ? StatusPenerimaanPo::Lengkap->value : StatusPenerimaanPo::Sebagian->value;
            $statusPo  = match (true) {
                $allReceived => StatusPesananPembelian::DiterimaLengkap->value,
                default => StatusPesananPembelian::DiterimaSebagian->value,
            };

            // 3. Buat dokumen penerimaan barang
            $rcv = PenerimaanBarang::create([
                'kode'                 => $kodeRcv,
                'pesanan_pembelian_id' => $po->id,
                'gudang_id'            => $gudangId,
                'nomor_surat_jalan'    => $request->input('nomor_surat_jalan'),
                'tanggal_terima'       => now()->toDateString(),
                'status'               => $statusRcv,
                'catatan'              => $request->input('catatan'),
                'diterima_oleh'        => $user->id,
            ]);

            // 4. Catat detail & stok batch
            $this->catatDetailDanStokBatch($request, $user, $po, $rcv, $gudangId);

            // 5. Update status PO
            $po->update(['status' => $statusPo]);

            // 6. Jika seluruh barang PO telah diterima lengkap, update status PR induk menjadi SELESAI
            if ($statusPo === StatusPesananPembelian::DiterimaLengkap->value && $po->permintaan_pengadaan_id) {
                $po->permintaanPengadaan()->update(['status' => StatusPermintaanPengadaan::Selesai->value]);
            }
            return $rcv;
        });
    }

    protected function tambahJumlahDiterimaPoDetail(Request $request, PesananPembelian $po): void
    {
        foreach ($request->input('items') as $row) {
            $qty = (int) $row['jumlah_diterima'];
            if ($qty <= 0) continue;

            $poDetail = $po->detail->firstWhere('barang_id', $row['barang_id']);
            if ($poDetail) {
                $poDetail->increment('jumlah_diterima', $qty);

                if ((float) $poDetail->harga_satuan > 0) {
                    Barang::where('id', $row['barang_id'])->update([
                        'harga_beli_terakhir' => $poDetail->harga_satuan,
                    ]);
                }
            }
        }
    }

    protected function catatDetailDanStokBatch(Request $request, User $user, PesananPembelian $po, PenerimaanBarang $rcv, int $gudangId): void
    {
        foreach ($request->input('items') as $row) {
            $qty = (int) $row['jumlah_diterima'];
            $rusak = (int) ($row['jumlah_rusak'] ?? 0);
            $net = max(0, $qty - $rusak);

            if ($qty <= 0) continue;

            $poDetail = $po->detail->firstWhere('barang_id', $row['barang_id']);
            $hargaSatuan = $poDetail ? $poDetail->harga_satuan : 0;

            PenerimaanBarangDetail::create([
                'penerimaan_barang_id' => $rcv->id,
                'barang_id'            => $row['barang_id'],
                'lokasi_gudang_id'     => $row['lokasi_gudang_id'],
                'nomor_batch'          => $row['nomor_batch'],
                'tanggal_kedaluwarsa'  => $row['tanggal_kedaluwarsa'] ?? null,
                'jumlah_diterima'      => $qty,
                'jumlah_rusak'         => $rusak,
                'harga_satuan'         => $hargaSatuan,
            ]);

            if ($net > 0) {
                $batch = StokBatch::create([
                    'barang_id'           => $row['barang_id'],
                    'gudang_id'           => $gudangId,
                    'lokasi_gudang_id'    => $row['lokasi_gudang_id'],
                    'nomor_batch'         => $row['nomor_batch'],
                    'tanggal_kedaluwarsa' => $row['tanggal_kedaluwarsa'] ?? null,
                    'jumlah'              => $net,
                    'harga_satuan'        => $hargaSatuan,
                    'status'              => 'AKTIF',
                ]);

                PergerakanStok::create([
                    'barang_id'        => $row['barang_id'],
                    'stok_batch_id'    => $batch->id,
                    'gudang_id'        => $gudangId,
                    'lokasi_gudang_id' => $row['lokasi_gudang_id'],
                    'jenis'            => 'MASUK',
                    'jumlah'           => $net,
                    'saldo_sebelum'    => 0,
                    'saldo_sesudah'    => $net,
                    'referensi_jenis'  => 'PENERIMAAN_PO',
                    'referensi_id'     => $rcv->id,
                    'keterangan'       => "Penerimaan PO {$po->kode} - Batch {$row['nomor_batch']}",
                    'dibuat_oleh'      => $user->id,
                ]);
            }
        }
    }

    private function validatorPenerimaanPo(Request $request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'pesanan_pembelian_id'              => 'required|exists:pesanan_pembelian,id',
            'nomor_surat_jalan'                 => 'required|string|max:100',
            'catatan'                           => 'nullable|string|max:1000',
            'items'                             => 'required|array|min:1',
            'items.*.barang_id'                 => 'required|exists:barang,id',
            'items.*.lokasi_gudang_id'          => 'required|exists:lokasi_gudang,id',
            'items.*.nomor_batch'               => 'required|string|max:100',
            'items.*.tanggal_kedaluwarsa'       => 'nullable|date',
            'items.*.jumlah_diterima'           => 'required|integer|min:0',
            'items.*.jumlah_rusak'              => 'nullable|integer|min:0',
        ], [
            'nomor_surat_jalan.required'        => 'Nomor surat jalan vendor wajib diisi.',
            'items.required'                    => 'Minimal satu barang harus dicatat penerimaannya.',
            'items.*.lokasi_gudang_id.required' => 'Rak penyimpanan fisik wajib dipilih.',
            'items.*.nomor_batch.required'      => 'Nomor batch barang wajib diisi.',
        ]);
    }

    /**
     * M-04: Validasi bahwa setiap lokasi_gudang_id (rak) benar-benar berada di dalam $gudangId.
     */
    private function validasiRakDalamGudang(Request $request, int $gudangId): ?JsonResponse
    {
        $itemRackIds = collect($request->input('items'))->pluck('lokasi_gudang_id')->filter()->unique()->toArray();
        $invalidRacks = \App\Models\Master\LokasiGudang::whereIn('id', $itemRackIds)
            ->where('gudang_id', '!=', $gudangId)
            ->with('gudang:id,nama')
            ->get();

        if ($invalidRacks->isEmpty()) {
            return null;
        }

        $daftarRakInvalid = $invalidRacks->map(fn ($r) => "{$r->kode} ({$r->nama}) di {$r->gudang?->nama}")->implode(', ');

        return $this->jsonError("Alokasi rak tidak valid: Rak [{$daftarRakInvalid}] bukan bagian dari fasilitas gudang penerima yang ditugaskan.", 422, [
            'lokasi_gudang_id' => ['Rak penyimpanan fisik wajib berada di dalam fasilitas gudang penerima yang sama.'],
        ]);
    }
}
