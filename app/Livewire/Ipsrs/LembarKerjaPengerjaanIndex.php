<?php

namespace App\Livewire\Ipsrs;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Barang;
use App\Models\LogMaintenance;
use App\Models\PenugasanMaintenance;
use App\Models\PergerakanStok;
use App\Models\PermintaanMaintenance;
use App\Models\StokBatch;
use App\Models\SukuCadangMaintenance;
use Exception;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;

class LembarKerjaPengerjaanIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal;

    #[Url(history: true)]
    public string $filterStatus = 'AKTIF'; // 'AKTIF' atau 'SELESAI' atau 'SEMUA'

    public bool $showModal = false;
    public bool $showDetailModal = false;
    public ?PenugasanMaintenance $selectedSpk = null;

    // Form Lembar Kerja
    public ?int $penugasan_id = null;
    public ?PenugasanMaintenance $formSpk = null;

    #[Validate('required|date')]
    public string $tanggal_pengerjaan = '';

    #[Validate('nullable|string')]
    public ?string $jam_mulai = '08:00';

    #[Validate('nullable|string')]
    public ?string $jam_selesai = '10:00';

    #[Validate('required|string|min:5')]
    public string $diagnosa = '';

    #[Validate('required|string|min:5')]
    public string $tindakan = '';

    #[Validate('required|in:BERHASIL,PENDING,GAGAL')]
    public string $hasil = 'BERHASIL';

    #[Validate('nullable|string')]
    public ?string $catatan = null;

    public bool $uji_fungsi_kelaikan = true;

    // Suku Cadang yang Digunakan
    public array $sparepartItems = [];

    public function mount(): void
    {
        $this->tanggal_pengerjaan = now()->format('Y-m-d\TH:i');
    }

    public function openLembarKerja(int $spkId): void
    {
        abort_unless(auth()->user()?->can('create_pemeliharaan::lembar::kerja'), 403);

        $this->penugasan_id = $spkId;
        $this->formSpk = PenugasanMaintenance::with(['permintaanMaintenance.aset.barang', 'permintaanMaintenance.unit'])->findOrFail($spkId);

        $this->tanggal_pengerjaan = now()->format('Y-m-d\TH:i');
        $this->jam_mulai = now()->subHours(2)->format('H:i');
        $this->jam_selesai = now()->format('H:i');
        $this->diagnosa = '';
        $this->tindakan = '';
        $this->hasil = 'BERHASIL';
        $this->catatan = null;
        $this->uji_fungsi_kelaikan = true;
        $this->sparepartItems = [];

        $this->showModal = true;
    }

    public function addSparepartRow(): void
    {
        $this->sparepartItems[] = [
            'barang_id' => '',
            'jumlah'    => 1,
        ];
    }

    public function removeSparepartRow(int $index): void
    {
        unset($this->sparepartItems[$index]);
        $this->sparepartItems = array_values($this->sparepartItems);
    }

    public function getStokTersedia(int $barangId): int
    {
        return (int) StokBatch::where('barang_id', $barangId)
            ->where('status', 'AKTIF')
            ->where('jumlah', '>', 0)
            ->sum('jumlah');
    }

    public function saveLembarKerja(): void
    {
        abort_unless(auth()->user()?->can('create_pemeliharaan::lembar::kerja'), 403);
        $this->validate();

        // Validasi pembatasan stok suku cadang sesuai master barang
        foreach ($this->sparepartItems as $index => $item) {
            $barangId = (int) ($item['barang_id'] ?? 0);
            $jumlahDiminta = (int) ($item['jumlah'] ?? 0);

            if (! $barangId) {
                continue;
            }

            $stokTersedia = $this->getStokTersedia($barangId);
            $barang = Barang::with('satuan')->find($barangId);
            $namaBarang = $barang?->nama ?? "Barang #{$barangId}";
            $satuan = $barang?->satuan?->simbol ?? 'Unit';

            if ($stokTersedia <= 0) {
                $this->addError("sparepartItems.{$index}.barang_id", "Stok fisik '{$namaBarang}' saat ini habis (0 {$satuan}). Tidak dapat digunakan.");
                return;
            }

            if ($jumlahDiminta > $stokTersedia) {
                $this->addError("sparepartItems.{$index}.jumlah", "Jumlah diminta ({$jumlahDiminta} {$satuan}) melebihi stok yang tersedia ({$stokTersedia} {$satuan}).");
                return;
            }
        }

        $user = auth()->user();
        $spk = PenugasanMaintenance::with(['permintaanMaintenance.aset'])->findOrFail($this->penugasan_id);
        $tiket = $spk->permintaanMaintenance;

        try {
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
                    $spk->update(['status' => 'SELESAI']);
                    $tiket->update(['status' => 'SELESAI']);

                    // Kembalikan Aset ke kondisi BAIK dan status TERSEDIA / DIGUNAKAN
                    if ($tiket->aset) {
                        $newAsetStatus = $tiket->aset->unit_id ? 'DIGUNAKAN' : 'TERSEDIA';
                        $tiket->aset->update([
                            'status'  => $newAsetStatus,
                            'kondisi' => 'BAIK',
                        ]);
                    }
                } elseif ($this->hasil === 'BERHASIL' && ! $this->uji_fungsi_kelaikan) {
                    $spk->update(['status' => 'DITERIMA']);
                    $tiket->update(['status' => 'UJI_FUNGSI']);
                } elseif ($this->hasil === 'PENDING') {
                    $spk->update(['status' => 'DITERIMA']);
                    $tiket->update(['status' => 'SEDANG_DIKERJAKAN']);
                } else {
                    $spk->update(['status' => 'DITERIMA']);
                    $tiket->update(['status' => 'MENUNGGU_SPAREPART']);
                }
            });

            $this->showModal = false;
            $this->reset(['penugasan_id', 'formSpk', 'diagnosa', 'tindakan', 'catatan', 'sparepartItems']);
            $this->toast("Lembar kerja servis SPK {$spk->kode} berhasil disimpan.", 'success');
        } catch (Exception $e) {
            $this->addError('general', $e->getMessage());
            $this->toast($e->getMessage(), 'danger');
        }
    }

    public function showDetail(int $spkId): void
    {
        $this->selectedSpk = PenugasanMaintenance::with([
            'permintaanMaintenance.aset.barang',
            'permintaanMaintenance.unit',
            'permintaanMaintenance.pelapor',
            'teknisi',
            'penugast',
            'logMaintenance.teknisi',
            'logMaintenance.sukuCadang.barang',
            'permintaanMaintenance.sukuCadang.barang',
        ])->findOrFail($spkId);

        $this->showDetailModal = true;
    }

    public function render()
    {
        abort_unless(
            auth()->user()?->can('view_any_pemeliharaan::lembar::kerja') ||
            auth()->user()?->can('view_pemeliharaan::lembar::kerja'),
            403
        );

        $query = PenugasanMaintenance::with([
            'permintaanMaintenance.aset.barang',
            'permintaanMaintenance.unit',
            'teknisi',
            'logMaintenance',
        ])
        ->when($this->filterStatus === 'AKTIF', fn ($q) => $q->whereIn('status', ['DITUGASKAN', 'DITERIMA']))
        ->when($this->filterStatus === 'SELESAI', fn ($q) => $q->where('status', 'SELESAI'))
        ->when($this->search, function ($q) {
            $q->where(function ($sub) {
                $sub->where('kode', 'ilike', "%{$this->search}%")
                    ->orWhereHas('teknisi', fn ($t) => $t->where('nama', 'ilike', "%{$this->search}%"))
                    ->orWhereHas('permintaanMaintenance', function ($m) {
                        $m->where('kode', 'ilike', "%{$this->search}%")
                            ->orWhereHas('aset', fn ($a) => $a->where('kode_aset', 'ilike', "%{$this->search}%"));
                    });
            });
        });

        $daftarSpk = $query
            ->orderBy($this->sortBy ?: 'id', $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);

        // Komoditas suku cadang yang aktif untuk dropdown beserta stok fisik tersedia
        $daftarSparepart = Barang::whereIn('tipe', ['SPAREPART', 'HABIS_PAKAI'])
            ->where('status', 'AKTIF')
            ->with('satuan')
            ->withSum(['stokBatch as stok_tersedia' => function ($q) {
                $q->where('status', 'AKTIF')->where('jumlah', '>', 0);
            }], 'jumlah')
            ->orderBy('nama')
            ->get();

        return view('livewire.ipsrs.lembar-kerja-pengerjaan-index', [
            'daftarSpk'       => $daftarSpk,
            'daftarSparepart' => $daftarSparepart,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Lembar Kerja Pengerjaan IPSRS']);
    }
}
