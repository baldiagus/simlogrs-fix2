<?php

namespace Tests\Feature\Aset;

use App\Models\Aset\Aset;
use App\Models\Master\Barang;
use App\Models\Master\KategoriBarang;
use App\Models\Master\SatuanBarang;
use App\Models\Master\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PengalihanAsetScopeTest extends TestCase
{
    use DatabaseTransactions;

    private Unit $unitA;
    private Unit $unitB;
    private SatuanBarang $satuan;
    private KategoriBarang $kategori;
    private Barang $barang;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->unitA, $this->unitB] = Unit::where('status', 'AKTIF')->take(2)->get()->all();

        $this->satuan = SatuanBarang::firstOrCreate(
            ['kode' => 'UNT-SCP'],
            ['nama' => 'Unit Scope', 'status' => 'AKTIF']
        );
        $this->kategori = KategoriBarang::firstOrCreate(
            ['kode' => 'KAT-SCP'],
            ['nama' => 'Kategori Scope', 'status' => 'AKTIF']
        );
        $this->barang = Barang::firstOrCreate(
            ['kode' => 'BRG-SCP-001'],
            [
                'nama' => 'Alat Medis Scope Test',
                'tipe' => 'ASET',
                'satuan_id' => $this->satuan->id,
                'kategori_id' => $this->kategori->id,
                'status' => 'AKTIF',
            ]
        );
    }

    public function test_teknisi_without_permission_is_rejected_with_403_when_calling_post_pengalihan(): void
    {
        $teknisi = $this->createUser('teknisi-user', $this->unitB->id);
        $teknisi->assignRole('teknisi');

        $aset = $this->createAset($this->unitA);

        $payload = [
            'tipe_tujuan' => 'RUANGAN',
            'unit_tujuan_id' => $this->unitB->id,
            'aset_ids' => [$aset->id],
            'tanggal_pengalihan' => now()->toDateString(),
            'alasan' => 'Pengajuan transfer dari teknisi',
        ];

        $response = $this->actingAs($teknisi)->postJson('/api/v1/aset/pengalihan', $payload);

        $response->assertStatus(403)
            ->assertJson([
                'sukses' => false,
                'pesan' => 'Anda tidak memiliki wewenang untuk mengajukan pengalihan aset.',
            ]);
    }

    public function test_petugas_unit_with_permission_passes_authorization(): void
    {
        $petugas = $this->createUser('petugas-user', $this->unitB->id);
        $petugas->assignRole('petugas_unit');

        $aset = $this->createAset($this->unitA);

        $payload = [
            'tipe_tujuan' => 'RUANGAN',
            'unit_tujuan_id' => $this->unitB->id,
            'aset_ids' => [$aset->id],
            'tanggal_pengalihan' => now()->toDateString(),
            'alasan' => 'Pengajuan pengalihan resmi oleh petugas unit',
        ];

        $response = $this->actingAs($petugas)->postJson('/api/v1/aset/pengalihan', $payload);

        $response->assertOk()
            ->assertJson([
                'sukses' => true,
            ]);

        $this->assertDatabaseHas('pengalihan_aset', [
            'unit_asal_id' => $this->unitA->id,
            'unit_tujuan_id' => $this->unitB->id,
            'diajukan_oleh' => $petugas->id,
            'status' => 'DIAJUKAN',
        ]);
    }

    public function test_super_admin_passes_authorization(): void
    {
        $superAdmin = $this->createUser('superadmin-user', null);
        $superAdmin->assignRole('super_admin');

        $aset = $this->createAset($this->unitA);

        $payload = [
            'tipe_tujuan' => 'RUANGAN',
            'unit_tujuan_id' => $this->unitB->id,
            'aset_ids' => [$aset->id],
            'tanggal_pengalihan' => now()->toDateString(),
            'alasan' => 'Pengajuan pengalihan aset oleh super admin',
        ];

        $response = $this->actingAs($superAdmin)->postJson('/api/v1/aset/pengalihan', $payload);

        $response->assertOk()
            ->assertJson([
                'sukses' => true,
            ]);

        $this->assertDatabaseHas('pengalihan_aset', [
            'unit_asal_id' => $this->unitA->id,
            'unit_tujuan_id' => $this->unitB->id,
            'diajukan_oleh' => $superAdmin->id,
            'status' => 'DIAJUKAN',
        ]);
    }

    private function createUser(string $username, ?int $unitId): User
    {
        return User::create([
            'nama' => $username,
            'username' => $username . '-' . uniqid(),
            'email' => $username . '-' . uniqid() . '@simlog.test',
            'password' => bcrypt('password'),
            'unit_id' => $unitId,
            'status' => 'AKTIF',
        ]);
    }

    private function createAset(Unit $unit): Aset
    {
        return Aset::create([
            'barang_id' => $this->barang->id,
            'unit_id' => $unit->id,
            'gudang_id' => null,
            'lokasi_gudang_id' => null,
            'kode_aset' => 'AST-SCP-' . uniqid(),
            'nomor_seri' => 'SN-' . uniqid(),
            'tanggal_perolehan' => now()->toDateString(),
            'kondisi' => 'BAIK',
            'status' => 'TERSEDIA',
        ]);
    }
}
