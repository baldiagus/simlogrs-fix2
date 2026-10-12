<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StatusMaintenance;
use App\Enums\StatusSpk;
use App\Http\Controllers\Api\ApiController;
use App\Models\Aset;
use App\Models\Barang;
use App\Models\LogMaintenance;
use App\Models\PenugasanMaintenance;
use App\Models\PergerakanStok;
use App\Models\PermintaanMaintenance;
use App\Models\StokBatch;
use App\Models\SukuCadangMaintenance;
use App\Models\User;
use App\Support\File\FileOptimizer;
use App\Support\Generator\CodeGenerator;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MaintenanceSpkApiController extends ApiController
{
    /**
     * Daftar teknisi resmi IPSRS untuk opsi penugasan SPK.
     * GET /v1/maintenance/teknisi
     */
    public function daftarTeknisi(Request $request): JsonResponse
    {
        $teknisi = User::where('status', 'AKTIF')
            ->whereHas('roles', fn ($r) => $r->where('name', 'teknisi'))
            ->orderBy('nama')
            ->get(['id', 'nama', 'email', 'nip']);

        return $this->jsonSuccess($teknisi, 'Daftar teknisi IPSRS berhasil dimuat.');
    }

    /**
     * Disposisi tiket kerusakan dan penerbitan SPK penugasan teknisi.
     * POST /v1/maintenance/tiket/{id}/disposisi
     */
    public function disposisiTiket(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $canDisposisi = $user && (
            $user->hasRole(['super_admin', 'admin_logistik']) ||
            ($user->can('create_pemeliharaan::spk::perbaikan') && $user->hasRole('kepala_teknisi'))
        );

        if (! $canDisposisi) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk menerbitkan SPK penugasan teknisi.', 403);
        }

        $tiket = PermintaanMaintenance::with('aset')->find($id);

        if (! $tiket) {
            return $this->jsonError('Tiket kerusakan tidak ditemukan.', 404);
        }

        if (! in_array($tiket->status, StatusMaintenance::menungguPenugasan(), true)) {
            return $this->jsonError("Tiket {$tiket->kode} sudah diproses sebelumnya (saat ini: {$tiket->status}).", 422);
        }

        $validator = Validator::make($request->all(), [
            'teknisi_id'        => 'required|exists:pengguna,id',
            'tanggal_penugasan' => 'nullable|date',
            'target_selesai'    => 'nullable|date',
            'instruksi_khusus'  => 'nullable|string|max:500',
        ], [
            'teknisi_id.required' => 'Teknisi penanggung jawab wajib dipilih.',
            'teknisi_id.exists'   => 'Teknisi yang dipilih tidak valid di sistem.',
        ]);

        if ($validator->fails()) {
            return $this->jsonError('Validasi disposisi gagal.', 422, $validator->errors());
        }

        $teknisiId = (int) $request->input('teknisi_id');
        $tglPenugasan = $request->input('tanggal_penugasan') ?: now()->toDateString();
        $targetSelesai = $request->input('target_selesai') ?: now()->addDays(2)->toDateString();
        $instruksiKhusus = $request->input('instruksi_khusus');

        $kodeSpk = CodeGenerator::generateSpk();

        try {
            $spk = DB::transaction(function () use ($kodeSpk, $tiket, $teknisiId, $user, $tglPenugasan, $targetSelesai, $instruksiKhusus) {
                $spkRecord = PenugasanMaintenance::create([
                    'kode'                      => $kodeSpk,
                    'permintaan_maintenance_id' => $tiket->id,
                    'teknisi_id'                => $teknisiId,
                    'ditugaskan_oleh'           => $user->id,
                    'tanggal_penugasan'         => $tglPenugasan,
                    'target_selesai'            => $targetSelesai,
                    'instruksi_khusus'          => $instruksiKhusus,
                    'status'                    => StatusSpk::Ditugaskan->value,
                ]);

                $tiket->update([
                    'status' => StatusMaintenance::Ditugaskan->value,
                ]);

                return $spkRecord;
            });

            $teknisi = User::find($teknisiId);

            return $this->jsonSuccess([
                'id'       => $spk->id,
                'kode_spk' => $spk->kode,
                'teknisi'  => $teknisi?->nama ?? 'Teknisi',
                'status'   => StatusSpk::Ditugaskan->value,
            ], "Surat Perintah Kerja {$spk->kode} berhasil diterbitkan dan ditugaskan ke {$teknisi?->nama}.", 201);
        } catch (\Throwable $e) {
            return $this->jsonError('Gagal menerbitkan SPK: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Daftar Surat Perintah Kerja (SPK) untuk mobile teknisi.
     * GET /v1/maintenance/spk
     */
    public function spkList(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->jsonError('Unauthenticated.', 401);
        }

        $isGlobalAdmin = $user->hasRole(['super_admin', 'admin_logistik']);
        $canViewAll = $isGlobalAdmin || $user->can('view_any_pemeliharaan::spk::perbaikan');
        if (! $canViewAll && ! $user->can('view_pemeliharaan::spk::perbaikan') && ! $user->hasRole(['teknisi', 'kepala_teknisi'])) {
            return $this->jsonError('Anda tidak memiliki wewenang melihat daftar SPK teknisi.', 403);
        }

        $filterStatus = $request->query('status'); // DITUGASKAN, DITERIMA, SELESAI
        $spks = PenugasanMaintenance::with([
            'permintaanMaintenance.aset.barang',
            'permintaanMaintenance.unit',
            'teknisi',
            'penugast',
        ])
        ->when(! $canViewAll, fn ($q) => $q->where('teknisi_id', $user->id))
        ->when($filterStatus, fn ($q) => $q->where('status', $filterStatus))
        ->orderByRaw('CASE WHEN status = ? THEN 1 WHEN status = ? THEN 2 ELSE 3 END', [StatusSpk::Ditugaskan->value, StatusSpk::Diterima->value])
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
                    ? asset('storage/' . ltrim($spk->permintaanMaintenance->foto_kerusakan, '/'))
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

        $isGlobalAdmin = $user?->hasRole(['super_admin', 'admin_logistik']);
        if (! $user || (! $isGlobalAdmin && ! $user->can('view_any_pemeliharaan::spk::perbaikan') && ! $user->can('view_pemeliharaan::spk::perbaikan'))) {
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
        if ($user->hasRole('teknisi') && ! $isGlobalAdmin && (int) $spk->teknisi_id !== (int) $user->id) {
            return $this->jsonError('Anda hanya diizinkan melihat SPK yang ditugaskan kepada Anda.', 403);
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
                    ? asset('storage/' . ltrim($spk->permintaanMaintenance->foto_kerusakan, '/'))
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
            $validated = $request->validate([
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
            $opt = FileOptimizer::optimizeAndStore($request->file('foto'), 'maintenance/pengerjaan');
            $fotoLogPath = $opt['path'];
            $fotoLogUrl = $opt['url'];
        }
        // Validasi ketersediaan stok fisik suku cadang sebelum transaksi
        if (! empty($validated['spareparts'])) {
            foreach ($validated['spareparts'] as $item) {
                $barang = Barang::with('satuan')->find($item['barang_id']);
                if ($barang && $barang->tipe !== 'SPAREPART') {
                    return $this->jsonError("Komoditas '{$barang->nama}' bertipe {$barang->tipe}. Hanya barang bertipe SPAREPART yang dapat digunakan pada lembar kerja pengerjaan.", 422);
                }
                $needed = (int) $item['jumlah'];
                $available = (int) StokBatch::where('barang_id', $item['barang_id'])
                    ->where('status', 'AKTIF')
                    ->where('jumlah', '>', 0)
                    ->sum('jumlah');

                $namaBarang = $barang?->nama ?? "Barang #{$item['barang_id']}";
                $satuan = $barang?->satuan?->simbol ?? 'Unit';
                if ($available <= 0) {
                    return $this->jsonError("Stok fisik suku cadang '{$namaBarang}' saat ini habis (0 {$satuan}).", 422);
                }

                if ($needed > $available) {
                    return $this->jsonError("Jumlah diminta untuk '{$namaBarang}' ({$needed} {$satuan}) melebihi stok fisik yang tersedia ({$available} {$satuan}).", 422);
                }
            }
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
                    $spk->update(['status' => StatusSpk::Selesai->value]);
                    $tiket->update(['status' => StatusMaintenance::Selesai->value]);

                    if ($tiket->aset) {
                        $newStatus = $tiket->aset->unit_id ? 'DIGUNAKAN' : 'TERSEDIA';
                        $tiket->aset->update([
                            'status'  => $newStatus,
                            'kondisi' => 'BAIK',
                        ]);
                    }
                } elseif ($validated['hasil'] === 'BERHASIL' && ! $ujiFungsiLolos) {
                    $spk->update(['status' => StatusSpk::Diterima->value]);
                    $tiket->update(['status' => StatusMaintenance::UjiFungsi->value]);
                } elseif ($validated['hasil'] === 'PENDING') {
                    $spk->update(['status' => StatusSpk::Diterima->value]);
                    $tiket->update(['status' => StatusMaintenance::SedangDikerjakan->value]);
                } else {
                    $spk->update(['status' => StatusSpk::Diterima->value]);
                    $tiket->update(['status' => StatusMaintenance::MenungguSparepart->value]);
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
