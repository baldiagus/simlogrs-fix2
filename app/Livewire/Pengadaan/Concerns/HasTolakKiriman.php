<?php

namespace App\Livewire\Pengadaan\Concerns;

use App\Models\Gudang;
use App\Services\Pengadaan\PenerimaanPoService;
use Illuminate\Validation\Rule;

trait HasTolakKiriman
{
    public function openRejectModal(): void
    {
        abort_unless(auth()->user()?->can('create_gudang_stok::penerimaan::po'), 403);

        $this->validate([
            'nomor_surat_jalan' => 'required|string|min:3|max:100',
            'gudang_id'         => ['required', Rule::exists('gudang', 'id')->where('status', 'AKTIF')],
        ], [
            'nomor_surat_jalan.required' => 'Nomor surat jalan vendor wajib diisi sebelum menolak kiriman.',
            'nomor_surat_jalan.min'      => 'Nomor surat jalan minimal 3 karakter.',
            'gudang_id.required'         => 'Gudang penerima logistik wajib dipilih.',
        ]);
        $this->authorizeGudangPenerima();

        $this->alasan_penolakan = '';
        $this->showModal = false;
        $this->showRejectModal = true;
    }

    public function executeTolakKiriman(): void
    {
        abort_unless(auth()->user()?->can('create_gudang_stok::penerimaan::po'), 403);

        $this->validate([
            'nomor_surat_jalan' => 'required|string|min:3|max:100',
            'gudang_id'         => ['required', Rule::exists('gudang', 'id')->where('status', 'AKTIF')],
            'alasan_penolakan'  => 'required|string|min:5|max:1000',
        ], [
            'nomor_surat_jalan.required' => 'Nomor surat jalan vendor wajib diisi.',
            'gudang_id.required'         => 'Gudang penerima logistik wajib dipilih.',
            'alasan_penolakan.required'  => 'Alasan penolakan barang wajib dijelaskan.',
            'alasan_penolakan.min'       => 'Alasan penolakan minimal 5 karakter.',
        ]);
        $this->authorizeGudangPenerima();

        $service = app(PenerimaanPoService::class);
        $rcvKode = $service->tolakKiriman(
            $this->poId,
            $this->gudang_id,
            $this->nomor_surat_jalan,
            $this->alasan_penolakan,
            auth()->user()
        );

        if (! $rcvKode) {
            $this->showRejectModal = false;
            $this->showModal = false;
            $this->toast('Pesanan pembelian sudah tidak dalam status yang dapat menerima dokumen penolakan. Muat ulang data PO.', 'danger');
            return;
        }

        $poKode = $this->po?->kode ?? 'PO';
        $this->showRejectModal = false;
        $this->showModal = false;
        $this->activeTab = 'riwayat';
        $this->filterStatus = '';
        $this->search = '';
        $this->resetPage();
        $this->toast("Kiriman fisik {$poKode} berhasil ditolak. Berita acara penolakan diterbitkan dengan nomor {$rcvKode}.", 'info');
    }
}
