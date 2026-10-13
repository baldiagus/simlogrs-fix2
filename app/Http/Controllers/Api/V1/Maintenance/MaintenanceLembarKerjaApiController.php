<?php

namespace App\Http\Controllers\Api\V1\Maintenance;

use App\Enums\Ipsrs\StatusMaintenance;
use App\Enums\Ipsrs\StatusSpk;
use App\Http\Controllers\Api\ApiController;
use App\Models\Aset\Aset;
use App\Models\Master\Barang;
use App\Models\Ipsrs\LogMaintenance;
use App\Models\Ipsrs\PenugasanMaintenance;
use App\Models\Persediaan\PergerakanStok;
use App\Models\Ipsrs\PermintaanMaintenance;
use App\Models\Persediaan\StokBatch;
use App\Models\Ipsrs\SukuCadangMaintenance;
use App\Models\User;
use App\Support\File\FileOptimizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pencatatan lembar kerja servis & pemakaian suku cadang SPK.
 */
class MaintenanceLembarKerjaApiController extends ApiController
{
    /**
     * Teknisi mengunggah progres pengerjaan dan pencatatan suku cadang langsung dari mobile HP.
     * POST /v1/maintenance/spk/{id}/log
     */
    public function submitLog(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->can('create_pemeliharaan::lembar::kerja')) {
            return $this->jsonError('Anda tidak memiliki wewenang mencatat lembar kerja teknisi.', 403);
        }

        $spk = PenugasanMaintenance::with(['permintaanMaintenance.aset'])->find($id);

        if (! $spk) {
            return $this->jsonError('Dokumen SPK tidak ditemukan.', 404);
        }
        $isOwner = (int) $spk->teknisi_id === (int) $user->id;
        $isSupervisor = $user->hasRole(['super_admin', 'admin_logistik']) ||
            ($user->can('update_pemeliharaan::spk::perbaikan') && $user->hasRole('kepala_teknisi'));
        if (! $isOwner && ! $isSupervisor) {
            return $this->jsonError('Anda hanya diizinkan mengisi lembar kerja untuk SPK yang ditugaskan kepada Anda.', 403);
        }

        if (in_array($spk->status, StatusSpk::riwayat(), true)) {
            return $this->jsonError("SPK berstatus '{$spk->status}' tidak dapat ditambahkan catatan pengerjaan.", 422);
        }

        try {
            $validated = $request->validate($this->aturanValidasiLog());
        } catch (ValidationException $e) {
            return $this->jsonError('Validasi input lembar kerja gagal.', 422, $e->errors());
        }

        $tiket = $spk->permintaanMaintenance;
        if (! $tiket) {
            return $this->jsonError('Tiket pemeliharaan induk tidak ditemukan.', 404);
        }

        $fotoLogPath = null;
        $fotoLogUrl = null;
        if ($request->hasFile('foto')) {
            $opt = FileOptimizer::optimizeAndStore($request->file('foto'), 'maintenance/pengerjaan');
            $fotoLogPath = $opt['path'];
            $fotoLogUrl = $opt['url'];
        }
        if (! empty($validated['spareparts'])) {
            if ($errMsg = $this->validasiKetersediaanSparepart($validated['spareparts'])) {
                return $this->jsonError($errMsg, 422);
            }
        }


        $tglPengerjaan = $validated['tanggal_pengerjaan'] ?? now();
        $ujiFungsiLolos = (bool) ($validated['uji_fungsi_kelaikan'] ?? ($validated['hasil'] === 'BERHASIL'));

