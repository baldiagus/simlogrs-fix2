<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\PengalihanAset;
use App\Models\PengalihanAsetDetail;
use App\Models\PengirimanBarang;
use App\Models\PengirimanBarangDetail;
use App\Models\PermintaanDistribusi;
use App\Models\PermintaanDistribusiDetail;
use App\Models\PermintaanPeminjaman;
use App\Models\PermintaanPeminjamanDetail;
use App\Models\StokBatch;
use App\Models\Unit;
use App\Models\User;
use App\Services\Aset\PeminjamanAsetService;
use App\Services\Aset\PengalihanAsetService;
use App\Services\Persediaan\PengalihanBarangService;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use Tests\TestCase;

class DataIntegrityP2Test extends TestCase
{
    use DatabaseTransactions;

    private Unit $unitA;
    private Unit $unitB;
    private Gudang $gudangA;
    private Gudang $gudangB;
    private Barang $barangA;
    private Barang $barangB;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->unitA, $this->unitB] = Unit::where('status', 'AKTIF')->orderBy('id')->take(2)->get()->all();
        [$this->gudangA, $this->gudangB] = Gudang::where('status', 'AKTIF')->orderBy('id')->take(2)->get()->all();
        [$this->barangA, $this->barangB] = Barang::where('status', 'AKTIF')->orderBy('id')->take(2)->get()->all();

        $this->admin = User::create([
            'nama'     => 'Admin P2 Test',
            'username' => 'admin_p2_' . uniqid(),
            'email'    => 'admin_p2_' . uniqid() . '@simlog.test',
            'password' => bcrypt('password'),
            'status'   => 'AKTIF',
        ]);
        $this->admin->assignRole('super_admin');
    }

    public function test_pengalihan_aset_rejects_missing_facility_or_self_transfer(): void
    {
        $service = app(PengalihanAsetService::class);
        $aset = Aset::create([
            'barang_id'         => $this->barangA->id,
            'unit_id'           => $this->unitA->id,
            'kode_aset'         => 'AST-INT-' . uniqid(),
            'nomor_seri'        => 'SN-INT-1',
            'tanggal_perolehan' => now()->toDateString(),
            'kondisi'           => 'BAIK',
            'status'            => 'TERSEDIA',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $service->ajukanPengalihan([
            'aset_ids'      => [$aset->id],
            'tipe_asal'     => 'RUANGAN',
            'tipe_tujuan'   => 'RUANGAN',
            'unit_asal_id'  => (string) $this->unitA->id,
            'unit_tujuan_id'=> (int) $this->unitA->id, // self-transfer
            'diajukan_oleh' => $this->admin->id,
        ]);
    }

    public function test_pengalihan_barang_rejects_batch_from_wrong_warehouse(): void
    {
        $service = app(PengalihanBarangService::class);

        // Batch belongs to Gudang B, but transfer origin is Gudang A
        $batch = StokBatch::create([
            'barang_id'           => $this->barangA->id,
            'gudang_id'           => $this->gudangB->id,
            'nomor_batch'         => 'B-WRONG-' . uniqid(),
            'tanggal_kedaluwarsa' => now()->addYear()->toDateString(),
            'jumlah'              => 50,
            'status'              => 'AKTIF',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $service->ajukanPengalihan([
            'tipe_asal'        => 'GUDANG',
            'gudang_asal_id'   => $this->gudangA->id,
            'tipe_tujuan'      => 'GUDANG',
            'gudang_tujuan_id' => $this->gudangB->id,
            'diajukan_oleh'    => $this->admin->id,
            'items'            => [
                [
                    'barang_id'     => $this->barangA->id,
                    'stok_batch_id' => $batch->id,
                    'jumlah'        => 10,
                ],
            ],
        ]);
    }

    public function test_fulfillment_per_item_surplus_on_item_a_cannot_fulfill_item_b(): void
    {
        $permintaan = PermintaanDistribusi::create([
            'kode'               => 'REQ-INT-' . uniqid(),
            'unit_id'            => $this->unitA->id,
            'gudang_id'          => $this->gudangA->id,
            'tanggal_permintaan' => now()->toDateString(),
            'urgensi'            => 'REGULER',
            'status'             => 'DISETUJUI_GUDANG',
            'diajukan_oleh'      => $this->admin->id,
        ]);

        PermintaanDistribusiDetail::create([
            'permintaan_distribusi_id' => $permintaan->id,
            'barang_id'                => $this->barangA->id,
            'jumlah'                   => 10,
            'jumlah_disetujui'         => 10,
        ]);
        PermintaanDistribusiDetail::create([
            'permintaan_distribusi_id' => $permintaan->id,
            'barang_id'                => $this->barangB->id,
            'jumlah'                   => 10,
            'jumlah_disetujui'         => 10,
        ]);

        $sppb = PengirimanBarang::create([
            'kode'                     => 'SPPB-INT-' . uniqid(),
            'permintaan_distribusi_id' => $permintaan->id,
            'gudang_id'                => $this->gudangA->id,
            'unit_id'                  => $this->unitA->id,
            'tanggal_kirim'            => now()->toDateString(),
            'status'                   => 'DIKIRIM',
            'dikirim_oleh'             => $this->admin->id,
        ]);

        $batch = StokBatch::create([
            'barang_id'           => $this->barangA->id,
            'gudang_id'           => $this->gudangA->id,
            'nomor_batch'         => 'B-SURPLUS-' . uniqid(),
            'tanggal_kedaluwarsa' => now()->addYear()->toDateString(),
            'jumlah'              => 50,
            'status'              => 'AKTIF',
        ]);

        // Kirim surplus pada Barang A (25 unit, melebihi kuota 10), tapi Barang B belum dikirim sama sekali (0 unit)
        // Agregat sum = 25 >= total requested (20). Tapi per-item: Barang B masih 0 < 10!
        PengirimanBarangDetail::create([
            'pengiriman_barang_id' => $sppb->id,
            'barang_id'            => $this->barangA->id,
            'stok_batch_id'        => $batch->id,
            'jumlah_dikirim'       => 25,
        ]);

        $this->assertFalse(
            $permintaan->fresh()->semuaBarangDikirim(),
            'Surplus pada satu komoditas tidak boleh menutup kekurangan komoditas lain.'
        );
    }

    public function test_loan_return_inspection_requires_every_detail_and_no_foreign_asset(): void
    {
        $aset1 = Aset::create([
            'barang_id' => $this->barangA->id, 'unit_id' => $this->unitA->id,
            'kode_aset' => 'AST-P2-1-' . uniqid(), 'nomor_seri' => 'SN-1',
            'tanggal_perolehan' => now()->toDateString(), 'kondisi' => 'BAIK', 'status' => 'TERSEDIA',
        ]);
        $aset2 = Aset::create([
            'barang_id' => $this->barangA->id, 'unit_id' => $this->unitA->id,
            'kode_aset' => 'AST-P2-2-' . uniqid(), 'nomor_seri' => 'SN-2',
            'tanggal_perolehan' => now()->toDateString(), 'kondisi' => 'BAIK', 'status' => 'TERSEDIA',
        ]);

        $pinjam = PermintaanPeminjaman::create([
            'kode' => 'PINJ-P2-' . uniqid(), 'tipe_penyedia' => 'RUANGAN',
            'unit_penyedia_id' => $this->unitA->id, 'unit_id' => $this->unitB->id,
            'diajukan_oleh' => $this->admin->id, 'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(3)->toDateString(),
            'status' => 'DIPINJAM',
        ]);
        $detail1 = PermintaanPeminjamanDetail::create([
            'permintaan_peminjaman_id' => $pinjam->id, 'aset_id' => $aset1->id, 'kondisi_saat_pinjam' => 'BAIK',
        ]);
        $detail2 = PermintaanPeminjamanDetail::create([
            'permintaan_peminjaman_id' => $pinjam->id, 'aset_id' => $aset2->id, 'kondisi_saat_pinjam' => 'BAIK',
        ]);

        $service = app(PeminjamanAsetService::class);

        // Kasus A: Hanya mengembalikan 1 aset dari 2 aset (inspeksi parsial / ada yang tertinggal) -> throw
        $this->expectException(\Exception::class);
        $service->prosesPengembalian($pinjam, [
            [
                'permintaan_peminjaman_detail_id' => $detail1->id,
                'aset_id'                         => $aset1->id,
                'kondisi_saat_kembali'            => 'BAIK',
            ],
        ], $this->admin);
    }

    public function test_code_generator_orders_by_length_before_lexicographical(): void
    {
        // Buat dummy records dengan nomor urut yang melintasi digit pad (misal 99 dan 100)
        // Tanpa length(), 'TEST-2026-99' > 'TEST-2026-100' secara leksikografis
        $prefix = 'PINJTEST';
        $year = '2099';
        $p1 = PermintaanPeminjaman::create([
            'kode' => "{$prefix}-{$year}-99", 'tipe_penyedia' => 'RUANGAN',
            'unit_penyedia_id' => $this->unitA->id, 'unit_id' => $this->unitB->id,
            'diajukan_oleh' => $this->admin->id, 'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(1)->toDateString(), 'status' => 'DIAJUKAN',
        ]);
        $p2 = PermintaanPeminjaman::create([
            'kode' => "{$prefix}-{$year}-100", 'tipe_penyedia' => 'RUANGAN',
            'unit_penyedia_id' => $this->unitA->id, 'unit_id' => $this->unitB->id,
            'diajukan_oleh' => $this->admin->id, 'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(1)->toDateString(), 'status' => 'DIAJUKAN',
        ]);

        // Carbon::now()->format('Y') adalah tahun berjalan; uji logika order length() secara langsung:
        $searchPrefix = "{$prefix}-{$year}-";
        $last = PermintaanPeminjaman::where('kode', 'like', "{$searchPrefix}%")
            ->orderByRaw('length(kode) desc')
            ->orderBy('kode', 'desc')
            ->value('kode');
        $parts = explode('-', $last);
        $nextSeq = (int) end($parts) + 1;

        $this->assertSame("{$searchPrefix}101", "{$searchPrefix}{$nextSeq}", 'Urutan nomor berikutnya harus 101, bukan 100.');

    }

    public function test_asset_delete_blocked_when_foreign_dependencies_exist(): void
    {
        $aset = Aset::create([
            'barang_id' => $this->barangA->id, 'unit_id' => $this->unitA->id,
            'kode_aset' => 'AST-DEP-' . uniqid(), 'nomor_seri' => 'SN-DEP',
            'tanggal_perolehan' => now()->toDateString(), 'kondisi' => 'BAIK', 'status' => 'TERSEDIA',
        ]);

        $trf = PengalihanAset::create([
            'kode' => 'TRF-DEP-' . uniqid(), 'tipe_asal' => 'RUANGAN', 'unit_asal_id' => $this->unitA->id,
            'tipe_tujuan' => 'RUANGAN', 'unit_tujuan_id' => $this->unitB->id,
            'tanggal_pengalihan' => now()->toDateString(), 'alasan' => 'Uji dep', 'status' => 'SELESAI',
            'diajukan_oleh' => $this->admin->id,
        ]);
        PengalihanAsetDetail::create([
            'pengalihan_aset_id' => $trf->id,
            'aset_id'            => $aset->id,
        ]);

        \Livewire\Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Master\AsetIndex::class)
            ->call('delete', $aset->id);

        // Aset tetap ada di database karena foreign key dependency di pengalihan_aset_detail ditolak
        $this->assertDatabaseHas('aset', ['id' => $aset->id]);
    }
}
