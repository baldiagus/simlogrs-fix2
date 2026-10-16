<?php

namespace App\Services\Pengadaan;

use App\Enums\Pengadaan\StatusPermintaanPengadaan;
use App\Models\Pengadaan\PermintaanPengadaan;
use App\Models\Pengadaan\PermintaanPengadaanDetail;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Support\Facades\DB;

/**
 * Aturan tunggal pengajuan Permintaan Pengadaan (PR) untuk web (Livewire) dan API mobile,
 * agar scope pemohon dan auto-otorisasi kepala tidak berbeda antar kanal.
 */
class PengajuanPrService
{
    public const PESAN_DI_LUAR_SCOPE = 'Anda hanya dapat mengajukan permintaan pengadaan untuk unit/gudang Anda sendiri.';

    /**
     * Role global yang boleh mengajukan/mengotorisasi lintas unit & gudang.
     */
    public function isGlobalAdmin(?User $user): bool
    {
        return (bool) $user?->hasRole(['super_admin', 'admin_logistik']);
    }

    /**
     * Periksa apakah unit atau gudang tersebut milik pengguna.
     */
    public function ownsScope(?User $user, ?int $unitId, ?int $gudangId): bool
    {
        return ($unitId && $user?->unit_id && (int) $user->unit_id === (int) $unitId)
            || ($gudangId && $user?->gudang_id && (int) $user->gudang_id === (int) $gudangId);
    }

    /**
     * Pemohon non-global hanya boleh mengajukan atas nama unit/gudang miliknya sendiri.
     */
    public function bolehMengajukanUntuk(User $user, ?int $unitId, ?int $gudangId): bool
    {
        return $this->isGlobalAdmin($user) || $this->ownsScope($user, $unitId, $gudangId);
    }

    /**
     * Tahap otorisasi kepala: role jabatan yang sesuai pada unit/gudang pemohon miliknya.
     */
    public function isWorkflowLeaderFor(?User $user, ?int $unitId, ?int $gudangId): bool
    {
        if (! $user) {
            return false;
        }

        if ($gudangId) {
            return $user->hasRole('kepala_gudang') && $user->gudang_id && (int) $user->gudang_id === (int) $gudangId;
        }

        if ($unitId) {
            return $user->hasRole(['kepala_unit', 'kepala_teknisi']) && $user->unit_id && (int) $user->unit_id === (int) $unitId;
        }

        return false;
    }

    /**
     * Auto-otorisasi tahap kepala membutuhkan permission otorisasi DAN role jabatan yang sesuai.
     */
    public function otorisasiOtomatis(User $user, ?int $unitId, ?int $gudangId): bool
    {
        return $user->can('update_pengadaan::permintaan::pengadaan')
            && ($this->isGlobalAdmin($user) || $this->isWorkflowLeaderFor($user, $unitId, $gudangId));
    }

    /**
     * Simpan header + detail PR dalam satu transaksi (ADR 0002 B). Status awal ditentukan server,
     * tidak pernah dari input klien. Scope pemohon diperiksa pemanggil; di sini dijaga ulang.
     *
     * @param  array<int, array{barang_id:int, jumlah:int, catatan?:?string}>  $items
     */
    public function ajukan(
        User $user,
        ?int $unitId,
        ?int $gudangId,
        string $urgensi,
        ?string $catatan,
        array $items,
        ?string $kode = null
    ): PermintaanPengadaan {
        abort_unless($this->bolehMengajukanUntuk($user, $unitId, $gudangId), 403, self::PESAN_DI_LUAR_SCOPE);

        $isLeader = $this->otorisasiOtomatis($user, $unitId, $gudangId);

        return DB::transaction(function () use ($user, $unitId, $gudangId, $urgensi, $catatan, $items, $kode, $isLeader) {
            $pr = PermintaanPengadaan::create([
                'kode'               => $kode ?: CodeGenerator::generatePr(),
                'unit_id'            => $unitId,
                'gudang_id'          => $gudangId,
                'diajukan_oleh'      => $user->id,
                'disetujui_oleh'     => $isLeader ? $user->id : null,
                'tanggal_permintaan' => now()->toDateString(),
                'urgensi'            => $urgensi,
                'status'             => $isLeader
                    ? StatusPermintaanPengadaan::DiteruskanKePengadaan->value
                    : StatusPermintaanPengadaan::Diajukan->value,
                'catatan'            => $catatan ?: null,
            ]);

            foreach ($items as $item) {
                PermintaanPengadaanDetail::create([
                    'permintaan_pengadaan_id' => $pr->id,
                    'barang_id'               => $item['barang_id'],
                    'jumlah'                  => $item['jumlah'],
                    'jumlah_disetujui'        => $isLeader ? $item['jumlah'] : null,
                    'catatan'                 => $item['catatan'] ?? null,
                ]);
            }

            return $pr;
        });
    }
}
