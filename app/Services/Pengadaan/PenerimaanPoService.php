<?php

namespace App\Services\Pengadaan;

use App\Enums\StatusPenerimaanPo;
use App\Enums\StatusPermintaanPengadaan;
use App\Enums\StatusPesananPembelian;
use App\Models\Barang;
use App\Models\PenerimaanBarang;
use App\Models\PenerimaanBarangDetail;
use App\Models\PergerakanStok;
use App\Models\PesananPembelian;
use App\Models\StokBatch;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Support\Facades\DB;

class PenerimaanPoService
{
    /**
     * Eksekusi penerimaan barang PO secara atomik.
     * Mengembalikan true bila berhasil, string pesan kesalahan bila validasi gagal, atau false bila status PO tidak valid.
     */
    public function prosesPenerimaan(
        int $poId,
        int $gudangId,
        string $nomorSuratJalan,
        string $kodePenerimaan,
        ?string $catatan,
        array $itemRows,
        User $actor
    ): bool|string {
        return DB::transaction(function () use ($poId, $gudangId, $nomorSuratJalan, $kodePenerimaan, $catatan, $itemRows, $actor) {
            $po = PesananPembelian::with('detail')->lockForUpdate()->findOrFail($poId);

            if (! in_array($po->status, StatusPesananPembelian::bisaDiterima(), true)) {
                return false;
            }

            $rows = $this->validasiBarisPenerimaan($po, $itemRows);
            if (is_string($rows)) {
                return $rows;
            }

            $this->tambahJumlahDiterimaPo($po, $rows);

            $po->refresh();
            $po->load('detail');
            $allReceived = $po->detail->every(fn ($d) => (int) $d->jumlah_diterima >= (int) $d->jumlah_dipesan);
            $totalPernahDiterima = (int) $po->detail->sum('jumlah_diterima');
            $statusRcv = $allReceived ? StatusPenerimaanPo::Lengkap->value : StatusPenerimaanPo::Sebagian->value;
            $statusPo  = match (true) {
                $allReceived => StatusPesananPembelian::DiterimaLengkap->value,
                $totalPernahDiterima > 0 => StatusPesananPembelian::DiterimaSebagian->value,
                default => $po->status,
            };

            $penerimaan = PenerimaanBarang::create([
                'kode'                  => $kodePenerimaan,
                'pesanan_pembelian_id'  => $poId,
                'gudang_id'             => $gudangId,
                'nomor_surat_jalan'     => $nomorSuratJalan,
                'tanggal_terima'        => now()->toDateString(),
                'status'                => $statusRcv,
                'catatan'               => $catatan ?: null,
                'diterima_oleh'         => $actor->id,
            ]);

            $this->simpanDetailDanStokMasuk($rows, $penerimaan, $po, $gudangId, $actor);

            $po->update(['status' => $statusPo]);

            if ($statusPo === StatusPesananPembelian::DiterimaLengkap->value && $po->permintaan_pengadaan_id) {
                $po->permintaanPengadaan()->update(['status' => StatusPermintaanPengadaan::Selesai->value]);
            }

            return true;
        });
    }

    /**
     * Validasi baris penerimaan terhadap rincian PO terkunci.
     * Mengembalikan baris ternormalisasi, atau string pesan kesalahan.
     */
    private function validasiBarisPenerimaan(PesananPembelian $po, array $itemRows): array|string
    {
        $rows = [];
        $terimaPerDetail = [];
        foreach ($itemRows as $row) {
            $terima = (int) ($row['jumlah_diterima'] ?? 0);
            if ($terima <= 0) {
                continue;
            }

            $poDetail = $po->detail->firstWhere('id', $row['po_detail_id'] ?? null);
            if (! $poDetail) {
                return 'Baris penerimaan tidak sesuai dengan rincian PO. Muat ulang data PO.';
            }

            $rusak = (int) ($row['jumlah_rusak'] ?? 0);
            $terimaPerDetail[$poDetail->id] = ($terimaPerDetail[$poDetail->id] ?? 0) + $terima;
            $sisaTerkunci = max(0, (int) $poDetail->jumlah_dipesan - (int) $poDetail->jumlah_diterima);
            if ($rusak < 0 || $rusak > $terima || $terimaPerDetail[$poDetail->id] > $sisaTerkunci) {
                return "Jumlah penerimaan {$poDetail->barang?->nama} melebihi sisa pesanan terkini ({$sisaTerkunci}). Muat ulang data PO.";
            }

            $rows[] = array_merge($row, [
                'barang_id'       => $poDetail->barang_id,
                'harga_satuan'    => (float) $poDetail->harga_satuan,
                'jumlah_diterima' => $terima,
                'jumlah_rusak'    => $rusak,
            ]);
        }

        return $rows;
    }

