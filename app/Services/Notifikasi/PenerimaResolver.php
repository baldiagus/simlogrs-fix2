<?php

namespace App\Services\Notifikasi;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Menentukan id pengguna AKTIF penerima notifikasi per peran.
 * Aturan visibilitas sama dengan ApprovalController::antreanPr/antreanAmprah:
 * Karu hanya unit_id yang sama, staf gudang hanya gudang_id yang sama.
 */
class PenerimaResolver
{
    /** @return Builder<User> */
    private function aktif(): Builder
    {
        return User::query()->where('status', 'AKTIF');
    }

    /** Kepala unit (Karu) pada unit tertentu. @return list<int> */
    public function karuUnit(int $unitId): array
    {
        return $this->aktif()->role('kepala_unit')->where('unit_id', $unitId)->pluck('id')->all();
    }

    /** Kepala gudang pada gudang tertentu. @return list<int> */
    public function kepalaGudang(int $gudangId): array
    {
        return $this->aktif()->role('kepala_gudang')->where('gudang_id', $gudangId)->pluck('id')->all();
    }

    /** Admin logistik (lintas unit/gudang). @return list<int> */
    public function adminLogistik(): array
    {
        return $this->role('admin_logistik');
    }

    /** Pengguna dengan satu peran, tanpa batas unit/gudang. @return list<int> */
    public function role(string $peran): array
    {
        return $this->aktif()->role($peran)->pluck('id')->all();
    }

    /** Kepala + petugas gudang pada gudang tertentu. @return list<int> */
    public function gudangStaf(int $gudangId): array
    {
        return $this->aktif()->role(['kepala_gudang', 'petugas_gudang'])->where('gudang_id', $gudangId)->pluck('id')->all();
    }

    /** Kepala + petugas gudang di semua gudang (dokumen yang belum terikat gudang tertentu). @return list<int> */
    public function semuaGudangStaf(): array
    {
        return $this->aktif()->role(['kepala_gudang', 'petugas_gudang'])->pluck('id')->all();
    }

    /** Kepala teknisi IPSRS; bila $unitId diisi, hanya yang berada di unit tersebut. @return list<int> */
    public function kepalaTeknisi(?int $unitId = null): array
    {
        if ($unitId === null) {
            return $this->role('kepala_teknisi');
        }

        return $this->aktif()->role('kepala_teknisi')->where('unit_id', $unitId)->pluck('id')->all();
    }

    /** Petugas unit pada unit tertentu. @return list<int> */
    public function petugasUnit(int $unitId): array
    {
        return $this->aktif()->role('petugas_unit')->where('unit_id', $unitId)->pluck('id')->all();
    }

    /**
     * Penerima pengalihan masuk: kepala gudang bila tujuan GUDANG; bila RUANGAN kepala unit,
     * kepala teknisi, dan petugas unit di unit tujuan. Hasil unik. @return list<int>
     */
    public function tujuanPengalihan(?string $tipeTujuan, ?int $unitId, ?int $gudangId): array
    {
        if ($tipeTujuan === 'GUDANG') {
            return $this->kepalaGudang((int) $gudangId);
        }

        $unitId = (int) $unitId;

        return array_values(array_unique([
            ...$this->karuUnit($unitId),
            ...$this->kepalaTeknisi($unitId),
            ...$this->petugasUnit($unitId),
        ]));
    }

    /** Satu pengguna bila masih AKTIF. @return list<int> */
    public function pengguna(?int $id): array
    {
        return $id ? $this->aktif()->whereKey($id)->pluck('id')->all() : [];
    }
}
