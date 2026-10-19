<?php

namespace App\Livewire\Aset\Concerns;

use App\Enums\Aset\StatusPeminjaman;
use App\Models\Aset\Aset;
use App\Models\Aset\PermintaanPeminjaman;
use Carbon\Carbon;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

trait HasAksiPeminjaman
{
    /**
     * Buka modal formulir permohonan perpanjangan masa peminjaman aset.
     */
    public function openPerpanjang(int $id): void
    {
        abort_unless(auth()->user()?->can('update_layanan_unit::peminjaman::aset') || auth()->user()?->can('create_layanan_unit::peminjaman::aset'), 403);

        $pinjam = PermintaanPeminjaman::findOrFail($id);
        if (! in_array($pinjam->status, StatusPeminjaman::aktif(), true)) {
            $this->toast('Hanya peminjaman aktif yang dapat diperpanjang.', 'error');
            return;
        }

        $this->perpanjangId = $pinjam->id;
        $this->tanggal_kembali_baru = $pinjam->tanggal_kembali_rencana
            ? $pinjam->tanggal_kembali_rencana->copy()->addDays(7)->toDateString()
            : date('Y-m-d');
        $this->alasan_perpanjang = '';
        $this->showPerpanjangModal = true;
    }

    /**
     * Validasi dan ajukan perpanjangan durasi peminjaman aset.
     */
    public function submitPerpanjang(): void
    {
        abort_unless(auth()->user()?->can('update_layanan_unit::peminjaman::aset') || auth()->user()?->can('create_layanan_unit::peminjaman::aset'), 403);

        $this->validate([
            'tanggal_kembali_baru' => 'required|date',
            'alasan_perpanjang'    => 'required|string|max:500',
        ], [
            'tanggal_kembali_baru.required' => 'Tanggal rencana pengembalian baru wajib diisi.',
            'alasan_perpanjang.required'    => 'Alasan perpanjangan wajib dicantumkan.',
        ]);

        $pinjam = PermintaanPeminjaman::findOrFail($this->perpanjangId);
        $user = auth()->user();
        $isGlobalAdmin = $user && $user->hasRole(['super_admin', 'admin_logistik']);

        if (! $isGlobalAdmin && (int) $user?->unit_id !== (int) $pinjam->unit_id) {
            abort(403, 'Anda tidak berwenang memperpanjang permohonan peminjaman dari unit lain.');
        }

        if ($this->tanggal_kembali_baru <= $pinjam->tanggal_pinjam->toDateString()) {
            $this->addError('tanggal_kembali_baru', 'Tanggal kembali baru harus lebih lambat dari tanggal pinjam.');
            return;
        }

        $catatanLama = $pinjam->catatan ? $pinjam->catatan . "\n" : '';
        $user = auth()->user();
        $logPerpanjangan = '[Perpanjangan ' . now()->format('d/m/Y H:i') . " oleh {$user->nama}]: " .
            'Rencana kembali diperpanjang dari ' . $pinjam->tanggal_kembali_rencana->format('d/m/Y') .
            ' menjadi ' . Carbon::parse($this->tanggal_kembali_baru)->format('d/m/Y') .
            '. Alasan: ' . $this->alasan_perpanjang;

        $pinjam->update([
            'tanggal_kembali_rencana' => $this->tanggal_kembali_baru,
            'catatan'                 => $catatanLama . $logPerpanjangan,
        ]);

        $this->showPerpanjangModal = false;
        $this->toast("Rencana pengembalian '{$pinjam->kode}' berhasil diperpanjang.", 'success');
    }

    /**
     * Tampilkan modal dialog konfirmasi pembatalan transaksi.
     */
    public function confirmBatal(int $id): void
    {
        $pinjam = PermintaanPeminjaman::findOrFail($id);
        if ($pinjam->status !== StatusPeminjaman::Diajukan->value) {
            $this->toast('Hanya peminjaman berstatus DIAJUKAN yang dapat dibatalkan.', 'error');
            return;
        }

        abort_unless($this->canCancelPeminjaman($pinjam), 403, 'Anda tidak memiliki wewenang untuk membatalkan pengajuan ini.');

        $this->askConfirmation(
            target: $id,
            action: 'batalkanPinjam',
            title: 'Batalkan Pengajuan Peminjaman',
            description: "Apakah Anda yakin ingin membatalkan pengajuan peminjaman {$pinjam->kode}?",
            buttonText: 'Ya, Batalkan',
            variant: 'danger'
        );
    }

