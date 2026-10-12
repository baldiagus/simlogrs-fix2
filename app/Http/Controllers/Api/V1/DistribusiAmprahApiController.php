<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StatusPermintaanDistribusi;
use App\Http\Controllers\Api\ApiController;
use App\Models\Aset;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\PermintaanDistribusi;
use App\Models\PermintaanDistribusiDetail;
use App\Models\StokBatch;
use App\Models\Unit;
use App\Support\Generator\CodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DistribusiAmprahApiController extends ApiController
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
                $canCancel = $item->status === StatusPermintaanDistribusi::Diajukan->value && (
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
        $canCancel = $amprah->status === StatusPermintaanDistribusi::Diajukan->value && (
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

        $canAmprah = $user && $user->can('create_layanan_unit::permintaan::barang');

        if (! $canAmprah) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk mengajukan amprah barang ruangan.', 403);
        }

        $validator = $this->validatorAmprah($request);

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
        // Komoditas dengan stok gudang kosong atau kurang tetap diizinkan diajukan via API
        // Selisih kebutuhan akan otomatis dicatat sebagai hutang gudang (backorder)

        $directToGudang = $user->hasRole('kepala_unit')
            && $user->can('update_layanan_unit::approval::karu');
        $statusAwal     = $directToGudang ? StatusPermintaanDistribusi::DisetujuiKaru->value : StatusPermintaanDistribusi::Diajukan->value;
        $disetujuiOleh  = $directToGudang ? $user->id : null;

        try {
            $amprah = DB::transaction(fn () => $this->buatDokumenAmprah($request, $user, $unitId, $gudangId, $statusAwal, $disetujuiOleh));

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

    private function buatDokumenAmprah(Request $request, $user, $unitId, $gudangId, string $statusAwal, $disetujuiOleh): PermintaanDistribusi
    {
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
    }

    private function validatorAmprah(Request $request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
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

        if ($amprah->status !== StatusPermintaanDistribusi::Diajukan->value) {
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
            'status'  => StatusPermintaanDistribusi::Ditolak->value,
            'catatan' => ($amprah->catatan ? $amprah->catatan . "\n" : '') . '[Dibatalkan oleh pemohon pada ' . now()->format('d/m/Y H:i') . ']',
        ]);

        return $this->jsonSuccess(null, "Permintaan amprah {$amprah->kode} berhasil dibatalkan.");
    }
}
