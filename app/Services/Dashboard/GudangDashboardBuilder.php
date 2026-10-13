<?php

namespace App\Services\Dashboard;

use App\Enums\Aset\StatusPeminjaman;
use App\Enums\Distribusi\StatusPermintaanDistribusi;
use App\Models\Master\Gudang;
use App\Models\Persediaan\PenyesuaianStok;
use App\Models\Distribusi\PermintaanDistribusi;
use App\Models\Aset\PermintaanPeminjaman;
use App\Models\Pengadaan\PesananPembelian;
use App\Models\User;
use Illuminate\Support\Collection;

class GudangDashboardBuilder
{
    use HasDashboardFormatting;

    /**
     * KASUS 2: Petugas & Staf Gudang.
     */
    public function buildPetugasGudang(User $user): array
    {
        $siapPicking = PermintaanDistribusi::whereIn('status', [StatusPermintaanDistribusi::DisetujuiGudang->value, StatusPermintaanDistribusi::DisetujuiKaru->value])
            ->when($user->gudang_id, fn ($q) => $q->where('gudang_id', $user->gudang_id))
            ->count();
        $poInbound = PesananPembelian::whereIn('status', ['DIKIRIM_VENDOR', 'DITERIMA_SEBAGIAN'])->count();
        $poDiterbitkan = PesananPembelian::where('status', 'DITERBITKAN')->count();

        $totalTugasFisik = $siapPicking + $poInbound;

        $hero = [
            'judul'        => 'Antrean Tugas Fisik Gudang',
            'total'        => $totalTugasFisik,
            'satuan'       => 'Tugas',
            'label_tombol' => 'Buka Picking',
            'target_aksi'  => 'picking',
            'sub_metrik'   => [
                ['label' => 'Siap Picking', 'nilai' => (string) $siapPicking, 'warna' => 'purple'],
                ['label' => 'Inbound Vendor', 'nilai' => (string) $poInbound, 'warna' => 'blue'],
                ['label' => 'PO Diterbitkan', 'nilai' => (string) $poDiterbitkan, 'warna' => 'warning'],
            ],
        ];

        $amprahList = $this->pickingAmprahList($user);
        $poList = $this->poInboundList(15, 'item komoditas');
        $pinjamGudang = $this->pinjamGudangPetugasList($user);

        $activities = $amprahList->concat($poList)->concat($pinjamGudang)->sortByDesc('timestamp')->values()->take(30)->toArray();

        return [
            'hero'      => $hero,
            'aktivitas' => $activities,
        ];
    }

    /**
     * KASUS 3: Kepala Gudang & Admin Logistik.
     */
    public function buildKepalaGudang(User $user): array
    {
        $gudangIds = Gudang::where('kepala_gudang_id', $user->id)->pluck('id');
        $filterGudang = $gudangIds->isNotEmpty() ? $gudangIds->toArray() : ($user->gudang_id ? [$user->gudang_id] : []);

        $amprahOtorisasi = PermintaanDistribusi::where('status', StatusPermintaanDistribusi::DisetujuiKaru->value)
            ->when($filterGudang, fn ($q) => $q->whereIn('gudang_id', $filterGudang))
            ->count();
        $poBerjalan = PesananPembelian::whereIn('status', ['DIKIRIM_VENDOR', 'DITERIMA_SEBAGIAN'])->count();
        $penyesuaianMenunggu = PenyesuaianStok::where('status', 'MENUNGGU')->count();

        $totalOtorisasi = $amprahOtorisasi + $penyesuaianMenunggu + $poBerjalan;

        $hero = [
            'judul'        => 'Otorisasi & Sirkulasi Sentral',
            'total'        => $totalOtorisasi,
            'satuan'       => 'Antrean',
            'label_tombol' => 'Otorisasi',
            'target_aksi'  => 'approval',
            'sub_metrik'   => [
                ['label' => 'Verifikasi Gudang', 'nilai' => (string) $amprahOtorisasi, 'warna' => 'warning'],
                ['label' => 'Otorisasi Stok', 'nilai' => (string) $penyesuaianMenunggu, 'warna' => 'purple'],
                ['label' => 'Inbound Vendor', 'nilai' => (string) $poBerjalan, 'warna' => 'blue'],
            ],
        ];

        $amprahList = $this->otorisasiAmprahList($filterGudang);
        $penyesuaianList = $this->penyesuaianMenungguList();
        $poList = $this->poInboundList(10, null);
        $pinjamGudang = $this->pinjamGudangKepalaList($filterGudang);

        $activities = $amprahList->concat($penyesuaianList)->concat($poList)->concat($pinjamGudang)->sortByDesc('timestamp')->values()->take(30)->toArray();

        return [
            'hero'      => $hero,
            'aktivitas' => $activities,
        ];
    }

    private function pickingAmprahList(User $user): Collection
    {
        return PermintaanDistribusi::whereIn('status', [StatusPermintaanDistribusi::DisetujuiGudang->value, StatusPermintaanDistribusi::DisetujuiKaru->value])
            ->when($user->gudang_id, fn ($q) => $q->where('gudang_id', $user->gudang_id))
            ->with(['unit:id,kode,nama', 'detail'])
            ->latest('id')
            ->take(15)
            ->get()
            ->map(fn ($a) => [
                'id'         => $a->id,
                'tipe'       => 'AMPRAH',
                'kode'       => $a->kode,
                'judul'      => 'Picking: ' . ($a->unit?->nama ?? 'Amprah Ruangan'),
                'subjudul'   => $a->detail->count() . ' item barang • Urgensi: ' . $a->urgensi,
                'status'     => $a->status,
                'waktu_lalu' => $this->diffForHumans($a->dibuat_pada),
                'timestamp'  => $a->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
            ]);
    }

