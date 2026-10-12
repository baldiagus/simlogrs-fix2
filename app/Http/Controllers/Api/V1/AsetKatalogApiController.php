<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StatusAset;
use App\Enums\StatusPeminjaman;
use App\Http\Controllers\Api\ApiController;
use App\Models\Aset;
use App\Models\PengalihanAsetDetail;
use App\Models\PermintaanPeminjaman;
use App\Models\PermintaanPeminjamanDetail;
use App\Models\StokBatch;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AsetKatalogApiController extends ApiController
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
            ->whereIn('status', StatusPeminjaman::aktif())
            ->with(['unitPeminjam', 'pengaju'])
            ->latest('id')
            ->first();

        if ($transaksiPinjam) {
            if ($transaksiPinjam->status === StatusPeminjaman::Dipinjam->value) {
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
        $riwayatPengalihan = $this->getRiwayatPengalihan($aset->id);
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
            'total_riwayat_pengalihan' => $riwayatPengalihan->count(),
            'servis_terakhir'          => $latestMaintenance ? [
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
            'riwayat_pengalihan'       => $riwayatPengalihan,
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
            ->whereIn('status', StatusPeminjaman::aktif())
            ->with(['unitPeminjam', 'pengaju'])
            ->latest('id')
            ->first();

        if ($transaksiPinjam) {
            if ($transaksiPinjam->status === StatusPeminjaman::Dipinjam->value) {
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
        $riwayatPengalihan = $this->getRiwayatPengalihan($aset->id);
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
            'total_riwayat_pengalihan' => $riwayatPengalihan->count(),
            'servis_terakhir'          => $latestMaintenance ? [
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
            'riwayat_pengalihan'       => $riwayatPengalihan,
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
                $q->whereIn('status', StatusAset::dapatDialihkan())
                    ->whereNotIn('kondisi', ['RUSAK_BERAT', 'DIHAPUSKAN'])
                    ->whereDoesntHave('permintaanMaintenance', function ($sub) {
                        $sub->whereNotIn('status', ['SELESAI', 'DITOLAK', 'DIBATALKAN']);
                    });
            }, function ($q) {
                $q->where('status', StatusAset::Tersedia->value)
                    ->where('kondisi', 'BAIK');
            })
            // Kunci integritas: kecualikan aset yang sedang dalam proses pengajuan atau aktif dipinjam
            ->whereDoesntHave('peminjamanDetail.permintaanPeminjaman', function ($q) {
                $q->whereIn('status', StatusPeminjaman::aktif());
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
     * Mengambil riwayat kronologis pengalihan dan mutasi fisik permanen aset.
     */
    private function getRiwayatPengalihan(int $asetId)
    {
        return PengalihanAsetDetail::where('aset_id', $asetId)
            ->with([
                'pengalihanAset.unitAsal',
                'pengalihanAset.gudangAsal',
                'pengalihanAset.unitTujuan',
                'pengalihanAset.gudangTujuan',
                'pengalihanAset.pengaju',
                'pengalihanAset.penerima',
            ])
            ->whereHas('pengalihanAset', fn ($q) => $q->where('status', 'SELESAI'))
            ->get()
            ->sortByDesc(fn ($d) => $d->pengalihanAset?->tanggal_serah_terima ?? $d->pengalihanAset?->dibuat_pada)
            ->map(function ($d) {
                $p = $d->pengalihanAset;
                return [
                    'id'                   => $d->id,
                    'pengalihan_id'        => $p?->id,
                    'kode_pengalihan'      => $p?->kode ?? '-',
                    'asal_nama'            => $p?->nama_lokasi_asal ?? '-',
                    'tujuan_nama'          => $p?->nama_lokasi_tujuan ?? '-',
                    'tanggal_pengalihan'   => $p?->tanggal_pengalihan?->format('Y-m-d') ?? '-',
                    'tanggal_serah_terima' => $p?->tanggal_serah_terima?->format('Y-m-d H:i') ?? ($p?->tanggal_serah_terima?->format('Y-m-d') ?? '-'),
                    'status'               => $p?->status ?? 'SELESAI',
                    'alasan'               => $p?->alasan ?? '-',
                    'kondisi_sebelum'      => $d->kondisi_sebelum ?? 'BAIK',
                    'kondisi_saat_diterima'=> $d->kondisi_saat_diterima ?? 'BAIK',
                    'pengaju'              => $p?->pengaju?->nama ?? '-',
                    'penerima'             => $p?->penerima?->nama ?? '-',
                    'catatan'              => $d->catatan ?: $p?->catatan,
                ];
            })
            ->values();
    }
}
