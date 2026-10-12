<?php

namespace Tests\Feature;

use App\Livewire\Aset\ApprovalPeminjamanIndex;
use App\Livewire\Aset\PengalihanAsetIndex;
use App\Livewire\Distribusi\PickingDistribusiIndex;
use App\Livewire\Distribusi\StokRuanganIndex;
use App\Livewire\Ipsrs\DaftarSpkPerbaikanIndex;
use App\Livewire\Ipsrs\LaporServisIndex;
use App\Livewire\Pengadaan\PermintaanPengadaanIndex;
use App\Models\AnggaranPengadaan;
use App\Models\Aset;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\KategoriBarang;
use App\Models\LogAudit;
use App\Models\LokasiGudang;
use App\Models\PengalihanAset;
use App\Models\PengirimanBarang;
use App\Models\PenugasanMaintenance;
use App\Models\PermintaanDistribusi;
use App\Models\PermintaanDistribusiDetail;
use App\Models\PermintaanMaintenance;
use App\Models\PermintaanPeminjaman;
use App\Models\PermintaanPeminjamanDetail;
use App\Models\PermintaanPengadaan;
use App\Models\PermintaanPengadaanDetail;
use App\Models\SatuanBarang;
use App\Models\StokBatch;
use App\Models\StokRuangan;
use App\Models\Unit;
use App\Models\User;
use App\Services\Anggaran\AnggaranRealtimeService;
use App\Services\Aset\PengalihanAsetService;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * ADR 0002 B: proses bisnis multi-langkah yang menulis model ber-HasAuditLog
 * berjalan dalam satu transaksi; jika penulisan log_audit gagal, seluruh proses batal.
 */
class AuditAtomicityTransaksiTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected Unit $unit;
    protected Gudang $gudang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('email', 'superadmin@simlogrs.local')->firstOrFail();
        $this->unit = Unit::where('status', 'AKTIF')->firstOrFail();
        $this->gudang = Gudang::where('status', 'AKTIF')->firstOrFail();
    }

    protected function tearDown(): void
    {
        LogAudit::flushEventListeners();

        parent::tearDown();
    }

    /**
     * Paksa penulisan log_audit ke-$n (dan seterusnya) gagal. Penulisan sebelumnya berhasil,
     * sehingga test membuktikan langkah yang SUDAH tersimpan ikut dibatalkan.
     */
    protected function failAuditFromWrite(int $n = 1): void
    {
        $count = 0;
        LogAudit::creating(function () use (&$count, $n) {
            if (++$count >= $n) {
                throw new RuntimeException('log_audit tidak dapat ditulis');
            }
        });
    }

    protected function makeUser(string $username, array $attrs = []): User
    {
        return User::create(array_merge([
            'nama'     => "Pengguna {$username}",
            'username' => $username,
            'email'    => "{$username}@simlog.test",
            'password' => bcrypt('password'),
            'status'   => 'AKTIF',
        ], $attrs));
    }

    protected function makeAset(string $status = 'TERSEDIA'): Aset
    {
        $barang = Barang::where('status', 'AKTIF')->where('tipe', 'ASET')->first()
            ?: Barang::where('status', 'AKTIF')->firstOrFail();

        return Aset::create([
            'barang_id'         => $barang->id,
            'unit_id'           => $this->unit->id,
            'gudang_id'         => null,
            'kode_aset'         => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri'        => 'SN-ATOM-' . uniqid(),
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan'   => 1000000,
            'kondisi'           => 'BAIK',
            'status'            => $status,
        ]);
    }

    public function test_picking_fefo_rolls_back_sppb_and_stock_when_audit_fails(): void
    {
        $petugas = $this->makeUser('gudangatomicity', ['gudang_id' => $this->gudang->id]);
        $petugas->givePermissionTo([
            'view_any_gudang_stok::picking::distribusi',
            'create_gudang_stok::picking::distribusi',
            'page_PickingDistribusiIndex',
        ]);

        $kategori = KategoriBarang::firstOrCreate(['kode' => 'KAT-ATOM'], ['nama' => 'Kategori Atomicity', 'status' => 'AKTIF']);
        $satuan = SatuanBarang::firstOrCreate(['kode' => 'PCS-ATOM'], ['nama' => 'Pcs', 'status' => 'AKTIF']);
        $barang = Barang::firstOrCreate(['kode' => 'BRG-ATOM-01'], [
            'nama' => 'Kasa Steril Atomicity', 'kategori_id' => $kategori->id, 'satuan_id' => $satuan->id,
            'tipe' => 'HABIS_PAKAI', 'status' => 'AKTIF',
        ]);
        $lokasi = LokasiGudang::firstOrCreate(
            ['kode' => 'RAK-ATOM-01', 'gudang_id' => $this->gudang->id],
            ['nama' => 'Rak Atomicity', 'status' => 'AKTIF']
        );
        $batch = StokBatch::create([
            'barang_id' => $barang->id, 'gudang_id' => $this->gudang->id, 'lokasi_gudang_id' => $lokasi->id,
            'nomor_batch' => 'BATCH-ATOM-01', 'tanggal_kedaluwarsa' => now()->addYear()->toDateString(),
            'jumlah' => 10, 'status' => 'AKTIF',
        ]);

        $req = PermintaanDistribusi::create([
            'kode' => CodeGenerator::generate('REQ', PermintaanDistribusi::class, 'kode', 'Ym', 3),
            'unit_id' => $this->unit->id, 'gudang_id' => $this->gudang->id,
            'tanggal_permintaan' => now()->toDateString(), 'urgensi' => 'REGULER', 'status' => 'DISETUJUI_GUDANG',
            'diajukan_oleh' => $petugas->id, 'disetujui_oleh' => $petugas->id,
        ]);
        PermintaanDistribusiDetail::create([
            'permintaan_distribusi_id' => $req->id, 'barang_id' => $barang->id, 'jumlah' => 4, 'jumlah_disetujui' => 4,
        ]);

        $this->actingAs($petugas);
        // SPPB (audit #1) tersimpan; audit langkah berikutnya gagal (ditoast, tidak dilempar)
        $this->failAuditFromWrite(2);

        Livewire::test(PickingDistribusiIndex::class)->call('executePicking', $req->id);

        $this->assertSame('DISETUJUI_GUDANG', $req->fresh()->status);
        $this->assertEquals(10, $batch->fresh()->jumlah);
        $this->assertFalse(PengirimanBarang::where('permintaan_distribusi_id', $req->id)->exists());
    }

    public function test_peminjaman_approval_rolls_back_when_audit_fails(): void
    {
        $aset = $this->makeAset();
        $pemohon = $this->makeUser('pemohonatomicity', ['unit_id' => $this->unit->id]);

        $pinjam = PermintaanPeminjaman::create([
            'kode' => CodeGenerator::generate('PINJ', PermintaanPeminjaman::class, 'kode', 'Y', 3),
            'tipe_penyedia' => 'RUANGAN', 'unit_penyedia_id' => $this->unit->id, 'unit_id' => $this->unit->id,
            'diajukan_oleh' => $pemohon->id, 'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(3)->toDateString(), 'keperluan' => 'Uji atomisitas',
            'status' => 'DIAJUKAN',
        ]);
        PermintaanPeminjamanDetail::create([
            'permintaan_peminjaman_id' => $pinjam->id, 'aset_id' => $aset->id, 'kondisi_saat_pinjam' => 'BAIK',
        ]);

        $this->actingAs($this->admin);
        $this->failAuditFromWrite(1);

        $this->assertThrows(
            fn () => Livewire::test(ApprovalPeminjamanIndex::class)->call('approve', $pinjam->id),
            RuntimeException::class,
            'log_audit tidak dapat ditulis'
        );

        $pinjam->refresh();
        $this->assertSame('DIAJUKAN', $pinjam->status);
        $this->assertNull($pinjam->disetujui_oleh);
    }

    public function test_pengalihan_rejection_keeps_asset_locked_when_audit_fails(): void
    {
        $aset = $this->makeAset();
        $unitTujuan = Unit::where('status', 'AKTIF')->where('id', '!=', $this->unit->id)->firstOrFail();
        $pengaju = $this->makeUser('pengajuatomicity', ['unit_id' => $unitTujuan->id]);

        $transfer = app(PengalihanAsetService::class)->ajukanPengalihan([
            'tipe_asal' => 'RUANGAN', 'unit_asal_id' => $this->unit->id,
            'tipe_tujuan' => 'RUANGAN', 'unit_tujuan_id' => $unitTujuan->id,
            'tanggal_pengalihan' => now()->toDateString(), 'alasan' => 'Uji atomisitas pengalihan',
            'diajukan_oleh' => $pengaju->id, 'aset_ids' => [$aset->id],
        ]);
        $this->assertSame('DALAM_PENGALIHAN', $aset->fresh()->status);

        $this->actingAs($this->admin);
        // Pelepasan status aset (audit #1) berhasil; audit status dokumen gagal (ditoast, tidak dilempar)
        $this->failAuditFromWrite(2);

        Livewire::test(PengalihanAsetIndex::class)
            ->set('actionTransferId', $transfer->id)
            ->set('actionType', 'REJECT')
            ->set('catatanApproval', 'Ditolak uji atomisitas')
            ->call('executeApproval');

        $this->assertSame('DIAJUKAN', PengalihanAset::find($transfer->id)->status);
        $this->assertSame('DALAM_PENGALIHAN', $aset->fresh()->status);
    }

    public function test_spk_assignment_rolls_back_ticket_and_asset_when_audit_fails(): void
    {
        $aset = $this->makeAset('DIGUNAKAN');
        $teknisi = $this->makeUser('teknisiatomicity');

        $tiket = PermintaanMaintenance::create([
            'kode' => CodeGenerator::generateMnt(), 'aset_id' => $aset->id, 'unit_id' => $this->unit->id,
            'dilaporkan_oleh' => $this->admin->id, 'deskripsi_masalah' => 'Uji atomisitas SPK',
            'prioritas' => 'SEDANG', 'status' => 'DILAPORKAN',
        ]);

        $this->actingAs($this->admin);
        // SPK (audit #1) dan status tiket (audit #2) tersimpan; audit status aset gagal
        $this->failAuditFromWrite(3);

        $this->assertThrows(
            fn () => Livewire::test(DaftarSpkPerbaikanIndex::class)
                ->set('selected_tiket_id', $tiket->id)
                ->set('teknisi_id', $teknisi->id)
                ->set('tanggal_penugasan', now()->toDateString())
                ->set('target_selesai', now()->addDays(2)->toDateString())
                ->call('saveSpk'),
            RuntimeException::class,
            'log_audit tidak dapat ditulis'
        );

        $this->assertSame('DILAPORKAN', $tiket->fresh()->status);
        $this->assertFalse(PenugasanMaintenance::where('permintaan_maintenance_id', $tiket->id)->exists());
        $this->assertSame('DIGUNAKAN', $aset->fresh()->status);
    }

    public function test_stok_ruangan_pemakaian_rolls_back_stock_when_audit_fails(): void
    {
        $barang = Barang::where('status', 'AKTIF')->where('tipe', 'HABIS_PAKAI')->firstOrFail();
        $stok = StokRuangan::updateOrCreate(
            ['unit_id' => $this->unit->id, 'barang_id' => $barang->id],
            ['jumlah' => 10, 'stok_minimum' => 2, 'stok_maksimum' => 20]
        );

        $this->actingAs($this->admin);
        $this->failAuditFromWrite(1);

        // Kegagalan ditoast (tidak dilempar), stok wajib utuh
        Livewire::test(StokRuanganIndex::class)
            ->call('openCatatPemakaian', $stok->id)
            ->set('jumlah_pakai', 3)
            ->set('keterangan', 'Uji atomisitas pemakaian')
            ->call('simpanPemakaian')
            ->assertSet('showPakaiModal', true);

        $this->assertEquals(10, $stok->fresh()->jumlah);
    }

    public function test_lapor_servis_rolls_back_ticket_when_asset_audit_fails(): void
    {
        $aset = $this->makeAset('DIGUNAKAN');

        $this->actingAs($this->admin);
        // Tiket (audit #1) tersimpan; audit perubahan status aset gagal
        $this->failAuditFromWrite(2);

        $this->assertThrows(
            fn () => Livewire::test(LaporServisIndex::class)
                ->set('aset_id', $aset->id)
                ->set('prioritas', 'TINGGI')
                ->set('deskripsi_masalah', 'Uji atomisitas lapor servis')
                ->call('save'),
            RuntimeException::class,
            'log_audit tidak dapat ditulis'
        );

        $this->assertFalse(PermintaanMaintenance::where('aset_id', $aset->id)->exists());
        $this->assertSame('DIGUNAKAN', $aset->fresh()->status);
        $this->assertSame('BAIK', $aset->fresh()->kondisi);
    }

    public function test_permintaan_pengadaan_create_rolls_back_header_when_audit_fails(): void
    {
        $barang = Barang::where('status', 'AKTIF')->firstOrFail();
        $jumlahPr = PermintaanPengadaan::count();
        $jumlahDetail = PermintaanPengadaanDetail::count();

        $this->actingAs($this->admin);
        $this->failAuditFromWrite(1);

        $this->assertThrows(
            fn () => Livewire::test(PermintaanPengadaanIndex::class)
                ->set('unit_id', $this->unit->id)
                ->set('urgensi', 'REGULER')
                ->set('items', [['barang_id' => $barang->id, 'jumlah' => 5]])
                ->call('save'),
            RuntimeException::class,
            'log_audit tidak dapat ditulis'
        );

        $this->assertSame($jumlahPr, PermintaanPengadaan::count());
        $this->assertSame($jumlahDetail, PermintaanPengadaanDetail::count());
    }

    public function test_permintaan_pengadaan_approval_rolls_back_when_audit_fails(): void
    {
        $barang = Barang::where('status', 'AKTIF')->firstOrFail();
        $pr = PermintaanPengadaan::create([
            'kode' => CodeGenerator::generatePr(), 'unit_id' => $this->unit->id, 'diajukan_oleh' => $this->admin->id,
            'tanggal_permintaan' => now()->toDateString(), 'urgensi' => 'REGULER', 'status' => 'DIAJUKAN',
        ]);
        $detail = PermintaanPengadaanDetail::create([
            'permintaan_pengadaan_id' => $pr->id, 'barang_id' => $barang->id, 'jumlah' => 7, 'jumlah_disetujui' => null,
        ]);

        $this->actingAs($this->admin);
        $this->failAuditFromWrite(1);

        $this->assertThrows(
            fn () => Livewire::test(PermintaanPengadaanIndex::class)->call('approve', $pr->id),
            RuntimeException::class,
            'log_audit tidak dapat ditulis'
        );

        $this->assertSame('DIAJUKAN', $pr->fresh()->status);
        $this->assertNull($detail->fresh()->jumlah_disetujui);
    }

    public function test_sync_realisasi_anggaran_rolls_back_all_rows_when_one_audit_fails(): void
    {
        $unitLain = Unit::where('status', 'AKTIF')->where('id', '!=', $this->unit->id)->firstOrFail();
        $rows = collect([$this->unit, $unitLain])->map(fn (Unit $u, $i) => AnggaranPengadaan::create([
            'unit_id' => $u->id, 'tipe' => 'UNIT', 'kode' => "ANG-ATOM-{$i}", 'pos_anggaran' => 'Uji atomisitas',
            'pagu' => 1000, 'realisasi' => 999, 'sisa' => 1, 'serapan' => 99.9, 'tahun' => '2099', 'status' => 'Waspada',
        ]));

        // Anggaran pertama (audit #1) tersinkron; audit anggaran kedua gagal
        $this->failAuditFromWrite(2);

        $this->assertThrows(
            fn () => app(AnggaranRealtimeService::class)->syncRealisasiAnggaran('2099'),
            RuntimeException::class,
            'log_audit tidak dapat ditulis'
        );

        foreach ($rows as $row) {
            $this->assertEquals(999, (float) $row->fresh()->realisasi);
            $this->assertSame('Waspada', $row->fresh()->status);
        }
    }
}
