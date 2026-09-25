<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Aset;
use App\Models\PermintaanPeminjaman;
use App\Models\PermintaanPeminjamanDetail;
use App\Models\StokBatch;
use App\Services\Aset\PeminjamanAsetService;
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
        $aset = Aset::with(['barang.kategori', 'barang.satuan', 'unit', 'gudang', 'lokasiGudang'])
            ->whereRaw('LOWER(kode_aset) = ?', [strtolower($query)])
            ->orWhereRaw('LOWER(nomor_seri) = ?', [strtolower($query)])
            ->orWhereHas('barang', fn ($q) => $q->whereRaw('LOWER(nama) LIKE ?', ['%' . strtolower($query) . '%']))
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

        // Cari transaksi peminjaman aktif jika aset sedang berstatus DIPINJAM
        $peminjamanAktif = null;
        if ($aset->status === 'DIPINJAM') {
            $peminjamanAktif = PermintaanPeminjaman::whereHas('detail', fn ($q) => $q->where('aset_id', $aset->id))
                ->where('status', 'DIPINJAM')
                ->with(['unitPeminjam', 'pengaju'])
                ->latest('id')
                ->first();
        }

        return $this->jsonSuccess([
            'id'                => $aset->id,
            'kode_aset'         => $aset->kode_aset,
            'nomor_seri'        => $aset->nomor_seri ?? '-',
            'nama_komoditas'    => $aset->barang?->nama ?? '-',
            'kategori'          => $aset->barang?->kategori?->nama ?? '-',
            'status'            => $aset->status,
            'kondisi'           => $aset->kondisi,
            'lokasi_tercatat'   => $lokasiSaatIni,
            'penanggung_jawab'  => $peminjamanAktif ? $peminjamanAktif->unitPeminjam?->nama : $lokasiSaatIni,
            'peminjaman_aktif'  => $peminjamanAktif ? [
                'peminjaman_id'           => $peminjamanAktif->id,
                'kode_pinjam'             => $peminjamanAktif->kode,
                'unit_peminjam'           => $peminjamanAktif->unitPeminjam?->nama,
                'tanggal_pinjam'          => $peminjamanAktif->tanggal_pinjam?->format('Y-m-d'),
                'tanggal_kembali_rencana' => $peminjamanAktif->tanggal_kembali_rencana?->format('Y-m-d'),
                'is_overdue'              => now()->startOfDay()->gt($peminjamanAktif->tanggal_kembali_rencana),
            ] : null,
        ], 'Data aset berhasil ditemukan.');
    }

    /**
     * Ambil profil fisik dan status terkini aset berdasarkan ID.
     * GET /v1/aset/detail/{id}
     */
    public function detailAset(int $id): JsonResponse
    {
        $aset = Aset::with(['barang.kategori', 'barang.satuan', 'unit', 'gudang', 'lokasiGudang'])->find($id);
        if (! $aset) {
            return $this->jsonError('Data aset tidak ditemukan.', 404);
        }

        $lokasiSaatIni = $aset->unit ? $aset->unit->nama : ($aset->gudang ? $aset->gudang->nama . ($aset->lokasiGudang ? " (Rak: {$aset->lokasiGudang->nama})" : '') : '-');

        $peminjamanAktif = null;
        if ($aset->status === 'DIPINJAM') {
            $peminjamanAktif = PermintaanPeminjaman::whereHas('detail', fn ($q) => $q->where('aset_id', $aset->id))
                ->where('status', 'DIPINJAM')
                ->with(['unitPeminjam', 'pengaju'])
                ->latest('id')
                ->first();
        }

        return $this->jsonSuccess([
            'id'                => $aset->id,
            'kode_aset'         => $aset->kode_aset,
            'nomor_seri'        => $aset->nomor_seri ?? '-',
            'nama_komoditas'    => $aset->barang?->nama ?? '-',
            'kategori'          => $aset->barang?->kategori?->nama ?? '-',
            'status'            => $aset->status,
            'kondisi'           => $aset->kondisi,
            'lokasi_tercatat'   => $lokasiSaatIni,
            'penanggung_jawab'  => $peminjamanAktif ? $peminjamanAktif->unitPeminjam?->nama : $lokasiSaatIni,
            'peminjaman_aktif'  => $peminjamanAktif ? [
                'peminjaman_id'           => $peminjamanAktif->id,
                'kode_pinjam'             => $peminjamanAktif->kode,
                'unit_peminjam'           => $peminjamanAktif->unitPeminjam?->nama,
                'tanggal_pinjam'          => $peminjamanAktif->tanggal_pinjam?->format('Y-m-d'),
                'tanggal_kembali_rencana' => $peminjamanAktif->tanggal_kembali_rencana?->format('Y-m-d'),
                'is_overdue'              => now()->startOfDay()->gt($peminjamanAktif->tanggal_kembali_rencana),
            ] : null,
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

        $aset = Aset::query()
            ->where('status', 'TERSEDIA')
            ->where('kondisi', 'BAIK')
            ->with(['barang.kategori:id,nama', 'barang.satuan:id,nama', 'barang.merek:id,nama', 'unit:id,kode,nama', 'gudang:id,kode,nama', 'lokasiGudang:id,kode,nama'])
            // Jangan meminjam aset dari ruangan sendiri
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
            if ($user->unit_id) {
                if ($perspektif === 'penyedia') {
                    $query->where('tipe_penyedia', 'RUANGAN')->where('unit_penyedia_id', $user->unit_id);
                } else {
                    $query->where('unit_id', $user->unit_id);
                }
            } elseif ($user->gudang_id) {
                $query->where('tipe_penyedia', 'GUDANG')->where('gudang_penyedia_id', $user->gudang_id);
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

        if (! $user->can('create_layanan_unit::peminjaman::aset') &&
            ! $user->hasRole('petugas_unit') &&
            ! $user->hasRole('kepala_unit') &&
            ! $user->hasRole('super_admin')) {
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
            return $this->jsonError('Pengguna wajib terdaftar dalam unit pelayanan peminjam.', 422);
        }

        $aset = Aset::where('id', $request->input('aset_id'))
            ->where('status', 'TERSEDIA')
            ->where('kondisi', 'BAIK')
            ->first();

        if (! $aset) {
            return $this->jsonError('Aset yang dipilih tidak tersedia untuk dipinjam.', 422);
        }

        $isRuangan = $aset->unit_id !== null;
        $unitPenyediaId = $isRuangan ? $aset->unit_id : null;
        $gudangPenyediaId = ! $isRuangan ? $aset->gudang_id : null;

        if ($isRuangan && (int) $unitPenyediaId === (int) $unitPeminjamId) {
            return $this->jsonError('Unit penyedia tidak boleh sama dengan unit peminjam.', 422);
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
                $user->hasRole('kepala_unit')
            ));

        if (! $canCancel) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk membatalkan pengajuan ini.', 403);
        }

        $pinjam->update([
            'status'  => 'DITOLAK',
            'catatan' => ($pinjam->catatan ? $pinjam->catatan . "\n" : '') . '[Dibatalkan oleh pemohon pada ' . now()->format('d/m/Y H:i') . ']',
        ]);

        return $this->jsonSuccess(null, "Pengajuan peminjaman {$pinjam->kode} berhasil dibatalkan.");
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
            $user->hasRole('kepala_gudang') ||
            $user->hasRole('admin_logistik') ||
            $user->hasRole('super_admin');

        if (! $canApprove) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk mengakses otorisasi peminjaman aset.', 403);
        }

        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('admin_logistik');

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

        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('admin_logistik');
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

        if ($pinjam->status !== 'DISETUJUI') {
            return $this->jsonError("Permintaan {$pinjam->kode} tidak dalam status siap serah terima (saat ini: {$pinjam->status}).", 422);
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

    /**
     * Konfirmasi pengembalian dan inspeksi fisik aset via mobile.
     */
    public function kembalikan(Request $request, PeminjamanAsetService $service): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'peminjaman_id'                             => 'required|exists:permintaan_peminjaman,id',
            'inspeksi'                                  => 'required|array|min:1',
            'inspeksi.*.permintaan_peminjaman_detail_id' => 'required|exists:permintaan_peminjaman_detail,id',
            'inspeksi.*.aset_id'                        => 'required|exists:aset,id',
            'inspeksi.*.kondisi_saat_kembali'           => 'required|in:BAIK,RUSAK_RINGAN,RUSAK_BERAT',
            'inspeksi.*.catatan'                        => 'nullable|string|max:500',
            'catatan'                                   => 'nullable|string|max:1000',
        ]);

        $pinjam = PermintaanPeminjaman::with('detail')->find($request->input('peminjaman_id'));

        if ($pinjam->status !== 'DIPINJAM') {
            return $this->jsonError("Peminjaman {$pinjam->kode} tidak dalam status aktif dipinjam (saat ini: {$pinjam->status}).", 422);
        }

        try {
            $pengembalian = $service->prosesPengembalian(
                $pinjam,
                $request->input('inspeksi'),
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
}
