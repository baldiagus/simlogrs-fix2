<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Aset;
use App\Models\StokBatch;
use App\Models\PermintaanPeminjaman;
use App\Services\Aset\PeminjamanAsetService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'peminjaman_id'                         => 'required|exists:permintaan_peminjaman,id',
            'inspeksi'                              => 'required|array|min:1',
            'inspeksi.*.permintaan_peminjaman_detail_id' => 'required|exists:permintaan_peminjaman_detail,id',
            'inspeksi.*.aset_id'                    => 'required|exists:aset,id',
            'inspeksi.*.kondisi_saat_kembali'       => 'required|in:BAIK,RUSAK_RINGAN,RUSAK_BERAT',
            'inspeksi.*.catatan'                    => 'nullable|string|max:500',
            'catatan'                               => 'nullable|string|max:1000',
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
