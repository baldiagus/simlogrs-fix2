<?php

namespace Tests\Feature\Persediaan;

use App\Livewire\Persediaan\PengalihanBarangIndex;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\LokasiGudang;
use App\Models\PengalihanBarang;
use App\Models\StokBatch;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class PengalihanBarangIndexTest extends TestCase
{
    use DatabaseTransactions;

    protected User $userAdmin;
    protected User $userGudang;
    protected User $userUnit;
    protected Gudang $gudang1;
    protected Gudang $gudang2;
    protected Unit $unit1;
    protected Barang $barangBhp;
    protected StokBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userAdmin = User::where('email', 'superadmin@simlogrs.local')->first();

        $gudangs = Gudang::where('status', 'AKTIF')->take(2)->get();
        $this->gudang1 = $gudangs[0];
        $this->gudang2 = $gudangs[1] ?? Gudang::create(['kode' => 'GDG-DEST-TEST', 'nama' => 'Gudang Destinasi Test', 'status' => 'AKTIF']);

        $this->unit1 = Unit::where('status', 'AKTIF')->first();

        // Akun Gudang
        $this->userGudang = User::create([
            'nama' => 'Petugas Gudang RBAC Test',
            'username' => 'petugas_gudang_rbac',
            'email' => 'gudang_rbac@simlog.test',
            'password' => bcrypt('password'),
            'gudang_id' => $this->gudang1->id,
            'status' => 'AKTIF',
        ]);
        $this->userGudang->givePermissionTo([
            'view_any_gudang_stok::pengalihan::barang',
            'create_gudang_stok::pengalihan::barang',
        ]);

        // Akun Unit Ruangan
        $this->userUnit = User::create([
            'nama' => 'Petugas Unit Ruangan Test',
            'username' => 'petugas_unit_rbac',
            'email' => 'unit_rbac@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $this->unit1->id,
            'status' => 'AKTIF',
        ]);
        $this->userUnit->givePermissionTo([
            'view_any_layanan_unit::pengalihan::aset',
            'create_layanan_unit::pengalihan::aset',
        ]);

        $satuanId = \App\Models\SatuanBarang::firstOrCreate(['kode' => 'UNT-TRF-BRG'], ['nama' => 'Unit', 'status' => 'AKTIF'])->id;
        $kategoriId = \App\Models\KategoriBarang::firstOrCreate(['kode' => 'KAT-TRF-BRG'], ['nama' => 'Kategori BHP', 'status' => 'AKTIF'])->id;
        $this->barangBhp = Barang::firstOrCreate(
            ['kode' => 'BRG-BHP-INDEX-01'],
            [
                'nama' => 'Plester Medis Roll',
                'tipe' => 'HABIS_PAKAI',
                'satuan_id' => $satuanId,
                'kategori_id' => $kategoriId,
                'status' => 'AKTIF',
            ]
        );

        $lokasi = LokasiGudang::firstOrCreate(
            ['gudang_id' => $this->gudang1->id, 'kode' => 'RAK-BHP-INDEX'],
            ['nama' => 'Rak BHP Index', 'status' => 'AKTIF']
        );

        $this->batch = StokBatch::create([
            'barang_id' => $this->barangBhp->id,
            'gudang_id' => $this->gudang1->id,
            'lokasi_gudang_id' => $lokasi->id,
            'nomor_batch' => 'BATCH-INDEX-01',
            'tanggal_kedaluwarsa' => now()->addYear()->toDateString(),
            'jumlah' => 50,
            'status' => 'AKTIF',
        ]);
    }

    public function test_warehouse_user_can_access_goods_transfer_page(): void
    {
        $this->actingAs($this->userGudang);

        Livewire::test(PengalihanBarangIndex::class)
            ->assertSee('Pengalihan')
            ->assertSee('Mutasi Barang (BHP)')
            ->assertSee('Pengalihan Berjalan');
    }

    public function test_room_unit_user_is_forbidden_from_accessing_goods_transfer_page(): void
    {
        $this->actingAs($this->userUnit);

        Livewire::test(PengalihanBarangIndex::class)
            ->assertForbidden();
    }

    public function test_warehouse_user_can_submit_goods_transfer_from_another_warehouse_to_their_warehouse(): void
    {
        $this->actingAs($this->userGudang);

        $batch2 = StokBatch::create([
            'barang_id' => $this->barangBhp->id,
            'gudang_id' => $this->gudang2->id,
            'nomor_batch' => 'BATCH-GDG2-TEST',
            'tanggal_kedaluwarsa' => now()->addYear()->toDateString(),
            'jumlah' => 30,
            'status' => 'AKTIF',
        ]);

        Livewire::test(PengalihanBarangIndex::class)
            ->call('openCreateModal')
            ->set('selected_barang_id', $this->barangBhp->id)
            ->set('gudang_asal_id', $this->gudang2->id)
            ->set('selected_stok_batch_id', $batch2->id)
            ->set('quantity', 10)
            ->set('alasan', 'Relokasi stok dari depo logistik 2 ke gudang utama')
            ->call('submitPengalihan')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pengalihan_barang', [
            'gudang_asal_id' => $this->gudang2->id,
            'gudang_tujuan_id' => $this->gudang1->id,
            'status' => 'DIAJUKAN',
            'diajukan_oleh' => $this->userGudang->id,
        ]);
    }

    public function test_destination_location_is_automatically_set_to_authenticated_warehouse_user(): void
    {
        $this->actingAs($this->userGudang);

        Livewire::test(PengalihanBarangIndex::class)
            ->call('openCreateModal')
            ->assertSet('tipe_tujuan', 'GUDANG')
            ->assertSet('gudang_tujuan_id', $this->gudang1->id)
            ->assertSee($this->gudang1->nama);
    }

    public function test_origin_warehouse_defaults_away_from_destination_warehouse(): void
    {
        $this->actingAs($this->userGudang);

        $component = Livewire::test(PengalihanBarangIndex::class)
            ->call('openCreateModal');

        $this->assertNotEquals($this->gudang1->id, $component->get('gudang_asal_id'));
    }

    public function test_user_cannot_tamper_destination_location_on_goods_transfer(): void
    {
        $this->actingAs($this->userGudang);

        // Buat batch di gudang 2
        $batch2 = StokBatch::create([
            'barang_id' => $this->barangBhp->id,
            'gudang_id' => $this->gudang2->id,
            'nomor_batch' => 'BATCH-TAMPER-TEST',
            'tanggal_kedaluwarsa' => now()->addYear()->toDateString(),
            'jumlah' => 40,
            'status' => 'AKTIF',
        ]);

        Livewire::test(PengalihanBarangIndex::class)
            ->call('openCreateModal')
            ->set('selected_barang_id', $this->barangBhp->id)
            ->set('gudang_asal_id', $this->gudang2->id)
            ->set('selected_stok_batch_id', $batch2->id)
            ->set('gudang_tujuan_id', 9999) // Percobaan manipulasi ke ID lain
            ->set('quantity', 5)
            ->set('alasan', 'Percobaan manipulasi gudang tujuan')
            ->call('submitPengalihan')
            ->assertHasNoErrors();

        // Dokumen HARUS tersimpan dengan tujuan gudang1 (gudang milik user login)
        $this->assertDatabaseHas('pengalihan_barang', [
            'gudang_asal_id' => $this->gudang2->id,
            'gudang_tujuan_id' => $this->gudang1->id,
            'status' => 'DIAJUKAN',
            'diajukan_oleh' => $this->userGudang->id,
        ]);
    }
}
