<?php

namespace App\Services\Aset;

use App\Enums\StatusAset;
use App\Enums\StatusPeminjaman;
use App\Models\Aset;
use App\Models\PengembalianPeminjaman;
use App\Models\PengembalianPeminjamanDetail;
use App\Models\PermintaanMaintenance;
use App\Models\PermintaanPeminjaman;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Models\PermintaanPeminjamanDetail;

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
        if ($pinjam->status !== StatusPeminjaman::Disetujui->value) {
            throw new Exception("Permintaan {$pinjam->kode} belum disetujui atau sudah diproses.");
        }

        DB::transaction(function () use ($pinjam, $actor) {
            $catatanHandover = '[Serah Terima Fisik ' . now()->format('d/m/Y H:i') . " WIB]: Diserahkan oleh {$actor->nama} (NIP: " . ($actor->nip ?: '-') . ", User: {$actor->username})";
            $catatanBaru = trim(($pinjam->catatan ? $pinjam->catatan . "\n" : '') . $catatanHandover);

            $pinjam->update([
                'status'  => StatusPeminjaman::Dipinjam->value,
                'catatan' => $catatanBaru,
            ]);
            foreach ($pinjam->detail as $detail) {
                // Kunci aset dengan pessimistic locking
                $aset = Aset::where('id', $detail->aset_id)->lockForUpdate()->first();
                if (! $aset) {
                    continue;
                }

                if ($aset->status === StatusAset::Dipinjam->value) {
                    throw new Exception("Aset '{$aset->kode_aset}' ({$aset->barang?->nama}) saat ini sedang berstatus aktif DIPINJAM oleh unit lain.");
                }

                if ($aset->status !== StatusAset::Tersedia->value) {
                    throw new Exception("Aset '{$aset->kode_aset}' tidak dalam status TERSEDIA untuk diserahterimakan (saat ini: {$aset->status}).");
                }

                // Chain of custody: kepemilikan operasional berpindah ke unit peminjam.
                // Gudang disetel null agar patuh pada check constraint chk_aset_lokasi_xor.
                $aset->update([
                    'status'           => StatusAset::Dipinjam->value,
                    'unit_id'          => $pinjam->unit_id,
                    'gudang_id'        => null,
                    'lokasi_gudang_id' => null,
                ]);

                // Catat jejak staf pelaksana pada detail peminjaman
                $logDetail = '[Diserahkan oleh: ' . $actor->nama . ' (' . $actor->username . ')]';
                $detail->update([
                    'catatan' => trim(($detail->catatan ? $detail->catatan . ' ' : '') . $logDetail),
                ]);
            }
        });
    }

    /**
     * Pengajuan transaksi peminjaman aset baru beserta rincian aset teralokasi.
     */
    public function ajukanPeminjaman(array $data, User $actor, iterable $allocatedAssets): string
    {
        $kode = CodeGenerator::generate('PINJ', PermintaanPeminjaman::class, 'kode', 'Y', 3);

        DB::transaction(function () use ($kode, $data, $actor, $allocatedAssets) {
            $pinjam = PermintaanPeminjaman::create([
                'kode'                    => $kode,
                'tipe_penyedia'           => $data['tipe_penyedia'],
                'unit_penyedia_id'        => $data['tipe_penyedia'] === 'RUANGAN' ? $data['unit_penyedia_id'] : null,
                'gudang_penyedia_id'      => $data['tipe_penyedia'] === 'GUDANG' ? $data['gudang_penyedia_id'] : null,
                'unit_id'                 => $data['unit_id'],
                'diajukan_oleh'           => $actor->id,
                'tanggal_pinjam'          => $data['tanggal_pinjam'],
                'tanggal_kembali_rencana' => $data['tanggal_kembali_rencana'],
                'keperluan'               => $data['keperluan'],
                'status'                  => StatusPeminjaman::Diajukan->value,
                'catatan'                 => $data['catatan'] ?? null,
            ]);

            foreach ($allocatedAssets as $ast) {
                PermintaanPeminjamanDetail::create([
                    'permintaan_peminjaman_id' => $pinjam->id,
                    'aset_id'                  => $ast->id,
                    'kondisi_saat_pinjam'      => $ast->kondisi,
                    'catatan'                  => $data['catatan'] ?? null,
                ]);
            }
        });

        return $kode;
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
        return DB::transaction(function () use ($pinjam, $inspeksiItems, $actor, $catatan) {
            // Kunci dokumen lalu cek ulang status: dua pengembalian bersamaan tidak boleh sama-sama diproses.
            $pinjam = PermintaanPeminjaman::with('detail')->lockForUpdate()->findOrFail($pinjam->id);
            if ($pinjam->status !== StatusPeminjaman::Dipinjam->value) {
                throw new Exception("Peminjaman {$pinjam->kode} tidak dalam status aktif dipinjam.");
            }

            // Inspeksi harus tepat mencakup setiap aset pada dokumen: tidak ada aset asing, ganda, atau tertinggal.
            $detailPerId = $pinjam->detail->keyBy('id');
            $terinspeksi = [];
            foreach ($inspeksiItems as $item) {
                $detail = $detailPerId->get((int) ($item['permintaan_peminjaman_detail_id'] ?? 0));
                if (! $detail || (int) $detail->aset_id !== (int) ($item['aset_id'] ?? 0)) {
                    throw new Exception('Data inspeksi tidak sesuai dengan rincian aset pada dokumen peminjaman.');
                }
                if (isset($terinspeksi[$detail->id])) {
                    throw new Exception('Satu aset tidak boleh diinspeksi lebih dari sekali dalam satu pengembalian.');
                }
                $terinspeksi[$detail->id] = true;
            }
            if (count($terinspeksi) !== $detailPerId->count()) {
                throw new Exception('Seluruh aset pada dokumen peminjaman wajib diinspeksi sebelum pengembalian diselesaikan.');
            }

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
                    'status'  => $kondisi === 'BAIK' ? StatusAset::Tersedia->value : StatusAset::DalamMaintenance->value,
                    'kondisi' => $kondisi,
                ];

                if ($pinjam->tipe_penyedia === 'GUDANG') {
                    $defaultRackId = \App\Models\LokasiGudang::where('gudang_id', $pinjam->gudang_penyedia_id)
                        ->where('status', 'AKTIF')
                        ->value('id');

                    $updateData['unit_id']          = null;
                    $updateData['gudang_id']        = $pinjam->gudang_penyedia_id;
                    $updateData['lokasi_gudang_id'] = $defaultRackId;
                } else {
                    $updateData['unit_id']          = $pinjam->unit_penyedia_id;
                    $updateData['gudang_id']        = null;
                    $updateData['lokasi_gudang_id'] = null;
                }

                $aset->update($updateData);

                if ($kondisi !== 'BAIK') {
                    $prioritas = $kondisi === 'RUSAK_BERAT' ? 'CITO' : 'SEDANG';
                    $kodeMnt = CodeGenerator::generate('MNT', PermintaanMaintenance::class, 'kode', 'Ym', 3);

                    $ownerUnitId = $pinjam->tipe_penyedia === 'RUANGAN' ? $pinjam->unit_penyedia_id : null;
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
                'status' => StatusPeminjaman::Selesai->value,
            ]);

            return $pengembalian;
        });
    }
}
