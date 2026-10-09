<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Aset;
use App\Models\PermintaanPeminjaman;
use App\Models\PermintaanPeminjamanDetail;
use App\Models\StokBatch;
use App\Services\Aset\PeminjamanAsetService;
use App\Models\PengalihanAset;
use App\Services\Aset\PengalihanAsetService;
use App\Support\Generator\CodeGenerator;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AsetApiController extends ApiController
{
    /**
     * Pindai QR code fisik aset dan kembalikan detail, lokasi, dan status terkini.
     */
    public function scanQr(Request $request): JsonResponse
    {
        $request->validate([
            'kode_aset' => 'required|string|max:100',
        ]);

        $query = trim($request->input('kode_aset'));
        $aset = Aset::with(['barang.kategori', 'barang.satuan', 'barang.merek', 'unit', 'gudang', 'lokasiGudang'])
            ->withCount('permintaanMaintenance')
            ->where(function ($q) use ($query) {
                $q->whereRaw('LOWER(kode_aset) = ?', [strtolower($query)])
                  ->orWhereRaw('LOWER(nomor_seri) = ?', [strtolower($query)])
                  ->orWhereHas('barang', fn ($b) => $b->whereRaw('LOWER(nama) LIKE ?', ['%' . strtolower($query) . '%']));
            })
            ->first();

        if (! $aset) {
            $batch = StokBatch::whereRaw('LOWER(nomor_batch) = ?', [strtolower($query)])->first();
            if ($batch) {
                return $this->jsonError("Kode '{$query}' adalah nomor batch logistik gudang. Silakan periksa di menu Pemindai Barcode Gudang.", 404);
            }
            return $this->jsonError("Aset dengan kode atau kata kunci '{$query}' tidak ditemukan di sistem.", 404);
        }

        // Lokasi aktif saat ini
        $lokasiSaatIni = $aset->unit ? $aset->unit->nama : ($aset->gudang ? $aset->gudang->nama . ($aset->lokasiGudang ? " (Rak: {$aset->lokasiGudang->nama})" : '') : '-');

        // Cari transaksi peminjaman aktif (DIPINJAM, atau antrean DIAJUKAN/DISETUJUI)
        $peminjamanAktif = null;
        $antreanPeminjaman = null;
        $transaksiPinjam = PermintaanPeminjaman::whereHas('detail', fn ($q) => $q->where('aset_id', $aset->id))
            ->whereIn('status', ['DIAJUKAN', 'DISETUJUI', 'DIPINJAM'])
            ->with(['unitPeminjam', 'pengaju'])
            ->latest('id')
            ->first();

        if ($transaksiPinjam) {
            if ($transaksiPinjam->status === 'DIPINJAM') {
                $peminjamanAktif = $transaksiPinjam;
            } else {
                $antreanPeminjaman = [
                    'peminjaman_id' => $transaksiPinjam->id,
                    'kode_pinjam'   => $transaksiPinjam->kode,
                    'unit_peminjam' => $transaksiPinjam->unitPeminjam?->nama ?? 'Unit Ruangan',
                    'pengaju'       => $transaksiPinjam->pengaju?->nama ?? 'Staf',
                    'status'        => $transaksiPinjam->status,
                ];
            }
        }

        $latestMaintenance = $aset->permintaanMaintenance()->latest('id')->first();
        $riwayatPeminjaman = $this->getRiwayatPeminjaman($aset->id);
        return $this->jsonSuccess([
            'id'                   => $aset->id,
            'kode_aset'            => $aset->kode_aset,
            'nomor_seri'           => $aset->nomor_seri ?? '-',
            'nama_komoditas'       => $aset->barang?->nama ?? '-',
            'kategori'             => $aset->barang?->kategori?->nama ?? '-',
            'merek'                => $aset->barang?->merek?->nama ?? '-',
            'spesifikasi'          => $aset->barang?->spesifikasi ?? '-',
            'status'               => $aset->status,
            'kondisi'              => $aset->kondisi,
            'unit_id'              => $aset->unit_id,
            'gudang_id'            => $aset->gudang_id,
            'tipe_penyedia'        => $aset->unit_id ? 'RUANGAN' : 'GUDANG',
            'penyedia_id'          => $aset->unit_id ?: $aset->gudang_id,
            'nama_penyedia'        => $aset->unit?->nama ?? $aset->gudang?->nama ?? '-',
            'lokasi_tercatat'      => $lokasiSaatIni,
            'penanggung_jawab'     => $peminjamanAktif ? $peminjamanAktif->unitPeminjam?->nama : $lokasiSaatIni,
            'nilai_perolehan'      => (float) ($aset->nilai_perolehan ?? 0),
            'tanggal_perolehan'    => $aset->tanggal_perolehan?->format('Y-m-d') ?? '-',
            'total_riwayat_servis'     => (int) ($aset->permintaan_maintenance_count ?? 0),
            'total_riwayat_peminjaman' => $riwayatPeminjaman->count(),
            'servis_terakhir'      => $latestMaintenance ? [
                'kode'              => $latestMaintenance->kode,
                'status'            => $latestMaintenance->status,
                'prioritas'         => $latestMaintenance->prioritas,
                'deskripsi'         => $latestMaintenance->deskripsi_masalah,
                'tanggal_pengajuan' => $latestMaintenance->dibuat_pada?->format('Y-m-d') ?? '-',
            ] : null,
            'peminjaman_aktif'     => $peminjamanAktif ? [
                'peminjaman_id'           => $peminjamanAktif->id,
                'peminjaman_detail_id'    => $peminjamanAktif->detail->firstWhere('aset_id', $aset->id)?->id,
                'kode_pinjam'             => $peminjamanAktif->kode,
                'unit_peminjam'           => $peminjamanAktif->unitPeminjam?->nama,
                'tanggal_pinjam'          => $peminjamanAktif->tanggal_pinjam?->format('Y-m-d'),
                'tanggal_kembali_rencana' => $peminjamanAktif->tanggal_kembali_rencana?->format('Y-m-d'),
                'is_overdue'              => now()->startOfDay()->gt($peminjamanAktif->tanggal_kembali_rencana),
            ] : null,
            'is_sedang_diajukan'       => $antreanPeminjaman !== null,
            'status_sirkulasi'         => $transaksiPinjam ? $transaksiPinjam->status : $aset->status,
            'antrean_peminjaman'       => $antreanPeminjaman,
            'riwayat_peminjaman'       => $riwayatPeminjaman,
        ], 'Data aset berhasil ditemukan.');
    }

    /**
     * Ambil profil fisik dan status terkini aset berdasarkan ID.
     * GET /v1/aset/detail/{id}
     */
    public function detailAset(int $id): JsonResponse
    {
        $aset = Aset::with(['barang.kategori', 'barang.satuan', 'barang.merek', 'unit', 'gudang', 'lokasiGudang'])
            ->withCount('permintaanMaintenance')
            ->find($id);
        if (! $aset) {
            return $this->jsonError('Data aset tidak ditemukan.', 404);
        }

        $lokasiSaatIni = $aset->unit ? $aset->unit->nama : ($aset->gudang ? $aset->gudang->nama . ($aset->lokasiGudang ? " (Rak: {$aset->lokasiGudang->nama})" : '') : '-');

        // Cari transaksi peminjaman aktif (DIPINJAM, atau antrean DIAJUKAN/DISETUJUI)
        $peminjamanAktif = null;
        $antreanPeminjaman = null;
        $transaksiPinjam = PermintaanPeminjaman::whereHas('detail', fn ($q) => $q->where('aset_id', $aset->id))
            ->whereIn('status', ['DIAJUKAN', 'DISETUJUI', 'DIPINJAM'])
            ->with(['unitPeminjam', 'pengaju'])
            ->latest('id')
            ->first();

        if ($transaksiPinjam) {
            if ($transaksiPinjam->status === 'DIPINJAM') {
                $peminjamanAktif = $transaksiPinjam;
            } else {
                $antreanPeminjaman = [
                    'peminjaman_id' => $transaksiPinjam->id,
                    'kode_pinjam'   => $transaksiPinjam->kode,
                    'unit_peminjam' => $transaksiPinjam->unitPeminjam?->nama ?? 'Unit Ruangan',
                    'pengaju'       => $transaksiPinjam->pengaju?->nama ?? 'Staf',
                    'status'        => $transaksiPinjam->status,
                ];
            }
        }

        $latestMaintenance = $aset->permintaanMaintenance()->latest('id')->first();
        $riwayatPeminjaman = $this->getRiwayatPeminjaman($aset->id);
        return $this->jsonSuccess([
            'id'                   => $aset->id,
            'kode_aset'            => $aset->kode_aset,
            'nomor_seri'           => $aset->nomor_seri ?? '-',
            'nama_komoditas'       => $aset->barang?->nama ?? '-',
            'kategori'             => $aset->barang?->kategori?->nama ?? '-',
            'merek'                => $aset->barang?->merek?->nama ?? '-',
            'spesifikasi'          => $aset->barang?->spesifikasi ?? '-',
            'status'               => $aset->status,
            'kondisi'              => $aset->kondisi,
            'unit_id'              => $aset->unit_id,
            'gudang_id'            => $aset->gudang_id,
            'tipe_penyedia'        => $aset->unit_id ? 'RUANGAN' : 'GUDANG',
            'penyedia_id'          => $aset->unit_id ?: $aset->gudang_id,
            'nama_penyedia'        => $aset->unit?->nama ?? $aset->gudang?->nama ?? '-',
            'lokasi_tercatat'      => $lokasiSaatIni,
            'penanggung_jawab'     => $peminjamanAktif ? $peminjamanAktif->unitPeminjam?->nama : $lokasiSaatIni,
            'nilai_perolehan'      => (float) ($aset->nilai_perolehan ?? 0),
            'tanggal_perolehan'    => $aset->tanggal_perolehan?->format('Y-m-d') ?? '-',
            'total_riwayat_servis'     => (int) ($aset->permintaan_maintenance_count ?? 0),
            'total_riwayat_peminjaman' => $riwayatPeminjaman->count(),
            'servis_terakhir'      => $latestMaintenance ? [
                'kode'              => $latestMaintenance->kode,
                'status'            => $latestMaintenance->status,
                'prioritas'         => $latestMaintenance->prioritas,
                'deskripsi'         => $latestMaintenance->deskripsi_masalah,
                'tanggal_pengajuan' => $latestMaintenance->dibuat_pada?->format('Y-m-d') ?? '-',
            ] : null,
            'peminjaman_aktif'     => $peminjamanAktif ? [
                'peminjaman_id'           => $peminjamanAktif->id,
                'peminjaman_detail_id'    => $peminjamanAktif->detail->firstWhere('aset_id', $aset->id)?->id,
                'kode_pinjam'             => $peminjamanAktif->kode,
                'unit_peminjam'           => $peminjamanAktif->unitPeminjam?->nama,
                'tanggal_pinjam'          => $peminjamanAktif->tanggal_pinjam?->format('Y-m-d'),
                'tanggal_kembali_rencana' => $peminjamanAktif->tanggal_kembali_rencana?->format('Y-m-d'),
                'is_overdue'              => now()->startOfDay()->gt($peminjamanAktif->tanggal_kembali_rencana),
            ] : null,
            'is_sedang_diajukan'       => $antreanPeminjaman !== null,
            'status_sirkulasi'         => $transaksiPinjam ? $transaksiPinjam->status : $aset->status,
            'antrean_peminjaman'       => $antreanPeminjaman,
            'riwayat_peminjaman'       => $riwayatPeminjaman,
        ], 'Data aset berhasil ditemukan.');
    }

    /**
     * Katalog aset yang siap dipinjam (Status TERSEDIA & Kondisi BAIK).
     * GET /v1/aset/katalog-tersedia
     */
    public function katalogTersedia(Request $request): JsonResponse
    {
        $user = $request->user();
        $cari = $request->query('cari');
        $tipePenyedia = $request->query('penyedia_tipe'); // RUANGAN atau GUDANG
        $tujuan = $request->query('tujuan');
        $aset = Aset::query()
            ->when($tujuan === 'pengalihan', function ($q) {
                $q->whereIn('status', ['TERSEDIA', 'DIGUNAKAN'])
                    ->whereNotIn('kondisi', ['RUSAK_BERAT', 'DIHAPUSKAN'])
                    ->whereDoesntHave('permintaanMaintenance', function ($sub) {
                        $sub->whereNotIn('status', ['SELESAI', 'DITOLAK', 'DIBATALKAN']);
                    });
            }, function ($q) {
                $q->where('status', 'TERSEDIA')
                    ->where('kondisi', 'BAIK');
            })
            // Kunci integritas: kecualikan aset yang sedang dalam proses pengajuan atau aktif dipinjam
            ->whereDoesntHave('peminjamanDetail.permintaanPeminjaman', function ($q) {
                $q->whereIn('status', ['DIAJUKAN', 'DISETUJUI', 'DIPINJAM']);
            })
            ->with(['barang.kategori:id,nama', 'barang.satuan:id,nama', 'barang.merek:id,nama', 'unit:id,kode,nama', 'gudang:id,kode,nama', 'lokasiGudang:id,kode,nama'])
            // Jangan meminjam aset dari ruangan sendiri (selaras dengan validasi web)
            ->when($user->unit_id, function ($q) use ($user) {
                $q->where(function ($sub) use ($user) {
                    $sub->whereNull('unit_id')
                        ->orWhere('unit_id', '!=', $user->unit_id);
                });
            })
            ->when($tipePenyedia === 'RUANGAN', fn ($q) => $q->whereNotNull('unit_id'))
            ->when($tipePenyedia === 'GUDANG', fn ($q) => $q->whereNotNull('gudang_id'))
            ->when($cari, function ($q) use ($cari) {
                $q->where(function ($sub) use ($cari) {
                    $sub->where('kode_aset', 'like', "%{$cari}%")
                        ->orWhere('nomor_seri', 'like', "%{$cari}%")
                        ->orWhereHas('barang', fn ($b) => $b->where('nama', 'like', "%{$cari}%")->orWhere('kode', 'like', "%{$cari}%"));
                });
            })
            ->limit(50)
            ->get()
            ->map(function ($a) {
                $isRuangan = $a->unit_id !== null;
                return [
                    'id'               => $a->id,
                    'kode_aset'        => $a->kode_aset,
                    'nomor_seri'       => $a->nomor_seri ?? '-',
                    'nama_komoditas'   => $a->barang?->nama ?? 'Aset Fisik',
                    'spesifikasi'      => $a->barang?->spesifikasi ?? '-',
                    'kategori'         => $a->barang?->kategori?->nama ?? 'Umum',
                    'satuan'           => $a->barang?->satuan?->nama ?? 'Unit',
                    'merek'            => $a->barang?->merek?->nama ?? '-',
                    'kondisi'          => $a->kondisi,
                    'tipe_penyedia'    => $isRuangan ? 'RUANGAN' : 'GUDANG',
                    'penyedia_id'      => $isRuangan ? $a->unit_id : $a->gudang_id,
                    'nama_penyedia'    => $isRuangan ? ($a->unit?->nama ?? '-') : ($a->gudang?->nama ?? '-'),
                    'lokasi_lengkap'   => $isRuangan ? ($a->unit?->nama ?? '-') : ($a->gudang?->nama . ($a->lokasiGudang ? " (Rak: {$a->lokasiGudang->nama})" : '')),
                ];
            });

        return $this->jsonSuccess($aset, 'Katalog aset siap pinjam berhasil diambil.');
    }

    /**
     * Mengambil riwayat kronologis sirkulasi peminjaman dan pengembalian fisik aset.
     */
    private function getRiwayatPeminjaman(int $asetId)
    {
        return PermintaanPeminjamanDetail::where('aset_id', $asetId)
            ->with([
                'permintaanPeminjaman.unitPeminjam',
                'permintaanPeminjaman.pengaju',
                'pengembalianDetail.pengembalianPeminjaman.pemeriksa',
            ])
            ->whereHas('permintaanPeminjaman')
            ->get()
            ->sortByDesc(fn ($d) => $d->permintaanPeminjaman?->id)
            ->map(function ($d) {
                $pinjam = $d->permintaanPeminjaman;
                $kembali = $d->pengembalianDetail->first();
                $pengembalianHeader = $kembali?->pengembalianPeminjaman;

                return [
                    'id'                      => $d->id,
                    'kode_peminjaman'         => $pinjam?->kode ?? '-',
                    'unit_peminjam'           => $pinjam?->unitPeminjam?->nama ?? 'Ruangan',
                    'peminjam'                => $pinjam?->pengaju?->nama ?? '-',
                    'tanggal_pinjam'          => $pinjam?->tanggal_pinjam?->format('Y-m-d') ?? '-',
                    'tanggal_kembali_rencana' => $pinjam?->tanggal_kembali_rencana?->format('Y-m-d') ?? '-',
                    'tanggal_kembali_aktual'  => $pengembalianHeader?->tanggal_kembali_aktual?->format('Y-m-d'),
                    'status'                  => $pinjam?->status ?? '-',
                    'keperluan'               => $pinjam?->keperluan ?? '-',
                    'kondisi_saat_pinjam'     => $d->kondisi_saat_pinjam ?? 'BAIK',
                    'kondisi_saat_kembali'    => $kembali?->kondisi_saat_kembali,
                    'catatan_pengembalian'    => $kembali?->catatan,
                    'pemeriksa_kembali'       => $pengembalianHeader?->pemeriksa?->nama,
                ];
            })
            ->values();
    }

    /**
     * Riwayat daftar transaksi peminjaman aset unit pengguna.
     * GET /v1/aset/peminjaman
     */
    public function daftarPeminjaman(Request $request): JsonResponse
    {
        $user = $request->user();
        $perspektif = $request->query('perspektif', 'peminjam'); // peminjam atau penyedia
        $filterStatus = $request->query('status'); // DIAJUKAN, DISETUJUI, DIPINJAM, SELESAI, DITOLAK

        $isGlobalAdmin = $user->can('page_Dashboard') ||
            $user->can('page_AdminLogistikDashboard') ||
            $user->hasRole('super_admin') ||
            $user->hasRole('admin_logistik');

        $query = PermintaanPeminjaman::query()
            ->with([
                'unitPeminjam:id,kode,nama',
                'unitPenyedia:id,kode,nama',
                'gudangPenyedia:id,kode,nama',
                'pengaju:id,nama',
                'detail.aset.barang:id,kode,nama',
            ]);

        if (! $isGlobalAdmin) {
            $effectiveUnitId = $user->unit_id ?: ($user->hasRole('pengadaan') ? Unit::where('kode', 'LOGISTIK')->value('id') : null);
            if ($effectiveUnitId) {
                if ($perspektif === 'penyedia') {
                    $query->where('tipe_penyedia', 'RUANGAN')->where('unit_penyedia_id', $effectiveUnitId);
                } else {
                    $query->where('unit_id', $effectiveUnitId);
                }
            } elseif ($user->gudang_id) {
                if ($perspektif === 'penyedia') {
                    $query->where('tipe_penyedia', 'GUDANG')->where('gudang_penyedia_id', $user->gudang_id);
                } else {
                    $query->where('diajukan_oleh', $user->id);
                }
            } else {
                return $this->jsonSuccess([], 'Pengguna tidak terikat pada unit atau gudang operasional.');
            }
        }

        if ($filterStatus) {
            $query->where('status', $filterStatus);
        }

        $list = $query->latest('id')
            ->limit(100)
            ->get()
            ->map(function ($p) use ($user) {
                $firstDetail = $p->detail->first();
                $aset = $firstDetail?->aset;
                $isOverdue = $p->status === 'DIPINJAM' && $p->tanggal_kembali_rencana && now()->startOfDay()->gt($p->tanggal_kembali_rencana);

                return [
                    'id'                      => $p->id,
                    'kode'                    => $p->kode,
                    'tipe_penyedia'           => $p->tipe_penyedia,
                    'nama_penyedia'           => $p->tipe_penyedia === 'RUANGAN' ? ($p->unitPenyedia?->nama ?? '-') : ($p->gudangPenyedia?->nama ?? '-'),
                    'unit_peminjam'           => $p->unitPeminjam?->nama ?? '-',
                    'pengaju'                 => $p->pengaju?->nama ?? 'Staf',
                    'tanggal_pinjam'          => $p->tanggal_pinjam?->format('Y-m-d') ?? '',
                    'tanggal_kembali_rencana' => $p->tanggal_kembali_rencana?->format('Y-m-d') ?? '',
                    'keperluan'               => $p->keperluan,
                    'status'                  => $p->status,
                    'catatan'                 => $p->catatan,
                    'is_overdue'              => $isOverdue,
                    'jumlah_aset'             => $p->detail->count(),
                    'aset_preview'            => $aset ? [
                        'id'             => $aset->id,
                        'kode_aset'      => $aset->kode_aset,
                        'nama_komoditas' => $aset->barang?->nama ?? 'Aset Fisik',
                    ] : null,
                    'items'                   => $p->detail->map(fn ($d) => [
                        'id'                  => $d->id,
                        'aset_id'             => $d->aset_id,
                        'kode_aset'           => $d->aset?->kode_aset ?? '-',
                        'nama_komoditas'      => $d->aset?->barang?->nama ?? 'Aset Fisik',
                        'kondisi_saat_pinjam' => $d->kondisi_saat_pinjam ?? 'BAIK',
                    ]),
                ];
            });

        return $this->jsonSuccess($list, 'Daftar peminjaman aset berhasil diambil.');
    }

    /**
     * Pengajuan peminjaman aset baru dari unit.
     * POST /v1/aset/peminjaman
     */
    public function simpanPeminjaman(Request $request): JsonResponse
    {
        $user = $request->user();

        $canPinjam = $user && (
            $user->can('create_layanan_unit::peminjaman::aset') ||
            $user->hasRole('petugas_unit') ||
            $user->hasRole('kepala_unit') ||
            $user->hasRole('auditor') ||
            $user->hasRole('manajemen') ||
            $user->hasRole('pengadaan') ||
            $user->hasRole('super_admin')
        );

        if (! $canPinjam) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk mengajukan peminjaman aset.', 403);
        }

        $validator = Validator::make($request->all(), [
            'aset_id'                 => 'required|exists:aset,id',
            'tanggal_pinjam'          => 'required|date',
            'tanggal_kembali_rencana' => 'required|date|after_or_equal:tanggal_pinjam',
            'keperluan'               => 'required|string|max:500',
            'catatan'                 => 'nullable|string|max:1000',
        ], [
            'aset_id.required'                 => 'Aset wajib dipilih.',
            'tanggal_pinjam.required'          => 'Tanggal pinjam wajib diisi.',
            'tanggal_kembali_rencana.required' => 'Tanggal rencana pengembalian wajib diisi.',
            'tanggal_kembali_rencana.after_or_equal' => 'Tanggal kembali tidak boleh sebelum tanggal pinjam.',
            'keperluan.required'              => 'Keperluan peminjaman wajib dicantumkan.',
        ]);

        if ($validator->fails()) {
            return $this->jsonError('Validasi data gagal.', 422, $validator->errors());
        }

        $unitPeminjamId = $user->unit_id;
        if (! $unitPeminjamId) {
            return $this->jsonError('Anda harus terdaftar pada unit/ruangan untuk mengajukan peminjaman aset.', 422);
        }

        $aset = Aset::where('id', $request->input('aset_id'))
            ->where('status', 'TERSEDIA')
            ->where('kondisi', 'BAIK')
            ->first();
        if (! $aset) {
            return $this->jsonError('Aset yang dipilih tidak tersedia untuk dipinjam.', 422);
        }

        // Kunci integritas: periksa apakah aset sedang dalam transaksi peminjaman aktif lain
        $isSedangDipinjam = PermintaanPeminjaman::whereIn('status', ['DIAJUKAN', 'DISETUJUI', 'DIPINJAM'])
            ->whereHas('detail', fn ($d) => $d->where('aset_id', $aset->id))
            ->exists();

        if ($isSedangDipinjam) {
            return $this->jsonError("Aset '{$aset->kode_aset}' ({$aset->barang?->nama}) sedang dalam proses pengajuan atau sedang aktif dipinjam oleh unit lain.", 422);
        }

        $isRuangan = $aset->unit_id !== null;
        $unitPenyediaId = $isRuangan ? $aset->unit_id : null;
        $gudangPenyediaId = ! $isRuangan ? $aset->gudang_id : null;

        if ($isRuangan && (int) $unitPenyediaId === (int) $unitPeminjamId) {
            return $this->jsonError('Unit penyedia tidak boleh sama dengan unit peminjam. Aset sudah berada di unit Anda.', 422);
        }

        try {
            $pinjam = DB::transaction(function () use ($request, $user, $unitPeminjamId, $aset, $isRuangan, $unitPenyediaId, $gudangPenyediaId) {
                $kode = CodeGenerator::generatePinj();

                $peminjaman = PermintaanPeminjaman::create([
                    'kode'                    => $kode,
                    'tipe_penyedia'           => $isRuangan ? 'RUANGAN' : 'GUDANG',
                    'unit_penyedia_id'        => $unitPenyediaId,
                    'gudang_penyedia_id'      => $gudangPenyediaId,
                    'unit_id'                 => $unitPeminjamId,
                    'diajukan_oleh'           => $user->id,
                    'tanggal_pinjam'          => $request->input('tanggal_pinjam'),
                    'tanggal_kembali_rencana' => $request->input('tanggal_kembali_rencana'),
                    'keperluan'               => $request->input('keperluan'),
                    'status'                  => 'DIAJUKAN',
                    'catatan'                 => $request->input('catatan'),
                ]);

                PermintaanPeminjamanDetail::create([
                    'permintaan_peminjaman_id' => $peminjaman->id,
                    'aset_id'                  => $aset->id,
                    'kondisi_saat_pinjam'      => $aset->kondisi,
                    'catatan'                  => $request->input('catatan'),
                ]);

                return $peminjaman;
            });

            return $this->jsonSuccess([
                'id'     => $pinjam->id,
                'kode'   => $pinjam->kode,
                'status' => $pinjam->status,
            ], "Permintaan peminjaman aset {$pinjam->kode} berhasil diajukan.", 201);
        } catch (\Throwable $e) {
            return $this->jsonError('Gagal mengajukan peminjaman: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Batalkan pengajuan peminjaman aset yang masih DIAJUKAN.
     * POST /v1/aset/peminjaman/{id}/batal
     */
    public function batalPeminjaman(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $pinjam = PermintaanPeminjaman::find($id);

        if (! $pinjam) {
            return $this->jsonError('Data peminjaman tidak ditemukan.', 404);
        }

        if ($pinjam->status !== 'DIAJUKAN') {
            return $this->jsonError("Hanya peminjaman berstatus DIAJUKAN yang dapat dibatalkan (saat ini: {$pinjam->status}).", 422);
        }

        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('admin_logistik');
        $canCancel = $isGlobalAdmin ||
            (int) $pinjam->diajukan_oleh === (int) $user->id ||
            ((int) $pinjam->unit_id === (int) $user->unit_id && (
                $user->can('create_layanan_unit::peminjaman::aset') ||
                $user->can('delete_layanan_unit::peminjaman::aset') ||
                $user->hasRole('petugas_unit') ||
                $user->hasRole('kepala_unit') ||
                $user->hasRole('auditor') ||
                $user->hasRole('manajemen') ||
                $user->hasRole('pengadaan')
            ));

        if (! $canCancel) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk membatalkan pengajuan ini.', 403);
        }

        $pinjam->update([
            'status'  => 'DIBATALKAN',
            'catatan' => ($pinjam->catatan ? $pinjam->catatan . "\n" : '') . '[Dibatalkan oleh pemohon pada ' . now()->format('d/m/Y H:i') . ']',
        ]);

        return $this->jsonSuccess(null, "Pengajuan peminjaman {$pinjam->kode} berhasil dibatalkan.");
    }

    /**
     * Perpanjang masa peminjaman aset aktif (DIAJUKAN, DISETUJUI, DIPINJAM).
     * POST /v1/aset/peminjaman/{id}/perpanjang
     */
    public function perpanjangPeminjaman(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $pinjam = PermintaanPeminjaman::find($id);

        if (! $pinjam) {
            return $this->jsonError('Data peminjaman tidak ditemukan.', 404);
        }

        if ($pinjam->status !== 'DIPINJAM') {
            return $this->jsonError("Hanya peminjaman dengan status DIPINJAM yang dapat diperpanjang (saat ini: {$pinjam->status}).", 422);
        }

        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('admin_logistik');
        $canExtend = $isGlobalAdmin ||
            (int) $pinjam->diajukan_oleh === (int) $user->id ||
            ((int) $pinjam->unit_id === (int) $user->unit_id && (
                $user->can('update_layanan_unit::peminjaman::aset') ||
                $user->can('create_layanan_unit::peminjaman::aset') ||
                $user->hasRole('petugas_unit') ||
                $user->hasRole('kepala_unit') ||
                $user->hasRole('auditor') ||
                $user->hasRole('manajemen') ||
                $user->hasRole('pengadaan')
            ));

        if (! $canExtend) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk memperpanjang peminjaman ini.', 403);
        }

        $validator = Validator::make($request->all(), [
            'tanggal_kembali_baru' => 'required|date',
            'alasan'               => 'required|string|max:500',
        ], [
            'tanggal_kembali_baru.required' => 'Tanggal rencana pengembalian baru wajib diisi.',
            'alasan.required'               => 'Alasan perpanjangan wajib dicantumkan.',
        ]);

        if ($validator->fails()) {
            return $this->jsonError('Validasi perpanjangan gagal.', 422, $validator->errors());
        }

        $tanggalBaru = $request->input('tanggal_kembali_baru');
        if ($pinjam->tanggal_pinjam && $tanggalBaru <= $pinjam->tanggal_pinjam->toDateString()) {
            return $this->jsonError('Tanggal kembali baru harus lebih lambat dari tanggal pinjam.', 422);
        }

        $catatanLama = $pinjam->catatan ? $pinjam->catatan . "\n" : '';
        $tglLama = $pinjam->tanggal_kembali_rencana ? $pinjam->tanggal_kembali_rencana->format('d/m/Y') : '-';
        $tglBaruFormatted = \Carbon\Carbon::parse($tanggalBaru)->format('d/m/Y');
        $alasan = $request->input('alasan');

        $logPerpanjangan = '[Perpanjangan ' . now()->format('d/m/Y H:i') . " oleh {$user->nama}]: " .
            "Rencana kembali diperpanjang dari {$tglLama} menjadi {$tglBaruFormatted}. Alasan: {$alasan}";

        $pinjam->update([
            'tanggal_kembali_rencana' => $tanggalBaru,
            'catatan'                 => $catatanLama . $logPerpanjangan,
        ]);

        return $this->jsonSuccess([
            'id'                      => $pinjam->id,
            'kode'                    => $pinjam->kode,
            'tanggal_kembali_rencana' => $pinjam->tanggal_kembali_rencana?->format('Y-m-d'),
            'catatan'                 => $pinjam->catatan,
        ], "Rencana pengembalian peminjaman '{$pinjam->kode}' berhasil diperpanjang.");
    }

    /**
     * Daftar antrean otorisasi peminjaman masuk untuk Karu & Kepala Gudang.
     * GET /v1/aset/approval-peminjaman
     */
    public function daftarApprovalPeminjaman(Request $request): JsonResponse
    {
        $user = $request->user();

        $canApprove = $user->can('layanan_unit::approval::peminjaman') ||
            $user->can('view_any_layanan_unit::approval::karu') ||
            $user->hasRole('kepala_unit') ||
                $user->hasRole('auditor') ||
                $user->hasRole('manajemen') ||
                $user->hasRole('pengadaan') ||
            $user->hasRole('kepala_gudang') ||
            $user->hasRole('admin_logistik') ||
            $user->hasRole('super_admin');

        if (! $canApprove) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk mengakses otorisasi peminjaman aset.', 403);
        }

        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('admin_logistik') || $user->hasRole('manajemen');

        $query = PermintaanPeminjaman::query()
            ->whereIn('status', ['DIAJUKAN', 'DISETUJUI'])
            ->with([
                'unitPeminjam:id,kode,nama',
                'unitPenyedia:id,kode,nama',
                'gudangPenyedia:id,kode,nama',
                'pengaju:id,nama',
                'detail.aset.barang:id,kode,nama',
            ]);

        if (! $isGlobalAdmin) {
            if ($user->unit_id) {
                $query->where('tipe_penyedia', 'RUANGAN')->where('unit_penyedia_id', $user->unit_id);
            } elseif ($user->gudang_id) {
                $query->where('tipe_penyedia', 'GUDANG')->where('gudang_penyedia_id', $user->gudang_id);
            } else {
                return $this->jsonSuccess([], 'Tidak ada antrean otorisasi.');
            }
        }

        $list = $query->latest('id')
            ->limit(100)
            ->get()
            ->map(function ($p) {
                $firstDetail = $p->detail->first();
                $aset = $firstDetail?->aset;

                return [
                    'id'                      => $p->id,
                    'kode'                    => $p->kode,
                    'tipe_penyedia'           => $p->tipe_penyedia,
                    'unit_peminjam'           => $p->unitPeminjam?->nama ?? '-',
                    'pengaju'                 => $p->pengaju?->nama ?? 'Staf',
                    'tanggal_pinjam'          => $p->tanggal_pinjam?->format('Y-m-d') ?? '',
                    'tanggal_kembali_rencana' => $p->tanggal_kembali_rencana?->format('Y-m-d') ?? '',
                    'keperluan'               => $p->keperluan,
                    'status'                  => $p->status,
                    'catatan'                 => $p->catatan,
                    'nama_aset'               => $aset?->barang?->nama ?? 'Aset Fisik',
                    'kode_aset'               => $aset?->kode_aset ?? '-',
                ];
            });

        return $this->jsonSuccess($list, 'Antrean otorisasi peminjaman aset berhasil diambil.');
    }

    /**
     * Otorisasi peminjaman aset (Setujui, Tolak, atau Serah Terima Fisik).
     * POST /v1/aset/peminjaman/{id}/otorisasi
     */
    public function otorisasiPeminjaman(Request $request, int $id, PeminjamanAsetService $service): JsonResponse
    {
        $user = $request->user();

        $canApprove = $user->can('layanan_unit::approval::peminjaman') ||
            $user->can('view_any_layanan_unit::approval::karu') ||
            $user->hasRole('kepala_unit') ||
                $user->hasRole('auditor') ||
                $user->hasRole('manajemen') ||
                $user->hasRole('pengadaan') ||
            $user->hasRole('kepala_gudang') ||
            $user->hasRole('admin_logistik') ||
            $user->hasRole('super_admin');

        if (! $canApprove) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk mengotorisasi peminjaman ini.', 403);
        }

        $pinjam = PermintaanPeminjaman::with('detail.aset')->find($id);
        if (! $pinjam) {
            return $this->jsonError('Data peminjaman tidak ditemukan.', 404);
        }

        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('admin_logistik') || $user->hasRole('manajemen');
        if (! $isGlobalAdmin) {
            if ($pinjam->tipe_penyedia === 'RUANGAN' && (int) $user->unit_id !== (int) $pinjam->unit_penyedia_id) {
                return $this->jsonError('Anda tidak memiliki wewenang memproses permintaan dari ruangan penyedia ini.', 403);
            }
            if ($pinjam->tipe_penyedia === 'GUDANG' && (int) $user->gudang_id !== (int) $pinjam->gudang_penyedia_id) {
                return $this->jsonError('Anda tidak memiliki wewenang memproses permintaan dari gudang penyedia ini.', 403);
            }
        }

        $request->validate([
            'aksi'    => 'required|in:setujui,tolak,serah_terima',
            'catatan' => 'nullable|string|max:500',
        ]);

        $aksi = $request->input('aksi');

        if ($aksi === 'setujui') {
            if ($pinjam->status !== 'DIAJUKAN') {
                return $this->jsonError("Hanya permintaan berstatus DIAJUKAN yang dapat disetujui (saat ini: {$pinjam->status}).", 422);
            }

            // Kunci integritas: periksa apakah ada aset di permohonan ini yang sudah disetujui/dipinjam pada transaksi lain
            $asetIds = $pinjam->detail->pluck('aset_id')->filter();
            $konflik = PermintaanPeminjaman::whereIn('status', ['DISETUJUI', 'DIPINJAM'])
                ->where('id', '!=', $pinjam->id)
                ->whereHas('detail', fn ($d) => $d->whereIn('aset_id', $asetIds))
                ->first();

            if ($konflik) {
                return $this->jsonError("Aset pada permohonan ini telah disetujui atau sedang aktif dipinjam pada transaksi {$konflik->kode}.", 422);
            }

            $pinjam->update([
                'status'         => 'DISETUJUI',
                'disetujui_oleh' => $user->id,
            ]);
            return $this->jsonSuccess([
                'kode'   => $pinjam->kode,
                'status' => 'DISETUJUI',
            ], "Permintaan peminjaman {$pinjam->kode} berhasil disetujui, siap diserahterimakan.");
        }

        if ($aksi === 'tolak') {
            if ($pinjam->status !== 'DIAJUKAN') {
                return $this->jsonError("Hanya permintaan berstatus DIAJUKAN yang dapat ditolak (saat ini: {$pinjam->status}).", 422);
            }

            $pinjam->update([
                'status'         => 'DITOLAK',
                'disetujui_oleh' => $user->id,
                'catatan'        => $request->input('catatan') ?? $pinjam->catatan,
            ]);

            return $this->jsonSuccess([
                'kode'   => $pinjam->kode,
                'status' => 'DITOLAK',
            ], "Permintaan peminjaman {$pinjam->kode} ditolak.");
        }

        if ($aksi === 'serah_terima') {
            if ($pinjam->status !== 'DISETUJUI') {
                return $this->jsonError("Hanya peminjaman berstatus DISETUJUI yang dapat diserahterimakan (saat ini: {$pinjam->status}).", 422);
            }

            try {
                $service->serahTerimaFisik($pinjam, $user);

                return $this->jsonSuccess([
                    'kode'   => $pinjam->kode,
                    'status' => 'DIPINJAM',
                ], "Serah terima fisik berhasil. Aset resmi berstatus DIPINJAM oleh {$pinjam->unitPeminjam?->nama}.");
            } catch (Exception $e) {
                return $this->jsonError($e->getMessage(), 422);
            }
        }

        return $this->jsonError('Aksi tidak dikenali.', 422);
    }

    /**
     * Konfirmasi serah terima fisik aset ke peminjam via mobile scanner.
     */
    public function serahTerima(Request $request, PeminjamanAsetService $service): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'peminjaman_id' => 'required|exists:permintaan_peminjaman,id',
        ]);

        $pinjam = PermintaanPeminjaman::with(['detail.aset', 'unitPenyedia', 'gudangPenyedia'])->find($request->input('peminjaman_id'));

        if (! $pinjam) {
            return $this->jsonError('Data peminjaman tidak ditemukan.', 404);
        }

        if ($pinjam->status !== 'DISETUJUI') {
            return $this->jsonError("Permintaan {$pinjam->kode} tidak dalam status siap serah terima (saat ini: {$pinjam->status}).", 422);
        }

        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('admin_logistik');
        if (! $isGlobalAdmin) {
            if ($pinjam->tipe_penyedia === 'RUANGAN' && (! $user->unit_id || (int) $user->unit_id !== (int) $pinjam->unit_penyedia_id)) {
                return $this->jsonError('Anda tidak memiliki wewenang menyerahkan aset dari ruangan penyedia ini.', 403);
            }
            if ($pinjam->tipe_penyedia === 'GUDANG' && (! $user->gudang_id || (int) $user->gudang_id !== (int) $pinjam->gudang_penyedia_id)) {
                return $this->jsonError('Anda tidak memiliki wewenang menyerahkan aset dari gudang penyedia ini.', 403);
            }
        }

        try {
            $service->serahTerimaFisik($pinjam, $user);

            return $this->jsonSuccess([
                'kode'            => $pinjam->kode,
                'status'          => 'DIPINJAM',
                'diserahkan_oleh' => [
                    'id'       => $user->id,
                    'nama'     => $user->nama,
                    'nip'      => $user->nip,
                    'username' => $user->username,
                ],
            ], "Serah terima fisik berhasil dicatat oleh {$user->nama}. Aset resmi berstatus DIPINJAM oleh {$pinjam->unitPeminjam?->nama}.");
        } catch (Exception $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    /**
     * Konfirmasi pengembalian dan inspeksi fisik aset via mobile.
     */
    public function kembalikan(Request $request, PeminjamanAsetService $service): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'peminjaman_id'                             => 'required|exists:permintaan_peminjaman,id',
            'inspeksi'                                  => 'required|array|min:1',
            'inspeksi.*.permintaan_peminjaman_detail_id' => 'nullable|integer',
            'inspeksi.*.aset_id'                        => 'required|exists:aset,id',
            'inspeksi.*.kondisi_saat_kembali'           => 'required|in:BAIK,RUSAK_RINGAN,RUSAK_BERAT',
            'inspeksi.*.catatan'                        => 'nullable|string|max:500',
            'catatan'                                   => 'nullable|string|max:1000',
        ]);

        $pinjam = PermintaanPeminjaman::with('detail')->find($request->input('peminjaman_id'));

        if (! $pinjam) {
            return $this->jsonError('Data peminjaman tidak ditemukan.', 404);
        }

        if ($pinjam->status !== 'DIPINJAM') {
            return $this->jsonError("Peminjaman {$pinjam->kode} tidak dalam status aktif dipinjam (saat ini: {$pinjam->status}).", 422);
        }

        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('admin_logistik');
        $isPenyedia = ($pinjam->tipe_penyedia === 'RUANGAN' && $user->unit_id && (int) $user->unit_id === (int) $pinjam->unit_penyedia_id)
            || ($pinjam->tipe_penyedia === 'GUDANG' && $user->gudang_id && (int) $user->gudang_id === (int) $pinjam->gudang_penyedia_id);

        if (! $isGlobalAdmin && ! $isPenyedia) {
            $isPeminjam = ((int) $user->unit_id === (int) $pinjam->unit_id)
                || ((int) $user->id === (int) $pinjam->diajukan_oleh);
            if ($isPeminjam) {
                return $this->jsonError('Peminjam tidak berwenang melakukan inspeksi fisik pengembalian aset. Pemeriksaan fisik wajib dilakukan oleh unit penyedia pemilik aset untuk menghindari konflik kepentingan.', 403);
            }

            return $this->jsonError('Hanya ruangan atau fasilitas gudang penyedia pemilik aset yang berhak memproses inspeksi dan pengembalian fisik aset.', 403);
        }
        $inspeksi = $request->input('inspeksi');
        foreach ($inspeksi as &$row) {
            $detailId = $row['permintaan_peminjaman_detail_id'] ?? null;
            $exists = $detailId && \App\Models\PermintaanPeminjamanDetail::where('id', $detailId)->where('permintaan_peminjaman_id', $pinjam->id)->exists();
            if (! $exists) {
                $matchedDetail = $pinjam->detail->firstWhere('aset_id', $row['aset_id']) ?? $pinjam->detail->first();
                if ($matchedDetail) {
                    $row['permintaan_peminjaman_detail_id'] = $matchedDetail->id;
                }
            }
        }
        unset($row);

        try {
            $pengembalian = $service->prosesPengembalian(
                $pinjam,
                $inspeksi,
                $user,
                $request->input('catatan')
            );

            return $this->jsonSuccess([
                'kode_pengembalian' => $pengembalian->kode,
                'kode_peminjaman'   => $pinjam->kode,
                'status_transaksi'  => 'SELESAI',
            ], "Pengembalian aset berhasil diproses. Status transaksi: SELESAI.");
        } catch (Exception $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }
    /**
     * Daftar dokumen pengalihan / mutasi aset.
     * GET /v1/aset/pengalihan?tab=aktif|otorisasi|riwayat
     */
    public function daftarPengalihan(Request $request): JsonResponse
    {
        $user = $request->user();
        $isGlobalAdmin = (bool) ($user && ($user->hasRole(['super_admin', 'admin_logistik', 'manajemen'])));
        $tab = $request->query('tab', 'aktif');

        $query = PengalihanAset::with([
            'unitAsal', 'unitTujuan', 'gudangAsal', 'gudangTujuan', 'lokasiGudangTujuan',
            'pengaju', 'penyetuju', 'penerima',
            'detail.aset.barang.satuan',
        ])->latest('id');

        if (! $isGlobalAdmin) {
            $userUnitId = $user->unit_id;
            $userGudangId = $user->gudang_id;
            $userId = $user->id;

            if ($tab === 'otorisasi') {
                $query->where('status', 'DIAJUKAN')
                    ->where(function ($q) use ($userUnitId, $userGudangId, $userId) {
                        if ($userUnitId) {
                            $q->orWhere(function ($sub) use ($userUnitId, $userId) {
                                $sub->where('tipe_asal', 'RUANGAN')
                                    ->where('unit_asal_id', $userUnitId)
                                    ->where(function ($nest) use ($userUnitId, $userId) {
                                        $nest->where('unit_tujuan_id', '!=', $userUnitId)
                                             ->orWhere('diajukan_oleh', '!=', $userId);
                                    });
                            });
                        }
                        if ($userGudangId) {
                            $q->orWhere(function ($sub) use ($userGudangId, $userId) {
                                $sub->where('tipe_asal', 'GUDANG')
                                    ->where('gudang_asal_id', $userGudangId)
                                    ->where(function ($nest) use ($userGudangId, $userId) {
                                        $nest->where('gudang_tujuan_id', '!=', $userGudangId)
                                             ->orWhere('diajukan_oleh', '!=', $userId);
                                    });
                            });
                        }
                    });
            } elseif ($tab === 'aktif') {
                $query->whereIn('status', ['DIAJUKAN', 'DISETUJUI'])
                    ->where(function ($q) use ($userUnitId, $userGudangId, $userId) {
                        $q->where('diajukan_oleh', $userId);
                        if ($userUnitId) {
                            $q->orWhere(function ($sub) use ($userUnitId) {
                                $sub->where('tipe_tujuan', 'RUANGAN')
                                    ->where('unit_tujuan_id', $userUnitId);
                            });
                        }
                        if ($userGudangId) {
                            $q->orWhere(function ($sub) use ($userGudangId) {
                                $sub->where('tipe_tujuan', 'GUDANG')
                                    ->where('gudang_tujuan_id', $userGudangId);
                            });
                        }
                    });
            } else {
                $query->whereIn('status', ['SELESAI', 'DITOLAK', 'DIBATALKAN'])
                    ->where(function ($q) use ($userUnitId, $userGudangId, $userId) {
                        $q->where('diajukan_oleh', $userId);
                        if ($userUnitId) {
                            $q->orWhere('unit_asal_id', $userUnitId)
                              ->orWhere('unit_tujuan_id', $userUnitId);
                        }
                        if ($userGudangId) {
                            $q->orWhere('gudang_asal_id', $userGudangId)
                              ->orWhere('gudang_tujuan_id', $userGudangId);
                        }
                    });
            }
        } else {
            if ($tab === 'aktif') {
                $query->whereIn('status', ['DIAJUKAN', 'DISETUJUI']);
            } elseif ($tab === 'otorisasi') {
                $query->where('status', 'DIAJUKAN');
            } elseif ($tab === 'riwayat') {
                $query->whereIn('status', ['SELESAI', 'DITOLAK', 'DIBATALKAN']);
            }
        }

        $list = $query->get()->map(function ($item) use ($user, $isGlobalAdmin) {
            $namaItem = $item->detail->first()?->aset?->barang?->nama
                ?? $item->detail->first()?->aset?->nama_aset
                ?? 'Aset Inventaris';

            $canCancel = $item->status === 'DIAJUKAN' && (
                $isGlobalAdmin ||
                (int) $item->diajukan_oleh === (int) $user->id ||
                ($user->unit_id && (int) $user->unit_id === (int) $item->unit_asal_id && $user->hasRole('kepala_unit'))
            );

            $canApprove = $item->status === 'DIAJUKAN' && (
                $isGlobalAdmin ||
                ($user->unit_id && (int) $user->unit_id === (int) $item->unit_asal_id && $user->hasRole(['kepala_unit', 'super_admin', 'admin_logistik'])) ||
                ($user->gudang_id && (int) $user->gudang_id === (int) $item->gudang_asal_id && $user->hasRole(['kepala_gudang', 'super_admin', 'admin_logistik']))
            );

            $canReceive = $item->status === 'DISETUJUI' && (
                $isGlobalAdmin ||
                ($user->unit_id && (int) $user->unit_id === (int) $item->unit_tujuan_id) ||
                ($user->gudang_id && (int) $user->gudang_id === (int) $item->gudang_tujuan_id)
            );

            return [
                'id'                 => $item->id,
                'kode'               => $item->kode,
                'tipe'               => 'ASET',
                'tipe_asal'          => $item->tipe_asal,
                'asal_nama'          => $item->nama_lokasi_asal,
                'unit_asal_id'       => $item->unit_asal_id,
                'gudang_asal_id'     => $item->gudang_asal_id,
                'tipe_tujuan'        => $item->tipe_tujuan,
                'tujuan_nama'        => $item->nama_lokasi_tujuan,
                'unit_tujuan_id'     => $item->unit_tujuan_id,
                'gudang_tujuan_id'   => $item->gudang_tujuan_id,
                'status'             => $item->status,
                'tanggal_pengalihan' => $item->tanggal_pengalihan?->format('Y-m-d') ?? '',
                'nama_item'          => $namaItem,
                'jumlah'             => $item->detail->count(),
                'alasan'             => $item->alasan,
                'catatan'            => $item->catatan,
                'pengaju'            => $item->pengaju?->nama ?? 'Staf',
                'penyetuju'          => $item->penyetuju?->nama,
                'penerima'           => $item->penerima?->nama,
                'can_cancel'         => $canCancel,
                'can_approve'        => $canApprove,
                'can_receive'        => $canReceive,
                'items'              => $item->detail->map(fn ($d) => [
                    'id'                   => $d->id,
                    'aset_id'              => $d->aset_id,
                    'nama_barang'          => $d->aset?->barang?->nama ?? 'Aset',
                    'kode_aset'            => $d->aset?->kode_aset ?? '-',
                    'nomor_seri'           => $d->aset?->nomor_seri ?? '-',
                    'kondisi_sebelum'      => $d->kondisi_sebelum ?? 'BAIK',
                    'kondisi_saat_diterima'=> $d->kondisi_saat_diterima,
                ]),
            ];
        });

        return $this->jsonSuccess($list, 'Daftar pengalihan aset berhasil dimuat.');
    }

    /**
     * Daftar komoditas dan fasilitas penyedia aset yang siap dialihkan (inbound).
     * GET /v1/aset/pengalihan/komoditas-tersedia
     */
    public function komoditasAsetTersedia(Request $request): JsonResponse
    {
        $user = $request->user();
        $isGlobalAdmin = (bool) ($user && ($user->hasRole(['super_admin', 'admin_logistik', 'manajemen'])));
        $ownUnitId = ! $isGlobalAdmin ? $user?->unit_id : null;
        $ownGudangId = ! $isGlobalAdmin ? $user?->gudang_id : null;

        $assets = Aset::with(['barang.kategori', 'barang.satuan', 'unit', 'gudang'])
            ->whereIn('status', ['TERSEDIA', 'DIGUNAKAN'])
            ->whereNotIn('kondisi', ['RUSAK_BERAT', 'DIHAPUSKAN'])
            ->whereDoesntHave('permintaanMaintenance', function ($sub) {
                $sub->whereNotIn('status', ['SELESAI', 'DITOLAK', 'DIBATALKAN']);
            })
            ->whereDoesntHave('peminjamanDetail.permintaanPeminjaman', function ($sub) {
                $sub->whereIn('status', ['DIAJUKAN', 'DISETUJUI', 'DIPINJAM']);
            })
            ->when($ownUnitId, fn ($sub) => $sub->where(fn ($sq) => $sq->where('unit_id', '!=', $ownUnitId)->orWhereNull('unit_id')))
            ->when($ownGudangId, fn ($sub) => $sub->where(fn ($sq) => $sq->where('gudang_id', '!=', $ownGudangId)->orWhereNull('gudang_id')))
            ->orderBy('kode_aset')
            ->get();

        $grouped = $assets->groupBy('barang_id')->map(function ($items, $barangId) {
            $first = $items->first();
            $barang = $first->barang;

            $penyediaList = [];
            foreach ($items->groupBy(fn ($a) => $a->unit_id ? "RUANGAN_{$a->unit_id}" : "GUDANG_{$a->gudang_id}") as $key => $subGroup) {
                $firstSub = $subGroup->first();
                $isRuangan = $firstSub->unit_id !== null;
                $penyediaList[] = [
                    'key'        => $key,
                    'tipe'       => $isRuangan ? 'RUANGAN' : 'GUDANG',
                    'id'         => $isRuangan ? $firstSub->unit_id : $firstSub->gudang_id,
                    'nama'       => $isRuangan ? ($firstSub->unit?->nama ?? 'Ruangan') : ($firstSub->gudang?->nama ?? 'Gudang'),
                    'kode'       => $isRuangan ? ($firstSub->unit?->kode ?? '-') : ($firstSub->gudang?->kode ?? '-'),
                    'jumlah'     => $subGroup->count(),
                    'aset_ids'   => $subGroup->pluck('id')->values()->toArray(),
                    'kode_asets' => $subGroup->pluck('kode_aset')->implode(', '),
                ];
            }

            return [
                'barang_id'      => (int) $barangId,
                'nama_komoditas' => $barang?->nama ?? 'Aset Fisik',
                'kategori'       => $barang?->kategori?->nama ?? 'Umum',
                'satuan'         => $barang?->satuan?->nama ?? 'Unit',
                'total_tersedia' => $items->count(),
                'penyedia'       => $penyediaList,
            ];
        })->values();

        return $this->jsonSuccess($grouped, 'Daftar komoditas aset tersedia untuk pengalihan berhasil diambil.');
    }

    /**
     * Daftar inventaris aset fisik milik unit atau gudang pengguna login yang siap dialihkan.
     * GET /v1/aset/pengalihan/inventaris-asal
     */
    public function inventarisAsal(Request $request): JsonResponse
    {
        $user = $request->user();
        $isGlobalAdmin = (bool) ($user && ($user->hasRole(['super_admin', 'admin_logistik', 'manajemen'])));

        $query = Aset::with(['barang.kategori', 'barang.satuan', 'barang.merek', 'unit', 'gudang'])
            ->whereIn('status', ['TERSEDIA', 'DIGUNAKAN'])
            ->whereNotIn('kondisi', ['RUSAK_BERAT', 'DIHAPUSKAN'])
            ->whereDoesntHave('permintaanMaintenance', function ($sub) {
                $sub->whereNotIn('status', ['SELESAI', 'DITOLAK', 'DIBATALKAN']);
            })
            ->whereDoesntHave('peminjamanDetail.permintaanPeminjaman', function ($sub) {
                $sub->whereIn('status', ['DIAJUKAN', 'DISETUJUI', 'DIPINJAM']);
            });

        if (! $isGlobalAdmin) {
            $query->where(function ($q) use ($user) {
                if ($user->unit_id) {
                    $q->where('unit_id', $user->unit_id);
                } elseif ($user->gudang_id) {
                    $q->where('gudang_id', $user->gudang_id);
                }
            });
        }

        $items = $query->orderBy('kode_aset')->get()->map(function ($a) {
            $isRuangan = $a->unit_id !== null;
            return [
                'id'             => $a->id,
                'kode_aset'      => $a->kode_aset,
                'nomor_seri'     => $a->nomor_seri ?? '-',
                'nama_komoditas' => $a->barang?->nama ?? 'Aset Fisik',
                'spesifikasi'    => $a->barang?->spesifikasi ?? '-',
                'kategori'       => $a->barang?->kategori?->nama ?? 'Umum',
                'satuan'         => $a->barang?->satuan?->nama ?? 'Unit',
                'merek'          => $a->barang?->merek?->nama ?? '-',
                'kondisi'        => $a->kondisi,
                'tipe_penyedia'  => $isRuangan ? 'RUANGAN' : 'GUDANG',
                'penyedia_id'    => $isRuangan ? $a->unit_id : $a->gudang_id,
                'nama_penyedia'  => $isRuangan ? ($a->unit?->nama ?? 'Ruangan') : ($a->gudang?->nama ?? 'Gudang'),
                'lokasi_lengkap' => $a->lokasiTercatat ?? ($isRuangan ? $a->unit?->nama : $a->gudang?->nama),
            ];
        });

        return $this->jsonSuccess($items, 'Daftar inventaris aset fisik asal berhasil dimuat.');
    }

    /**
     * Simpan pengajuan pengalihan aset baru.
     * POST /v1/aset/pengalihan
     */
    public function simpanPengalihan(Request $request, PengalihanAsetService $service): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'tipe_tujuan'        => 'required|in:RUANGAN,GUDANG',
            'unit_tujuan_id'     => 'required_if:tipe_tujuan,RUANGAN|nullable|exists:unit,id',
            'gudang_tujuan_id'   => 'required_if:tipe_tujuan,GUDANG|nullable|exists:gudang,id',
            'aset_ids'           => 'required|array|min:1',
            'aset_ids.*'         => 'exists:aset,id',
            'tanggal_pengalihan' => 'required|date',
            'alasan'             => 'required|string|min:5|max:1000',
            'catatan'            => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->jsonError('Validasi data gagal.', 422, $validator->errors());
        }

        $isGlobalAdmin = (bool) ($user && ($user->hasRole(['super_admin', 'admin_logistik'])));

        // Di sistem SIMLOGRS, pengalihan aset adalah INBOUND (meminta aset dari fasilitas lain masuk ke fasilitas pemohon)
        if (! $isGlobalAdmin) {
            $tipeTujuan = $user->unit_id ? 'RUANGAN' : ($user->gudang_id ? 'GUDANG' : 'RUANGAN');
            $unitTujuanId = $user->unit_id;
            $gudangTujuanId = $user->gudang_id;
        } else {
            $tipeTujuan = $request->input('tipe_tujuan', 'RUANGAN');
            $unitTujuanId = $request->input('unit_tujuan_id');
            $gudangTujuanId = $request->input('gudang_tujuan_id');
        }

        // Resolusi lokasi asal dari aset fisik yang dipilih
        $firstAsetId = $request->input('aset_ids')[0] ?? null;
        $firstAset = $firstAsetId ? Aset::find($firstAsetId) : null;

        $tipeAsal = $request->input('tipe_asal') ?? ($firstAset?->unit_id ? 'RUANGAN' : ($firstAset?->gudang_id ? 'GUDANG' : 'RUANGAN'));
        $unitAsalId = $request->input('unit_asal_id') ?? $firstAset?->unit_id;
        $gudangAsalId = $request->input('gudang_asal_id') ?? $firstAset?->gudang_id;

        // Anti self-transfer guard
        if ($tipeAsal === 'RUANGAN' && $tipeTujuan === 'RUANGAN' && (int) $unitAsalId === (int) $unitTujuanId) {
            return $this->jsonError('Unit asal dan unit tujuan pengalihan tidak boleh sama. Aset sudah berada di unit Anda.', 422);
        }
        if ($tipeAsal === 'GUDANG' && $tipeTujuan === 'GUDANG' && (int) $gudangAsalId === (int) $gudangTujuanId) {
            return $this->jsonError('Gudang asal dan gudang tujuan pengalihan tidak boleh sama.', 422);
        }

        try {
            $pengalihan = $service->ajukanPengalihan([
                'aset_ids'                => $request->input('aset_ids'),
                'tipe_asal'               => $tipeAsal,
                'unit_asal_id'            => $unitAsalId,
                'gudang_asal_id'          => $gudangAsalId,
                'tipe_tujuan'             => $tipeTujuan,
                'unit_tujuan_id'          => $unitTujuanId,
                'gudang_tujuan_id'        => $gudangTujuanId,
                'lokasi_gudang_tujuan_id' => $request->input('lokasi_gudang_tujuan_id'),
                'tanggal_pengalihan'      => $request->input('tanggal_pengalihan'),
                'alasan'                  => $request->input('alasan'),
                'catatan'                 => $request->input('catatan'),
                'diajukan_oleh'           => $user->id,
            ]);

            return $this->jsonSuccess([
                'id'     => $pengalihan->id,
                'kode'   => $pengalihan->kode,
                'status' => $pengalihan->status,
            ], "Pengalihan aset {$pengalihan->kode} berhasil diajukan.");
        } catch (Exception $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    /**
     * Otorisasi pengalihan aset (APPROVE / REJECT).
     * POST /v1/aset/pengalihan/{id}/otorisasi
     */
    public function otorisasiPengalihan(Request $request, int $id, PengalihanAsetService $service): JsonResponse
    {
        $user = $request->user();
        $aksi = strtoupper($request->input('aksi', 'APPROVE'));
        $catatan = $request->input('catatan');

        try {
            if ($aksi === 'REJECT') {
                $pengalihan = $service->tolakPengalihan($id, $user->id, $catatan);
                return $this->jsonSuccess(['id' => $pengalihan->id, 'status' => 'DITOLAK'], "Pengalihan aset {$pengalihan->kode} berhasil ditolak.");
            } else {
                $pengalihan = $service->setujuiPengalihan($id, $user->id, $catatan);
                return $this->jsonSuccess(['id' => $pengalihan->id, 'status' => 'DISETUJUI'], "Pengalihan aset {$pengalihan->kode} berhasil disetujui.");
            }
        } catch (Exception $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    /**
     * Konfirmasi BAST serah terima fisik aset.
     * POST /v1/aset/pengalihan/{id}/serah-terima
     */
    public function serahTerimaPengalihan(Request $request, int $id, PengalihanAsetService $service): JsonResponse
    {
        $user = $request->user();

        try {
            $pengalihan = $service->eksekusiSerahTerima($id, $user->id);
            return $this->jsonSuccess(['id' => $pengalihan->id, 'status' => 'SELESAI'], "Serah terima fisik aset {$pengalihan->kode} berhasil. Status: SELESAI.");
        } catch (Exception $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    /**
     * Batalkan pengalihan aset.
     * POST /v1/aset/pengalihan/{id}/batal
     */
    public function batalPengalihan(Request $request, int $id, PengalihanAsetService $service): JsonResponse
    {
        $user = $request->user();
        $alasan = $request->input('alasan', 'Dibatalkan oleh pemohon');

        try {
            $pengalihan = $service->batalkanPengalihan($id, $user->id, $alasan);
            return $this->jsonSuccess(['id' => $pengalihan->id, 'status' => 'DIBATALKAN'], "Pengalihan aset {$pengalihan->kode} berhasil dibatalkan.");
        } catch (Exception $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

}
