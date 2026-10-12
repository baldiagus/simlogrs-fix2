<?php

namespace App\Services\Dashboard;

use App\Enums\StatusPeminjaman;
use App\Enums\StatusPermintaanDistribusi;
use App\Enums\StatusPermintaanPengadaan;
use App\Models\PermintaanDistribusi;
use App\Models\PermintaanPeminjaman;
use App\Models\PermintaanPengadaan;
use App\Models\PesananPembelian;
use App\Models\User;
use Illuminate\Support\Collection;

class PengadaanDashboardBuilder
{
    use HasDashboardFormatting;

    /**
     * KASUS 8: Pengadaan & Super Admin (Default).
     */
    public function buildDefault(User $user): array
    {
        $prMenunggu = PermintaanPengadaan::where('status', StatusPermintaanPengadaan::Diajukan->value)->count();
        $amprahBerjalan = PermintaanDistribusi::where('status', StatusPermintaanDistribusi::Diajukan->value)->count();
        $poVendor = PesananPembelian::whereIn('status', ['DIKIRIM_VENDOR', 'DITERIMA_SEBAGIAN'])->count();

        $totalVerifikasi = $prMenunggu + $poVendor + $amprahBerjalan;

        $hero = [
            'judul'        => 'Antrean Verifikasi & Belanja',
            'total'        => $totalVerifikasi,
            'satuan'       => 'Dokumen',
            'label_tombol' => 'Periksa',
            'target_aksi'  => 'approval',
            'sub_metrik'   => [
                ['label' => 'PR Menunggu', 'nilai' => (string) $prMenunggu, 'warna' => 'warning'],
                ['label' => 'Amprah Masuk', 'nilai' => (string) $amprahBerjalan, 'warna' => 'purple'],
                ['label' => 'Inbound Vendor', 'nilai' => (string) $poVendor, 'warna' => 'blue'],
            ],
        ];

        $prList = $this->prDiajukanList();
        $amprahList = $this->amprahDiajukanList();
        $poList = $this->poInboundList();
        $pinjamList = $this->pinjamDiajukanList();

        $activities = $prList->concat($amprahList)
            ->concat($poList)
            ->concat($pinjamList)
            ->sortByDesc('timestamp')
            ->values()
            ->take(30)
            ->toArray();

        return [
            'hero'      => $hero,
            'aktivitas' => $activities,
        ];
    }

    private function prDiajukanList(): Collection
    {
        return PermintaanPengadaan::where('status', StatusPermintaanPengadaan::Diajukan->value)
            ->with(['unit', 'gudang', 'pengaju', 'detail'])
            ->latest('id')
            ->take(15)
            ->get()
            ->map(fn ($p) => [
                'id'         => $p->id,
                'tipe'       => 'PR',
                'kode'       => $p->kode,
                'judul'      => 'PR: ' . ($p->nama_pemohon ?? 'Permohonan Barang'),
                'subjudul'   => $p->detail->count() . ' item • ' . ($p->pengaju?->nama ?? 'Staf'),
                'status'     => $p->status,
                'waktu_lalu' => $this->diffForHumans($p->dibuat_pada),
                'timestamp'  => $p->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
            ]);
    }

    private function amprahDiajukanList(): Collection
    {
        return PermintaanDistribusi::where('status', StatusPermintaanDistribusi::Diajukan->value)
            ->with(['unit', 'pengaju', 'detail'])
            ->latest('id')
            ->take(15)
            ->get()
            ->map(fn ($a) => [
                'id'         => $a->id,
                'tipe'       => 'AMPRAH',
                'kode'       => $a->kode,
                'judul'      => 'Amprah: ' . ($a->unit?->nama ?? 'Unit Ruangan'),
                'subjudul'   => $a->detail->count() . ' item komoditas',
                'status'     => $a->status,
                'waktu_lalu' => $this->diffForHumans($a->dibuat_pada),
                'timestamp'  => $a->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
            ]);
    }

    private function poInboundList(): Collection
    {
        return PesananPembelian::whereIn('status', ['DIKIRIM_VENDOR', 'DITERIMA_SEBAGIAN'])
            ->with(['pemasok:id,nama', 'detail'])
            ->latest('id')
            ->take(10)
            ->get()
            ->map(fn ($p) => [
                'id'         => $p->id,
                'tipe'       => 'PO',
                'kode'       => $p->kode,
                'judul'      => 'PO Inbound: ' . ($p->pemasok?->nama ?? 'Vendor'),
                'subjudul'   => $p->detail->count() . ' item • ' . $p->status,
                'status'     => $p->status,
                'waktu_lalu' => $this->diffForHumans($p->dibuat_pada),
                'timestamp'  => $p->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
            ]);
    }

    private function pinjamDiajukanList(): Collection
    {
        return PermintaanPeminjaman::where('status', StatusPeminjaman::Diajukan->value)
            ->with(['unitPeminjam', 'pengaju', 'detail.aset.barang'])
            ->latest('id')
            ->take(10)
            ->get()
            ->map(fn ($pj) => [
                'id'         => $pj->id,
                'tipe'       => 'PINJAM',
                'kode'       => $pj->kode,
                'judul'      => 'Pinjam: ' . ($pj->detail->first()?->aset?->barang?->nama ?? 'Aset Fisik'),
                'subjudul'   => 'Pemohon: ' . ($pj->pengaju?->nama ?? 'Staf') . ' • Unit: ' . ($pj->unitPeminjam?->nama ?? '-'),
                'status'     => $pj->status,
                'waktu_lalu' => $this->diffForHumans($pj->dibuat_pada),
                'timestamp'  => $pj->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
            ]);
    }
}
