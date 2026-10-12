<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Enums\StatusPeminjaman;
use App\Enums\StatusPermintaanDistribusi;
use App\Enums\StatusPermintaanPengadaan;
use App\Enums\StatusSpk;
use App\Models\PenugasanMaintenance;
use App\Models\JadwalMaintenance;
use App\Models\PenyesuaianStok;
use App\Models\PermintaanDistribusi;
use App\Models\PermintaanMaintenance;
use App\Models\PermintaanPeminjaman;
use App\Models\PermintaanPengadaan;
use App\Models\PesananPembelian;
use App\Models\Aset;
use App\Models\Gudang;
use App\Models\Unit;
use App\Models\StokOpname;
use App\Models\TemuanAudit;
use Carbon\Carbon;
use App\Services\Anggaran\AnggaranRealtimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardApiController extends ApiController
{
    /**
     * Endpoint terpadu ringkasan metrik hero & aktivitas terkini berbasis peran pengguna.
     * Hanya menampilkan transaksi berstatus antrean aktif (DIAJUKAN, DILAPORKAN, DITUGASKAN, MENUNGGU, dsb).
     * GET /api/v1/dashboard/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->jsonError('Unauthenticated.', 401);
        }

        $roles = $user->getRoleNames()->toArray();
        $isSuperAdmin = in_array('super_admin', $roles);
        $isKepalaTeknisi = in_array('kepala_teknisi', $roles) || $user->hasRole('kepala_teknisi');
        $isTeknisi = ! $isSuperAdmin && ! $isKepalaTeknisi && (in_array('teknisi', $roles) || $user->can('page_TeknisiDashboard'));
        $isPetugasGudang = in_array('petugas_gudang', $roles);
        $isKepalaGudang = $isSuperAdmin || in_array('kepala_gudang', $roles) || in_array('admin_logistik', $roles);
        $isKaru = (in_array('kepala_unit', $roles) || $user->can('page_KepalaUnitDashboard')) && ! $isKepalaTeknisi;
        $isAuditor = in_array('auditor', $roles) || $user->can('page_AuditorDashboard');
        $isManajemen = in_array('manajemen', $roles) || $user->can('page_ManajemenDashboard');
        $isPetugasUnit = (in_array('petugas_unit', $roles) || (! empty($user->unit_id) && ! $isKaru)) && ! $isAuditor && ! $isManajemen && ! $isKepalaTeknisi;

        $hero = [];
        $activities = [];

        // -------------------------------------------------------------
        // KASUS 0: KEPALA / KOORDINATOR TEKNISI IPSRS
        // -------------------------------------------------------------
        if ($isKepalaTeknisi) {
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
        }

        // -------------------------------------------------------------
        // KASUS 1: TEKNISI IPSRS LAPANGAN
        // -------------------------------------------------------------
        elseif ($isTeknisi) {
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

            // Aktivitas Teknisi: Hanya SPK aktif (DITUGASKAN, DITERIMA) & Tiket Masuk (DILAPORKAN, MENUNGGU_SPK)
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
        }

        // -------------------------------------------------------------
        // KASUS 2: PETUGAS & STAF GUDANG
        // -------------------------------------------------------------
        elseif ($isPetugasGudang) {
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

            // Aktivitas Gudang: Siap Picking (DISETUJUI_KARU/GUDANG), Inbound Vendor (DIKIRIM_VENDOR), Pinjam Gudang (DIAJUKAN)
            $amprahList = PermintaanDistribusi::whereIn('status', [StatusPermintaanDistribusi::DisetujuiGudang->value, StatusPermintaanDistribusi::DisetujuiKaru->value])
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

            $poList = PesananPembelian::whereIn('status', ['DIKIRIM_VENDOR', 'DITERIMA_SEBAGIAN'])
                ->with(['pemasok:id,nama', 'detail'])
                ->latest('id')
                ->take(15)
                ->get()
                ->map(fn ($p) => [
                    'id'         => $p->id,
                    'tipe'       => 'PO',
                    'kode'       => $p->kode,
                    'judul'      => 'PO Inbound: ' . ($p->pemasok?->nama ?? 'Vendor'),
                    'subjudul'   => $p->detail->count() . ' item komoditas',
                    'status'     => $p->status,
                    'waktu_lalu' => $this->diffForHumans($p->dibuat_pada),
                    'timestamp'  => $p->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
                ]);

            $pinjamGudang = PermintaanPeminjaman::where('tipe_penyedia', 'GUDANG')
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

            $activities = $amprahList->concat($poList)->concat($pinjamGudang)->sortByDesc('timestamp')->values()->take(30)->toArray();
        }

        // -------------------------------------------------------------
        // KASUS 3: KEPALA GUDANG & ADMIN LOGISTIK
        // -------------------------------------------------------------
        elseif ($isKepalaGudang) {
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

            // Aktivitas Kepala Gudang: Amprah Meja 2 (DISETUJUI_KARU), Penyesuaian (MENUNGGU), PO Inbound (DIKIRIM_VENDOR), Pinjam Gudang (DIAJUKAN)
            $amprahList = PermintaanDistribusi::where('status', StatusPermintaanDistribusi::DisetujuiKaru->value)
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

            $penyesuaianList = PenyesuaianStok::where('status', 'MENUNGGU')
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

            $poList = PesananPembelian::whereIn('status', ['DIKIRIM_VENDOR', 'DITERIMA_SEBAGIAN'])
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

            $pinjamGudang = PermintaanPeminjaman::where('tipe_penyedia', 'GUDANG')
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

            $activities = $amprahList->concat($penyesuaianList)->concat($poList)->concat($pinjamGudang)->sortByDesc('timestamp')->values()->take(30)->toArray();
        }

        // -------------------------------------------------------------
        // KASUS 4: KEPALA RUANGAN (KARU)
        // -------------------------------------------------------------
        elseif ($isKaru) {
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

            // Aktivitas Karu: Seluruh data aktivitas pengguna berstatus DIAJUKAN / DILAPORKAN
            // 1. Permintaan PR: status DIAJUKAN
            $prList = PermintaanPengadaan::whereIn('unit_id', $filterUnit)
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

            // 2. Amprah Ruangan: status DIAJUKAN
            $amprahList = PermintaanDistribusi::whereIn('unit_id', $filterUnit)
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

            // 3. Sirkulasi Aset (Peminjaman): status DIAJUKAN
            $pinjamList = PermintaanPeminjaman::where(fn ($q) => $q->whereIn('unit_id', $filterUnit)->orWhereIn('unit_penyedia_id', $filterUnit))
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

            // 4. Lapor Kerusakan Unit: status DILAPORKAN / MENUNGGU_SPK
            $tiketList = PermintaanMaintenance::whereIn('unit_id', $filterUnit)
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

            $activities = $prList->concat($amprahList)
                ->concat($pinjamList)
                ->concat($tiketList)
                ->sortByDesc('timestamp')
                ->values()
                ->take(30)
                ->toArray();
        }

        // -------------------------------------------------------------
        // KASUS 5: PETUGAS / STAF RUANGAN (PERAWAT / PEMOHON)
        // -------------------------------------------------------------
        elseif ($isPetugasUnit) {
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

            // Aktivitas Petugas Ruangan: Hanya yang berstatus DIAJUKAN / DILAPORKAN
            // 1. Amprah Ruangan: status DIAJUKAN
            $amprahList = PermintaanDistribusi::where('unit_id', $user->unit_id)
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

            // 2. Permintaan PR: status DIAJUKAN
            $prList = PermintaanPengadaan::where('unit_id', $user->unit_id)
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

            // 3. Sirkulasi Aset (Peminjaman): status DIAJUKAN
            $pinjamList = PermintaanPeminjaman::where('unit_id', $user->unit_id)
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

            // 4. Lapor Rusak Unit: status DILAPORKAN / MENUNGGU_SPK
            $tiketList = PermintaanMaintenance::where('unit_id', $user->unit_id)
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

            $activities = $amprahList->concat($prList)
                ->concat($pinjamList)
                ->concat($tiketList)
                ->sortByDesc('timestamp')
                ->values()
                ->take(30)
                ->toArray();
        }

        // -------------------------------------------------------------
        // KASUS 6: AUDITOR INTERNAL (SPI)
        // -------------------------------------------------------------
        elseif ($isAuditor) {
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
        }

        // -------------------------------------------------------------
        // KASUS 7: MANAJEMEN DIREKSI (EKSEKUTIF)
        // -------------------------------------------------------------
        elseif ($isManajemen) {
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
        }

        // -------------------------------------------------------------
        // KASUS 8: PENGADAAN & SUPER ADMIN (DEFAULT)
        // -------------------------------------------------------------
        else {
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

            $prList = PermintaanPengadaan::where('status', StatusPermintaanPengadaan::Diajukan->value)
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

            $amprahList = PermintaanDistribusi::where('status', StatusPermintaanDistribusi::Diajukan->value)
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

            $poList = PesananPembelian::whereIn('status', ['DIKIRIM_VENDOR', 'DITERIMA_SEBAGIAN'])
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

            $pinjamList = PermintaanPeminjaman::where('status', StatusPeminjaman::Diajukan->value)
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

            $activities = $prList->concat($amprahList)
                ->concat($poList)
                ->concat($pinjamList)
                ->sortByDesc('timestamp')
                ->values()
                ->take(30)
                ->toArray();
        }

        return $this->jsonSuccess([
            'hero'      => $hero,
            'aktivitas' => $activities,
        ], 'Ringkasan dashboard personal berhasil dimuat.');
    }

    /**
     * Format waktu lampau ramah pengguna.
     */
    private function diffForHumans($date): string
    {
        return $date ? Carbon::parse($date)->diffForHumans() : 'Baru saja';
    }
}