    /**
     * Eksekusi pembatalan transaksi peminjaman aset di database.
     */
    public function batalkanPinjam(int $id): void
    {
        $pinjam = PermintaanPeminjaman::findOrFail($id);
        if ($pinjam->status !== StatusPeminjaman::Diajukan->value) {
            $this->toast('Hanya peminjaman berstatus DIAJUKAN yang dapat dibatalkan.', 'error');
            return;
        }

        abort_unless($this->canCancelPeminjaman($pinjam), 403, 'Anda tidak memiliki wewenang untuk membatalkan pengajuan ini.');

        $pinjam->update([
            'status'  => StatusPeminjaman::Dibatalkan->value,
            'catatan' => ($pinjam->catatan ? $pinjam->catatan . "\n" : '') . '[Dibatalkan oleh pemohon pada ' . now()->format('d/m/Y H:i') . ']',
        ]);

        $this->toast("Pengajuan peminjaman '{$pinjam->kode}' berhasil dibatalkan.", 'success');
    }

    /**
     * Pembatalan hanya untuk status DIAJUKAN oleh pembuat dokumen atau admin global.
     * Kepala unit tidak boleh membatalkan dokumen staf; non-admin wajib satu unit dan punya izin.
     */
    public function canCancelPeminjaman(PermintaanPeminjaman $pinjam): bool
    {
        $user = auth()->user();

        if (! $user || $pinjam->status !== StatusPeminjaman::Diajukan->value) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'admin_logistik'])) {
            return true;
        }

        return (int) $pinjam->diajukan_oleh === (int) $user->id
            && $user->unit_id
            && (int) $user->unit_id === (int) $pinjam->unit_id
            && ($user->can('create_layanan_unit::peminjaman::aset') || $user->can('delete_layanan_unit::peminjaman::aset'));
    }

    /**
     * Tampilkan modal konfirmasi serah terima peminjaman aset.
     */
    public function confirmSerahTerima(int $id): void
    {
        $pinjam = PermintaanPeminjaman::with(['unitPeminjam', 'detail.aset'])->findOrFail($id);
        $this->authorizePeminjaman($pinjam, 'serah_terima', 'Hanya fasilitas penyedia dengan izin yang berhak melakukan serah terima fisik.');
        if ($pinjam->status !== StatusPeminjaman::Disetujui->value) {
            $this->toast('Hanya peminjaman berstatus DISETUJUI yang dapat diserahterimakan.', 'danger');
            return;
        }

        $this->askConfirmation(
            target: $id,
            action: 'serahTerima',
            title: 'Konfirmasi Serah Terima Fisik Aset',
            description: "Lakukan serah terima fisik aset untuk {$pinjam->kode} ke unit {$pinjam->unitPeminjam?->nama}? Tanggung jawab aset akan beralih ke peminjam dan status menjadi DIPINJAM.",
            buttonText: 'Ya, Serah Terima Fisik',
            variant: 'primary'
        );
    }

    /**
     * Eksekusi serah terima fisik aset kepada peminjam.
     */
    public function serahTerima(int $id): void
    {
        $user = auth()->user();
        $pinjam = PermintaanPeminjaman::with('detail.aset')->findOrFail($id);

        $this->authorizePeminjaman($pinjam, 'serah_terima', 'Hanya fasilitas penyedia dengan izin yang berhak melakukan serah terima fisik.');

        if ($pinjam->status !== StatusPeminjaman::Disetujui->value) {
            $this->toast('Hanya peminjaman berstatus DISETUJUI yang dapat diserahterimakan.', 'danger');
            return;
        }

        try {
            app(\App\Services\Aset\PeminjamanAsetService::class)->serahTerimaFisik($pinjam, $user);
            $this->showDetailModal = false;
            $this->toast("Serah terima fisik {$pinjam->kode} berhasil. Aset resmi berstatus DIPINJAM.", 'success');
        } catch (\Throwable $e) {
            $this->toast($e->getMessage(), 'danger');
        }
    }

    /**
     * Tampilkan kode QR atau barcode aset terpilih.
     */
    public function showQr(int $asetId): void
    {
        $this->selectedAsetQr = Aset::with(['barang', 'unit', 'gudang'])->findOrFail($asetId);
        $this->qrCodeSvg = (string) QrCode::size(200)->format('svg')->generate($this->selectedAsetQr->kode_aset);
        $this->showQrModal = true;
    }
}
