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
            // Kunci baris permintaan untuk mencegah eksekusi ganda / race condition
            $lockedPermintaan = PermintaanDistribusi::lockForUpdate()->with(['detail', 'unit'])->findOrFail($permintaan->id);

            // Validasi status permintaan dari database terkunci
            if (! in_array($lockedPermintaan->status, ['DISETUJUI_GUDANG', 'DIKIRIM_SEBAGIAN', 'DISETUJUI_KARU'])) {
                throw new Exception("Permintaan {$lockedPermintaan->kode} sudah diproses atau belum disetujui untuk pengeluaran barang.");
            }

            // Hitung total kuantitas yang sudah dikirim sebelumnya pada SPPB aktif
            $existingShipments = PengirimanBarang::where('permintaan_distribusi_id', $lockedPermintaan->id)
                ->where('status', '!=', 'BATAL')
                ->pluck('id');

            $alreadySentMap = PengirimanBarangDetail::whereIn('pengiriman_barang_id', $existingShipments)
                ->groupBy('barang_id')
                ->selectRaw('barang_id, sum(jumlah_dikirim) as total_sent')
                ->pluck('total_sent', 'barang_id')
                ->toArray();

            // 1. Cek apakah masih ada sisa barang yang belum terpenuhi (terhutang)
            $totalOwed = 0;
            foreach ($lockedPermintaan->detail as $detail) {
                $approvedQty = $detail->jumlah_disetujui !== null ? (int) $detail->jumlah_disetujui : (int) $detail->jumlah;
                $alreadySent = (int) ($alreadySentMap[$detail->barang_id] ?? 0);
                $owed = max(0, $approvedQty - $alreadySent);
                $totalOwed += $owed;
            }

            if ($totalOwed <= 0) {
                throw new Exception("Seluruh komoditas pada permintaan {$lockedPermintaan->kode} sudah terpenuhi 100%.");
            }

            // 2. Siapkan alokasi batch FEFO untuk SPPB baru
            $itemsToDispatch = [];

            foreach ($lockedPermintaan->detail as $detail) {
                $approvedQty = $detail->jumlah_disetujui !== null ? (int) $detail->jumlah_disetujui : (int) $detail->jumlah;
                $alreadySent = (int) ($alreadySentMap[$detail->barang_id] ?? 0);
                $owed = max(0, $approvedQty - $alreadySent);

                if ($owed <= 0) {
                    continue;
                }

                // Ambil stok batch aktif, urutkan berdasarkan expired date terdekat (FEFO)
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

                $remainingToFulfill = $owed;

                foreach ($batches as $batch) {
                    if ($remainingToFulfill <= 0) {
                        break;
                    }

                    $takeFromBatch = min($batch->jumlah, $remainingToFulfill);
                    if ($takeFromBatch <= 0) {
                        continue;
                    }

                    $itemsToDispatch[] = [
                        'detail'        => $detail,
                        'batch'         => $batch,
                        'takeFromBatch' => $takeFromBatch,
                    ];

                    $remainingToFulfill -= $takeFromBatch;
                }
            }

            if (empty($itemsToDispatch)) {
                throw new Exception("Tidak ada saldo stok fisik tersedia di gudang saat ini. Sisa pesanan tetap terhutang dan akan dikirim saat restok.");
            }

            // 3. Buat Header SPPB (Surat Perintah Pengeluaran Barang)
            $kodeSppb = CodeGenerator::generate('SPPB', PengirimanBarang::class, 'kode', 'Ymd', 4);

            $sppb = PengirimanBarang::create([
                'kode'                     => $kodeSppb,
                'permintaan_distribusi_id' => $lockedPermintaan->id,
                'gudang_id'                => $gudangId,
                'unit_id'                  => $lockedPermintaan->unit_id,
                'tanggal_kirim'            => now()->toDateString(),
                'status'                   => 'DIKIRIM',
                'catatan'                  => $lockedPermintaan->catatan,
                'dikirim_oleh'             => $actor->id,
            ]);

            // 4. Potong saldo stok batch dan catat mutasi keluar
            foreach ($itemsToDispatch as $dispatch) {
                $batch = $dispatch['batch'];
                $takeFromBatch = $dispatch['takeFromBatch'];
                $detail = $dispatch['detail'];

                $saldoSebelum = $batch->jumlah;
                $saldoSesudah = $saldoSebelum - $takeFromBatch;

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
                    'keterangan'       => "Pengeluaran FEFO SPPB {$sppb->kode} untuk {$lockedPermintaan->unit?->nama} (Batch {$batch->nomor_batch})",
                    'dibuat_oleh'      => $actor->id,
                ]);
            }

            // 5. Update status permintaan distribusi (SEDANG_DIKIRIM jika lunas, DIKIRIM_SEBAGIAN jika masih ada hutang)
            $allShipments = PengirimanBarang::where('permintaan_distribusi_id', $lockedPermintaan->id)
                ->where('status', '!=', 'BATAL')
                ->pluck('id');

            $totalSentAfterThis = PengirimanBarangDetail::whereIn('pengiriman_barang_id', $allShipments)
                ->sum('jumlah_dikirim');

            $totalRequested = $lockedPermintaan->detail->sum(fn ($d) => $d->jumlah_disetujui !== null ? $d->jumlah_disetujui : $d->jumlah);

            $newStatus = ($totalSentAfterThis >= $totalRequested) ? 'SEDANG_DIKIRIM' : 'DIKIRIM_SEBAGIAN';

            $lockedPermintaan->update([
                'status'         => $newStatus,
                'disetujui_oleh' => $actor->id,
            ]);
            $permintaan->status = $newStatus;

            return $sppb;
        });
    }
}
