<?php

namespace App\Http\Controllers\Api\V1\Aset;

use App\Enums\Aset\StatusAset;
use App\Enums\Aset\StatusPeminjaman;
use App\Http\Controllers\Api\ApiController;
use App\Models\Aset\Aset;
use App\Models\Aset\PermintaanPeminjaman;
use App\Models\Aset\PermintaanPeminjamanDetail;
use App\Models\Master\Unit;
use App\Support\Generator\CodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AsetPeminjamanApiController extends ApiController
{
    /**
     * Riwayat daftar transaksi peminjaman aset unit pengguna.
     * GET /v1/aset/peminjaman
     */
    public function daftarPeminjaman(Request $request): JsonResponse
    {
        $user = $request->user();
        $perspektif = $request->query('perspektif', 'peminjam'); // peminjam atau penyedia
        $filterStatus = $request->query('status'); // DIAJUKAN, DISETUJUI, DIPINJAM, SELESAI, DITOLAK

        $isGlobalAdmin = $user->hasRole(['super_admin', 'admin_logistik']);

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
            ->map(fn ($p) => $this->formatBarisPeminjaman($p));

        return $this->jsonSuccess($list, 'Daftar peminjaman aset berhasil diambil.');
    }

    private function formatBarisPeminjaman(PermintaanPeminjaman $p): array
    {
        $firstDetail = $p->detail->first();
        $aset = $firstDetail?->aset;
        $isOverdue = $p->status === StatusPeminjaman::Dipinjam->value && $p->tanggal_kembali_rencana && now()->startOfDay()->gt($p->tanggal_kembali_rencana);

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
            $user->hasRole(['super_admin', 'admin_logistik'])
        );

        if (! $canPinjam) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk mengajukan peminjaman aset.', 403);
        }

        $validator = $this->validatorPengajuan($request);

        if ($validator->fails()) {
            return $this->jsonError('Validasi data gagal.', 422, $validator->errors());
        }

        $unitPeminjamId = $user->unit_id;
        if (! $unitPeminjamId) {
            return $this->jsonError('Anda harus terdaftar pada unit/ruangan untuk mengajukan peminjaman aset.', 422);
        }

        $aset = Aset::where('id', $request->input('aset_id'))
            ->where('status', StatusAset::Tersedia->value)
            ->where('kondisi', 'BAIK')
            ->first();
        if (! $aset) {
            return $this->jsonError('Aset yang dipilih tidak tersedia untuk dipinjam.', 422);
        }

        // Kunci integritas: periksa apakah aset sedang dalam transaksi peminjaman aktif lain
        $isSedangDipinjam = PermintaanPeminjaman::whereIn('status', StatusPeminjaman::aktif())
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
            $pinjam = DB::transaction(fn () => $this->buatPengajuanPeminjaman($request, $user, $unitPeminjamId, $aset, $isRuangan, $unitPenyediaId, $gudangPenyediaId));

            return $this->jsonSuccess([
                'id'     => $pinjam->id,
                'kode'   => $pinjam->kode,
                'status' => $pinjam->status,
            ], "Permintaan peminjaman aset {$pinjam->kode} berhasil diajukan.", 201);
        } catch (\Throwable $e) {
            return $this->jsonError('Gagal mengajukan peminjaman: ' . $e->getMessage(), 500);
        }
    }

    private function buatPengajuanPeminjaman(Request $request, $user, $unitPeminjamId, Aset $aset, bool $isRuangan, $unitPenyediaId, $gudangPenyediaId): PermintaanPeminjaman
    {
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
            'status'                  => StatusPeminjaman::Diajukan->value,
            'catatan'                 => $request->input('catatan'),
        ]);

        PermintaanPeminjamanDetail::create([
            'permintaan_peminjaman_id' => $peminjaman->id,
            'aset_id'                  => $aset->id,
            'kondisi_saat_pinjam'      => $aset->kondisi,
            'catatan'                  => $request->input('catatan'),
        ]);

        return $peminjaman;
    }

    private function validatorPengajuan(Request $request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
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

        if ($pinjam->status !== StatusPeminjaman::Diajukan->value) {
            return $this->jsonError("Hanya peminjaman berstatus DIAJUKAN yang dapat dibatalkan (saat ini: {$pinjam->status}).", 422);
        }

        $isGlobalAdmin = $user->hasRole(['super_admin', 'admin_logistik']);
        $canCancel = $isGlobalAdmin ||
            ((int) $pinjam->diajukan_oleh === (int) $user->id && $user->can('delete_layanan_unit::peminjaman::aset'));

        if (! $canCancel) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk membatalkan pengajuan ini.', 403);
        }

        $pinjam->update([
            'status'  => StatusPeminjaman::Dibatalkan->value,
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

        if ($pinjam->status !== StatusPeminjaman::Dipinjam->value) {
            return $this->jsonError("Hanya peminjaman dengan status DIPINJAM yang dapat diperpanjang (saat ini: {$pinjam->status}).", 422);
        }

        $isGlobalAdmin = $user->hasRole(['super_admin', 'admin_logistik']);
        $canExtend = $isGlobalAdmin ||
            ((int) $pinjam->diajukan_oleh === (int) $user->id && $user->can('update_layanan_unit::peminjaman::aset'));

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
}
