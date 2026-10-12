<?php

namespace App\Support\Auth;

use App\Models\User;

/**
 * Satu-satunya sumber aturan otorisasi pengalihan (aset & barang) untuk web dan API, sesuai ADR 0001.
 * - super_admin/admin_logistik: bypass operasional.
 * - otorisasi: permission update + role kepala fasilitas asal + scope asal.
 * - serah terima: permission update + scope fasilitas tujuan.
 * - batal: permission delete + pengaju dokumen.
 * Unit/gudang kosong selalu ditolak (fail-closed).
 */
class PengalihanAccess
{
    public const ENTITAS_ASET = 'layanan_unit::pengalihan::aset';
    public const ENTITAS_BARANG = 'gudang_stok::pengalihan::barang';

    public static function boleh(?User $user, object $pengalihan, string $aksi, string $entitas): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'admin_logistik'])) {
            return true;
        }

        $dalamScope = fn (string $sisi): bool => $pengalihan->{"tipe_{$sisi}"} === 'RUANGAN'
            ? $user->unit_id && (int) $user->unit_id === (int) $pengalihan->{"unit_{$sisi}_id"}
            : $user->gudang_id && (int) $user->gudang_id === (int) $pengalihan->{"gudang_{$sisi}_id"};

        return match ($aksi) {
            'otorisasi'    => $user->can("update_{$entitas}")
                && $user->hasRole($pengalihan->tipe_asal === 'RUANGAN' ? 'kepala_unit' : 'kepala_gudang')
                && $dalamScope('asal'),
            'serah_terima' => $user->can("update_{$entitas}") && $dalamScope('tujuan'),
            'batal'        => $user->can("delete_{$entitas}") && (int) $pengalihan->diajukan_oleh === (int) $user->id,
            default        => false,
        };
    }
}
