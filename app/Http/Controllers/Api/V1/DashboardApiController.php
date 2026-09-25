<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\PenugasanMaintenance;
use App\Models\PermintaanDistribusi;
use App\Models\PermintaanMaintenance;
use App\Models\PermintaanPeminjaman;
use App\Models\PermintaanPengadaan;
use App\Models\PesananPembelian;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardApiController extends ApiController
{
    /**
     * Endpoint terpadu ringkasan metrik hero & aktivitas terkini berbasis peran pengguna.
     * GET /api/v1/dashboard/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->jsonError('Unauthenticated.', 401);
        }

        $roles = $user->getRoleNames()->toArray();
        $isTeknisi = in_array('teknisi', $roles) || $user->can('page_TeknisiDashboard');
        $isPetugasGudang = in_array('petugas_gudang', $roles) || in_array('staf_gudang', $roles);
        $isKepalaGudang = in_array('kepala_gudang', $roles) || in_array('admin_logistik', $roles);
        $isKaru = in_array('kepala_unit', $roles) || $user->can('page_KepalaUnitDashboard');
        $isPetugasUnit = in_array('petugas_unit', $roles) || (! empty($user->unit_id) && ! $isKaru);

        $hero = [];
        $activities = [];

        // -------------------------------------------------------------
        // KASUS 1: TEKNISI IPSRS
        // -------------------------------------------------------------
        if ($isTeknisi) {
            $tugasBaru = PenugasanMaintenance::where('teknisi_id', $user->id)
                ->where('status', 'DITUGASKAN')
                ->count();
            $dikerjakan = PenugasanMaintenance::where('teknisi_id', $user->id)
                ->where('status', 'DITERIMA')
                ->count();
            $tiketMasuk = PermintaanMaintenance::where('status', 'DILAPORKAN')->count();

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

            // Aktivitas Teknisi: SPK & Tiket Baru
            $spkList = PenugasanMaintenance::where('teknisi_id', $user->id)
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

            $tiketList = PermintaanMaintenance::where('status', 'DILAPORKAN')
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
            $siapPicking = PermintaanDistribusi::whereIn('status', ['DISETUJUI_GUDANG', 'DISETUJUI_KARU'])
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

            $amprahList = PermintaanDistribusi::whereIn('status', ['DISETUJUI_GUDANG', 'DISETUJUI_KARU'])
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

            $poList = PesananPembelian::whereIn('status', ['DIKIRIM_VENDOR', 'DITERBITKAN', 'DITERIMA_SEBAGIAN'])
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

            $activities = $amprahList->concat($poList)->sortByDesc('timestamp')->values()->take(30)->toArray();
        }

        // -------------------------------------------------------------
        // KASUS 3: KEPALA GUDANG & ADMIN LOGISTIK
        // -------------------------------------------------------------
        elseif ($isKepalaGudang) {
            $amprahOtorisasi = PermintaanDistribusi::where('status', 'DISETUJUI_KARU')->count();
            $poBerjalan = PesananPembelian::whereIn('status', ['DIKIRIM_VENDOR', 'DITERIMA_SEBAGIAN'])->count();
            $siapPicking = PermintaanDistribusi::whereIn('status', ['DISETUJUI_GUDANG'])->count();

            $totalOtorisasi = $amprahOtorisasi + $poBerjalan;

            $hero = [
                'judul'        => 'Otorisasi & Sirkulasi Sentral',
                'total'        => $totalOtorisasi,
                'satuan'       => 'Antrean',
                'label_tombol' => 'Otorisasi',
                'target_aksi'  => 'approval',
                'sub_metrik'   => [
                    ['label' => 'Amprah Meja 2', 'nilai' => (string) $amprahOtorisasi, 'warna' => 'warning'],
                    ['label' => 'Inbound Vendor', 'nilai' => (string) $poBerjalan, 'warna' => 'blue'],
                    ['label' => 'Siap Picking', 'nilai' => (string) $siapPicking, 'warna' => 'purple'],
                ],
            ];

            $amprahList = PermintaanDistribusi::whereIn('status', ['DISETUJUI_KARU', 'DISETUJUI_GUDANG'])
                ->with(['unit:id,kode,nama', 'detail'])
                ->latest('id')
                ->take(15)
                ->get()
                ->map(fn ($a) => [
                    'id'         => $a->id,
                    'tipe'       => 'AMPRAH',
                    'kode'       => $a->kode,
                    'judul'      => 'Amprah: ' . ($a->unit?->nama ?? 'Unit Ruangan'),
                    'subjudul'   => $a->detail->count() . ' item komoditas • ' . $a->urgensi,
                    'status'     => $a->status,
                    'waktu_lalu' => $this->diffForHumans($a->dibuat_pada),
                    'timestamp'  => $a->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
                ]);

            $poList = PesananPembelian::whereIn('status', ['DIKIRIM_VENDOR', 'DITERBITKAN'])
                ->with(['pemasok:id,nama', 'detail'])
                ->latest('id')
                ->take(15)
                ->get()
                ->map(fn ($p) => [
                    'id'         => $p->id,
                    'tipe'       => 'PO',
                    'kode'       => $p->kode,
                    'judul'      => 'PO: ' . ($p->pemasok?->nama ?? 'Vendor'),
                    'subjudul'   => $p->detail->count() . ' item • ' . ($p->status == 'DIKIRIM_VENDOR' ? 'Dalam Pengiriman' : 'Diterbitkan'),
                    'status'     => $p->status,
                    'waktu_lalu' => $this->diffForHumans($p->dibuat_pada),
                    'timestamp'  => $p->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
                ]);

            $activities = $amprahList->concat($poList)->sortByDesc('timestamp')->values()->take(30)->toArray();
        }

        // -------------------------------------------------------------
        // KASUS 4: KEPALA RUANGAN (KARU)
        // -------------------------------------------------------------
        elseif ($isKaru) {
            $prMenunggu = PermintaanPengadaan::where('unit_id', $user->unit_id)
                ->where('status', 'DIAJUKAN')
                ->count();
            $amprahMenunggu = PermintaanDistribusi::where('unit_id', $user->unit_id)
                ->where('status', 'DIAJUKAN')
                ->count();
            $pinjamMenunggu = PermintaanPeminjaman::where('unit_id', $user->unit_id)
                ->where('status', 'DIAJUKAN')
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

            $prList = PermintaanPengadaan::where('unit_id', $user->unit_id)
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

            $amprahList = PermintaanDistribusi::where('unit_id', $user->unit_id)
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

            $activities = $prList->concat($amprahList)->sortByDesc('timestamp')->values()->take(30)->toArray();
        }

        // -------------------------------------------------------------
        // KASUS 5: PETUGAS / STAF RUANGAN (PERAWAT / PEMOHON)
        // -------------------------------------------------------------
        elseif ($isPetugasUnit) {
            $amprahAktif = PermintaanDistribusi::where('unit_id', $user->unit_id)
                ->whereIn('status', ['DIAJUKAN', 'DISETUJUI_KARU', 'DISETUJUI_GUDANG'])
                ->count();
            $prAktif = PermintaanPengadaan::where('unit_id', $user->unit_id)
                ->whereIn('status', ['DIAJUKAN', 'DISETUJUI'])
                ->count();
            $laporRusak = PermintaanMaintenance::where('unit_id', $user->unit_id)
                ->whereIn('status', ['DILAPORKAN', 'DITUGASKAN'])
                ->count();

            $totalPengajuan = $amprahAktif + $prAktif + $laporRusak;

            $hero = [
                'judul'        => 'Status Pengajuan Unit Aktif',
                'total'        => $totalPengajuan,
                'satuan'       => 'Pengajuan',
                'label_tombol' => 'Pantau',
                'target_aksi'  => 'amprah_hub',
                'sub_metrik'   => [
                    ['label' => 'Amprah Berjalan', 'nilai' => (string) $amprahAktif, 'warna' => 'blue'],
                    ['label' => 'PR Berjalan', 'nilai' => (string) $prAktif, 'warna' => 'purple'],
                    ['label' => 'Servis Aktif', 'nilai' => (string) $laporRusak, 'warna' => 'warning'],
                ],
            ];

            $amprahList = PermintaanDistribusi::where('unit_id', $user->unit_id)
                ->with(['detail'])
                ->latest('id')
                ->take(15)
                ->get()
                ->map(fn ($a) => [
                    'id'         => $a->id,
                    'tipe'       => 'AMPRAH',
                    'kode'       => $a->kode,
                    'judul'      => 'Amprah: ' . $a->detail->count() . ' Item Komoditas',
                    'subjudul'   => 'Status Terkini: ' . str_replace('_', ' ', $a->status),
                    'status'     => $a->status,
                    'waktu_lalu' => $this->diffForHumans($a->dibuat_pada),
                    'timestamp'  => $a->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
                ]);

            $prList = PermintaanPengadaan::where('unit_id', $user->unit_id)
                ->with(['detail'])
                ->latest('id')
                ->take(15)
                ->get()
                ->map(fn ($p) => [
                    'id'         => $p->id,
                    'tipe'       => 'PR',
                    'kode'       => $p->kode,
                    'judul'      => 'PR: Pengadaan ' . $p->detail->count() . ' Barang',
                    'subjudul'   => 'Status: ' . str_replace('_', ' ', $p->status),
                    'status'     => $p->status,
                    'waktu_lalu' => $this->diffForHumans($p->dibuat_pada),
                    'timestamp'  => $p->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
                ]);

            $activities = $amprahList->concat($prList)->sortByDesc('timestamp')->values()->take(30)->toArray();
        }

        // -------------------------------------------------------------
        // KASUS 6: PENGADAAN, MANAJEMEN, SUPER ADMIN, AUDITOR (DEFAULT)
        // -------------------------------------------------------------
        else {
            $prMenunggu = PermintaanPengadaan::whereIn('status', ['DIAJUKAN', 'DISETUJUI'])->count();
            $amprahBerjalan = PermintaanDistribusi::whereIn('status', ['DIAJUKAN', 'DISETUJUI_KARU', 'DISETUJUI_GUDANG'])->count();
            $poVendor = PesananPembelian::whereIn('status', ['DITERBITKAN', 'DIKIRIM_VENDOR', 'DITERIMA_SEBAGIAN'])->count();

            $totalVerifikasi = $prMenunggu + $poVendor;

            $hero = [
                'judul'        => 'Antrean Verifikasi & Belanja',
                'total'        => $totalVerifikasi,
                'satuan'       => 'Dokumen',
                'label_tombol' => 'Periksa',
                'target_aksi'  => 'approval',
                'sub_metrik'   => [
                    ['label' => 'PR Menunggu', 'nilai' => (string) $prMenunggu, 'warna' => 'warning'],
                    ['label' => 'Amprah Berjalan', 'nilai' => (string) $amprahBerjalan, 'warna' => 'purple'],
                    ['label' => 'PO Vendor Aktif', 'nilai' => (string) $poVendor, 'warna' => 'blue'],
                ],
            ];

            $prList = PermintaanPengadaan::with(['unit', 'gudang', 'pengaju', 'detail'])
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

            $amprahList = PermintaanDistribusi::with(['unit', 'pengaju', 'detail'])
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

            $activities = $prList->concat($amprahList)->sortByDesc('timestamp')->values()->take(30)->toArray();
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
        if (! $date) {
            return 'Baru saja';
        }

        Carbon::setLocale('id');
        $c = Carbon::parse($date);
        return $c->diffForHumans();
    }
}
