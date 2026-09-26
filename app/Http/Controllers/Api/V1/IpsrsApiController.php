<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Aset;
use App\Models\JadwalMaintenance;
use App\Models\PenugasanMaintenance;
use App\Models\PermintaanMaintenance;
use App\Support\Generator\CodeGenerator;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class IpsrsApiController extends ApiController
{
    /**
     * 1. Daftar Jadwal Pemeliharaan Preventif Berkala
     * GET /v1/maintenance/preventif/jadwal
     */
    public function jadwalPreventif(Request $request): JsonResponse
    {
        $user = $request->user();
        $statusFilter = strtoupper($request->query('status', 'SEMUA')); // SEMUA, OVERDUE, HARI_INI, MENDATANG
        $teknisiId = $request->query('teknisi_id');
        $cari = $request->query('cari');

        $today = now()->toDateString();

        $query = JadwalMaintenance::with([
            'aset.barang.kategori:id,nama',
            'aset.unit:id,kode,nama',
            'aset.gudang:id,kode,nama',
            'teknisi:id,nama',
        ])
        ->when($teknisiId, fn ($q) => $q->where('teknisi_id', $teknisiId))
        ->when($user && $user->hasRole('teknisi') && ! $user->hasRole('super_admin') && ! $user->hasRole('admin_logistik'), function ($q) use ($user) {
            $q->where('teknisi_id', $user->id);
        })
        ->when($cari, function ($q) use ($cari) {
            $q->where(function ($sub) use ($cari) {
                $sub->where('kode', 'ilike', "%{$cari}%")
                    ->orWhere('jenis_maintenance', 'ilike', "%{$cari}%")
                    ->orWhereHas('aset', fn ($a) => $a->where('kode_aset', 'ilike', "%{$cari}%")->orWhere('nomor_seri', 'ilike', "%{$cari}%"))
                    ->orWhereHas('aset.barang', fn ($b) => $b->where('nama', 'ilike', "%{$cari}%"));
            });
        });

        // Filter Jatuh Tempo
        if ($statusFilter === 'OVERDUE') {
            $query->where('status', 'AKTIF')->where('tanggal_berikutnya', '<', $today);
        } elseif ($statusFilter === 'HARI_INI') {
            $query->where('status', 'AKTIF')->where('tanggal_berikutnya', $today);
        } elseif ($statusFilter === 'MENDATANG') {
            $query->where('status', 'AKTIF')->where('tanggal_berikutnya', '>', $today);
        }

        $items = $query->orderBy('tanggal_berikutnya', 'asc')
            ->limit(50)
            ->get()
            ->map(function ($j) use ($today) {
                $tgl = $j->tanggal_berikutnya;
                $sisaHari = null;
                $statusJadwal = 'MENDATANG'; // OVERDUE, HARI_INI, MENDATANG, NONAKTIF

                if ($j->status !== 'AKTIF') {
                    $statusJadwal = 'NONAKTIF';
                } elseif ($tgl) {
                    $sisaHari = Carbon::parse($today)->diffInDays(Carbon::parse($tgl), false);
                    if ($sisaHari < 0) {
                        $statusJadwal = 'OVERDUE';
                    } elseif ($sisaHari == 0) {
                        $statusJadwal = 'HARI_INI';
                    } else {
                        $statusJadwal = 'MENDATANG';
                    }
                }

                $lokasi = $j->aset?->unit ? $j->aset->unit->nama : ($j->aset?->gudang ? $j->aset->gudang->nama : '-');

                return [
                    'id'                   => $j->id,
                    'kode'                 => $j->kode,
                    'aset_id'              => $j->aset_id,
                    'kode_aset'            => $j->aset?->kode_aset ?? '-',
                    'nomor_seri'           => $j->aset?->nomor_seri ?? '-',
                    'nama_sarana'          => $j->aset?->barang?->nama ?? 'Sarana Medis/Non-Medis',
                    'lokasi'               => $lokasi,
                    'jenis_maintenance'    => $j->jenis_maintenance,
                    'interval_nilai'       => (int) $j->interval_nilai,
                    'interval_tipe'        => $j->interval_tipe,
                    'interval_label'       => "Setiap {$j->interval_nilai} {$j->interval_tipe}",
                    'tanggal_terakhir'     => $j->tanggal_terakhir?->format('Y-m-d') ?? '-',
                    'tanggal_berikutnya'   => $tgl?->format('Y-m-d') ?? '-',
                    'sisa_hari'            => $sisaHari,
                    'status_jadwal'        => $statusJadwal,
                    'status_aktif'         => $j->status,
                    'teknisi_id'           => $j->teknisi_id,
                    'teknisi_nama'         => $j->teknisi?->nama ?? 'Teknisi IPSRS',
                    'catatan'              => $j->catatan ?? '-',
                ];
            });

        return $this->jsonSuccess($items, 'Jadwal preventif berkala berhasil dimuat.');
    }

    /**
     * 2. Eksekusi Terbitkan SPK dari Jadwal Preventif
     * POST /v1/maintenance/preventif/{id}/eksekusi
     */
    public function eksekusiPreventif(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $canExecute = $user && (
            $user->hasRole('teknisi') ||
            $user->hasRole('admin_logistik') ||
            $user->hasRole('super_admin') ||
            $user->can('create_pemeliharaan::spk::perbaikan')
        );

        if (! $canExecute) {
            return $this->jsonError('Hanya teknisi IPSRS dan admin logistik yang berwenang menerbitkan SPK preventif.', 403);
        }

        $jadwal = JadwalMaintenance::with(['aset', 'teknisi'])->find($id);

        if (! $jadwal) {
            return $this->jsonError('Jadwal pemeliharaan preventif tidak ditemukan.', 404);
        }

        $kodeMnt = CodeGenerator::generateMnt();
        $kodeSpk = CodeGenerator::generateSpk();

        try {
            DB::transaction(function () use ($jadwal, $user, $kodeMnt, $kodeSpk) {
                // 1. Buat Permintaan Maintenance Preventif
                $permintaan = PermintaanMaintenance::create([
                    'kode'              => $kodeMnt,
                    'aset_id'           => $jadwal->aset_id,
                    'unit_id'           => $jadwal->aset?->unit_id,
                    'dilaporkan_oleh'   => $user->id,
                    'deskripsi_masalah' => "Servis preventif berkala: {$jadwal->jenis_maintenance} (Sesuai agenda {$jadwal->kode})",
                    'prioritas'         => 'SEDANG',
                    'status'            => 'DITUGASKAN',
                ]);

                // 2. Buat Dokumen SPK Ditugaskan Langsung ke Teknisi Jadwal
                PenugasanMaintenance::create([
                    'kode'                      => $kodeSpk,
                    'permintaan_maintenance_id' => $permintaan->id,
                    'teknisi_id'                => $jadwal->teknisi_id,
                    'ditugaskan_oleh'           => $user->id,
                    'tanggal_penugasan'         => now()->toDateString(),
                    'target_selesai'            => now()->addDays(2)->toDateString(),
                    'instruksi_khusus'          => "Servis preventif berkala rutin sesuai jadwal {$jadwal->kode}. " . ($jadwal->catatan ?: ''),
                    'status'                    => 'DITUGASKAN',
                ]);

                // 3. Catat tanggal eksekusi terakhir dan majukan tanggal berikutnya
                $tglBerikutnya = $jadwal->hitungTanggalBerikutnya(now())->toDateString();
                $jadwal->update([
                    'tanggal_terakhir'   => now()->toDateString(),
                    'tanggal_berikutnya' => $tglBerikutnya,
                ]);
            });

            return $this->jsonSuccess([
                'kode_spk' => $kodeSpk,
                'kode_mnt' => $kodeMnt,
            ], "SPK Servis Preventif {$kodeSpk} berhasil diterbitkan dan ditugaskan.");
        } catch (Exception $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    /**
     * 3. Rekam Medis Aset Fisik (Comprehensive Life-Cycle History)
     * GET /v1/maintenance/rekam-medis/{aset_id}
     */
    public function rekamMedisAset(int $asetId): JsonResponse
    {
        $aset = Aset::with([
            'barang.kategori',
            'barang.satuan',
            'barang.merek',
            'unit',
            'gudang',
            'lokasiGudang',
            'permintaanMaintenance' => fn ($q) => $q->orderBy('id', 'desc'),
            'permintaanMaintenance.pelapor:id,nama',
            'permintaanMaintenance.penugasan.teknisi:id,nama',
            'permintaanMaintenance.penugasan.penugas:id,nama',
            'permintaanMaintenance.logMaintenance.teknisi:id,nama',
            'permintaanMaintenance.sukuCadang.barang:id,kode,nama',
            'permintaanMaintenance.sukuCadang.stokBatch:id,nomor_batch',
        ])
        ->withCount('permintaanMaintenance')
        ->find($asetId);

        if (! $aset) {
            return $this->jsonError('Data aset fisik tidak ditemukan.', 404);
        }

        $lokasi = $aset->unit ? $aset->unit->nama : ($aset->gudang ? $aset->gudang->nama . ($aset->lokasiGudang ? " (Rak: {$aset->lokasiGudang->nama})" : '') : '-');

        // Timeline Riwayat Servis / Gangguan
        $riwayatServis = $aset->permintaanMaintenance->map(function ($pm) {
            $spk = $pm->penugasan->first();
            $log = $pm->logMaintenance->first();

            $sukuCadangDipakai = $pm->sukuCadang->map(fn ($sc) => [
                'nama_barang' => $sc->barang?->nama ?? 'Suku Cadang',
                'nomor_batch' => $sc->stokBatch?->nomor_batch ?? '-',
                'jumlah'      => (int) $sc->jumlah,
            ]);

            return [
                'id'                 => $pm->id,
                'kode_tiket'         => $pm->kode,
                'tanggal_lapor'      => $pm->dibuat_pada?->format('Y-m-d H:i') ?? '-',
                'pelapor'            => $pm->pelapor?->nama ?? 'Staf',
                'deskripsi_masalah'  => $pm->deskripsi_masalah,
                'prioritas'          => $pm->prioritas,
                'status_tiket'       => $pm->status,
                'kode_spk'           => $spk?->kode ?? '-',
                'teknisi'            => $spk?->teknisi?->nama ?? '-',
                'tanggal_penugasan'  => $spk?->tanggal_penugasan?->format('Y-m-d') ?? '-',
                'status_spk'         => $spk?->status ?? '-',
                'diagnosa'           => $log?->diagnosa_kerusakan ?? '-',
                'tindakan'           => $log?->tindakan_perbaikan ?? '-',
                'hasil_pengerjaan'   => $log?->hasil_pengerjaan ?? '-',
                'tanggal_selesai'    => $log?->tanggal_selesai?->format('Y-m-d H:i') ?? '-',
                'suku_cadang'        => $sukuCadangDipakai,
            ];
        });

        // Agenda Preventif Terkait Aset
        $jadwalPreventif = JadwalMaintenance::where('aset_id', $aset->id)
            ->with('teknisi:id,nama')
            ->get()
            ->map(fn ($j) => [
                'id'                => $j->id,
                'kode'              => $j->kode,
                'jenis_maintenance' => $j->jenis_maintenance,
                'interval'          => "Setiap {$j->interval_nilai} {$j->interval_tipe}",
                'tanggal_berikutnya'=> $j->tanggal_berikutnya?->format('Y-m-d') ?? '-',
                'teknisi'           => $j->teknisi?->nama ?? 'Teknisi IPSRS',
                'status'            => $j->status,
            ]);

        $data = [
            'identitas' => [
                'id'               => $aset->id,
                'kode_aset'        => $aset->kode_aset,
                'nomor_seri'       => $aset->nomor_seri ?? '-',
                'nama_sarana'      => $aset->barang?->nama ?? 'Sarana Medis/Non-Medis',
                'kategori'         => $aset->barang?->kategori?->nama ?? 'Umum',
                'merek'            => $aset->barang?->merek?->nama ?? '-',
                'spesifikasi'      => $aset->barang?->spesifikasi ?? '-',
                'kondisi'          => $aset->kondisi,
                'status'           => $aset->status,
                'lokasi'           => $lokasi,
                'tanggal_perolehan'=> $aset->tanggal_perolehan?->format('Y-m-d') ?? '-',
                'nilai_perolehan'  => (float) ($aset->nilai_perolehan ?? 0),
                'total_servis'     => $aset->permintaan_maintenance_count,
            ],
            'riwayat_servis'   => $riwayatServis,
            'jadwal_preventif' => $jadwalPreventif,
        ];

        return $this->jsonSuccess($data, 'Rekam medis aset berhasil dimuat.');
    }

    /**
     * 4. Katalog Seluruh Aset untuk Pilihan Rekam Medis
     * GET /v1/maintenance/rekam-medis/katalog
     */
    public function katalogRekamMedis(Request $request): JsonResponse
    {
        $cari = $request->query('cari');
        $kondisi = $request->query('kondisi');

        $query = Aset::with(['barang.kategori', 'barang.merek', 'unit', 'gudang'])
            ->withCount('permintaanMaintenance')
            ->when($kondisi, fn ($q) => $q->where('kondisi', $kondisi))
            ->when($cari, function ($q) use ($cari) {
                $q->where(function ($sub) use ($cari) {
                    $sub->where('kode_aset', 'ilike', "%{$cari}%")
                        ->orWhere('nomor_seri', 'ilike', "%{$cari}%")
                        ->orWhereHas('barang', fn ($b) => $b->where('nama', 'ilike', "%{$cari}%"))
                        ->orWhereHas('unit', fn ($u) => $u->where('nama', 'ilike', "%{$cari}%"));
                });
            });

        $items = $query->orderBy('kode_aset', 'asc')
            ->limit(50)
            ->get()
            ->map(function ($a) {
                $lokasi = $a->unit ? $a->unit->nama : ($a->gudang ? $a->gudang->nama : '-');
                return [
                    'id'           => $a->id,
                    'kode_aset'    => $a->kode_aset,
                    'nomor_seri'   => $a->nomor_seri ?? '-',
                    'nama_sarana'  => $a->barang?->nama ?? 'Sarana Fisik',
                    'kategori'     => $a->barang?->kategori?->nama ?? 'Umum',
                    'merek'        => $a->barang?->merek?->nama ?? '-',
                    'kondisi'      => $a->kondisi,
                    'status'       => $a->status,
                    'lokasi'       => $lokasi,
                    'total_servis' => $a->permintaan_maintenance_count,
                ];
            });

        return $this->jsonSuccess($items, 'Katalog rekam medis aset berhasil dimuat.');
    }
}
