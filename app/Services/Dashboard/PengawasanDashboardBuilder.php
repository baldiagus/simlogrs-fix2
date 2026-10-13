<?php

namespace App\Services\Dashboard;

use App\Models\Ipsrs\PermintaanMaintenance;
use App\Models\Pengadaan\PesananPembelian;
use App\Models\Persediaan\StokOpname;
use App\Models\Audit\TemuanAudit;
use App\Models\Master\Unit;
use App\Models\User;
use App\Services\Anggaran\AnggaranRealtimeService;

class PengawasanDashboardBuilder
{
    use HasDashboardFormatting;

    /**
     * KASUS 6: Auditor Internal (SPI).
     */
    public function buildAuditor(User $user): array
    {
        $today = now()->toDateString();
        $openCapa = TemuanAudit::where('status_capa', '!=', 'Closed')->count();
        $mayorOpen = TemuanAudit::where('tingkat_risiko', 'Mayor')->where('status_capa', '!=', 'Closed')->count();
        $anomaliPo = PesananPembelian::where(function ($q) use ($today) {
            $q->whereNull('permintaan_pengadaan_id')
              ->orWhere(fn ($sub) => $sub->whereNotNull('estimasi_tanggal_kirim')->where('estimasi_tanggal_kirim', '<', $today)->where('status', '!=', 'DITERIMA_LENGKAP'))
              ->orWhere('status', 'DITERIMA_SEBAGIAN');
        })->count();
        $selisihOpnameCount = StokOpname::whereHas('detail', fn ($q) => $q->where('selisih', '!=', 0))->count();

        $totalAuditCases = $openCapa + $anomaliPo + $selisihOpnameCount;

        $hero = [
            'judul'        => 'Forensik Audit & Kepatuhan SPI',
            'total'        => $totalAuditCases,
            'satuan'       => 'Temuan',
            'label_tombol' => 'Buka Audit SPI',
            'target_aksi'  => 'audit_spi',
            'sub_metrik'   => [
                ['label' => 'Open CAPA', 'nilai' => (string) $openCapa, 'warna' => 'danger'],
                ['label' => 'Anomali PO', 'nilai' => (string) $anomaliPo, 'warna' => 'warning'],
                ['label' => 'Selisih Opname', 'nilai' => (string) $selisihOpnameCount, 'warna' => 'purple'],
            ],
        ];

        $temuanList = TemuanAudit::latest('id')->take(10)->get()->map(fn ($t) => [
            'id'         => $t->id,
            'tipe'       => 'AUDIT',
            'kode'       => $t->kode,
            'judul'      => 'Temuan: ' . $t->objek_audit,
            'subjudul'   => 'Risiko ' . $t->tingkat_risiko . ' • Status: ' . $t->status_capa,
            'status'     => $t->status_capa,
            'waktu_lalu' => $this->diffForHumans($t->dibuat_pada),
            'timestamp'  => $t->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
        ]);

        $poAnomaliList = PesananPembelian::whereNull('permintaan_pengadaan_id')
            ->orWhere('status', 'DITERIMA_SEBAGIAN')
            ->latest('id')
            ->take(10)
            ->get()
            ->map(fn ($p) => [
                'id'         => $p->id,
                'tipe'       => 'PO',
                'kode'       => $p->kode,
                'judul'      => 'Uji PO: ' . $p->kode . ' (Anomali)',
                'subjudul'   => 'Vendor: ' . ($p->pemasok?->nama ?? 'Vendor') . ' • Nilai: Rp ' . number_format($p->total_biaya, 0, ',', '.'),
                'status'     => $p->status,
                'waktu_lalu' => $this->diffForHumans($p->dibuat_pada),
                'timestamp'  => $p->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
            ]);

        $activities = $temuanList->concat($poAnomaliList)->sortByDesc('timestamp')->values()->take(30)->toArray();

        return [
            'hero'      => $hero,
            'aktivitas' => $activities,
        ];
    }

    /**
     * KASUS 7: Manajemen Direksi (Eksekutif).
     */
    public function buildManajemen(User $user): array
    {
        $tahun = date('Y');
        $service = app(AnggaranRealtimeService::class);
        $anggaranEksekutif = $service->getRingkasanEksekutif($tahun);

        $totalPengeluaran = (float) $anggaranEksekutif['total_realisasi_global'];
        $totalAmprah = (float) $anggaranEksekutif['total_realisasi_distribusi'];
        $totalSparepart = (float) $anggaranEksekutif['total_realisasi_sparepart'];
        $unitAktifCount = Unit::where('status', 'AKTIF')->count();

        $hero = [
            'judul'        => 'Pengeluaran Logistik & IPSRS',
            'total'        => (int) $totalPengeluaran,
            'satuan'       => 'Rp',
            'label_tombol' => 'Pengeluaran RS',
            'target_aksi'  => 'pagu_realisasi',
            'sub_metrik'   => [
                ['label' => 'Amprah Logistik', 'nilai' => 'Rp ' . number_format($totalAmprah, 0, ',', '.'), 'warna' => 'teal'],
                ['label' => 'Sparepart Servis', 'nilai' => 'Rp ' . number_format($totalSparepart, 0, ',', '.'), 'warna' => 'purple'],
                ['label' => 'Unit Terlayani', 'nilai' => $unitAktifCount . ' Ruangan', 'warna' => 'blue'],
            ],
        ];

        $spkMendesak = PermintaanMaintenance::with(['aset.barang', 'unit'])
            ->whereIn('prioritas', ['CITO', 'TINGGI'])
            ->whereNotIn('status', ['SELESAI', 'DIBATALKAN'])
            ->latest('id')
            ->take(10)
            ->get()
            ->map(fn ($m) => [
                'id'         => $m->id,
                'tipe'       => 'TIKET',
                'kode'       => $m->kode,
                'judul'      => 'SPK Mendesak: ' . ($m->aset?->barang?->nama ?? 'Sarana Medis'),
                'subjudul'   => ($m->unit?->nama ?? 'Unit') . ' • Prioritas ' . $m->prioritas,
                'status'     => $m->status,
                'waktu_lalu' => $this->diffForHumans($m->dibuat_pada),
                'timestamp'  => $m->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
            ]);

        $temuanList = TemuanAudit::latest('id')->take(10)->get()->map(fn ($t) => [
            'id'         => $t->id,
            'tipe'       => 'AUDIT',
            'kode'       => $t->kode,
            'judul'      => 'Temuan CAPA: ' . $t->objek_audit,
            'subjudul'   => 'Risiko ' . $t->tingkat_risiko . ' • Status: ' . $t->status_capa,
            'status'     => $t->status_capa,
            'waktu_lalu' => $this->diffForHumans($t->dibuat_pada),
            'timestamp'  => $t->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
        ]);

        $activities = $spkMendesak->concat($temuanList)->sortByDesc('timestamp')->values()->take(30)->toArray();

        return [
            'hero'      => $hero,
            'aktivitas' => $activities,
        ];
    }
}
