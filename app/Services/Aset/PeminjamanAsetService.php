<?php

namespace App\Services\Aset;

use App\Models\Aset;
use App\Models\PengembalianPeminjaman;
use App\Models\PengembalianPeminjamanDetail;
use App\Models\PermintaanMaintenance;
use App\Models\PermintaanPeminjaman;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Exception;
use Illuminate\Support\Facades\DB;

class PeminjamanAsetService
{
    /**
     * Eksekusi serah terima fisik aset inventaris ke unit peminjam.
     * Mengubah status transaksi menjadi DIPINJAM dan memutasi kepemilikan operasional aset.
     *
     * @throws Exception
     */
    public function serahTerimaFisik(PermintaanPeminjaman $pinjam, User $actor): void
    {
        if ($pinjam->status !== 'DISETUJUI') {
            throw new Exception("Permintaan {$pinjam->kode} belum disetujui atau sudah diproses.");
        }

        DB::transaction(function () use ($pinjam) {
            $pinjam->update([
                'status' => 'DIPINJAM',
            ]);

            foreach ($pinjam->detail as $detail) {
                // Kunci aset dengan pessimistic locking
                $aset = Aset::where('id', $detail->aset_id)->lockForUpdate()->first();
                if (! $aset) {
                    continue;
                }

                if ($aset->status === 'DIPINJAM') {
                    throw new Exception("Aset '{$aset->kode_aset}' ({$aset->barang?->nama}) saat ini sedang berstatus aktif DIPINJAM oleh unit lain.");
                }

                if ($aset->status !== 'TERSEDIA') {
                    throw new Exception("Aset '{$aset->kode_aset}' tidak dalam status TERSEDIA untuk diserahterimakan (saat ini: {$aset->status}).");
                }

                // Chain of custody: kepemilikan operasional berpindah ke unit peminjam.
                // Gudang disetel null agar patuh pada check constraint chk_aset_lokasi_xor.
                $aset->update([
                    'status'           => 'DIPINJAM',
                    'unit_id'          => $pinjam->unit_id,
                    'gudang_id'        => null,
                    'lokasi_gudang_id' => null,
                ]);
            }
        });
    }

    /**
     * Proses pengembalian fisik aset dan inspeksi kelayakan.
     * Jika kembali rusak, sistem otomatis membuat tiket servis ke antrean teknisi IPSRS.
     *
     * @throws Exception
     */
    public function prosesPengembalian(
        PermintaanPeminjaman $pinjam,
        array $inspeksiItems,
        User $actor,
        ?string $catatan = null
    ): PengembalianPeminjaman {
        if ($pinjam->status !== 'DIPINJAM') {
            throw new Exception("Peminjaman {$pinjam->kode} tidak dalam status aktif dipinjam.");
        }

        return DB::transaction(function () use ($pinjam, $inspeksiItems, $actor, $catatan) {
            $kodeKembali = CodeGenerator::generate('RTN', PengembalianPeminjaman::class, 'kode', 'Ym', 3);

            $pengembalian = PengembalianPeminjaman::create([
                'kode'                     => $kodeKembali,
                'permintaan_peminjaman_id' => $pinjam->id,
                'tanggal_kembali_aktual'   => now()->toDateString(),
                'diperiksa_oleh'           => $actor->id,
                'catatan'                  => $catatan ?: null,
            ]);

            foreach ($inspeksiItems as $item) {
                $detailId = (int) $item['permintaan_peminjaman_detail_id'];
                $asetId = (int) $item['aset_id'];
                $kondisi = in_array($item['kondisi_saat_kembali'] ?? 'BAIK', ['BAIK', 'RUSAK_RINGAN', 'RUSAK_BERAT'])
                    ? $item['kondisi_saat_kembali']
                    : 'BAIK';
                $itemCatatan = ! empty($item['catatan']) ? trim($item['catatan']) : null;

                $kembaliDetail = PengembalianPeminjamanDetail::create([
                    'pengembalian_peminjaman_id'    => $pengembalian->id,
                    'permintaan_peminjaman_detail_id' => $detailId,
                    'aset_id'                       => $asetId,
                    'kondisi_saat_kembali'          => $kondisi,
                    'catatan'                       => $itemCatatan,
                ]);

                $aset = Aset::find($asetId);
                if (! $aset) {
                    continue;
                }

                $updateData = [
                    'status'  => $kondisi === 'BAIK' ? 'TERSEDIA' : 'DALAM_MAINTENANCE',
                    'kondisi' => $kondisi,
                ];

                if ($pinjam->tipe_penyedia === 'GUDANG') {
                    $updateData['unit_id']   = null;
                    $updateData['gudang_id'] = $pinjam->gudang_penyedia_id;
                } else {
                    $updateData['unit_id']   = $pinjam->unit_penyedia_id;
                    $updateData['gudang_id'] = null;
                }

                $aset->update($updateData);

                if ($kondisi !== 'BAIK') {
                    $prioritas = $kondisi === 'RUSAK_BERAT' ? 'CITO' : 'SEDANG';
                    $kodeMnt = CodeGenerator::generate('MNT', PermintaanMaintenance::class, 'kode', 'Ym', 3);

                    $ownerUnitId = $pinjam->tipe_penyedia === 'RUANGAN' ? $pinjam->unit_penyedia_id : $pinjam->unit_id;
                    PermintaanMaintenance::create([
                        'kode'                              => $kodeMnt,
                        'aset_id'                           => $aset->id,
                        'unit_id'                           => $ownerUnitId,
                        'pengembalian_peminjaman_detail_id' => $kembaliDetail->id,
                        'dilaporkan_oleh'                   => $actor->id,
                        'deskripsi_masalah'                 => "Aset dikembalikan pasca-peminjaman ({$pinjam->kode}) dalam kondisi {$kondisi}. " . ($itemCatatan ? "Catatan: {$itemCatatan}" : ''),
                        'prioritas'                         => $prioritas,
                        'status'                            => 'DILAPORKAN',
                    ]);
                }
            }

            $pinjam->update([
                'status' => 'SELESAI',
            ]);

            return $pengembalian;
        });
    }
}
