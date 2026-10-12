<?php

namespace App\Livewire\Concerns;

use App\Models\User;

/**
 * Aturan otorisasi pengalihan (aset & barang) sesuai ADR 0001.
 * - super_admin/admin_logistik: bypass operasional.
 * - otorisasi: permission update + role kepala fasilitas asal + scope asal.
 * - serah terima: permission update + scope fasilitas tujuan.
 * - batal: permission delete + pengaju dokumen.
 * Unit/gudang kosong selalu ditolak (fail-closed).
 */
trait AuthorizesPengalihan
{
    /** Entitas permission modul, mis. 'layanan_unit::pengalihan::aset'. */
    abstract protected function entitasPengalihan(): string;

    protected function bolehPengalihan(?User $user, object $p, string $aksi): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'admin_logistik'])) {
            return true;
        }

        $entitas = $this->entitasPengalihan();
        $dalamScope = fn (string $sisi): bool => $p->{"tipe_{$sisi}"} === 'RUANGAN'
            ? $user->unit_id && (int) $user->unit_id === (int) $p->{"unit_{$sisi}_id"}
            : $user->gudang_id && (int) $user->gudang_id === (int) $p->{"gudang_{$sisi}_id"};

        return match ($aksi) {
            'otorisasi'    => $user->can("update_{$entitas}")
                && $user->hasRole($p->tipe_asal === 'RUANGAN' ? 'kepala_unit' : 'kepala_gudang')
                && $dalamScope('asal'),
            'serah_terima' => $user->can("update_{$entitas}") && $dalamScope('tujuan'),
            'batal'        => $user->can("delete_{$entitas}") && (int) $p->diajukan_oleh === (int) $user->id,
            default        => false,
        };
    }

    protected function authorizePengalihan(object $p, string $aksi, string $pesan): void
    {
        abort_unless($this->bolehPengalihan(auth()->user(), $p, $aksi), 403, $pesan);
    }

    /** Peta izin per baris untuk menu: [id => ['otorisasi'=>bool, 'serah_terima'=>bool, 'batal'=>bool]]. */
    protected function izinPengalihan(iterable $rows): array
    {
        $user = auth()->user();
        $peta = [];
        foreach ($rows as $p) {
            $peta[$p->id] = [
                'otorisasi'    => $this->bolehPengalihan($user, $p, 'otorisasi'),
                'serah_terima' => $this->bolehPengalihan($user, $p, 'serah_terima'),
                'batal'        => $this->bolehPengalihan($user, $p, 'batal'),
            ];
        }

        return $peta;
    }
}
