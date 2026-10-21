<?php

namespace App\Livewire\Ipsrs\Concerns;

use App\Enums\Ipsrs\StatusMaintenance;
use App\Enums\Ipsrs\StatusSpk;
use App\Models\Master\Barang;
use App\Models\Ipsrs\LogMaintenance;
use App\Models\Persediaan\PergerakanStok;
use App\Models\Persediaan\StokBatch;
use App\Models\Ipsrs\SukuCadangMaintenance;
use Illuminate\Support\Facades\DB;

trait HasSparepartLembarKerja
{
    /**
     * Tambahkan baris pilihan suku cadang baru pada lembar kerja pengerjaan.
     */
    public function addSparepartRow(): void
    {
        $this->sparepartItems[] = [
            'barang_id' => '',
            'jumlah'    => 1,
        ];
    }

    /**
     * Hapus baris suku cadang terpilih dari lembar kerja teknisi.
     */
    public function removeSparepartRow(int $index): void
    {
        unset($this->sparepartItems[$index]);
        $this->sparepartItems = array_values($this->sparepartItems);
    }

    /**
     * Dapatkan jumlah stok barang yang saat ini tersedia untuk diamprah.
     */
    public function getStokTersedia(int $barangId): int
    {
        return (int) StokBatch::where('barang_id', $barangId)
            ->where('status', 'AKTIF')
            ->where('jumlah', '>', 0)
            ->where(function ($q) {
                $q->whereNull('tanggal_kedaluwarsa')
                    ->orWhere('tanggal_kedaluwarsa', '>=', now()->toDateString());
            })
            ->sum('jumlah');
    }

    /**
     * Validasi kecukupan stok suku cadang di gudang sebelum mutasi pemakaian.
     */
    protected function validasiKetersediaanSparepart(): bool
    {
        foreach ($this->sparepartItems as $index => $item) {
            $barangId = (int) ($item['barang_id'] ?? 0);
            $jumlahDiminta = (int) ($item['jumlah'] ?? 0);

            if (! $barangId) {
                continue;
            }

            $stokTersedia = $this->getStokTersedia($barangId);
            $barang = Barang::with(['satuan', 'kategori'])->find($barangId);
            $isSparepart = $barang && (
                $barang->tipe === 'SPAREPART' ||
                str_contains(strtolower($barang->kategori?->nama ?? ''), 'sparepart')
            );

            if (! $isSparepart) {
                $this->addError("sparepartItems.{$index}.barang_id", "Komoditas yang dipilih bukan merupakan suku cadang / sparepart.");
                return false;
            }

            $namaBarang = $barang?->nama ?? "Barang #{$barangId}";
            $satuan = $barang?->satuan?->simbol ?? 'Unit';

            if ($stokTersedia <= 0) {
                $this->addError("sparepartItems.{$index}.barang_id", "Stok fisik '{$namaBarang}' saat ini habis (0 {$satuan}). Tidak dapat digunakan.");
                return false;
            }

            if ($jumlahDiminta > $stokTersedia) {
                $this->addError("sparepartItems.{$index}.jumlah", "Jumlah diminta ({$jumlahDiminta} {$satuan}) melebihi stok yang tersedia ({$stokTersedia} {$satuan}).");
                return false;
            }
        }

        return true;
    }