        try {
            [$log, $sparepartsRecorded] = $this->eksekusiLogPengerjaan($user, $spk, $tiket, $validated, $fotoLogPath, $tglPengerjaan, $ujiFungsiLolos);

            return $this->jsonSuccess([
                'spk_id'           => $spk->id,
                'spk_status'       => $spk->fresh()->status,
                'tiket_status'     => $tiket->fresh()->status,
                'aset_status'      => $tiket->aset?->fresh()->status,
                'aset_kondisi'     => $tiket->aset?->fresh()->kondisi,
                'log_id'           => $log->id,
                'foto_log_url'     => $fotoLogUrl,
                'hasil_pengerjaan' => $log->hasil,
                'suku_cadang'      => $sparepartsRecorded,
            ], "Progres lembar kerja SPK '{$spk->kode}' berhasil dicatat.");
        } catch (Exception $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    protected function validasiKetersediaanSparepart(array $spareparts): ?string
    {
        foreach ($spareparts as $item) {
            $barang = Barang::with('satuan')->find($item['barang_id']);
            if ($barang && $barang->tipe !== 'SPAREPART') {
                return "Komoditas '{$barang->nama}' bertipe {$barang->tipe}. Hanya barang bertipe SPAREPART yang dapat digunakan pada lembar kerja pengerjaan.";
            }
            $needed = (int) $item['jumlah'];
            $available = (int) StokBatch::where('barang_id', $item['barang_id'])
                ->where('status', 'AKTIF')
                ->where('jumlah', '>', 0)
                ->sum('jumlah');

            $namaBarang = $barang?->nama ?? "Barang #{$item['barang_id']}";
            $satuan = $barang?->satuan?->simbol ?? 'Unit';
            if ($available <= 0) {
                return "Stok fisik suku cadang '{$namaBarang}' saat ini habis (0 {$satuan}).";
            }

            if ($needed > $available) {
                return "Jumlah diminta untuk '{$namaBarang}' ({$needed} {$satuan}) melebihi stok fisik yang tersedia ({$available} {$satuan}).";
            }
        }

        return null;
    }

    protected function eksekusiLogPengerjaan(
        User $user,
        PenugasanMaintenance $spk,
        PermintaanMaintenance $tiket,
        array $validated,
        ?string $fotoLogPath,
        $tglPengerjaan,
        bool $ujiFungsiLolos
    ): array {
        $log = null;
        $sparepartsRecorded = [];

        DB::transaction(function () use ($user, $spk, $tiket, $validated, $fotoLogPath, $tglPengerjaan, $ujiFungsiLolos, &$log, &$sparepartsRecorded) {
            // 1. Simpan Catatan Lembar Kerja
            $log = LogMaintenance::create([
                'permintaan_maintenance_id' => $tiket->id,
                'penugasan_maintenance_id'  => $spk->id,
                'dikerjakan_oleh'           => $user->id,
                'tanggal_pengerjaan'        => $tglPengerjaan,
                'jam_mulai'                 => $validated['jam_mulai'] ?? null,
                'jam_selesai'               => $validated['jam_selesai'] ?? null,
                'diagnosa'                  => $validated['diagnosa'],
                'tindakan'                  => $validated['tindakan'],
                'hasil'                     => $validated['hasil'],
                'catatan'                   => $validated['catatan'] ?? null,
                'foto_pengerjaan'           => $fotoLogPath,
            ]);

            // 2. Alokasi Suku Cadang FEFO
            if (! empty($validated['spareparts'])) {
                $sparepartsRecorded = $this->alokasiSukuCadangFefo($user, $spk, $tiket, $log, $validated['spareparts']);
            }

            // 3. Update Status Dokumen & Aset
            $this->perbaruiStatusSetelahLog($spk, $tiket, $validated['hasil'], $ujiFungsiLolos);
        });

        return [$log, $sparepartsRecorded];
    }

    protected function alokasiSukuCadangFefo(User $user, PenugasanMaintenance $spk, PermintaanMaintenance $tiket, LogMaintenance $log, array $spareparts): array
    {
        $sparepartsRecorded = [];

        foreach ($spareparts as $item) {
            $barang = Barang::findOrFail($item['barang_id']);
            $needed = (int) $item['jumlah'];

            $batches = StokBatch::where('barang_id', $barang->id)
                ->where('status', 'AKTIF')
                ->where('jumlah', '>', 0)
                ->orderByRaw('tanggal_kedaluwarsa ASC NULLS LAST')
                ->lockForUpdate()
                ->get();

            $available = $batches->sum('jumlah');
            if ($available < $needed) {
                throw new Exception("Saldo stok fisik suku cadang '{$barang->nama}' tidak mencukupi (Tersedia: {$available}, Dibutuhkan: {$needed}).");
            }

            $remaining = $needed;
            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $deduct = min($batch->jumlah, $remaining);
                $sparepartsRecorded[] = $this->potongBatchFefo($user, $spk, $tiket, $log, $barang, $batch, $deduct);

                $remaining -= $deduct;
            }
        }

        return $sparepartsRecorded;
    }

