<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use App\Support\Auth\PengalihanAccess;

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
        return PengalihanAccess::boleh($user, $p, $aksi, $this->entitasPengalihan());
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
