<?php

namespace App\Services\Dashboard;

use App\Enums\StatusSpk;
use App\Models\JadwalMaintenance;
use App\Models\PenugasanMaintenance;
use App\Models\PermintaanMaintenance;
use App\Models\User;

class IpsrsDashboardBuilder
{
    use HasDashboardFormatting;

    /**
     * KASUS 0: Kepala / Koordinator Teknisi IPSRS.
     */
    public function buildKepalaTeknisi(User $user): array
    {
        $tiketMasuk = PermintaanMaintenance::whereIn('status', ['DILAPORKAN', 'MENUNGGU_SPK'])->count();
        $spkAktif = PenugasanMaintenance::whereIn('status', [StatusSpk::Ditugaskan->value, StatusSpk::Diterima->value])->count();
        $preventifJatuhTempo = JadwalMaintenance::where('status', 'AKTIF')
            ->whereDate('tanggal_berikutnya', '<=', now()->toDateString())
            ->count();
        $spkSelesai = PenugasanMaintenance::where('status', StatusSpk::Selesai->value)
            ->whereMonth('diperbarui_pada', now()->month)
            ->count();
        $totalAksi = $tiketMasuk + $spkAktif + $preventifJatuhTempo;

        $hero = [
            'judul'        => 'Pusat Komando & Disposisi IPSRS',
            'total'        => $totalAksi,
            'satuan'       => 'Agenda',
            'label_tombol' => 'Buka Komando',
            'target_aksi'  => 'ipsrs_hub',
            'sub_metrik'   => [
                ['label' => 'Tiket Masuk', 'nilai' => (string) $tiketMasuk, 'warna' => 'danger'],
                ['label' => 'SPK Berjalan', 'nilai' => (string) $spkAktif, 'warna' => 'blue'],
                ['label' => 'Preventif Due', 'nilai' => (string) $preventifJatuhTempo, 'warna' => 'warning'],
                ['label' => 'SPK Selesai', 'nilai' => (string) $spkSelesai, 'warna' => 'purple'],
            ],
        ];

        $tiketList = PermintaanMaintenance::whereIn('status', ['DILAPORKAN', 'MENUNGGU_SPK'])
            ->with(['aset.barang', 'unit', 'pelapor'])
            ->latest('id')
            ->take(10)
            ->get()
            ->map(fn ($t) => [
                'id'         => $t->id,
                'tipe'       => 'TIKET',
                'kode'       => $t->kode,
                'judul'      => 'Tiket: ' . ($t->aset?->barang?->nama ?? 'Sarana Medis'),
                'subjudul'   => ($t->unit?->nama ?? 'Unit') . ' • Prioritas ' . $t->prioritas,
                'status'     => $t->status,
                'waktu_lalu' => $this->diffForHumans($t->dibuat_pada),
                'timestamp'  => $t->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
            ]);

        $spkList = PenugasanMaintenance::whereIn('status', [StatusSpk::Ditugaskan->value, StatusSpk::Diterima->value])
            ->with(['permintaanMaintenance.aset.barang', 'permintaanMaintenance.unit', 'teknisi'])
            ->latest('id')
            ->take(10)
            ->get()
            ->map(fn ($s) => [
                'id'         => $s->id,
                'tipe'       => 'SPK',
                'kode'       => $s->kode,
                'judul'      => 'SPK: ' . ($s->permintaanMaintenance?->aset?->barang?->nama ?? 'Perbaikan'),
                'subjudul'   => 'Teknisi: ' . ($s->teknisi?->nama ?? '-') . ' (' . ($s->permintaanMaintenance?->unit?->nama ?? 'Unit') . ')',
                'status'     => $s->status,
                'waktu_lalu' => $this->diffForHumans($s->dibuat_pada),
                'timestamp'  => $s->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
            ]);

        $activities = $tiketList->concat($spkList)->sortByDesc('timestamp')->values()->take(30)->toArray();

        return [
            'hero'      => $hero,
            'aktivitas' => $activities,
        ];
    }

    /**
     * KASUS 1: Teknisi IPSRS Lapangan.
     */
    public function buildTeknisi(User $user): array
    {
        $tugasBaru = PenugasanMaintenance::where('teknisi_id', $user->id)
            ->where('status', StatusSpk::Ditugaskan->value)
            ->count();
        $dikerjakan = PenugasanMaintenance::where('teknisi_id', $user->id)
            ->where('status', StatusSpk::Diterima->value)
            ->count();
        $tiketMasuk = PermintaanMaintenance::whereIn('status', ['DILAPORKAN', 'MENUNGGU_SPK'])->count();

        $totalSpkAktif = $tugasBaru + $dikerjakan;

        $hero = [
            'judul'        => 'Surat Perintah Kerja (SPK) Aktif',
            'total'        => $totalSpkAktif,
            'satuan'       => 'SPK',
            'label_tombol' => 'Kerjakan SPK',
            'target_aksi'  => 'spk_list',
            'sub_metrik'   => [
                ['label' => 'Tugas Baru', 'nilai' => (string) $tugasBaru, 'warna' => 'warning'],
                ['label' => 'Dikerjakan', 'nilai' => (string) $dikerjakan, 'warna' => 'blue'],
                ['label' => 'Tiket Masuk', 'nilai' => (string) $tiketMasuk, 'warna' => 'danger'],
            ],
        ];

        $spkList = PenugasanMaintenance::where('teknisi_id', $user->id)
            ->whereIn('status', [StatusSpk::Ditugaskan->value, StatusSpk::Diterima->value])
            ->with(['permintaanMaintenance.aset.barang', 'permintaanMaintenance.unit'])
            ->latest('id')
            ->take(15)
            ->get()
            ->map(fn ($s) => [
                'id'         => $s->id,
                'tipe'       => 'SPK',
                'kode'       => $s->kode,
                'judul'      => 'SPK: ' . ($s->permintaanMaintenance?->aset?->barang?->nama ?? 'Perbaikan Sarana'),
                'subjudul'   => ($s->permintaanMaintenance?->unit?->nama ?? 'Unit') . ' • Prioritas ' . ($s->permintaanMaintenance?->prioritas ?? 'SEDANG'),
                'status'     => $s->status,
                'waktu_lalu' => $this->diffForHumans($s->dibuat_pada),
                'timestamp'  => $s->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
            ]);

        $tiketList = PermintaanMaintenance::whereIn('status', ['DILAPORKAN', 'MENUNGGU_SPK'])
            ->with(['aset.barang', 'unit'])
            ->latest('id')
            ->take(15)
            ->get()
            ->map(fn ($t) => [
                'id'         => $t->id,
                'tipe'       => 'TIKET',
                'kode'       => $t->kode,
                'judul'      => 'Laporan: ' . ($t->aset?->barang?->nama ?? 'Kerusakan Sarana'),
                'subjudul'   => ($t->unit?->nama ?? 'Unit') . ' • ' . $t->deskripsi_masalah,
                'status'     => $t->status,
                'waktu_lalu' => $this->diffForHumans($t->dibuat_pada),
                'timestamp'  => $t->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
            ]);

        $activities = $spkList->concat($tiketList)->sortByDesc('timestamp')->values()->take(30)->toArray();

        return [
            'hero'      => $hero,
            'aktivitas' => $activities,
        ];
    }
}