    /** PO inbound; $subjudulSuffix null = tampilkan status PO. */
    private function poInboundList(int $limit, ?string $subjudulSuffix): Collection
    {
        return PesananPembelian::whereIn('status', ['DIKIRIM_VENDOR', 'DITERIMA_SEBAGIAN'])
            ->with(['pemasok:id,nama', 'detail'])
            ->latest('id')
            ->take($limit)
            ->get()
            ->map(fn ($p) => [
                'id'         => $p->id,
                'tipe'       => 'PO',
                'kode'       => $p->kode,
                'judul'      => 'PO Inbound: ' . ($p->pemasok?->nama ?? 'Vendor'),
                'subjudul'   => $p->detail->count() . ' ' . ($subjudulSuffix ?? 'item • ' . $p->status),
                'status'     => $p->status,
                'waktu_lalu' => $this->diffForHumans($p->dibuat_pada),
                'timestamp'  => $p->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
            ]);
    }

    private function pinjamGudangPetugasList(User $user): Collection
    {
        return PermintaanPeminjaman::where('tipe_penyedia', 'GUDANG')
            ->where('status', StatusPeminjaman::Diajukan->value)
            ->when($user->gudang_id, fn ($q) => $q->where('gudang_penyedia_id', $user->gudang_id))
            ->with(['unitPeminjam', 'pengaju', 'detail.aset.barang'])
            ->latest('id')
            ->take(10)
            ->get()
            ->map(fn ($pj) => [
                'id'         => $pj->id,
                'tipe'       => 'PINJAM',
                'kode'       => $pj->kode,
                'judul'      => 'Pinjam Gudang: ' . ($pj->detail->first()?->aset?->barang?->nama ?? 'Aset Fisik'),
                'subjudul'   => 'Pemohon: ' . ($pj->pengaju?->nama ?? 'Staf') . ' • Unit: ' . ($pj->unitPeminjam?->nama ?? '-'),
                'status'     => $pj->status,
                'waktu_lalu' => $this->diffForHumans($pj->dibuat_pada),
                'timestamp'  => $pj->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
            ]);
    }

    private function otorisasiAmprahList(array $filterGudang): Collection
    {
        return PermintaanDistribusi::where('status', StatusPermintaanDistribusi::DisetujuiKaru->value)
            ->when($filterGudang, fn ($q) => $q->whereIn('gudang_id', $filterGudang))
            ->with(['unit:id,kode,nama', 'detail'])
            ->latest('id')
            ->take(15)
            ->get()
            ->map(fn ($a) => [
                'id'         => $a->id,
                'tipe'       => 'AMPRAH',
                'kode'       => $a->kode,
                'judul'      => 'Otorisasi Amprah: ' . ($a->unit?->nama ?? 'Unit Ruangan'),
                'subjudul'   => $a->detail->count() . ' item komoditas • ' . $a->urgensi,
                'status'     => $a->status,
                'waktu_lalu' => $this->diffForHumans($a->dibuat_pada),
                'timestamp'  => $a->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
            ]);
    }

    private function penyesuaianMenungguList(): Collection
    {
        return PenyesuaianStok::where('status', 'MENUNGGU')
            ->with(['gudang', 'detail'])
            ->latest('id')
            ->take(10)
            ->get()
            ->map(fn ($ps) => [
                'id'         => $ps->id,
                'tipe'       => 'PENYESUAIAN',
                'kode'       => $ps->kode,
                'judul'      => 'Penyesuaian Stok: ' . ($ps->gudang?->nama ?? 'Gudang'),
                'subjudul'   => $ps->detail->count() . ' batch selisih • ' . $ps->alasan,
                'status'     => $ps->status,
                'waktu_lalu' => $this->diffForHumans($ps->dibuat_pada),
                'timestamp'  => $ps->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
            ]);
    }

    private function pinjamGudangKepalaList(array $filterGudang): Collection
    {
        return PermintaanPeminjaman::where('tipe_penyedia', 'GUDANG')
            ->where('status', StatusPeminjaman::Diajukan->value)
            ->when($filterGudang, fn ($q) => $q->whereIn('gudang_penyedia_id', $filterGudang))
            ->with(['unitPeminjam', 'pengaju', 'detail.aset.barang'])
            ->latest('id')
            ->take(10)
            ->get()
            ->map(fn ($pj) => [
                'id'         => $pj->id,
                'tipe'       => 'PINJAM',
                'kode'       => $pj->kode,
                'judul'      => 'Otorisasi Pinjam: ' . ($pj->detail->first()?->aset?->barang?->nama ?? 'Aset Gudang'),
                'subjudul'   => 'Pemohon: ' . ($pj->pengaju?->nama ?? 'Staf') . ' • Unit: ' . ($pj->unitPeminjam?->nama ?? '-'),
                'status'     => $pj->status,
                'waktu_lalu' => $this->diffForHumans($pj->dibuat_pada),
                'timestamp'  => $pj->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
            ]);
    }
}
