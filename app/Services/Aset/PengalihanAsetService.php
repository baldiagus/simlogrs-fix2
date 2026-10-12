<?php

namespace App\Services\Aset;

use App\Enums\StatusAset;
use App\Enums\StatusPengalihan;
use App\Models\Aset;
use App\Models\PengalihanAset;
use App\Models\PengalihanAsetDetail;
use App\Support\Generator\CodeGenerator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PengalihanAsetService
{
    /**
     * Mengajukan pengalihan/mutasi aset fisik permanen.
     */
    public function ajukanPengalihan(array $data): PengalihanAset
    {
        $data = $this->validasiAjukan($data);

        return DB::transaction(function () use ($data) {
            $transfer = $this->buatHeaderPengalihan($data);

            foreach ($data['aset_ids'] as $asetId) {
                $aset = Aset::lockForUpdate()->findOrFail($asetId);
                $this->validasiAsetDapatDialihkan($aset, $data);

                PengalihanAsetDetail::create([
                    'pengalihan_aset_id' => $transfer->id,
                    'aset_id' => $aset->id,
                    'kondisi_sebelum' => $aset->kondisi,
                    'catatan' => $data['catatan_aset'][$asetId] ?? null,
                ]);

                // Kunci aset selama proses pengalihan berlangsung
                $aset->update(['status' => StatusAset::DalamPengalihan->value]);
            }

            return $transfer->load(['detail.aset', 'unitAsal', 'unitTujuan', 'gudangAsal', 'gudangTujuan']);
        });
    }

    /** Validasi input pengajuan; mengembalikan $data dengan default tipe_asal/tipe_tujuan terisi. */
    private function validasiAjukan(array $data): array
    {
        if (empty($data['aset_ids'] ?? [])) {
            throw new InvalidArgumentException('Minimal harus memilih 1 aset fisik untuk dialihkan.');
        }

        $data['tipe_asal'] = $tipeAsal = $data['tipe_asal'] ?? 'RUANGAN';
        $data['tipe_tujuan'] = $tipeTujuan = $data['tipe_tujuan'] ?? 'RUANGAN';
        $unitAsalId = $data['unit_asal_id'] ?? null;
        $gudangAsalId = $data['gudang_asal_id'] ?? null;
        $unitTujuanId = $data['unit_tujuan_id'] ?? null;
        $gudangTujuanId = $data['gudang_tujuan_id'] ?? null;

        if (! in_array($tipeAsal, ['RUANGAN', 'GUDANG'], true) || ! in_array($tipeTujuan, ['RUANGAN', 'GUDANG'], true)) {
            throw new InvalidArgumentException('Tipe asal dan tipe tujuan pengalihan harus RUANGAN atau GUDANG.');
        }
        if (($tipeAsal === 'RUANGAN' ? ! $unitAsalId : ! $gudangAsalId) || ($tipeTujuan === 'RUANGAN' ? ! $unitTujuanId : ! $gudangTujuanId)) {
            throw new InvalidArgumentException('Fasilitas asal dan tujuan pengalihan wajib dipilih sesuai tipenya.');
        }

        // Anti self-transfer guard (cast int: ID dari request bisa berupa string)
        if ($tipeAsal === 'RUANGAN' && $tipeTujuan === 'RUANGAN' && (int) $unitAsalId === (int) $unitTujuanId) {
            throw new InvalidArgumentException('Unit asal dan unit tujuan pengalihan tidak boleh sama.');
        }
        if ($tipeAsal === 'GUDANG' && $tipeTujuan === 'GUDANG' && (int) $gudangAsalId === (int) $gudangTujuanId) {
            throw new InvalidArgumentException('Gudang asal dan gudang tujuan pengalihan tidak boleh sama.');
        }

        return $data;
    }

    private function buatHeaderPengalihan(array $data): PengalihanAset
    {
        $tipeAsal = $data['tipe_asal'];
        $tipeTujuan = $data['tipe_tujuan'];

        return PengalihanAset::create([
            'kode' => CodeGenerator::generate('TRF', PengalihanAset::class, 'kode', 'Ym', 3),
            'tipe_asal' => $tipeAsal,
            'unit_asal_id' => $tipeAsal === 'RUANGAN' ? ($data['unit_asal_id'] ?? null) : null,
            'gudang_asal_id' => $tipeAsal === 'GUDANG' ? ($data['gudang_asal_id'] ?? null) : null,
            'tipe_tujuan' => $tipeTujuan,
            'unit_tujuan_id' => $tipeTujuan === 'RUANGAN' ? ($data['unit_tujuan_id'] ?? null) : null,
            'gudang_tujuan_id' => $tipeTujuan === 'GUDANG' ? ($data['gudang_tujuan_id'] ?? null) : null,
            'lokasi_gudang_tujuan_id' => $tipeTujuan === 'GUDANG' ? ($data['lokasi_gudang_tujuan_id'] ?? null) : null,
            'tanggal_pengalihan' => $data['tanggal_pengalihan'] ?? now()->toDateString(),
            'alasan' => $data['alasan'] ?? '-',
            'status' => StatusPengalihan::Diajukan->value,
            'diajukan_oleh' => $data['diajukan_oleh'],
            'catatan' => $data['catatan'] ?? null,
        ]);
    }

    /** Validasi lokasi kepemilikan dan status aset terhadap sumber pengalihan. */
    private function validasiAsetDapatDialihkan(Aset $aset, array $data): void
    {
        if ($data['tipe_asal'] === 'RUANGAN' && (int) $aset->unit_id !== (int) ($data['unit_asal_id'] ?? null)) {
            throw new InvalidArgumentException("Aset {$aset->kode_aset} tidak berada di unit asal yang dipilih.");
        }
        if ($data['tipe_asal'] === 'GUDANG' && (int) $aset->gudang_id !== (int) ($data['gudang_asal_id'] ?? null)) {
            throw new InvalidArgumentException("Aset {$aset->kode_aset} tidak berada di gudang asal yang dipilih.");
        }

        if (! in_array($aset->status, StatusAset::dapatDialihkan(), true) || in_array($aset->kondisi, ['RUSAK_BERAT', 'DIHAPUSKAN'], true)) {
            throw new InvalidArgumentException("Aset {$aset->kode_aset} ({$aset->status}) tidak dapat dialihkan.");
        }
    }

    /**
     * Menyetujui dokumen pengalihan aset.
     */
    public function setujuiPengalihan(int $pengalihanId, int $userId, ?string $catatan = null): PengalihanAset
    {
        return DB::transaction(function () use ($pengalihanId, $userId, $catatan) {
            $transfer = PengalihanAset::lockForUpdate()->findOrFail($pengalihanId);

            if ($transfer->status !== StatusPengalihan::Diajukan->value) {
                throw new InvalidArgumentException("Dokumen pengalihan {$transfer->kode} tidak dalam status DIAJUKAN.");
            }

            if ((int) $transfer->diajukan_oleh === (int) $userId) {
                throw new InvalidArgumentException('Konflik Kepentingan: Pemohon dilarang menyetujui permohonan pengalihannya sendiri.');
            }

            $transfer->update([
                'status' => StatusPengalihan::Disetujui->value,
                'disetujui_oleh' => $userId,
                'catatan' => $catatan ?: $transfer->catatan,
            ]);

            return $transfer;
        });
    }

    /**
     * Menolak dokumen pengalihan aset dan mengembalikan status fisik aset ke TERSEDIA.
     */
    public function tolakPengalihan(int $pengalihanId, int $userId, ?string $alasan = null): PengalihanAset
    {
        return DB::transaction(function () use ($pengalihanId, $userId, $alasan) {
            $transfer = PengalihanAset::with('detail.aset')->lockForUpdate()->findOrFail($pengalihanId);

            if (! in_array($transfer->status, StatusPengalihan::aktif(), true)) {
                throw new InvalidArgumentException("Dokumen pengalihan {$transfer->kode} tidak dapat ditolak.");
            }

            if ((int) $transfer->diajukan_oleh === (int) $userId) {
                throw new InvalidArgumentException('Konflik Kepentingan: Pemohon tidak berwenang menolak permohonan pengalihan; gunakan pembatalan pengajuan.');
            }

            // Kembalikan status aset
            foreach ($transfer->detail as $item) {
                if ($item->aset && $item->aset->status === StatusAset::DalamPengalihan->value) {
                    $item->aset->update(['status' => StatusAset::Tersedia->value]);
                }
            }

            $transfer->update([
                'status' => StatusPengalihan::Ditolak->value,
                'disetujui_oleh' => $userId,
                'catatan' => $alasan ?: $transfer->catatan,
            ]);

            return $transfer;
        });
    }

    /**
     * Membatalkan permohonan pengalihan oleh pengaju.
     */
    public function batalkanPengalihan(int $pengalihanId, int $userId, ?string $alasan = null): PengalihanAset
    {
        return DB::transaction(function () use ($pengalihanId, $userId, $alasan) {
            $transfer = PengalihanAset::with('detail.aset')->lockForUpdate()->findOrFail($pengalihanId);

            if ($transfer->status !== StatusPengalihan::Diajukan->value) {
                throw new InvalidArgumentException("Hanya pengalihan berstatus DIAJUKAN yang dapat dibatalkan.");
            }

            // Kembalikan status aset
            foreach ($transfer->detail as $item) {
                if ($item->aset && $item->aset->status === StatusAset::DalamPengalihan->value) {
                    $item->aset->update(['status' => StatusAset::Tersedia->value]);
                }
            }

            $transfer->update([
                'status' => StatusPengalihan::Dibatalkan->value,
                'catatan' => $alasan ?: $transfer->catatan,
            ]);

            return $transfer;
        });
    }

    /**
     * Konfirmasi serah terima fisik di unit/gudang tujuan dan memutasi kepemilikan aset secara permanen.
     */
    public function eksekusiSerahTerima(int $pengalihanId, int $userId, array $kondisiAset = []): PengalihanAset
    {
        return DB::transaction(function () use ($pengalihanId, $userId, $kondisiAset) {
            $transfer = PengalihanAset::with('detail.aset')->lockForUpdate()->findOrFail($pengalihanId);

            if ($transfer->status !== StatusPengalihan::Disetujui->value) {
                throw new InvalidArgumentException("Dokumen pengalihan {$transfer->kode} belum disetujui atau sudah selesai diproses.");
            }

            foreach ($transfer->detail as $item) {
                $aset = Aset::lockForUpdate()->findOrFail($item->aset_id);

                $kondisiBaru = $kondisiAset[$aset->id]['kondisi'] ?? $aset->kondisi;
                $catatanBaru = $kondisiAset[$aset->id]['catatan'] ?? null;

                $item->update([
                    'kondisi_saat_diterima' => $kondisiBaru,
                    'catatan' => $catatanBaru ?: $item->catatan,
                ]);

                // Mutasi Permanen sesuai XOR PostgreSQL constraint:
                // (unit_id IS NOT NULL AND gudang_id IS NULL AND lokasi_gudang_id IS NULL)
                // OR (unit_id IS NULL AND gudang_id IS NOT NULL)
                if ($transfer->tipe_tujuan === 'RUANGAN') {
                    $aset->update([
                        'unit_id' => $transfer->unit_tujuan_id,
                        'gudang_id' => null,
                        'lokasi_gudang_id' => null,
                        'status' => StatusAset::Tersedia->value,
                        'kondisi' => $kondisiBaru,
                    ]);
                } else {
                    $aset->update([
                        'unit_id' => null,
                        'gudang_id' => $transfer->gudang_tujuan_id,
                        'lokasi_gudang_id' => $transfer->lokasi_gudang_tujuan_id,
                        'status' => StatusAset::Tersedia->value,
                        'kondisi' => $kondisiBaru,
                    ]);
                }
            }

            $transfer->update([
                'status' => StatusPengalihan::Selesai->value,
                'diterima_oleh' => $userId,
                'tanggal_serah_terima' => now(),
            ]);

            return $transfer->load(['detail.aset', 'unitAsal', 'unitTujuan', 'gudangAsal', 'gudangTujuan', 'penerima', 'penyetuju']);
        });
    }
}
