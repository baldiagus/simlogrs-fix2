<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Aset;
use App\Models\Barang;
use App\Models\LogMaintenance;
use App\Models\PenugasanMaintenance;
use App\Models\PergerakanStok;
use App\Models\PermintaanMaintenance;
use App\Models\StokBatch;
use App\Models\SukuCadangMaintenance;
use App\Support\Generator\CodeGenerator;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MaintenanceApiController extends ApiController
{
    /**
     * Pelaporan cepat kerusakan fisik sarana via upload foto kamera HP.
     * POST /v1/maintenance/lapor-cepat
     */
    public function laporCepat(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->can('create_layanan_unit::lapor::servis')) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk melaporkan kerusakan sarana.', 403);
        }

        try {
            $validated = $request->validate([
                'aset_id'           => 'nullable|integer|exists:aset,id',
                'kode_aset'         => 'nullable|string|max:100',
                'unit_id'           => 'nullable|integer|exists:unit,id',
                'deskripsi_masalah' => 'required|string|min:5|max:1000',
                'prioritas'         => 'nullable|in:RENDAH,SEDANG,TINGGI,CITO',
                'foto'              => 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:10240',
            ]);
        } catch (ValidationException $e) {
            return $this->jsonError('Validasi formulir gagal.', 422, $e->errors());
        }

        // Resolusi aset berdasarkan id atau kode_aset (QR scanner)
        $aset = null;
        if (! empty($validated['aset_id'])) {
            $aset = Aset::with(['barang', 'unit'])->find($validated['aset_id']);
        } elseif (! empty($validated['kode_aset'])) {
            $aset = Aset::with(['barang', 'unit'])->where('kode_aset', trim($validated['kode_aset']))->first();
        }

        if (! $aset) {
            return $this->jsonError('Aset sarana tidak ditemukan. Harap sertakan aset_id atau kode_aset yang valid.', 404);
        }

        $prioritas = $validated['prioritas'] ?? 'SEDANG';
        $unitId = $validated['unit_id'] ?? $aset->unit_id ?? $user->unit_id;

        // Simpan foto kerusakan fisik jika diunggah
        $fotoPath = null;
        $fotoUrl = null;
        if ($request->hasFile('foto')) {
            $file = $request->file('foto');
            $fotoPath = $file->store('maintenance/kerusakan', 'public');
            $fotoUrl = Storage::disk('public')->url($fotoPath);
        }

        $kodeTiket = CodeGenerator::generateMnt();

        DB::transaction(function () use ($kodeTiket, $aset, $unitId, $user, $validated, $prioritas, $fotoPath, &$tiket) {
            $tiket = PermintaanMaintenance::create([
                'kode'              => $kodeTiket,
                'aset_id'           => $aset->id,
                'unit_id'           => $unitId,
                'dilaporkan_oleh'   => $user->id,
                'deskripsi_masalah' => $validated['deskripsi_masalah'],
                'foto_kerusakan'    => $fotoPath,
                'prioritas'         => $prioritas,
                'status'            => 'DILAPORKAN',
            ]);

            // Jika prioritas CITO atau TINGGI, tandai aset ke status DALAM_MAINTENANCE
            if (in_array($prioritas, ['CITO', 'TINGGI'], true)) {
                $aset->update([
                    'status'  => 'DALAM_MAINTENANCE',
                    'kondisi' => $prioritas === 'CITO' ? 'RUSAK_BERAT' : 'RUSAK_RINGAN',
                ]);
            }
        });

        return $this->jsonSuccess([
            'id'                => $tiket->id,
            'kode'              => $tiket->kode,
            'status'            => $tiket->status,
            'prioritas'         => $tiket->prioritas,
            'deskripsi_masalah' => $tiket->deskripsi_masalah,
            'foto_url'          => $fotoUrl,
            'aset'              => [
                'id'        => $aset->id,
                'kode_aset' => $aset->kode_aset,
                'nama'      => $aset->barang?->nama ?? '-',
            ],
            'dilaporkan_pada'   => $tiket->dibuat_pada?->toIso8601String(),
        ], "Laporan kerusakan '{$kodeTiket}' berhasil diterbitkan ke tim IPSRS.", 201);
    }

    /**
     * Daftar Surat Perintah Kerja (SPK) untuk mobile teknisi.
     * GET /v1/maintenance/spk
     */
    public function spkList(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || (! $user->can('view_any_pemeliharaan::spk::perbaikan') && ! $user->can('view_pemeliharaan::spk::perbaikan'))) {
            return $this->jsonError('Anda tidak memiliki wewenang melihat daftar SPK teknisi.', 403);
        }

        $filterStatus = $request->query('status'); // DITUGASKAN, DITERIMA, SELESAI
        $isGlobalAdmin = $user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard');

        $spks = PenugasanMaintenance::with([
            'permintaanMaintenance.aset.barang',
            'permintaanMaintenance.unit',
            'teknisi',
            'penugast',
        ])
        ->when(! $isGlobalAdmin, fn ($q) => $q->where('teknisi_id', $user->id))
        ->when($filterStatus, fn ($q) => $q->where('status', $filterStatus))
        ->orderByRaw("CASE WHEN status = 'DITUGASKAN' THEN 1 WHEN status = 'DITERIMA' THEN 2 ELSE 3 END")
        ->orderBy('id', 'desc')
        ->paginate(15);

        $items = $spks->getCollection()->map(fn ($spk) => [
            'id'                => $spk->id,
            'kode_spk'          => $spk->kode,
            'status'            => $spk->status,
            'tanggal_penugasan' => $spk->tanggal_penugasan?->format('Y-m-d'),
            'target_selesai'    => $spk->target_selesai?->format('Y-m-d'),
            'instruksi_khusus'  => $spk->instruksi_khusus,
            'teknisi'           => $spk->teknisi?->nama ?? '-',
            'tiket'             => [
                'kode'              => $spk->permintaanMaintenance?->kode,
                'prioritas'         => $spk->permintaanMaintenance?->prioritas,
                'deskripsi_masalah' => $spk->permintaanMaintenance?->deskripsi_masalah,
                'foto_url'          => $spk->permintaanMaintenance?->foto_kerusakan
                    ? Storage::disk('public')->url($spk->permintaanMaintenance->foto_kerusakan)
                    : null,
            ],
            'aset'              => [
                'kode_aset'  => $spk->permintaanMaintenance?->aset?->kode_aset ?? '-',
                'nama'       => $spk->permintaanMaintenance?->aset?->barang?->nama ?? '-',
                'nomor_seri' => $spk->permintaanMaintenance?->aset?->nomor_seri ?? '-',
                'lokasi'     => $spk->permintaanMaintenance?->unit?->nama ?? '-',
            ],
        ]);

        return $this->jsonSuccess([
            'spk' => $items,
            'paginasi' => [
                'total'        => $spks->total(),
                'per_halaman'  => $spks->perPage(),
                'halaman_ini'  => $spks->currentPage(),
                'total_halaman'=> $spks->lastPage(),
            ],
        ], 'Daftar penugasan SPK teknisi berhasil dimuat.');
    }

    /**
     * Rincian lengkap dokumen SPK teknisi.
     * GET /v1/maintenance/spk/{id}
     */
    public function spkDetail(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (! $user || (! $user->can('view_any_pemeliharaan::spk::perbaikan') && ! $user->can('view_pemeliharaan::spk::perbaikan'))) {
            return $this->jsonError('Anda tidak memiliki wewenang melihat detail SPK.', 403);
        }

        $spk = PenugasanMaintenance::with([
            'permintaanMaintenance.aset.barang',
            'permintaanMaintenance.unit',
            'permintaanMaintenance.pelapor',
            'teknisi',
            'penugast',
            'logMaintenance.teknisi',
            'logMaintenance.sukuCadang.barang',
        ])->find($id);

        if (! $spk) {
            return $this->jsonError('Dokumen SPK tidak ditemukan.', 404);
        }

        return $this->jsonSuccess([
            'id'                => $spk->id,
            'kode_spk'          => $spk->kode,
            'status'            => $spk->status,
            'tanggal_penugasan' => $spk->tanggal_penugasan?->format('Y-m-d'),
            'target_selesai'    => $spk->target_selesai?->format('Y-m-d'),
            'instruksi_khusus'  => $spk->instruksi_khusus,
            'teknisi'           => [
                'id'   => $spk->teknisi?->id,
                'nama' => $spk->teknisi?->nama,
            ],
            'tiket'             => [
                'id'                => $spk->permintaanMaintenance?->id,
                'kode'              => $spk->permintaanMaintenance?->kode,
                'prioritas'         => $spk->permintaanMaintenance?->prioritas,
                'status'            => $spk->permintaanMaintenance?->status,
                'deskripsi_masalah' => $spk->permintaanMaintenance?->deskripsi_masalah,
                'foto_url'          => $spk->permintaanMaintenance?->foto_kerusakan
                    ? Storage::disk('public')->url($spk->permintaanMaintenance->foto_kerusakan)
                    : null,
                'pelapor'           => $spk->permintaanMaintenance?->pelapor?->nama,
            ],
            'aset'              => [
                'id'         => $spk->permintaanMaintenance?->aset?->id,
                'kode_aset'  => $spk->permintaanMaintenance?->aset?->kode_aset,
                'nama'       => $spk->permintaanMaintenance?->aset?->barang?->nama,
                'nomor_seri' => $spk->permintaanMaintenance?->aset?->nomor_seri,
                'unit'       => $spk->permintaanMaintenance?->unit?->nama,
            ],
            'riwayat_log'       => $spk->logMaintenance->map(fn ($log) => [
                'id'                 => $log->id,
                'tanggal_pengerjaan' => $log->tanggal_pengerjaan?->toIso8601String(),
                'teknisi'            => $log->teknisi?->nama,
                'diagnosa'           => $log->diagnosa,
                'tindakan'           => $log->tindakan,
                'hasil'              => $log->hasil,
                'catatan'            => $log->catatan,
                'foto_url'           => $log->foto_pengerjaan
                    ? Storage::disk('public')->url($log->foto_pengerjaan)
                    : null,
                'suku_cadang'        => $log->sukuCadang->map(fn ($sc) => [
                    'barang'   => $sc->barang?->nama,
                    'jumlah'   => $sc->jumlah_digunakan,
                    'subtotal' => (float) $sc->subtotal,
                ]),
            ]),
        ], 'Detail SPK berhasil dimuat.');
    }

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

        if ($spk->status === 'SELESAI' || $spk->status === 'DIBATALKAN') {
            return $this->jsonError("SPK berstatus '{$spk->status}' tidak dapat ditambahkan catatan pengerjaan.", 422);
        }

        try {
            $validated = $request->validate([
                'tanggal_pengerjaan'    => 'nullable|date',
                'jam_mulai'             => 'nullable|string|max:10',
                'jam_selesai'           => 'nullable|string|max:10',
                'diagnosa'              => 'required|string|min:3|max:1000',
                'tindakan'              => 'required|string|min:3|max:1000',
                'hasil'                 => 'required|in:BERHASIL,PENDING,GAGAL',
                'catatan'               => 'nullable|string|max:1000',
                'foto'                  => 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:10240',
                'uji_fungsi_kelaikan'   => 'nullable|boolean',
                'spareparts'            => 'nullable|array',
                'spareparts.*.barang_id'=> 'required_with:spareparts|integer|exists:barang,id',
                'spareparts.*.jumlah'   => 'required_with:spareparts|integer|min:1',
            ]);
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
            $file = $request->file('foto');
            $fotoLogPath = $file->store('maintenance/pengerjaan', 'public');
            $fotoLogUrl = Storage::disk('public')->url($fotoLogPath);
        }

        $tglPengerjaan = $validated['tanggal_pengerjaan'] ?? now();
        $ujiFungsiLolos = (bool) ($validated['uji_fungsi_kelaikan'] ?? ($validated['hasil'] === 'BERHASIL'));

        try {
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
                $sparepartsRecorded = [];
                if (! empty($validated['spareparts'])) {
                    foreach ($validated['spareparts'] as $item) {
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
                            $sc = SukuCadangMaintenance::create([
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

                            $sparepartsRecorded[] = [
                                'nama_barang' => $barang->nama,
                                'jumlah'      => $deduct,
                                'batch'       => $batch->nomor_batch,
                                'subtotal'    => $subtotal,
                            ];

                            $remaining -= $deduct;
                        }
                    }
                }

                // 3. Update Status Dokumen & Aset
                if ($validated['hasil'] === 'BERHASIL' && $ujiFungsiLolos) {
                    $spk->update(['status' => 'SELESAI']);
                    $tiket->update(['status' => 'SELESAI']);

                    if ($tiket->aset) {
                        $newStatus = $tiket->aset->unit_id ? 'DIGUNAKAN' : 'TERSEDIA';
                        $tiket->aset->update([
                            'status'  => $newStatus,
                            'kondisi' => 'BAIK',
                        ]);
                    }
                } elseif ($validated['hasil'] === 'BERHASIL' && ! $ujiFungsiLolos) {
                    $spk->update(['status' => 'DITERIMA']);
                    $tiket->update(['status' => 'UJI_FUNGSI']);
                } elseif ($validated['hasil'] === 'PENDING') {
                    $spk->update(['status' => 'DITERIMA']);
                    $tiket->update(['status' => 'SEDANG_DIKERJAKAN']);
                } else {
                    $spk->update(['status' => 'DITERIMA']);
                    $tiket->update(['status' => 'MENUNGGU_SPAREPART']);
                }
            });

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
}
