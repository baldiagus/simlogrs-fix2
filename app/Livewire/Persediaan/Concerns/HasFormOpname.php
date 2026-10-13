<?php

namespace App\Livewire\Persediaan\Concerns;

use App\Enums\Persediaan\StatusPenyesuaianStok;
use App\Enums\Persediaan\StatusStokOpname;
use App\Models\Master\Gudang;
use App\Models\Persediaan\PenyesuaianStok;
use App\Models\Persediaan\PenyesuaianStokDetail;
use App\Models\Persediaan\StokBatch;
use App\Models\Persediaan\StokOpname;
use App\Models\Persediaan\StokOpnameDetail;
use App\Support\Generator\CodeGenerator;
use Illuminate\Support\Facades\DB;

trait HasFormOpname
{
    public function openInisiasi(): void
    {
        abort_unless(auth()->user()?->can('create_gudang_stok::stok::opname'), 403);

        $this->reset(['gudang_id', 'catatan']);
        $user = auth()->user();
        if ($user?->gudang_id) {
            $this->gudang_id = $user->gudang_id;
        }
        $this->tanggal_opname = now()->toDateString();
        $this->showInisiasiModal = true;
    }

    public function inisiasiOpname(): void
    {
        abort_unless(auth()->user()?->can('create_gudang_stok::stok::opname'), 403);

        $this->validate([
            'gudang_id'      => 'required|exists:gudang,id',
            'tanggal_opname' => 'required|date',
            'catatan'        => 'nullable|string',
        ], [
            'gudang_id.required'      => 'Fasilitas gudang yang diopname wajib dipilih.',
            'tanggal_opname.required' => 'Tanggal pelaksanaan opname fisik wajib diisi.',
        ]);
        $user = auth()->user();
        $isGlobalAdmin = $user?->hasRole(['super_admin', 'admin_logistik']);
        abort_unless($isGlobalAdmin || ($user?->gudang_id && (int) $user->gudang_id === (int) $this->gudang_id), 403, 'Anda hanya berhak menginisiasi opname di gudang Anda.');

        $gudang = Gudang::findOrFail($this->gudang_id);
        // Ambil seluruh batch aktif yang berada di gudang ini
        $batches = StokBatch::where('gudang_id', $gudang->id)
            ->where('status', 'AKTIF')
            ->get();

        if ($batches->isEmpty()) {
            $this->toast("Tidak ada stok batch aktif di gudang '{$gudang->nama}' untuk diopname.", 'danger');
            return;
        }

        $kodeOpn = CodeGenerator::generateOpn();

        DB::transaction(function () use ($kodeOpn, $gudang, $user, $batches) {
            $opname = StokOpname::create([
                'kode'           => $kodeOpn,
                'gudang_id'      => $gudang->id,
                'tanggal_opname' => $this->tanggal_opname,
                'status'         => StatusStokOpname::Berjalan->value,
                'catatan'        => $this->catatan,
                'dibuat_oleh'    => $user->id,
            ]);

            foreach ($batches as $b) {
                StokOpnameDetail::create([
                    'stok_opname_id' => $opname->id,
                    'barang_id'      => $b->barang_id,
                    'stok_batch_id'  => $b->id,
                    'jumlah_sistem'  => $b->jumlah,
                    'jumlah_fisik'   => 0,
                    'selisih'        => 0 - $b->jumlah,
                    'catatan'        => null,
                ]);
            }
        });

        $this->showInisiasiModal = false;
        $this->toast("Siklus Stock Opname '{$kodeOpn}' berhasil dibuka dengan status BERJALAN.", 'success');
    }

    public function openHitungFisik(int $opnameId): void
    {
        abort_unless(auth()->user()?->can('update_gudang_stok::stok::opname') || auth()->user()?->can('create_gudang_stok::stok::opname'), 403, 'Anda tidak memiliki wewenang mengakses formulir hitung fisik.');

        $this->activeOpname = StokOpname::with([
            'gudang',
            'detail.barang.satuan',
            'detail.stokBatch.lokasiGudang',
        ])->findOrFail($opnameId);
        $user = auth()->user();
        abort_unless($user?->hasRole(['super_admin', 'admin_logistik']) || ($user?->gudang_id && (int) $user->gudang_id === (int) $this->activeOpname->gudang_id), 403, 'Anda tidak memiliki wewenang melihat opname di gudang ini.');

        $this->countRows = [];
        foreach ($this->activeOpname->detail as $det) {
            $this->countRows[$det->id] = [
                'id'            => $det->id,
                'barang_nama'   => $det->barang?->nama,
                'barang_kode'   => $det->barang?->kode,
                'nomor_batch'   => $det->stokBatch?->nomor_batch,
                'lokasi_rak'    => $det->stokBatch?->lokasiGudang?->nama ?? 'Tanpa Rak',
                'satuan'        => $det->barang?->satuan?->simbol ?? 'Unit',
                'jumlah_sistem' => $det->jumlah_sistem,
                'jumlah_fisik'  => $det->jumlah_fisik,
                'catatan'       => $det->catatan ?? '',
            ];
        }

        $this->showCountModal = true;
    }

