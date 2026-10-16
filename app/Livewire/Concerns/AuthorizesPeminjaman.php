<?php

namespace App\Livewire\Concerns;

use App\Models\Aset\PermintaanPeminjaman;
use App\Models\User;

/**
 * Aturan otorisasi aksi peminjaman aset sesuai ADR 0001.
 * - super_admin/admin_logistik: bypass operasional.
 * - approve/tolak: permission update approval + role kepala fasilitas penyedia + scope penyedia.
 * - serah terima fisik: permission update (approval atau peminjaman aset) + scope penyedia.
 * Unit/gudang kosong selalu ditolak (fail-closed).
 */
trait AuthorizesPeminjaman
{
    /**
     * Periksa apakah peminjaman aset berada dalam unit penyedia yang menjadi wewenang pengguna.
     */
    protected function dalamScopePenyedia(?User $user, PermintaanPeminjaman $p): bool
    {
        if (! $user) {
            return false;
        }

        return match ($p->tipe_penyedia) {
            'RUANGAN' => $user->unit_id && (int) $user->unit_id === (int) $p->unit_penyedia_id,
            'GUDANG'  => $user->gudang_id && (int) $user->gudang_id === (int) $p->gudang_penyedia_id,
            default   => false,
        };
    }

    /**
     * Periksa kelayakan izin pengguna untuk melakukan tindakan pada transaksi peminjaman aset.
     */
    protected function bolehPeminjaman(?User $user, PermintaanPeminjaman $p, string $aksi): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'admin_logistik'])) {
            return true;
        }

        if (! $this->dalamScopePenyedia($user, $p)) {
            return false;
        }

        return match ($aksi) {
            'approve'      => $user->can('update_layanan_unit::approval::peminjaman')
                && $user->hasRole($p->tipe_penyedia === 'RUANGAN' ? ['kepala_unit', 'kepala_teknisi'] : 'kepala_gudang'),
            'serah_terima' => $user->canAny(['update_layanan_unit::approval::peminjaman', 'update_layanan_unit::peminjaman::aset']),
            default        => false,
        };
    }

    /**
     * Verifikasi otorisasi aksi peminjaman aset atau hentikan dengan 403 jika tidak berwenang.
     */
    protected function authorizePeminjaman(PermintaanPeminjaman $p, string $aksi, string $pesan): void
    {
        abort_unless($this->bolehPeminjaman(auth()->user(), $p, $aksi), 403, $pesan);
    }

    /** Peta izin per dokumen untuk menu: [id => ['approve'=>bool, 'serah_terima'=>bool]]. */
    protected function izinPeminjaman(iterable $rows): array
    {
        $user = auth()->user();
        $peta = [];
        foreach ($rows as $p) {
            $peta[$p->id] = [
                'approve'      => $this->bolehPeminjaman($user, $p, 'approve'),
                'serah_terima' => $this->bolehPeminjaman($user, $p, 'serah_terima'),
            ];
        }

        return $peta;
    }
}