    /**
     * Validasi dan simpan lembar kerja teknisi serta penggunaan suku cadang.
     */
    protected function eksekusiSimpanLembarKerja($user, $spk, $tiket): void
    {
        DB::transaction(function () use ($user, $spk, $tiket) {
            // 1. Catat Log Lembar Kerja
            $log = LogMaintenance::create([
                'permintaan_maintenance_id' => $tiket->id,
                'penugasan_maintenance_id'  => $spk->id,
                'dikerjakan_oleh'           => $user->id,
                'tanggal_pengerjaan'        => $this->tanggal_pengerjaan,
                'jam_mulai'                 => $this->jam_mulai,
                'jam_selesai'               => $this->jam_selesai,
                'diagnosa'                  => $this->diagnosa,
                'tindakan'                  => $this->tindakan,
                'hasil'                     => $this->hasil,
                'catatan'                   => $this->catatan,
            ]);

            // 2. Eksekusi Pemotongan Stok Suku Cadang (FEFO) jika ada
            foreach ($this->sparepartItems as $idx => $item) {
                if (empty($item['barang_id']) || empty($item['jumlah']) || $item['jumlah'] <= 0) {
                    continue;
                }

                $barang = Barang::findOrFail($item['barang_id']);
                $needed = (int) $item['jumlah'];

                // Cari stok batch aktif FEFO (Expired date terdekat)
                $batches = StokBatch::where('barang_id', $barang->id)
                    ->where('status', 'AKTIF')
                    ->where('jumlah', '>', 0)
                    ->where(function ($q) {
                        $q->whereNull('tanggal_kedaluwarsa')
                            ->orWhere('tanggal_kedaluwarsa', '>=', now()->toDateString());
                    })
                    ->orderByRaw('tanggal_kedaluwarsa ASC NULLS LAST')
                    ->lockForUpdate()
                    ->get();

                $available = $batches->sum('jumlah');
                if ($available < $needed) {
                    throw new Exception("Saldo stok fisik untuk suku cadang '{$barang->nama}' tidak mencukupi (Tersedia: {$available}, Diminta: {$needed}).");
                }

                $remainingToDeduct = $needed;
                foreach ($batches as $batch) {
                    if ($remainingToDeduct <= 0) {
                        break;
                    }

                    $deduct = min($batch->jumlah, $remainingToDeduct);
                    $saldoSebelum = $batch->jumlah;
                    $saldoSesudah = $saldoSebelum - $deduct;

                    // Kurangi stok batch
                    $batch->update([
                        'jumlah' => $saldoSesudah,
                        'status' => $saldoSesudah == 0 ? 'HABIS' : 'AKTIF',
                    ]);

                    // Catat Mutasi Pengeluaran di Kartu Stok
                    $pergerakan = PergerakanStok::create([
                        'barang_id'       => $barang->id,
                        'stok_batch_id'   => $batch->id,
                        'gudang_id'       => $batch->gudang_id,
                        'lokasi_gudang_id'=> $batch->lokasi_gudang_id,
                        'jenis'           => 'KELUAR',
                        'jumlah'          => $deduct,
                        'saldo_sebelum'   => $saldoSebelum,
                        'saldo_sesudah'   => $saldoSesudah,
                        'referensi_jenis' => 'SPK_MAINTENANCE',
                        'referensi_id'    => $spk->id,
                        'keterangan'      => "Servis fasilitas: {$tiket->kode} ({$log->diagnosa})",
                        'dibuat_oleh'     => $user->id,
                    ]);

                    // Catat Detail Suku Cadang Maintenance
                    $subtotal = $deduct * (float) $batch->harga_satuan;
                    SukuCadangMaintenance::create([
                        'permintaan_maintenance_id' => $tiket->id,
                        'log_maintenance_id'        => $log->id,
                        'barang_id'                 => $barang->id,
                        'stok_batch_id'             => $batch->id,
                        'pergerakan_stok_id'        => $pergerakan->id,
                        'jumlah_digunakan'          => $deduct,
                        'harga_satuan'              => $batch->harga_satuan,
                        'subtotal'                  => $subtotal,
                        'dicatat_oleh'              => $user->id,
                    ]);

                    $remainingToDeduct -= $deduct;
                }
            }

            // 3. Update Status SPK, Tiket, dan Aset
            if ($this->hasil === 'BERHASIL' && $this->uji_fungsi_kelaikan) {
                $spk->update(['status' => StatusSpk::Selesai->value]);
                $tiket->update(['status' => StatusMaintenance::Selesai->value]);

                // Kembalikan Aset ke kondisi BAIK dan status TERSEDIA / DIGUNAKAN
                if ($tiket->aset) {
                    $newAsetStatus = $tiket->aset->unit_id ? 'DIGUNAKAN' : 'TERSEDIA';
                    $tiket->aset->update([
                        'status'  => $newAsetStatus,
                        'kondisi' => 'BAIK',
                    ]);
                }
            } elseif ($this->hasil === 'BERHASIL' && ! $this->uji_fungsi_kelaikan) {
                $spk->update(['status' => StatusSpk::Diterima->value]);
                $tiket->update(['status' => StatusMaintenance::UjiFungsi->value]);
            } elseif ($this->hasil === 'PENDING') {
                $spk->update(['status' => StatusSpk::Diterima->value]);
                $tiket->update(['status' => StatusMaintenance::SedangDikerjakan->value]);
            } else {
                $spk->update(['status' => StatusSpk::Diterima->value]);
                $tiket->update(['status' => StatusMaintenance::MenungguSparepart->value]);
            }
        });
    }
}