    public function saveHitungFisik(): void
    {
        if (! $this->activeOpname) {
            return;
        }
        abort_unless(auth()->user()?->can('update_gudang_stok::stok::opname') || auth()->user()?->can('create_gudang_stok::stok::opname'), 403, 'Anda tidak memiliki wewenang menginput hasil stock opname.');

        $user = auth()->user();
        $isGlobalAdmin = $user && $user->hasRole(['super_admin', 'admin_logistik']);
        if (! $isGlobalAdmin && (! $user?->gudang_id || (int) $user->gudang_id !== (int) $this->activeOpname->gudang_id)) {
            abort(403, 'Anda tidak memiliki wewenang memproses opname di fasilitas gudang ini.');
        }

        // Hitung fisik hanya untuk sesi yang belum diajukan/ditutup (ADR 0002 A3, selaras dengan API).
        $statusTerkini = StokOpname::whereKey($this->activeOpname->id)->value('status');
        if (! in_array($statusTerkini, [StatusStokOpname::Berjalan->value, StatusStokOpname::ProsesCount->value], true)) {
            $this->showCountModal = false;
            $this->toast("Sesi opname {$this->activeOpname->kode} telah ditutup dengan status {$statusTerkini}.", 'danger');
            return;
        }

        $user = auth()->user();

        DB::transaction(function () use ($user) {
            $hasDifference = false;
            $selisihRows = [];

            foreach ($this->countRows as $detId => $row) {
                $detail = StokOpnameDetail::with(['stokBatch', 'barang'])->findOrFail($detId);
                $fisik = max(0, (int) ($row['jumlah_fisik'] ?? 0));
                $selisih = $fisik - $detail->jumlah_sistem;

                $detail->update([
                    'jumlah_fisik' => $fisik,
                    'selisih'      => $selisih,
                    'catatan'      => $row['catatan'] ?: null,
                ]);

                if ($selisih != 0) {
                    $hasDifference = true;
                    $selisihRows[] = [
                        'barang_id'     => $detail->barang_id,
                        'stok_batch_id' => $detail->stok_batch_id,
                        'jumlah'        => abs($selisih),
                        'jenis'         => $selisih > 0 ? 'TAMBAH' : 'KURANG',
                        'harga_satuan'  => $detail->stokBatch?->harga_satuan ?? 0,
                        'subtotal'      => abs($selisih) * (float) ($detail->stokBatch?->harga_satuan ?? 0),
                        'catatan'       => $row['catatan'] ?: 'Selisih opname fisik',
                    ];
                }
            }

            if ($hasDifference) {
                // Ada selisih -> status MENUNGGU_APPROVAL & terbitkan dokumen PenyesuaianStok
                $this->activeOpname->update([
                    'status' => StatusStokOpname::MenungguApproval->value,
                ]);

                // Buat draft penyesuaian stok jika belum ada
                $existingAdj = PenyesuaianStok::where('stok_opname_id', $this->activeOpname->id)->first();
                if (! $existingAdj) {
                    $kodeAdj = CodeGenerator::generateAdj();
                    $penyesuaian = PenyesuaianStok::create([
                        'kode'                => $kodeAdj,
                        'stok_opname_id'      => $this->activeOpname->id,
                        'gudang_id'           => $this->activeOpname->gudang_id,
                        'tanggal_penyesuaian' => now()->toDateString(),
                        'alasan'              => "Selisih perhitungan fisik Stock Opname {$this->activeOpname->kode}",
                        'status'              => StatusPenyesuaianStok::Menunggu->value,
                        'diajukan_oleh'       => $user->id,
                    ]);

                    foreach ($selisihRows as $sr) {
                        PenyesuaianStokDetail::create([
                            'penyesuaian_stok_id' => $penyesuaian->id,
                            'barang_id'           => $sr['barang_id'],
                            'stok_batch_id'       => $sr['stok_batch_id'],
                            'jumlah_penyesuaian'  => $sr['jumlah'],
                            'jenis_penyesuaian'   => $sr['jenis'],
                            'harga_satuan'        => $sr['harga_satuan'],
                            'subtotal_nilai'      => $sr['subtotal'],
                            'catatan'             => $sr['catatan'],
                        ]);
                    }
                }
            } else {
                // Selisih = 0 -> Selesai / DISETUJUI hanya jika pengguna punya wewenang otorisasi
                $canAuthorize = $user->can('update_gudang_stok::otorisasi::penyesuaian') && $user->hasRole('kepala_gudang');

                $this->activeOpname->update([
                    'status'         => $canAuthorize ? StatusStokOpname::Disetujui->value : StatusStokOpname::MenungguApproval->value,
                    'disetujui_oleh' => $canAuthorize ? $user->id : null,
                ]);
            }
        });

        $this->showCountModal = false;
        $this->toast("Perhitungan fisik opname {$this->activeOpname->kode} berhasil disimpan.", 'success');
    }
}
