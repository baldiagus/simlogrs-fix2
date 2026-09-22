<?php

namespace App\Services\Distribusi;

use App\Models\PengirimanBarang;
use App\Models\PengirimanBarangDetail;
use App\Models\PergerakanStok;
use App\Models\PermintaanDistribusi;
use App\Models\StokBatch;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Exception;
use Illuminate\Support\Facades\DB;

class DistribusiService
{
    /**
     * Eksekusi pengeluaran barang berbasis FEFO (First Expired, First Out)
     * dengan pessimistic locking (lockForUpdate) dalam database transaction.
     *
     * @throws Exception
     */
    public function eksekusiPickingFefo(
        PermintaanDistribusi $permintaan,
        User $actor,
        ?int $gudangId = null
    ): PengirimanBarang {
        $gudangId = $gudangId ?: $permintaan->gudang_id;

        return DB::transaction(function () use ($permintaan, $actor, $gudangId) {
            // Validasi status permintaan
            if (! in_array($permintaan->status, ['DISETUJUI_GUDANG', 'DISETUJUI_KARU'])) {
                throw new Exception("Permintaan {$permintaan->kode} belum disetujui untuk pengeluaran barang.");
            }

            // 1. Buat Header SPPB (Surat Perintah Pengeluaran Barang)
            $kodeSppb = CodeGenerator::generate('SPPB', PengirimanBarang::class, 'kode', 'Ymd', 4);

            $sppb = PengirimanBarang::create([
                'kode'                     => $kodeSppb,
                'permintaan_distribusi_id' => $permintaan->id,
                'gudang_id'                => $gudangId,
                'unit_id'                  => $permintaan->unit_id,
                'tanggal_kirim'            => now()->toDateString(),
                'status'                   => 'DIKIRIM',
                'catatan'                  => $permintaan->catatan,
                'dikirim_oleh'             => $actor->id,
            ]);

            // 2. Iterasi setiap barang yang diminta dan eksekusi algoritma FEFO
            foreach ($permintaan->detail as $detail) {
                $qtyNeeded = (int) ($detail->jumlah_disetujui ?: $detail->jumlah);
                if ($qtyNeeded <= 0) {
                    continue;
                }

                // Ambil stok batch aktif, urutkan berdasarkan expired date terdekat (FEFO)
                // Kunci baris dengan lockForUpdate untuk mencegah race condition / saldo negatif
                $batches = StokBatch::where('barang_id', $detail->barang_id)
                    ->where('gudang_id', $gudangId)
                    ->where('status', 'AKTIF')
                    ->where('jumlah', '>', 0)
                    ->where(function ($q) {
                        $q->whereNull('tanggal_kedaluwarsa')
                            ->orWhere('tanggal_kedaluwarsa', '>=', now()->toDateString());
                    })
                    ->orderByRaw('tanggal_kedaluwarsa ASC NULLS LAST')
                    ->lockForUpdate()
                    ->get();

                $totalAvailable = $batches->sum('jumlah');
                if ($totalAvailable < $qtyNeeded) {
                    $namaBarang = $detail->barang?->nama ?? "ID {$detail->barang_id}";
                    throw new Exception("Saldo stok fisik tidak mencukupi untuk '{$namaBarang}'. Diminta: {$qtyNeeded}, Tersedia di gudang: {$totalAvailable}.");
                }

                $remainingToFulfill = $qtyNeeded;

                foreach ($batches as $batch) {
                    if ($remainingToFulfill <= 0) {
                        break;
                    }

                    $takeFromBatch = min($batch->jumlah, $remainingToFulfill);
                    $saldoSebelum = $batch->jumlah;
                    $saldoSesudah = $saldoSebelum - $takeFromBatch;

                    // Potong saldo batch
                    $batch->decrement('jumlah', $takeFromBatch);
                    if ($saldoSesudah === 0) {
                        $batch->update(['status' => 'HABIS']);
                    }

                    // Catat detail SPPB
                    PengirimanBarangDetail::create([
                        'pengiriman_barang_id' => $sppb->id,
                        'barang_id'            => $detail->barang_id,
                        'stok_batch_id'        => $batch->id,
                        'jumlah_dikirim'       => $takeFromBatch,
                    ]);

                    // Catat mutasi KELUAR pada kartu pergerakan stok
                    PergerakanStok::create([
                        'barang_id'        => $detail->barang_id,
                        'stok_batch_id'    => $batch->id,
                        'gudang_id'        => $gudangId,
                        'lokasi_gudang_id' => $batch->lokasi_gudang_id,
                        'jenis'            => 'KELUAR',
                        'jumlah'           => $takeFromBatch,
                        'saldo_sebelum'    => $saldoSebelum,
                        'saldo_sesudah'    => $saldoSesudah,
                        'referensi_jenis'  => 'PENGIRIMAN_DISTRIBUSI',
                        'referensi_id'     => $sppb->id,
                        'keterangan'       => "Pengeluaran FEFO SPPB {$sppb->kode} untuk {$permintaan->unit?->nama} (Batch {$batch->nomor_batch})",
                        'dibuat_oleh'      => $actor->id,
                    ]);

                    $remainingToFulfill -= $takeFromBatch;
                }
            }

            // 3. Update status permintaan distribusi
            $permintaan->update([
                'status'         => 'SEDANG_DIKIRIM',
                'disetujui_oleh' => $actor->id,
            ]);

            return $sppb;
        });
    }
}
