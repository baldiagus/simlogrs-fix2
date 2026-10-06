<?php

namespace App\Services\Persediaan;

use App\Models\Barang;
use App\Models\PengalihanBarang;
use App\Models\PengalihanBarangDetail;
use App\Models\PergerakanStok;
use App\Models\StokBatch;
use App\Models\StokRuangan;
use App\Support\Generator\CodeGenerator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PengalihanBarangService
{
    /**
     * Mengajukan pengalihan/mutasi barang BHP.
     */
    public function ajukanPengalihan(array $data): PengalihanBarang
    {
        $items = $data['items'] ?? [];
        if (empty($items)) {
            throw new InvalidArgumentException('Minimal harus memilih 1 komoditas barang untuk dialihkan.');
        }

        $tipeAsal = $data['tipe_asal'] ?? 'GUDANG';
        $tipeTujuan = $data['tipe_tujuan'] ?? 'GUDANG';
        $gudangAsalId = $data['gudang_asal_id'] ?? null;
        $unitAsalId = $data['unit_asal_id'] ?? null;
        $gudangTujuanId = $data['gudang_tujuan_id'] ?? null;
        $unitTujuanId = $data['unit_tujuan_id'] ?? null;
        $lokasiGudangTujuanId = $data['lokasi_gudang_tujuan_id'] ?? null;

        // Anti self-transfer guard
        if ($tipeAsal === 'GUDANG' && $tipeTujuan === 'GUDANG' && $gudangAsalId === $gudangTujuanId) {
            throw new InvalidArgumentException('Gudang asal dan tujuan pengalihan tidak boleh sama.');
        }
        if ($tipeAsal === 'RUANGAN' && $tipeTujuan === 'RUANGAN' && $unitAsalId === $unitTujuanId) {
            throw new InvalidArgumentException('Unit asal dan tujuan pengalihan tidak boleh sama.');
        }

        return DB::transaction(function () use (
            $data,
            $items,
            $tipeAsal,
            $tipeTujuan,
            $gudangAsalId,
            $unitAsalId,
            $gudangTujuanId,
            $unitTujuanId,
            $lokasiGudangTujuanId
        ) {
            $kode = CodeGenerator::generate('TRF-BRG', PengalihanBarang::class, 'kode', 'Ym', 3);

            $transfer = PengalihanBarang::create([
                'kode' => $kode,
                'tipe_asal' => $tipeAsal,
                'gudang_asal_id' => $tipeAsal === 'GUDANG' ? $gudangAsalId : null,
                'unit_asal_id' => $tipeAsal === 'RUANGAN' ? $unitAsalId : null,
                'tipe_tujuan' => $tipeTujuan,
                'gudang_tujuan_id' => $tipeTujuan === 'GUDANG' ? $gudangTujuanId : null,
                'unit_tujuan_id' => $tipeTujuan === 'RUANGAN' ? $unitTujuanId : null,
                'lokasi_gudang_tujuan_id' => $tipeTujuan === 'GUDANG' ? $lokasiGudangTujuanId : null,
                'tanggal_pengalihan' => $data['tanggal_pengalihan'] ?? now()->toDateString(),
                'alasan' => $data['alasan'] ?? '-',
                'status' => 'DIAJUKAN',
                'diajukan_oleh' => $data['diajukan_oleh'],
                'catatan' => $data['catatan'] ?? null,
            ]);

            foreach ($items as $item) {
                $barangId = $item['barang_id'];
                $jumlah = (int) ($item['jumlah'] ?? 0);
                $batchId = $item['stok_batch_id'] ?? null;

                if ($jumlah <= 0) {
                    throw new InvalidArgumentException('Jumlah barang yang dialihkan harus lebih dari 0.');
                }

                if ($tipeAsal === 'GUDANG') {
                    $batch = StokBatch::lockForUpdate()->findOrFail($batchId);
                    if ($batch->jumlah < $jumlah) {
                        throw new InvalidArgumentException("Stok batch {$batch->nomor_batch} tidak mencukupi untuk dialihkan.");
                    }
                    // Kunci stok batch dengan memotong langsung
                    $batch->decrement('jumlah', $jumlah);
                } else {
                    $stokRuangan = StokRuangan::lockForUpdate()
                        ->where('unit_id', $unitAsalId)
                        ->where('barang_id', $barangId)
                        ->first();
                    if (! $stokRuangan || $stokRuangan->jumlah < $jumlah) {
                        throw new InvalidArgumentException('Stok persediaan ruangan asal tidak mencukupi.');
                    }
                    $stokRuangan->decrement('jumlah', $jumlah);
                }

                PengalihanBarangDetail::create([
                    'pengalihan_barang_id' => $transfer->id,
                    'barang_id' => $barangId,
                    'stok_batch_id' => $batchId,
                    'jumlah' => $jumlah,
                    'catatan' => $item['catatan'] ?? null,
                ]);
            }

            return $transfer->load(['detail.barang', 'gudangAsal', 'unitAsal', 'gudangTujuan', 'unitTujuan']);
        });
    }

    /**
     * Menyetujui pengalihan barang.
     */
    public function setujuiPengalihan(int $pengalihanId, int $userId, ?string $catatan = null): PengalihanBarang
    {
        return DB::transaction(function () use ($pengalihanId, $userId, $catatan) {
            $transfer = PengalihanBarang::lockForUpdate()->findOrFail($pengalihanId);

            if ($transfer->status !== 'DIAJUKAN') {
                throw new InvalidArgumentException("Dokumen {$transfer->kode} tidak dalam status DIAJUKAN.");
            }

            $transfer->update([
                'status' => 'DISETUJUI',
                'disetujui_oleh' => $userId,
                'catatan' => $catatan ?: $transfer->catatan,
            ]);

            return $transfer;
        });
    }

    /**
     * Menolak pengalihan dan mengembalikan stok ke lokasi asal.
     */
    public function tolakPengalihan(int $pengalihanId, int $userId, ?string $alasan = null): PengalihanBarang
    {
        return DB::transaction(function () use ($pengalihanId, $userId, $alasan) {
            $transfer = PengalihanBarang::with('detail')->lockForUpdate()->findOrFail($pengalihanId);

            if (! in_array($transfer->status, ['DIAJUKAN', 'DISETUJUI'], true)) {
                throw new InvalidArgumentException("Dokumen {$transfer->kode} tidak dapat ditolak.");
            }

            // Kembalikan stok ke lokasi asal
            foreach ($transfer->detail as $item) {
                if ($transfer->tipe_asal === 'GUDANG' && $item->stok_batch_id) {
                    StokBatch::where('id', $item->stok_batch_id)->increment('jumlah', $item->jumlah);
                } elseif ($transfer->tipe_asal === 'RUANGAN') {
                    $stok = StokRuangan::firstOrCreate(
                        ['unit_id' => $transfer->unit_asal_id, 'barang_id' => $item->barang_id],
                        ['jumlah' => 0]
                    );
                    $stok->increment('jumlah', $item->jumlah);
                }
            }

            $transfer->update([
                'status' => 'DITOLAK',
                'disetujui_oleh' => $userId,
                'catatan' => $alasan ?: $transfer->catatan,
            ]);

            return $transfer;
        });
    }

    /**
     * Membatalkan pengalihan dan mengembalikan stok ke lokasi asal.
     */
    public function batalkanPengalihan(int $pengalihanId, int $userId, ?string $alasan = null): PengalihanBarang
    {
        return DB::transaction(function () use ($pengalihanId, $userId, $alasan) {
            $transfer = PengalihanBarang::with('detail')->lockForUpdate()->findOrFail($pengalihanId);

            if ($transfer->status !== 'DIAJUKAN') {
                throw new InvalidArgumentException("Hanya pengalihan berstatus DIAJUKAN yang dapat dibatalkan.");
            }

            // Kembalikan stok
            foreach ($transfer->detail as $item) {
                if ($transfer->tipe_asal === 'GUDANG' && $item->stok_batch_id) {
                    StokBatch::where('id', $item->stok_batch_id)->increment('jumlah', $item->jumlah);
                } elseif ($transfer->tipe_asal === 'RUANGAN') {
                    $stok = StokRuangan::firstOrCreate(
                        ['unit_id' => $transfer->unit_asal_id, 'barang_id' => $item->barang_id],
                        ['jumlah' => 0]
                    );
                    $stok->increment('jumlah', $item->jumlah);
                }
            }

            $transfer->update([
                'status' => 'DIBATALKAN',
                'catatan' => $alasan ?: $transfer->catatan,
            ]);

            return $transfer;
        });
    }

    /**
     * Konfirmasi serah terima fisik pengalihan barang.
     * Mengalirkan stok ke inventaris lokasi tujuan dan mencatat riwayat kartu stok.
     */
    public function eksekusiSerahTerima(int $pengalihanId, int $userId): PengalihanBarang
    {
        return DB::transaction(function () use ($pengalihanId, $userId) {
            $transfer = PengalihanBarang::with(['detail.stokBatch', 'gudangAsal', 'unitAsal', 'gudangTujuan', 'unitTujuan'])
                ->lockForUpdate()
                ->findOrFail($pengalihanId);

            if ($transfer->status !== 'DISETUJUI') {
                throw new InvalidArgumentException("Dokumen {$transfer->kode} belum disetujui atau sudah selesai diproses.");
            }

            foreach ($transfer->detail as $item) {
                $batchAsal = $item->stokBatch;

                // 1. Catat mutasi KELUAR pada kartu pergerakan stok jika asal adalah gudang
                if ($transfer->tipe_asal === 'GUDANG' && $batchAsal) {
                    PergerakanStok::create([
                        'barang_id' => $item->barang_id,
                        'stok_batch_id' => $batchAsal->id,
                        'gudang_id' => $transfer->gudang_asal_id,
                        'lokasi_gudang_id' => $batchAsal->lokasi_gudang_id,
                        'jenis' => 'TRANSFER',
                        'jumlah' => $item->jumlah,
                        'saldo_sebelum' => $batchAsal->jumlah + $item->jumlah,
                        'saldo_sesudah' => $batchAsal->jumlah,
                        'referensi_jenis' => 'PENGALIHAN_BARANG',
                        'referensi_id' => $transfer->id,
                        'keterangan' => "Mutasi pengalihan barang {$transfer->kode} ke " . $transfer->nama_lokasi_tujuan,
                        'dibuat_oleh' => $userId,
                        'dibuat_pada' => now(),
                    ]);
                }

                // 2. Alirkan stok ke tujuan
                if ($transfer->tipe_tujuan === 'GUDANG') {
                    // Masuk ke batch gudang tujuan
                    $batchTujuan = StokBatch::firstOrCreate(
                        [
                            'barang_id' => $item->barang_id,
                            'gudang_id' => $transfer->gudang_tujuan_id,
                            'nomor_batch' => $batchAsal?->nomor_batch ?: 'BATCH-MUTASI-' . date('Ymd'),
                        ],
                        [
                            'lokasi_gudang_id' => $transfer->lokasi_gudang_tujuan_id,
                            'tanggal_kedaluwarsa' => $batchAsal?->tanggal_kedaluwarsa,
                            'jumlah' => 0,
                            'status' => 'AKTIF',
                        ]
                    );

                    $saldoSebelum = $batchTujuan->jumlah;
                    $batchTujuan->increment('jumlah', $item->jumlah);

                    // Catat mutasi MASUK kartu stok di gudang tujuan
                    PergerakanStok::create([
                        'barang_id' => $item->barang_id,
                        'stok_batch_id' => $batchTujuan->id,
                        'gudang_id' => $transfer->gudang_tujuan_id,
                        'lokasi_gudang_id' => $transfer->lokasi_gudang_tujuan_id,
                        'jenis' => 'MASUK',
                        'jumlah' => $item->jumlah,
                        'saldo_sebelum' => $saldoSebelum,
                        'saldo_sesudah' => $saldoSebelum + $item->jumlah,
                        'referensi_jenis' => 'PENGALIHAN_BARANG',
                        'referensi_id' => $transfer->id,
                        'keterangan' => "Penerimaan mutasi barang {$transfer->kode} dari " . $transfer->nama_lokasi_asal,
                        'dibuat_oleh' => $userId,
                        'dibuat_pada' => now(),
                    ]);
                } else {
                    // Masuk ke Persediaan Lokal Ruangan tujuan
                    $stokRuangan = StokRuangan::firstOrCreate(
                        [
                            'unit_id' => $transfer->unit_tujuan_id,
                            'barang_id' => $item->barang_id,
                        ],
                        [
                            'jumlah' => 0,
                        ]
                    );
                    $stokRuangan->increment('jumlah', $item->jumlah);
                }
            }

            $transfer->update([
                'status' => 'SELESAI',
                'diterima_oleh' => $userId,
                'tanggal_serah_terima' => now(),
            ]);

            return $transfer->load(['detail.barang', 'gudangAsal', 'unitAsal', 'gudangTujuan', 'unitTujuan', 'penerima', 'penyetuju']);
        });
    }
}