    protected function potongBatchFefo(User $user, PenugasanMaintenance $spk, PermintaanMaintenance $tiket, LogMaintenance $log, Barang $barang, StokBatch $batch, int $deduct): array
    {
        $saldoSebelum = $batch->jumlah;
        $saldoSesudah = $saldoSebelum - $deduct;

        $batch->update([
            'jumlah' => $saldoSesudah,
            'status' => $saldoSesudah == 0 ? 'HABIS' : 'AKTIF',
        ]);

        $pergerakan = PergerakanStok::create([
            'barang_id'        => $barang->id,
            'stok_batch_id'    => $batch->id,
            'gudang_id'        => $batch->gudang_id,
            'lokasi_gudang_id' => $batch->lokasi_gudang_id,
            'jenis'            => 'KELUAR',
            'jumlah'           => $deduct,
            'saldo_sebelum'    => $saldoSebelum,
            'saldo_sesudah'    => $saldoSesudah,
            'referensi_jenis'  => 'SPK_MAINTENANCE',
            'referensi_id'     => $spk->id,
            'keterangan'       => "Servis Mobile IPSRS: {$tiket->kode} ({$log->diagnosa})",
            'dibuat_oleh'      => $user->id,
        ]);

        $subtotal = $deduct * (float) $batch->harga_satuan;
        SukuCadangMaintenance::create([
            'permintaan_maintenance_id' => $tiket->id,
            'log_maintenance_id'        => $log->id,
            'barang_id'                 => $barang->id,
            'stok_batch_id'             => $batch->id,
            'pergerakan_stok_id'        => $pergerakan->id,
            'jumlah_digunakan'          => $deduct,
            'harga_satuan'              => $batch->harga_satuan,
            'subtotal'                  => $subtotal,
            'dicatat_oleh'              => $user->id,
        ]);

        return [
            'nama_barang' => $barang->nama,
            'jumlah'      => $deduct,
            'batch'       => $batch->nomor_batch,
            'subtotal'    => $subtotal,
        ];
    }

    protected function perbaruiStatusSetelahLog(PenugasanMaintenance $spk, PermintaanMaintenance $tiket, string $hasil, bool $ujiFungsiLolos): void
    {
        if ($hasil === 'BERHASIL' && $ujiFungsiLolos) {
            $spk->update(['status' => StatusSpk::Selesai->value]);
            $tiket->update(['status' => StatusMaintenance::Selesai->value]);

            if ($tiket->aset) {
                $newStatus = $tiket->aset->unit_id ? 'DIGUNAKAN' : 'TERSEDIA';
                $tiket->aset->update([
                    'status'  => $newStatus,
                    'kondisi' => 'BAIK',
                ]);
            }
        } elseif ($hasil === 'BERHASIL' && ! $ujiFungsiLolos) {
            $spk->update(['status' => StatusSpk::Diterima->value]);
            $tiket->update(['status' => StatusMaintenance::UjiFungsi->value]);
        } elseif ($hasil === 'PENDING') {
            $spk->update(['status' => StatusSpk::Diterima->value]);
            $tiket->update(['status' => StatusMaintenance::SedangDikerjakan->value]);
        } else {
            $spk->update(['status' => StatusSpk::Diterima->value]);
            $tiket->update(['status' => StatusMaintenance::MenungguSparepart->value]);
        }
    }

    protected function aturanValidasiLog(): array
    {
        return [
            'tanggal_pengerjaan'    => 'nullable|date',
            'jam_mulai'             => 'nullable|string|max:10',
            'jam_selesai'           => 'nullable|string|max:10',
            'diagnosa'              => 'required|string|min:3|max:1000',
            'tindakan'              => 'required|string|min:3|max:1000',
            'hasil'                 => 'required|in:BERHASIL,PENDING,GAGAL',
            'catatan'               => 'nullable|string|max:1000',
            'foto'                  => 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:' . config('simlog.upload.max_kb'),
            'uji_fungsi_kelaikan'   => 'nullable|boolean',
            'spareparts'            => 'nullable|array',
            'spareparts.*.barang_id'=> 'required_with:spareparts|integer|exists:barang,id',
            'spareparts.*.jumlah'   => 'required_with:spareparts|integer|min:1',
        ];
    }
}
