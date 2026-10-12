<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StatusMaintenance;
use App\Http\Controllers\Api\ApiController;
use App\Models\Aset;
use App\Models\PermintaanMaintenance;
use App\Support\File\FileOptimizer;
use App\Support\Generator\CodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaintenanceTiketApiController extends ApiController
{
    /**
     * Pelaporan cepat kerusakan fisik sarana via upload foto kamera HP.
     * POST /v1/maintenance/lapor-cepat
     */
    public function laporCepat(Request $request): JsonResponse
    {
        $user = $request->user();

        $canLapor = $user->can('create_layanan_unit::lapor::servis') ||
            $user->hasRole(['super_admin', 'admin_logistik']);
        if (! $user || ! $canLapor) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk melaporkan kerusakan sarana.', 403);
        }

        try {
            $validated = $request->validate([
                'aset_id'           => 'nullable|integer|exists:aset,id',
                'kode_aset'         => 'nullable|string|max:100',
                'unit_id'           => 'nullable|integer|exists:unit,id',
                'deskripsi_masalah' => 'required|string|min:5|max:1000',
                'prioritas'         => 'nullable|in:RENDAH,SEDANG,TINGGI,CITO',
                'foto'              => 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:' . config('simlog.upload.max_kb'),
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
        $isGlobalAdmin = $user->hasRole(['super_admin', 'admin_logistik']);
        if (! $isGlobalAdmin) {
            $isAsetUnitUser = $user->unit_id && (int) $aset->unit_id === (int) $user->unit_id;
            $isAsetGudangUser = $user->gudang_id && (int) $aset->gudang_id === (int) $user->gudang_id;

            if (! $isAsetUnitUser && ! $isAsetGudangUser) {
                return $this->jsonError('Anda hanya berhak melaporkan kerusakan untuk aset yang berada di unit atau gudang Anda.', 403);
            }
        }

        $prioritas = $validated['prioritas'] ?? 'SEDANG';
        $unitId = $user->unit_id ?: $aset->unit_id;

        // Simpan foto kerusakan fisik jika diunggah
        $fotoPath = null;
        $fotoUrl = null;
        if ($request->hasFile('foto')) {
            $opt = FileOptimizer::optimizeAndStore($request->file('foto'), 'maintenance/kerusakan');
            $fotoPath = $opt['path'];
            $fotoUrl = asset('storage/' . ltrim($fotoPath, '/'));
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
                'status'            => StatusMaintenance::Dilaporkan->value,
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
     * Daftar seluruh tiket kerusakan sarana milik unit pemohon.
     * GET /v1/maintenance/tiket
     */
    public function daftarTiketUnit(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->jsonError('Unauthenticated.', 401);
        }

        $canView = $user->can('view_any_layanan_unit::lapor::servis') ||
            $user->can('view_layanan_unit::lapor::servis') ||
            $user->hasRole(['super_admin', 'admin_logistik']);
        if (! $canView) {
            return $this->jsonError('Anda tidak memiliki wewenang melihat daftar tiket kerusakan.', 403);
        }

        $isGlobalAdmin = $user->hasRole(['super_admin', 'admin_logistik']);
        $userUnitId = $user->unit_id;
        $userGudangId = $user->gudang_id;
        $filterStatus = $request->query('status');
        $filterPrioritas = $request->query('prioritas');
        $cari = $request->query('cari');

        $query = PermintaanMaintenance::with([
            'aset.barang',
            'unit',
            'pelapor',
            'penugasanAktif.teknisi',
        ])
        ->when($userUnitId && ! $isGlobalAdmin, fn ($q) => $q->where('unit_id', $userUnitId))
        ->when($userGudangId && ! $userUnitId && ! $isGlobalAdmin, function ($q) use ($user, $userGudangId) {
            $q->where(function ($sub) use ($user, $userGudangId) {
                $sub->where('dilaporkan_oleh', $user->id)
                    ->orWhereHas('aset', fn ($a) => $a->where('gudang_id', $userGudangId));
            });
        })
        ->when($filterStatus, fn ($q) => $q->where('status', $filterStatus))
        ->when($filterPrioritas, fn ($q) => $q->where('prioritas', $filterPrioritas))
        ->when($cari, function ($q) use ($cari) {
            $q->where(function ($sub) use ($cari) {
                $sub->where('kode', 'like', "%{$cari}%")
                    ->orWhere('deskripsi_masalah', 'like', "%{$cari}%")
                    ->orWhereHas('aset', function ($a) use ($cari) {
                        $a->where('kode_aset', 'like', "%{$cari}%")
                          ->orWhere('nomor_seri', 'like', "%{$cari}%")
                          ->orWhereHas('barang', fn ($b) => $b->where('nama', 'like', "%{$cari}%"));
                    });
            });
        });

        $tikets = $query->latest('id')->limit(100)->get()->map(function ($item) use ($user) {
            $canCancel = $item->status === StatusMaintenance::Dilaporkan->value && (
                (int) $item->dilaporkan_oleh === (int) $user->id ||
                ($user->hasRole('kepala_unit') && (int) $user->unit_id === (int) $item->unit_id) ||
                ($user->hasRole('kepala_gudang') && (int) $user->gudang_id === (int) $item->aset?->gudang_id) ||
                $user->hasRole('super_admin') ||
                $user->hasRole('admin_logistik')
            );
            return [
                'id'                => $item->id,
                'kode'              => $item->kode,
                'status'            => $item->status,
                'prioritas'         => $item->prioritas,
                'deskripsi_masalah' => $item->deskripsi_masalah,
                'foto_url'          => $item->foto_kerusakan ? asset('storage/' . ltrim($item->foto_kerusakan, '/')) : null,
                'dibuat_pada'       => $item->dibuat_pada?->format('d/m/Y H:i'),
                'unit'              => $item->unit?->nama ?? '-',
                'pelapor'           => $item->pelapor?->nama ?? '-',
                'can_cancel'        => $canCancel,
                'aset'              => [
                    'id'         => $item->aset?->id,
                    'kode_aset'  => $item->aset?->kode_aset ?? '-',
                    'nama'       => $item->aset?->barang?->nama ?? '-',
                    'nomor_seri' => $item->aset?->nomor_seri ?? '-',
                ],
                'penugasan_aktif'   => $item->penugasanAktif ? [
                    'kode_spk'     => $item->penugasanAktif->kode,
                    'nama_teknisi' => $item->penugasanAktif->teknisi?->nama ?? '-',
                    'status_spk'   => $item->penugasanAktif->status,
                ] : null,
            ];
        });

        return $this->jsonSuccess($tikets, 'Daftar tiket kerusakan unit berhasil dimuat.');
    }

    /**
     * Daftar inventaris aset yang siap dilaporkan rusak dari unit pemohon.
     * GET /v1/maintenance/aset-unit
     */
    public function asetUnitTersedia(Request $request): JsonResponse
    {
        $user = $request->user();
        $userUnitId = $user->unit_id;

        $asets = Aset::with(['barang.kategori', 'barang.merek'])
            ->when($userUnitId, fn ($q) => $q->where('unit_id', $userUnitId))
            ->whereIn('status', ['TERSEDIA', 'DIGUNAKAN', 'DIPINJAM'])
            ->orderBy('kode_aset')
            ->get()
            ->map(fn ($a) => [
                'id'         => $a->id,
                'kode_aset'  => $a->kode_aset,
                'nama'       => $a->barang?->nama ?? 'Aset Fisik',
                'nomor_seri' => $a->nomor_seri ?? '-',
                'kategori'   => $a->barang?->kategori?->nama ?? 'Umum',
                'kondisi'    => $a->kondisi,
                'status'     => $a->status,
            ]);

        return $this->jsonSuccess($asets, 'Daftar inventaris sarana unit berhasil dimuat.');
    }

    /**
     * Detail lengkap dan progres penanganan tiket kerusakan.
     * GET /v1/maintenance/tiket/{id}
     */
    public function detailTiketUnit(Request $request, int $id): JsonResponse
    {
        $tiket = PermintaanMaintenance::with([
            'aset.barang.kategori',
            'unit',
            'pelapor',
            'penugasan.teknisi',
            'logMaintenance.teknisi',
            'sukuCadang.barang',
        ])->find($id);

        if (! $tiket) {
            return $this->jsonError('Tiket kerusakan tidak ditemukan.', 404);
        }

        $isPrivileged = $user && (
            $user->hasRole(['super_admin', 'admin_logistik']) ||
            $user->can('view_any_layanan_unit::lapor::servis')
        );

        $isUnitOwner = $user && (
            ($user->unit_id && (int) $user->unit_id === (int) $tiket->unit_id) ||
            ($user->gudang_id && (int) $user->gudang_id === (int) $tiket->aset?->gudang_id) ||
            ((int) $tiket->dilaporkan_oleh === (int) $user->id)
        );

        if (! $isPrivileged && ! $isUnitOwner) {
            return $this->jsonError('Akses ditolak: Anda tidak memiliki wewenang melihat tiket pemeliharaan unit lain.', 403);
        }
        $canCancel = $tiket->status === StatusMaintenance::Dilaporkan->value && (
            (int) $tiket->dilaporkan_oleh === (int) $user->id ||
            ($user->hasRole('kepala_unit') && (int) $user->unit_id === (int) $tiket->unit_id) ||
            ($user->hasRole('kepala_gudang') && (int) $user->gudang_id === (int) $tiket->aset?->gudang_id) ||
            $user->hasRole('super_admin') ||
            $user->hasRole('admin_logistik')
        );

        return $this->jsonSuccess([
            'id'                => $tiket->id,
            'kode'              => $tiket->kode,
            'status'            => $tiket->status,
            'prioritas'         => $tiket->prioritas,
            'deskripsi_masalah' => $tiket->deskripsi_masalah,
            'foto_url'          => $tiket->foto_kerusakan ? asset('storage/' . ltrim($tiket->foto_kerusakan, '/')) : null,
            'dibuat_pada'       => $tiket->dibuat_pada?->format('d/m/Y H:i'),
            'unit'              => $tiket->unit?->nama ?? '-',
            'pelapor'           => $tiket->pelapor?->nama ?? '-',
            'can_cancel'        => $canCancel,
            'aset'              => [
                'id'         => $tiket->aset?->id,
                'kode_aset'  => $tiket->aset?->kode_aset ?? '-',
                'nama'       => $tiket->aset?->barang?->nama ?? '-',
                'nomor_seri' => $tiket->aset?->nomor_seri ?? '-',
                'kategori'   => $tiket->aset?->barang?->kategori?->nama ?? '-',
            ],
            'penugasan'         => $tiket->penugasan->map(fn ($spk) => [
                'id'       => $spk->id,
                'kode_spk' => $spk->kode,
                'status'   => $spk->status,
                'teknisi'  => $spk->teknisi?->nama ?? '-',
                'target'   => $spk->target_selesai?->format('Y-m-d'),
            ]),
            'riwayat_log'       => $tiket->logMaintenance->map(fn ($log) => [
                'id'       => $log->id,
                'tanggal'  => $log->tanggal_pengerjaan?->format('d/m/Y H:i'),
                'teknisi'  => $log->teknisi?->nama ?? '-',
                'diagnosa' => $log->diagnosa,
                'tindakan' => $log->tindakan,
                'hasil'    => $log->hasil,
                'catatan'  => $log->catatan,
            ]),
        ], 'Detail tiket kerusakan berhasil dimuat.');
    }

    /**
     * Batalkan tiket kerusakan yang masih berstatus DILAPORKAN.
     * POST /v1/maintenance/tiket/{id}/batal
     */
    public function batalTiketUnit(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $tiket = PermintaanMaintenance::find($id);

        if (! $tiket) {
            return $this->jsonError('Tiket kerusakan tidak ditemukan.', 404);
        }

        if ($tiket->status !== StatusMaintenance::Dilaporkan->value) {
            return $this->jsonError("Hanya tiket berstatus DILAPORKAN yang dapat dibatalkan (saat ini: {$tiket->status}).", 422);
        }

        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('admin_logistik');
        $canCancel = $tiket->dilaporkan_oleh === $user->id ||
            ($user->hasRole('kepala_unit') && (int) $user->unit_id === (int) $tiket->unit_id) ||
            ($user->hasRole('kepala_gudang') && (int) $user->gudang_id === (int) $tiket->aset?->gudang_id) ||
            $isGlobalAdmin;

        if (! $canCancel) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk membatalkan tiket ini.', 403);
        }

        $tiket->update(['status' => StatusMaintenance::Dibatalkan->value]);

        return $this->jsonSuccess(null, "Laporan kerusakan {$tiket->kode} berhasil dibatalkan.");
    }
}
