<?php

namespace App\Services\Dashboard;

use App\Enums\StatusPeminjaman;
use App\Enums\StatusPermintaanDistribusi;
use App\Enums\StatusPermintaanPengadaan;
use App\Models\PermintaanDistribusi;
use App\Models\PermintaanMaintenance;
use App\Models\PermintaanPeminjaman;
use App\Models\PermintaanPengadaan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Collection;

class RuanganDashboardBuilder
{
    use HasDashboardFormatting;

    /**
     * KASUS 4: Kepala Ruangan (Karu).
     */
    public function buildKaru(User $user): array
    {
        $unitIds = Unit::where('kepala_unit_id', $user->id)->pluck('id');
        $filterUnit = $unitIds->isNotEmpty() ? $unitIds->toArray() : ($user->unit_id ? [$user->unit_id] : []);

        $prMenunggu = PermintaanPengadaan::whereIn('unit_id', $filterUnit)
            ->where('status', StatusPermintaanPengadaan::Diajukan->value)
            ->count();
        $amprahMenunggu = PermintaanDistribusi::whereIn('unit_id', $filterUnit)
            ->where('status', StatusPermintaanDistribusi::Diajukan->value)
            ->count();
        $pinjamMenunggu = PermintaanPeminjaman::where(fn ($q) => $q->whereIn('unit_id', $filterUnit)->orWhereIn('unit_penyedia_id', $filterUnit))
            ->where('status', StatusPeminjaman::Diajukan->value)
            ->count();

        $totalApproval = $prMenunggu + $amprahMenunggu + $pinjamMenunggu;

        $hero = [
            'judul'        => 'Antrean Persetujuan Ruangan',
            'total'        => $totalApproval,
            'satuan'       => 'Dokumen',
            'label_tombol' => 'Setujui',
            'target_aksi'  => 'approval',
            'sub_metrik'   => [
                ['label' => 'PR Ruangan', 'nilai' => (string) $prMenunggu, 'warna' => 'warning'],
                ['label' => 'Amprah Ruangan', 'nilai' => (string) $amprahMenunggu, 'warna' => 'blue'],
                ['label' => 'Pinjam Aset', 'nilai' => (string) $pinjamMenunggu, 'warna' => 'purple'],
            ],
        ];

        $prList = $this->karuPrList($filterUnit);
        $amprahList = $this->karuAmprahList($filterUnit);
        $pinjamList = $this->karuPinjamList($filterUnit);
        $tiketList = $this->karuTiketList($filterUnit);

        $activities = $prList->concat($amprahList)
            ->concat($pinjamList)
            ->concat($tiketList)
            ->sortByDesc('timestamp')
            ->values()
            ->take(30)
            ->toArray();

        return [
            'hero'      => $hero,
            'aktivitas' => $activities,
        ];
    }

    /**
     * KASUS 5: Petugas / Staf Ruangan (Perawat / Pemohon).
     */
    public function buildPetugasUnit(User $user): array
    {
        $amprahAktif = PermintaanDistribusi::where('unit_id', $user->unit_id)
            ->where('status', StatusPermintaanDistribusi::Diajukan->value)
            ->count();
        $prAktif = PermintaanPengadaan::where('unit_id', $user->unit_id)
            ->where('status', StatusPermintaanPengadaan::Diajukan->value)
            ->count();
        $pinjamAktif = PermintaanPeminjaman::where('unit_id', $user->unit_id)
            ->where('status', StatusPeminjaman::Diajukan->value)
            ->count();
        $laporRusak = PermintaanMaintenance::where('unit_id', $user->unit_id)
            ->whereIn('status', ['DILAPORKAN', 'MENUNGGU_SPK'])
            ->count();

        $totalPengajuan = $amprahAktif + $prAktif + $pinjamAktif + $laporRusak;

        $hero = [
            'judul'        => 'Status Pengajuan Unit Aktif',
            'total'        => $totalPengajuan,
            'satuan'       => 'Pengajuan',
            'label_tombol' => 'Pantau',
            'target_aksi'  => 'amprah_hub',
            'sub_metrik'   => [
                ['label' => 'Amprah Diajukan', 'nilai' => (string) $amprahAktif, 'warna' => 'blue'],
                ['label' => 'PR Diajukan', 'nilai' => (string) $prAktif, 'warna' => 'purple'],
                ['label' => 'Pinjam & Servis', 'nilai' => (string) ($pinjamAktif + $laporRusak), 'warna' => 'warning'],
            ],
        ];

        $amprahList = $this->unitAmprahList($user);
        $prList = $this->unitPrList($user);
        $pinjamList = $this->unitPinjamList($user);
        $tiketList = $this->unitTiketList($user);

        $activities = $amprahList->concat($prList)
            ->concat($pinjamList)
            ->concat($tiketList)
            ->sortByDesc('timestamp')
            ->values()
            ->take(30)
            ->toArray();

        return [
            'hero'      => $hero,
            'aktivitas' => $activities,
        ];
    }

