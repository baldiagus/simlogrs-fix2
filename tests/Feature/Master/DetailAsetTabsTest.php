<?php

namespace Tests\Feature\Master;

use App\Livewire\Master\AsetIndex;
use App\Models\Aset\Aset;
use App\Models\Aset\PengalihanAset;
use App\Models\Aset\PengalihanAsetDetail;
use App\Models\Aset\PermintaanPeminjaman;
use App\Models\Aset\PermintaanPeminjamanDetail;
use App\Models\Ipsrs\PermintaanMaintenance;
use App\Models\Master\Barang;
use App\Models\Master\Unit;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class DetailAsetTabsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected Unit $unitA;
    protected Unit $unitB;
    protected Barang $barangAset;
    protected Aset $aset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('email', 'superadmin@simlogrs.local')->first();
        $this->actingAs($this->admin);

        $this->unitA = Unit::where('status', 'AKTIF')->first();
        $this->unitB = Unit::where('status', 'AKTIF')->where('id', '!=', $this->unitA->id)->first() ?? $this->unitA;

        $this->barangAset = Barang::where('status', 'AKTIF')->where('tipe', 'ASET')->first();
        if (! $this->barangAset) {
            $this->barangAset = Barang::create([
                'kode' => 'BRG-AST-TAB-TEST',
                'nama' => 'USG Tab Test Unit',
                'kategori_id' => 1,
                'satuan_id' => 1,
                'tipe' => 'ASET',
                'status' => 'AKTIF',
            ]);
        }

        $this->aset = Aset::create([
            'barang_id' => $this->barangAset->id,
            'unit_id' => $this->unitA->id,
            'kode_aset' => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri' => 'SN-TAB-001',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 25000000,
            'kondisi' => 'BAIK',
            'status' => 'TERSEDIA',
        ]);
    }

    public function test_detail_aset_modal_renders_all_four_tab_navigation_headers(): void
    {
        Livewire::test(AsetIndex::class)
            ->call('showDetail', $this->aset->id)
            ->assertSet('showDetailModal', true)
            ->assertSee('Informasi Umum')
            ->assertSee('Rekam Medis & Servis')
            ->assertSee('Riwayat Peminjaman')
            ->assertSee('Riwayat Pengalihan');
    }

    public function test_detail_aset_modal_renders_complete_history_when_records_exist(): void
    {
        // 1. Buat histori servis maintenance
        $tiket = PermintaanMaintenance::create([
            'kode' => CodeGenerator::generateMnt(),
            'aset_id' => $this->aset->id,
            'unit_id' => $this->unitA->id,
            'dilaporkan_oleh' => $this->admin->id,
            'deskripsi_masalah' => 'Sensor probe buram dan tidak kalibrasi',
            'prioritas' => 'TINGGI',
            'status' => 'DITUGASKAN',
        ]);

        // 2. Buat histori peminjaman
        $pinjam = PermintaanPeminjaman::create([
            'kode' => 'PINJ-TAB-TEST-01',
            'tipe_penyedia' => 'RUANGAN',
            'unit_penyedia_id' => $this->unitA->id,
            'unit_id' => $this->unitB->id,
            'diajukan_oleh' => $this->admin->id,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(3)->toDateString(),
            'keperluan' => 'Peminjaman operasional poli spesialis',
            'status' => 'DIPINJAM',
        ]);

        PermintaanPeminjamanDetail::create([
            'permintaan_peminjaman_id' => $pinjam->id,
            'aset_id' => $this->aset->id,
            'kondisi_saat_pinjam' => 'BAIK',
        ]);

        // 3. Buat histori pengalihan
        $pengalihan = PengalihanAset::create([
            'kode' => 'TRF-TAB-TEST-01',
            'tipe_asal' => 'RUANGAN',
            'unit_asal_id' => $this->unitA->id,
            'tipe_tujuan' => 'RUANGAN',
            'unit_tujuan_id' => $this->unitB->id,
            'diajukan_oleh' => $this->admin->id,
            'tanggal_pengalihan' => now()->toDateString(),
            'alasan' => 'Pemerataan fasilitas medis',
            'status' => 'SELESAI',
        ]);

        PengalihanAsetDetail::create([
            'pengalihan_aset_id' => $pengalihan->id,
            'aset_id' => $this->aset->id,
            'kondisi_sebelum' => 'BAIK',
            'kondisi_saat_diterima' => 'BAIK',
        ]);

        // Buka modal detail aset
        Livewire::test(AsetIndex::class)
            ->call('showDetail', $this->aset->id)
            ->assertSet('showDetailModal', true)
            ->assertSee($tiket->kode)
            ->assertSee('Sensor probe buram dan tidak kalibrasi')
            ->assertSee($pinjam->kode)
            ->assertSee('Peminjaman operasional poli spesialis')
            ->assertSee($pengalihan->kode)
            ->assertSee('Pemerataan fasilitas medis');
    }
}
