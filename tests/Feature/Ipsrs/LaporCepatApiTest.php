<?php

namespace Tests\Feature\Ipsrs;

use App\Models\Aset\Aset;
use App\Models\Ipsrs\PermintaanMaintenance;
use App\Models\Master\Barang;
use App\Models\Master\Unit;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LaporCepatApiTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Unit $unit;
    protected Aset $aset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = Unit::where('status', 'AKTIF')->first();

        $this->user = User::create([
            'nama'     => 'Staff Lapor Cepat Test',
            'username' => 'stafflaporcepat',
            'email'    => 'stafflaporcepat@simlog.test',
            'password' => bcrypt('password'),
            'unit_id'  => $this->unit->id,
            'status'   => 'AKTIF',
        ]);

        $this->user->givePermissionTo([
            'create_layanan_unit::lapor::servis',
            'view_layanan_unit::lapor::servis',
        ]);

        $barang = Barang::where('status', 'AKTIF')->where('tipe', 'ASET')->first()
            ?: Barang::where('status', 'AKTIF')->first();

        $this->aset = Aset::create([
            'barang_id'         => $barang->id,
            'unit_id'           => $this->unit->id,
            'kode_aset'         => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri'        => 'SN-QUICK-TEST-001',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan'   => 5000000,
            'kondisi'           => 'BAIK',
            'status'            => 'DIGUNAKAN',
        ]);
    }

    public function test_lapor_cepat_succeeds_with_full_payload(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/maintenance/lapor-cepat', [
                'aset_id'           => $this->aset->id,
                'deskripsi_masalah' => 'Tombol power macet dan indikator berkedip merah terus menerus',
                'prioritas'         => 'TINGGI',
            ])
            ->assertStatus(201);
        $response->assertJsonPath('sukses', true);
        $response->assertJsonPath('data.prioritas', 'TINGGI');
        $response->assertJsonPath('data.aset.id', $this->aset->id);

        $tiketId = $response->json('data.id');
        $this->assertDatabaseHas('permintaan_maintenance', [
            'id'                => $tiketId,
            'aset_id'           => $this->aset->id,
            'unit_id'           => $this->unit->id,
            'dilaporkan_oleh'   => $this->user->id,
            'prioritas'         => 'TINGGI',
            'status'            => 'DILAPORKAN',
        ]);
    }

    public function test_lapor_cepat_defaults_prioritas_to_sedang_when_omitted(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/maintenance/lapor-cepat', [
                'aset_id'           => $this->aset->id,
                'deskripsi_masalah' => 'Kertas tersangkut di roller penarik depan',
            ])
            ->assertStatus(201);

        $response->assertJsonPath('data.prioritas', 'SEDANG');

        $tiketId = $response->json('data.id');
        $this->assertDatabaseHas('permintaan_maintenance', [
            'id'        => $tiketId,
            'prioritas' => 'SEDANG',
        ]);
    }

    public function test_lapor_cepat_with_cito_marks_asset_status_dalam_maintenance(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/maintenance/lapor-cepat', [
                'aset_id'           => $this->aset->id,
                'deskripsi_masalah' => 'Korsleting listrik darurat pada panel catu daya',
                'prioritas'         => 'CITO',
            ])
            ->assertStatus(201);

        $this->aset->refresh();
        $this->assertSame('DALAM_MAINTENANCE', $this->aset->status);
        $this->assertSame('RUSAK_BERAT', $this->aset->kondisi);
    }

    public function test_lapor_cepat_with_photo_upload_stores_file(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->user)
            ->post('/api/v1/maintenance/lapor-cepat', [
                'aset_id'           => $this->aset->id,
                'deskripsi_masalah' => 'Panel layar LCD retak fisik',
                'prioritas'         => 'RENDAH',
                'foto'              => UploadedFile::fake()->image('kerusakan.jpg', 600, 400),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertStatus(201);

        $this->assertNotNull($response->json('data.foto_url'));
    }
}