    /** Karu: Permintaan PR status DIAJUKAN. */
    private function karuPrList(array $filterUnit): Collection
    {
        return PermintaanPengadaan::whereIn('unit_id', $filterUnit)
            ->where('status', StatusPermintaanPengadaan::Diajukan->value)
            ->with(['pengaju:id,nama', 'detail'])
            ->latest('id')
            ->take(15)
            ->get()
            ->map(fn ($p) => [
                'id'         => $p->id,
                'tipe'       => 'PR',
                'kode'       => $p->kode,
                'judul'      => 'PR: Pengadaan ' . $p->detail->count() . ' Barang',
                'subjudul'   => 'Pengaju: ' . ($p->pengaju?->nama ?? 'Staf') . ' • Urgensi: ' . $p->urgensi,
                'status'     => $p->status,
                'waktu_lalu' => $this->diffForHumans($p->dibuat_pada),
                'timestamp'  => $p->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
            ]);
    }

    /** Karu: Amprah Ruangan status DIAJUKAN. */
    private function karuAmprahList(array $filterUnit): Collection
    {
        return PermintaanDistribusi::whereIn('unit_id', $filterUnit)
            ->where('status', StatusPermintaanDistribusi::Diajukan->value)
            ->with(['pengaju:id,nama', 'detail'])
            ->latest('id')
            ->take(15)
            ->get()
            ->map(fn ($a) => [
                'id'         => $a->id,
                'tipe'       => 'AMPRAH',
                'kode'       => $a->kode,
                'judul'      => 'Amprah: ' . $a->detail->count() . ' Item Komoditas',
                'subjudul'   => 'Pengaju: ' . ($a->pengaju?->nama ?? 'Staf') . ' • ' . $a->urgensi,
                'status'     => $a->status,
                'waktu_lalu' => $this->diffForHumans($a->dibuat_pada),
                'timestamp'  => $a->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
            ]);
    }

    /** Karu: Sirkulasi Aset (Peminjaman) status DIAJUKAN. */
    private function karuPinjamList(array $filterUnit): Collection
    {
        return PermintaanPeminjaman::where(fn ($q) => $q->whereIn('unit_id', $filterUnit)->orWhereIn('unit_penyedia_id', $filterUnit))
            ->where('status', StatusPeminjaman::Diajukan->value)
            ->with(['unitPeminjam:id,nama', 'pengaju:id,nama', 'detail.aset.barang:id,nama'])
            ->latest('id')
            ->take(15)
            ->get()
            ->map(fn ($pj) => [
                'id'         => $pj->id,
                'tipe'       => 'PINJAM',
                'kode'       => $pj->kode,
                'judul'      => 'Otorisasi Pinjam: ' . ($pj->detail->first()?->aset?->barang?->nama ?? 'Aset Fisik'),
                'subjudul'   => 'Pengaju: ' . ($pj->pengaju?->nama ?? 'Staf') . ' • Unit: ' . ($pj->unitPeminjam?->nama ?? '-'),
                'status'     => $pj->status,
                'waktu_lalu' => $this->diffForHumans($pj->dibuat_pada),
                'timestamp'  => $pj->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
            ]);
    }