    /**
     * Tambah jumlah_diterima pada rincian PO dan perbarui harga beli terakhir barang.
     */
    private function tambahJumlahDiterimaPo(PesananPembelian $po, array $rows): void
    {
        foreach ($rows as $row) {
            if ($row['jumlah_diterima'] <= 0) {
                continue;
            }

            $netReceived = max(0, (int) ($row['jumlah_diterima'] ?? 0) - (int) ($row['jumlah_rusak'] ?? 0));
            $poDetail = $po->detail->firstWhere('id', $row['po_detail_id']);
            if ($poDetail && $netReceived > 0) {
                $poDetail->increment('jumlah_diterima', $netReceived);
            }
            $verifiedPrice = (float) $row['harga_satuan'];

            if ($verifiedPrice > 0) {
                Barang::where('id', $row['barang_id'])->update([
                    'harga_beli_terakhir' => $verifiedPrice,
                ]);
            }
        }
    }

    /**
     * Catat detail penerimaan, buat stok batch, dan kartu pergerakan stok MASUK.
     */
    private function simpanDetailDanStokMasuk(
        array $rows,
        PenerimaanBarang $penerimaan,
        PesananPembelian $po,
        int $gudangId,
        User $actor
    ): void {
        foreach ($rows as $row) {
            if ($row['jumlah_diterima'] <= 0) {
                continue;
            }

            $netReceived = $row['jumlah_diterima'] - $row['jumlah_rusak'];
            $verifiedPrice = (float) $row['harga_satuan'];
            $rackId = ! empty($row['lokasi_gudang_id']) ? $row['lokasi_gudang_id'] : null;

            PenerimaanBarangDetail::create([
                'penerimaan_barang_id' => $penerimaan->id,
                'barang_id'            => $row['barang_id'],
                'lokasi_gudang_id'     => $rackId,
                'nomor_batch'          => $row['nomor_batch'],
                'tanggal_kedaluwarsa'  => $row['tanggal_kedaluwarsa'] ?: null,
                'jumlah_diterima'      => $row['jumlah_diterima'],
                'jumlah_rusak'         => $row['jumlah_rusak'],
                'harga_satuan'         => $verifiedPrice,
            ]);

            if ($netReceived > 0) {
                $batch = StokBatch::create([
                    'barang_id'           => $row['barang_id'],
                    'gudang_id'           => $gudangId,
                    'lokasi_gudang_id'    => $rackId,
                    'nomor_batch'         => $row['nomor_batch'],
                    'tanggal_kedaluwarsa' => $row['tanggal_kedaluwarsa'] ?: null,
                    'jumlah'              => $netReceived,
                    'harga_satuan'        => $verifiedPrice,
                    'status'              => 'AKTIF',
                ]);

                PergerakanStok::create([
                    'barang_id'        => $row['barang_id'],
                    'stok_batch_id'    => $batch->id,
                    'gudang_id'        => $gudangId,
                    'lokasi_gudang_id' => $rackId,
                    'jenis'            => 'MASUK',
                    'jumlah'           => $netReceived,
                    'saldo_sebelum'    => 0,
                    'saldo_sesudah'    => $netReceived,
                    'referensi_jenis'  => 'PENERIMAAN_PO',
                    'referensi_id'     => $penerimaan->id,
                    'keterangan'       => "Penerimaan PO {$po->kode} - Batch {$row['nomor_batch']}",
                    'dibuat_oleh'      => $actor->id,
                ]);
            }
        }
    }

    /**
     * Eksekusi tolak kiriman vendor secara atomik.
     */
    public function tolakKiriman(
        int $poId,
        int $gudangId,
        string $nomorSuratJalan,
        string $alasanPenolakan,
        User $actor
    ): ?string {
        return DB::transaction(function () use ($poId, $gudangId, $nomorSuratJalan, $alasanPenolakan, $actor) {
            $po = PesananPembelian::with('detail')->lockForUpdate()->findOrFail($poId);

            if (! in_array($po->status, StatusPesananPembelian::bisaDiterima(), true)) {
                return null;
            }

            $rcvKode = CodeGenerator::generate('RCV', PenerimaanBarang::class, 'kode', 'Ym', 3);

            $penerimaan = PenerimaanBarang::create([
                'kode'                 => $rcvKode,
                'pesanan_pembelian_id' => $poId,
                'gudang_id'            => $gudangId,
                'nomor_surat_jalan'    => $nomorSuratJalan,
                'tanggal_terima'       => now()->toDateString(),
                'status'               => StatusPenerimaanPo::Ditolak->value,
                'catatan'              => '[DITOLAK PETUGAS GUDANG]: ' . trim($alasanPenolakan),
                'diterima_oleh'        => $actor->id,
            ]);

            foreach ($po->detail as $d) {
                PenerimaanBarangDetail::create([
                    'penerimaan_barang_id' => $penerimaan->id,
                    'barang_id'            => $d->barang_id,
                    'lokasi_gudang_id'     => null,
                    'nomor_batch'          => 'REJECTED',
                    'tanggal_kedaluwarsa'  => null,
                    'jumlah_diterima'      => 0,
                    'jumlah_rusak'         => max(0, (int) $d->jumlah_dipesan - (int) $d->jumlah_diterima),
                    'harga_satuan'         => (float) $d->harga_satuan,
                ]);
            }

            return $rcvKode;
        });
    }
}
