<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Aset;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\PenerimaanDistribusi;
use App\Models\PenerimaanDistribusiDetail;
use App\Models\PengirimanBarang;
use App\Models\PermintaanDistribusi;
use App\Models\PermintaanDistribusiDetail;
use App\Models\StokBatch;
use App\Models\Unit;
use App\Models\StokRuangan;
use App\Services\Distribusi\DistribusiService;
use App\Support\Generator\CodeGenerator;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DistribusiApiController extends ApiController
{
    /**
     * Katalog komoditas barang siap amprah beserta informasi ketersediaan stok gudang.
     * GET /v1/distribusi/katalog-barang
     */
    public function katalogBarang(Request $request): JsonResponse
    {
        $cari = $request->query('cari');
        $gudangId = $request->query('gudang_id') ?: Gudang::where('status', 'AKTIF')->value('id');

        $barangs = Barang::query()
            ->whereIn('tipe', ['HABIS_PAKAI', 'SPAREPART', 'ASET'])
            ->where('status', 'AKTIF')
            ->with(['kategori:id,nama', 'satuan:id,nama', 'merek:id,nama'])
            ->when($cari, function ($q) use ($cari) {
                $q->where(function ($sub) use ($cari) {
                    $sub->where('nama', 'like', "%{$cari}%")
                        ->orWhere('kode', 'like', "%{$cari}%");
                });
            })
            ->limit(50)
            ->get()
            ->map(function ($b) use ($gudangId) {
                $stokGudang = (int) StokBatch::where('barang_id', $b->id)
                    ->where('status', 'AKTIF')
                    ->where('jumlah', '>', 0)
                    ->when($gudangId, fn ($q) => $q->where('gudang_id', $gudangId))
                    ->where(function ($q) {
                        $q->whereNull('tanggal_kedaluwarsa')
                          ->orWhere('tanggal_kedaluwarsa', '>=', now()->toDateString());
                    })
                    ->sum('jumlah');

                $tipeLabel = match ($b->tipe) {
                    'ASET'      => 'Aktiva / Aset Inventaris',
                    'SPAREPART' => 'Suku Cadang IPSRS',
                    default     => 'Habis Pakai (ATK/ART)',
                };

                return [
                    'id'             => $b->id,
                    'kode'           => $b->kode,
                    'nama'           => $b->nama,
                    'spesifikasi'    => $b->spesifikasi,
                    'tipe'           => $b->tipe,
                    'tipe_label'     => $tipeLabel,
                    'kategori'       => $b->kategori?->nama ?? 'Umum',
                    'satuan'         => $b->satuan?->nama ?? 'Pcs',
                    'merek'          => $b->merek?->nama ?? '-',
                    'stok_tersedia'  => $stokGudang,
                ];
            });

        return $this->jsonSuccess($barangs, 'Katalog barang amprah berhasil dimuat.');
    }

    /**
     * Riwayat daftar permintaan amprah ruangan milik unit pengguna.
     * GET /v1/distribusi/amprah
     */
    public function daftarAmprah(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->jsonError('Unauthenticated.', 401);
        }

        $isGlobalAdmin = $user->hasRole('super_admin') ||
            $user->hasRole('admin_logistik') ||
            $user->can('page_AdminLogistikDashboard');

        $filterStatus = $request->query('status'); // DIAJUKAN, DISETUJUI_KARU, DISETUJUI_GUDANG, SELESAI, DITOLAK
        $filterUrgensi = $request->query('urgensi');

        $effectiveUnitId = $user->unit_id ?: ($user->hasRole('pengadaan') ? Unit::where('kode', 'LOGISTIK')->value('id') : null);

        $query = PermintaanDistribusi::query()
            ->with([
                'unit:id,kode,nama',
                'gudang:id,kode,nama',
                'pengaju:id,nama',
                'penyetuju:id,nama',
                'detail.barang:id,kode,nama,satuan_id',
                'detail.barang.satuan:id,nama',
            ])
            ->when($effectiveUnitId && ! $isGlobalAdmin, fn ($q) => $q->where('unit_id', $effectiveUnitId))
            ->when($filterStatus, fn ($q) => $q->where('status', $filterStatus))
            ->when($filterUrgensi, fn ($q) => $q->where('urgensi', $filterUrgensi));

        $list = $query->latest('id')
            ->limit(100)
            ->get()
            ->map(function ($item) use ($user, $isGlobalAdmin) {
                $canCancel = $item->status === 'DIAJUKAN' && (
                    (int) $item->diajukan_oleh === (int) $user->id ||
                    ($user->unit_id && (int) $user->unit_id === (int) $item->unit_id && $user->hasRole('kepala_unit')) ||
                    $isGlobalAdmin
                );

                return [
                    'id'                 => $item->id,
                    'kode'               => $item->kode,
                    'unit'               => $item->unit?->nama ?? 'Unit',
                    'gudang'             => $item->gudang?->nama ?? 'Gudang Sentral',
                    'pengaju'            => $item->pengaju?->nama ?? 'Staf',
                    'tanggal_permintaan' => $item->tanggal_permintaan?->format('Y-m-d') ?? '',
                    'urgensi'            => $item->urgensi,
                    'status'             => $item->status,
                    'catatan'            => $item->catatan,
                    'can_cancel'         => $canCancel,
                    'jumlah_item'        => $item->detail->count(),
                    'items'              => $item->detail->map(fn ($d) => [
                        'id'               => $d->id,
                        'barang_id'        => $d->barang_id,
                        'nama_barang'      => $d->barang?->nama ?? 'Barang',
                        'kode_barang'      => $d->barang?->kode ?? '-',
                        'satuan'           => $d->barang?->satuan?->nama ?? 'Pcs',
                        'jumlah_diminta'   => (int) $d->jumlah,
                        'jumlah_disetujui' => $d->jumlah_disetujui !== null ? (int) $d->jumlah_disetujui : (int) $d->jumlah,
                        'catatan'          => $d->catatan,
                    ]),
                ];
            });

        return $this->jsonSuccess($list, 'Daftar amprah ruangan berhasil dimuat.');
    }

    /**
     * Detail lengkap satu permohonan amprah ruangan.
     * GET /v1/distribusi/amprah/{id}
     */
    public function detailAmprah(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $isGlobalAdmin = $user && (
            $user->hasRole(['super_admin', 'admin_logistik', 'auditor', 'manajemen', 'kepala_gudang', 'petugas_gudang']) ||
            $user->can('view_any_layanan_unit::permintaan::barang') ||
            $user->can('view_any_gudang_stok::distribusi::approval') ||
            $user->can('page_KepalaGudangDashboard') ||
            $user->can('page_GudangDashboard') ||
            $user->can('page_AdminLogistikDashboard') ||
            $user->can('page_AuditorDashboard') ||
            $user->can('page_ManajemenDashboard')
        );

        $amprah = PermintaanDistribusi::with([
            'unit',
            'gudang',
            'pengaju',
            'penyetuju',
            'detail.barang.satuan',
            'pengiriman.detail',
        ])->find($id);

        if (! $amprah) {
            return $this->jsonError('Data amprah tidak ditemukan.', 404);
        }

        $isUnitOwner = $user && (
            ($user->unit_id && (int) $user->unit_id === (int) $amprah->unit_id) ||
            ((int) $amprah->diajukan_oleh === (int) $user->id)
        );

        if (! $isGlobalAdmin && ! $isUnitOwner) {
            return $this->jsonError('Akses ditolak: Anda tidak memiliki wewenang melihat dokumen amprah unit lain.', 403);
        }
        $canCancel = $amprah->status === 'DIAJUKAN' && (
            (int) $amprah->diajukan_oleh === (int) $user->id ||
            ($user->unit_id && (int) $user->unit_id === (int) $amprah->unit_id && $user->hasRole('kepala_unit')) ||
            $isGlobalAdmin
        );

        return $this->jsonSuccess([
            'id'                 => $amprah->id,
            'kode'               => $amprah->kode,
            'unit'               => $amprah->unit?->nama ?? '-',
            'gudang'             => $amprah->gudang?->nama ?? '-',
            'pengaju'            => $amprah->pengaju?->nama ?? '-',
            'tanggal_permintaan' => $amprah->tanggal_permintaan?->format('Y-m-d'),
            'urgensi'            => $amprah->urgensi,
            'status'             => $amprah->status,
            'catatan'            => $amprah->catatan,
            'can_cancel'         => $canCancel,
            'items'              => $amprah->detail->map(fn ($d) => [
                'id'               => $d->id,
                'barang_id'        => $d->barang_id,
                'nama_barang'      => $d->barang?->nama ?? 'Barang',
                'kode_barang'      => $d->barang?->kode ?? '-',
                'satuan'           => $d->barang?->satuan?->nama ?? 'Pcs',
                'jumlah_diminta'   => (int) $d->jumlah,
                'jumlah_disetujui' => $d->jumlah_disetujui !== null ? (int) $d->jumlah_disetujui : (int) $d->jumlah,
                'catatan'          => $d->catatan,
            ]),
        ], 'Detail amprah berhasil dimuat.');
    }

    /**
     * Pengajuan dokumen amprah ruangan baru (REQ-YYYYMM-XXX).
     * POST /v1/distribusi/amprah
     */
    public function simpanAmprah(Request $request): JsonResponse
    {
        $user = $request->user();

        $canAmprah = $user && (
            $user->can('create_layanan_unit::permintaan::barang') ||
            $user->hasRole('petugas_unit') ||
            $user->hasRole('kepala_unit') ||
            $user->hasRole('auditor') ||
            $user->hasRole('manajemen') ||
            $user->hasRole('pengadaan') ||
            $user->hasRole('super_admin')
        );

        if (! $canAmprah) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk mengajukan amprah barang ruangan.', 403);
        }

        $validator = Validator::make($request->all(), [
            'gudang_id'        => 'nullable|exists:gudang,id',
            'urgensi'          => 'required|in:REGULER,CITO',
            'catatan'          => 'nullable|string|max:1000',
            'items'            => 'required|array|min:1',
            'items.*.barang_id'=> 'required|exists:barang,id',
            'items.*.jumlah'   => 'required|integer|min:1',
            'items.*.catatan'  => 'nullable|string|max:500',
        ], [
            'items.required'            => 'Minimal 1 barang harus dimasukkan ke dalam permohonan amprah.',
            'items.min'                 => 'Minimal 1 barang harus dimasukkan ke dalam permohonan amprah.',
            'items.*.barang_id.required'=> 'Barang wajib dipilih.',
            'items.*.jumlah.min'        => 'Jumlah barang minimal 1 unit.',
        ]);

        if ($validator->fails()) {
            return $this->jsonError('Validasi data gagal.', 422, $validator->errors());
        }

        $unitId = $user->unit_id;
        if (! $unitId) {
            return $this->jsonError('Anda harus terdaftar pada unit/ruangan untuk mengajukan amprah.', 422);
        }

        // Auto-route ke gudang yang memiliki stok barang pertama jika gudang_id tidak dikirim
        $gudangId = $request->input('gudang_id');
        if (! $gudangId) {
            $firstBarangId = (int) ($request->input('items.0.barang_id') ?? 0);
            $gudangId = $firstBarangId
                ? StokBatch::where('barang_id', $firstBarangId)->where('status', 'AKTIF')->where('jumlah', '>', 0)->groupBy('gudang_id')->selectRaw('gudang_id, sum(jumlah) as total')->orderByDesc('total')->value('gudang_id')
                : null;
            $gudangId = $gudangId ?: Gudang::where('status', 'AKTIF')->value('id');
        }
        if (! $gudangId) {
            return $this->jsonError('Fasilitas gudang aktif belum tersedia.', 422);
        }
        foreach ($request->input('items') as $row) {
            $barangId = (int) $row['barang_id'];
            $jumlahDiminta = (int) $row['jumlah'];

            $stokTersedia = (int) StokBatch::where('barang_id', $barangId)
                ->where('status', 'AKTIF')
                ->where('jumlah', '>', 0)
                ->when($gudangId, fn ($q) => $q->where('gudang_id', $gudangId))
                ->where(function ($q) {
                    $q->whereNull('tanggal_kedaluwarsa')
                      ->orWhere('tanggal_kedaluwarsa', '>=', now()->toDateString());
                })
                ->sum('jumlah');

            $barang = Barang::with('satuan')->find($barangId);
            $namaBarang = $barang?->nama ?? "Barang #{$barangId}";
            $satuan = $barang?->satuan?->nama ?? 'Pcs';

            if ($stokTersedia <= 0) {
                return $this->jsonError("Komoditas '{$namaBarang}' di gudang tujuan saat ini kosong (0 {$satuan}). Tidak dapat diajukan amprah.", 422);
            }

            if ($jumlahDiminta > $stokTersedia) {
                return $this->jsonError("Jumlah diminta untuk '{$namaBarang}' ({$jumlahDiminta} {$satuan}) melebihi stok gudang yang tersedia ({$stokTersedia} {$satuan}).", 422);
            }
        }

        $isPetugasUnitMurni = $user->hasRole('petugas_unit')
            && ! $user->hasRole('kepala_unit')
            && ! $user->can('update_layanan_unit::approval::karu');

        $directToGudang = ! $isPetugasUnitMurni;
        $statusAwal     = $directToGudang ? 'DISETUJUI_KARU' : 'DIAJUKAN';
        $disetujuiOleh  = $directToGudang ? $user->id : null;

        try {
            $amprah = DB::transaction(function () use ($request, $user, $unitId, $gudangId, $statusAwal, $disetujuiOleh) {
                $kodeReq = CodeGenerator::generate('REQ', PermintaanDistribusi::class, 'kode', 'Ym', 3);

                $header = PermintaanDistribusi::create([
                    'kode'               => $kodeReq,
                    'unit_id'            => $unitId,
                    'gudang_id'          => $gudangId,
                    'diajukan_oleh'      => $user->id,
                    'disetujui_oleh'     => $disetujuiOleh,
                    'tanggal_permintaan' => now()->toDateString(),
                    'urgensi'            => $request->input('urgensi'),
                    'status'             => $statusAwal,
                    'catatan'            => $request->input('catatan'),
                ]);

                foreach ($request->input('items') as $row) {
                    PermintaanDistribusiDetail::create([
                        'permintaan_distribusi_id' => $header->id,
                        'barang_id'                => $row['barang_id'],
                        'jumlah'                   => $row['jumlah'],
                        'jumlah_disetujui'         => $row['jumlah'],
                        'catatan'                  => $row['catatan'] ?? null,
                    ]);
                }

                return $header;
            });

            return $this->jsonSuccess([
                'id'      => $amprah->id,
                'kode'    => $amprah->kode,
                'status'  => $amprah->status,
                'urgensi' => $amprah->urgensi,
            ], "Permintaan amprah {$amprah->kode} berhasil diajukan.", 201);
        } catch (\Throwable $e) {
            return $this->jsonError('Gagal mengajukan amprah: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Batalkan dokumen amprah yang masih berstatus DIAJUKAN.
     * POST /v1/distribusi/amprah/{id}/batal
     */
    public function batalAmprah(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $amprah = PermintaanDistribusi::find($id);

        if (! $amprah) {
            return $this->jsonError('Data amprah tidak ditemukan.', 404);
        }

        if ($amprah->status !== 'DIAJUKAN') {
            return $this->jsonError("Hanya amprah berstatus DIAJUKAN yang dapat dibatalkan (saat ini: {$amprah->status}).", 422);
        }

        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('admin_logistik');
        $canCancel = (int) $amprah->diajukan_oleh === (int) $user->id ||
            ($user->unit_id && (int) $user->unit_id === (int) $amprah->unit_id && $user->hasRole('kepala_unit')) ||
            $isGlobalAdmin;

        if (! $canCancel) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk membatalkan amprah ini.', 403);
        }

        $amprah->update([
            'status'  => 'DITOLAK',
            'catatan' => ($amprah->catatan ? $amprah->catatan . "\n" : '') . '[Dibatalkan oleh pemohon pada ' . now()->format('d/m/Y H:i') . ']',
        ]);

        return $this->jsonSuccess(null, "Permintaan amprah {$amprah->kode} berhasil dibatalkan.");
    }

    /**
     * Antrean amprah siap picking FEFO untuk Petugas Gudang (Status DISETUJUI_GUDANG).
     * GET /v1/distribusi/antrean-picking
     */
    public function antreanPicking(Request $request): JsonResponse
    {
        $user = $request->user();

        $canPick = $user->can('create_gudang_stok::picking::distribusi') ||
            $user->can('update_gudang_stok::picking::distribusi') ||
            $user->can('view_any_gudang_stok::picking::distribusi') ||
            $user->can('view_gudang_stok::picking::distribusi') ||
            $user->hasRole('petugas_gudang') ||
            $user->hasRole('kepala_gudang') ||
            $user->hasRole('admin_logistik') ||
            $user->hasRole('super_admin');

        if (! $canPick) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk melihat antrean picking FEFO.', 403);
        }

        $isGlobalPickAdmin = $user->hasRole('super_admin') || $user->hasRole('admin_logistik');
        $gudangIds = Gudang::where('kepala_gudang_id', $user->id)->pluck('id');
        $filterGudang = $gudangIds->isNotEmpty() ? $gudangIds->toArray() : ($user->gudang_id ? [$user->gudang_id] : []);

        $items = PermintaanDistribusi::query()
            ->whereIn('status', ['DISETUJUI_GUDANG', 'DISETUJUI_KARU'])
            ->when(! $isGlobalPickAdmin && $filterGudang, fn ($q) => $q->whereIn('gudang_id', $filterGudang))
            ->with(['unit:id,kode,nama', 'pengaju:id,nama', 'detail.barang.satuan'])
            ->latest('id')
            ->get()
            ->map(fn ($p) => [
                'id'                 => $p->id,
                'kode'               => $p->kode,
                'unit'               => $p->unit?->nama ?? 'Unit Ruangan',
                'pengaju'            => $p->pengaju?->nama ?? 'Staf',
                'tanggal_permintaan' => $p->tanggal_permintaan?->format('Y-m-d'),
                'urgensi'            => $p->urgensi,
                'status'             => $p->status,
                'total_item'         => $p->detail->count(),
                'items'              => $p->detail->map(fn ($d) => [
                    'barang_id'        => $d->barang_id,
                    'nama_barang'      => $d->barang?->nama ?? 'Barang',
                    'satuan'           => $d->barang?->satuan?->nama ?? 'Pcs',
                    'jumlah_disetujui' => (int) ($d->jumlah_disetujui ?: $d->jumlah),
                ]),
            ]);

        return $this->jsonSuccess($items, 'Antrean picking distribusi berhasil dimuat.');
    }

    /**
     * Eksekusi alokasi picking FEFO dan penerbitan SPPB resmi.
     * POST /v1/distribusi/picking
     */
    public function eksekusiPicking(Request $request, DistribusiService $service): JsonResponse
    {
        $user = $request->user();

        if (! $user->can('create_gudang_stok::picking::distribusi') &&
            ! $user->hasRole('petugas_gudang') &&
            ! $user->hasRole('kepala_gudang') &&
            ! $user->hasRole('admin_logistik') &&
            ! $user->hasRole('super_admin')) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk eksekusi picking FEFO.', 403);
        }

        $request->validate([
            'permintaan_id' => 'required|exists:permintaan_distribusi,id',
        ]);

        $permintaan = PermintaanDistribusi::with(['detail.barang', 'unit'])->find($request->input('permintaan_id'));

        if (! in_array($permintaan->status, ['DISETUJUI_GUDANG', 'DISETUJUI_KARU'], true)) {
            return $this->jsonError("Permintaan {$permintaan->kode} tidak dalam status siap picking (saat ini: {$permintaan->status}).", 422);
        }

        try {
            $gudangId = $user->gudang_id ?: $permintaan->gudang_id;
            $sppb = $service->eksekusiPickingFefo($permintaan, $user, $gudangId);

            $sppb->load(['detail.barang', 'detail.stokBatch', 'unit']);

            return $this->jsonSuccess([
                'id'             => $sppb->id,
                'sppb_kode'      => $sppb->kode,
                'permintaan_id'  => $permintaan->id,
                'unit_tujuan'    => $sppb->unit?->nama,
                'tanggal_kirim'  => $sppb->tanggal_kirim?->format('Y-m-d'),
                'total_batch'    => $sppb->detail->count(),
            ], "Picking FEFO berhasil. Dokumen {$sppb->kode} telah diterbitkan dan barang siap dikirim.", 201);
        } catch (Exception $e) {
            return $this->jsonError('Gagal eksekusi picking: ' . $e->getMessage(), 422);
        }
    }

    /**
     * Daftar SPPB dalam pengiriman yang menuju ke unit pemohon (Inbound Ruangan).
     * GET /v1/distribusi/sppb-inbound
     */
    public function sppbInbound(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->jsonError('Unauthenticated.', 401);
        }

        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('admin_logistik');
        $userUnitId = $user->unit_id;

        $sppbs = PengirimanBarang::query()
            ->where('status', 'DIKIRIM')
            ->when($userUnitId && ! $isGlobalAdmin, fn ($q) => $q->where('unit_id', $userUnitId))
            ->with(['gudang:id,kode,nama', 'unit:id,kode,nama', 'pengirim:id,nama', 'detail.barang.satuan', 'detail.stokBatch'])
            ->latest('id')
            ->get()
            ->map(fn ($sppb) => [
                'id'            => $sppb->id,
                'kode'          => $sppb->kode,
                'gudang'        => $sppb->gudang?->nama ?? 'Gudang Sentral',
                'unit'          => $sppb->unit?->nama ?? '-',
                'pengirim'      => $sppb->pengirim?->nama ?? 'Petugas Gudang',
                'tanggal_kirim' => $sppb->tanggal_kirim?->format('Y-m-d'),
                'status'        => $sppb->status,
                'catatan'       => $sppb->catatan,
                'total_item'    => $sppb->detail->count(),
                'items'         => $sppb->detail->map(fn ($d) => [
                    'barang_id'      => $d->barang_id,
                    'nama_barang'    => $d->barang?->nama ?? 'Barang',
                    'kode_barang'    => $d->barang?->kode ?? '-',
                    'satuan'         => $d->barang?->satuan?->nama ?? 'Pcs',
                    'jumlah_dikirim' => (int) $d->jumlah_dikirim,
                    'nomor_batch'    => $d->stokBatch?->nomor_batch ?? '-',
                ]),
            ]);

        return $this->jsonSuccess($sppbs, 'Daftar pengiriman barang menuju ruangan berhasil dimuat.');
    }

    /**
     * Konfirmasi penerimaan fisik barang dari SPPB di ruangan (RCV-YYYYMM-XXX).
     * POST /v1/distribusi/sppb/{id}/terima
     */
    public function konfirmasiTerimaSppb(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (! $user->can('create_layanan_unit::penerimaan::barang') &&
            ! $user->can('update_layanan_unit::penerimaan::barang') &&
            ! $user->hasRole('petugas_unit') &&
            ! $user->hasRole('kepala_unit') &&
            ! $user->hasRole('auditor') &&
            ! $user->hasRole('manajemen') &&
            ! $user->hasRole('super_admin')) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk konfirmasi penerimaan barang ruangan.', 403);
        }

        $sppb = PengirimanBarang::with(['permintaanDistribusi', 'unit', 'gudang', 'detail.barang'])->find($id);

        if (! $sppb) {
            return $this->jsonError('Data pengiriman SPPB tidak ditemukan.', 404);
        }



        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('admin_logistik');
        $effectiveRcvUnitId = $user->unit_id ?: ($user->hasRole('pengadaan') ? Unit::where('kode', 'LOGISTIK')->value('id') : null);
        if (! $isGlobalAdmin && (int) $effectiveRcvUnitId !== (int) $sppb->unit_id) {
            return $this->jsonError('Anda hanya berhak mengonfirmasi penerimaan barang untuk ruangan Anda sendiri.', 403);
        }

        try {
            $penerimaan = DB::transaction(function () use ($id, $user) {
                $sppb = PengirimanBarang::lockForUpdate()->with(['permintaanDistribusi', 'unit', 'gudang', 'detail.barang', 'detail.stokBatch'])->findOrFail($id);

                if ($sppb->status !== 'DIKIRIM') {
                    throw new Exception("Pengiriman {$sppb->kode} telah diproses sebelumnya (saat ini: {$sppb->status}).");
                }

                $kodeRcv = CodeGenerator::generate('RCV', PenerimaanDistribusi::class, 'kode', 'Ym', 3);

                $rcv = PenerimaanDistribusi::create([
                    'kode'                 => $kodeRcv,
                    'pengiriman_barang_id' => $sppb->id,
                    'unit_id'              => $sppb->unit_id,
                    'tanggal_terima'       => now()->toDateString(),
                    'status'               => 'SELESAI',
                    'catatan'              => 'Penerimaan fisik barang dari SPPB ' . $sppb->kode,
                    'diterima_oleh'        => $user->id,
                ]);

                $hasAssetItem = false;
                foreach ($sppb->detail as $detail) {
                    PenerimaanDistribusiDetail::create([
                        'penerimaan_distribusi_id' => $rcv->id,
                        'barang_id'                => $detail->barang_id,
                        'jumlah_diterima'          => $detail->jumlah_dikirim,
                    ]);

                    $barang = $detail->barang;
                    $isAset = $barang && ($barang->tipe === 'ASET' || str_contains(strtoupper($barang->kategori?->nama ?? ''), 'ASET'));
                    $qty = (int) $detail->jumlah_dikirim;

                    if ($isAset) {
                        $hasAssetItem = true;
                        // 1. Cek apakah ada unit fisik aset di gudang pengirim yang siap dialihkan
                        $existingGudangAsets = Aset::where('barang_id', $detail->barang_id)
                            ->where('gudang_id', $sppb->gudang_id)
                            ->whereNull('unit_id')
                            ->where('status', 'TERSEDIA')
                            ->limit($qty)
                            ->get();

                        $transferredCount = 0;
                        foreach ($existingGudangAsets as $asetGudang) {
                            $asetGudang->update([
                                'unit_id'          => $sppb->unit_id,
                                'gudang_id'        => null,
                                'lokasi_gudang_id' => null,
                                'status'           => 'TERSEDIA',
                                'kondisi'          => 'BAIK',
                            ]);
                            $transferredCount++;
                        }

                        // 2. Jika belum ada unit aset di gudang, daftarkan otomatis ke register aset ruangan
                        $remainingToCreate = $qty - $transferredCount;
                        for ($i = 0; $i < $remainingToCreate; $i++) {
                            $kodeAset = CodeGenerator::generate('AST', Aset::class, 'kode_aset');
                            $batch = $detail->stokBatch;
                            $nilaiPerolehan = (float) ($batch?->harga_satuan ?: ($barang->harga_beli_terakhir ?: ($barang->harga_estimasi ?: 0)));
                            $nomorSeri = $batch?->nomor_batch ?: ('SN-' . $barang->kode . '-' . date('Ymd') . '-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT));

                            Aset::create([
                                'barang_id'         => $barang->id,
                                'unit_id'           => $sppb->unit_id,
                                'gudang_id'         => null,
                                'lokasi_gudang_id'  => null,
                                'kode_aset'         => $kodeAset,
                                'nomor_seri'        => $nomorSeri,
                                'tanggal_perolehan' => now()->toDateString(),
                                'nilai_perolehan'   => $nilaiPerolehan,
                                'kondisi'           => 'BAIK',
                                'status'            => 'TERSEDIA',
                            ]);
                        }
                    } else {
                        // Komoditas habis pakai atau sparepart: catat ke stok_ruangan
                        $stok = StokRuangan::firstOrCreate(
                            [
                                'unit_id'   => $sppb->unit_id,
                                'barang_id' => $detail->barang_id,
                            ],
                            [
                                'jumlah' => 0,
                            ]
                        );
                        $stok->increment('jumlah', $qty);
                    }
                }

                $sppb->update(['status' => 'DITERIMA']);

                if ($sppb->permintaanDistribusi) {
                    $sppb->permintaanDistribusi->update(['status' => 'SELESAI']);
                }

                return $rcv;
            });

            $msg = ($hasAssetItem ?? false)
                ? "Barang dari SPPB {$sppb->kode} berhasil diterima dan didaftarkan ke inventaris aset unit Anda ({$penerimaan->kode})."
                : "Barang dari SPPB {$sppb->kode} berhasil diterima dan dimasukkan ke stok ruangan ({$penerimaan->kode}).";
            return $this->jsonSuccess([
                'id'       => $penerimaan->id,
                'kode_rcv' => $penerimaan->kode,
                'status'   => 'SELESAI',
            ], $msg, 201);
        } catch (\Throwable $e) {
            return $this->jsonError('Gagal konfirmasi penerimaan: ' . $e->getMessage(), 500);
        }
    }
}