    /** Karu: Lapor Kerusakan Unit status DILAPORKAN / MENUNGGU_SPK. */
    private function karuTiketList(array $filterUnit): Collection
    {
        return PermintaanMaintenance::whereIn('unit_id', $filterUnit)
            ->whereIn('status', ['DILAPORKAN', 'MENUNGGU_SPK'])
            ->with(['aset.barang:id,nama', 'pelapor:id,nama'])
            ->latest('id')
            ->take(15)
            ->get()
            ->map(fn ($t) => [
                'id'         => $t->id,
                'tipe'       => 'TIKET',
                'kode'       => $t->kode,
                'judul'      => 'Lapor Rusak: ' . ($t->aset?->barang?->nama ?? 'Sarana Medis'),
                'subjudul'   => 'Pelapor: ' . ($t->pelapor?->nama ?? 'Staf') . ' • ' . $t->deskripsi_masalah,
                'status'     => $t->status,
                'waktu_lalu' => $this->diffForHumans($t->dibuat_pada),
                'timestamp'  => $t->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
            ]);
    }

    /** Petugas Unit: Amprah Ruangan status DIAJUKAN. */
    private function unitAmprahList(User $user): Collection
    {
        return PermintaanDistribusi::where('unit_id', $user->unit_id)
            ->where('status', StatusPermintaanDistribusi::Diajukan->value)
            ->with(['detail'])
            ->latest('id')
            ->take(15)
            ->get()
            ->map(fn ($a) => [
                'id'         => $a->id,
                'tipe'       => 'AMPRAH',
                'kode'       => $a->kode,
                'judul'      => 'Amprah: ' . $a->detail->count() . ' Item Komoditas',
                'subjudul'   => 'Status: Menunggu Persetujuan Karu • Urgensi: ' . $a->urgensi,
                'status'     => $a->status,
                'waktu_lalu' => $this->diffForHumans($a->dibuat_pada),
                'timestamp'  => $a->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
            ]);
    }

    /** Petugas Unit: Permintaan PR status DIAJUKAN. */
    private function unitPrList(User $user): Collection
    {
        return PermintaanPengadaan::where('unit_id', $user->unit_id)
            ->where('status', StatusPermintaanPengadaan::Diajukan->value)
            ->with(['detail'])
            ->latest('id')
            ->take(15)
            ->get()
            ->map(fn ($p) => [
                'id'         => $p->id,
                'tipe'       => 'PR',
                'kode'       => $p->kode,
                'judul'      => 'PR: Pengadaan ' . $p->detail->count() . ' Barang',
                'subjudul'   => 'Status: Menunggu Persetujuan Karu • Urgensi: ' . $p->urgensi,
                'status'     => $p->status,
                'waktu_lalu' => $this->diffForHumans($p->dibuat_pada),
                'timestamp'  => $p->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
            ]);
    }

    /** Petugas Unit: Sirkulasi Aset (Peminjaman) status DIAJUKAN. */
    private function unitPinjamList(User $user): Collection
    {
        return PermintaanPeminjaman::where('unit_id', $user->unit_id)
            ->where('status', StatusPeminjaman::Diajukan->value)
            ->with(['detail.aset.barang:id,nama'])
            ->latest('id')
            ->take(15)
            ->get()
            ->map(fn ($pj) => [
                'id'         => $pj->id,
                'tipe'       => 'PINJAM',
                'kode'       => $pj->kode,
                'judul'      => 'Pinjam Aset: ' . ($pj->detail->first()?->aset?->barang?->nama ?? 'Aset Fisik'),
                'subjudul'   => 'Status: Menunggu Otorisasi Karu • Keperluan: ' . ($pj->keperluan ?? '-'),
                'status'     => $pj->status,
                'waktu_lalu' => $this->diffForHumans($pj->dibuat_pada),
                'timestamp'  => $pj->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
            ]);
    }

    /** Petugas Unit: Lapor Rusak Unit status DILAPORKAN / MENUNGGU_SPK. */
    private function unitTiketList(User $user): Collection
    {
        return PermintaanMaintenance::where('unit_id', $user->unit_id)
            ->whereIn('status', ['DILAPORKAN', 'MENUNGGU_SPK'])
            ->with(['aset.barang:id,nama'])
            ->latest('id')
            ->take(15)
            ->get()
            ->map(fn ($t) => [
                'id'         => $t->id,
                'tipe'       => 'TIKET',
                'kode'       => $t->kode,
                'judul'      => 'Lapor Rusak: ' . ($t->aset?->barang?->nama ?? 'Sarana Medis'),
                'subjudul'   => 'Status: Menunggu Disposisi SPK • ' . $t->deskripsi_masalah,
                'status'     => $t->status,
                'waktu_lalu' => $this->diffForHumans($t->dibuat_pada),
                'timestamp'  => $t->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
            ]);
    }
}
