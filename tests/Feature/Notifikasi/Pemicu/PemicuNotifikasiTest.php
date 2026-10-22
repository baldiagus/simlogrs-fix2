<?php

namespace Tests\Feature\Notifikasi\Pemicu;

use App\Models\Aset\PengalihanAset;
use App\Models\Aset\Aset;
use App\Models\Aset\PermintaanPeminjaman;
use App\Models\Distribusi\PengirimanBarang;
use App\Models\Distribusi\PermintaanDistribusi;
use App\Models\Ipsrs\PenugasanMaintenance;
use App\Models\Ipsrs\PermintaanMaintenance;
use App\Models\Master\Notifikasi;
use App\Models\Master\Barang;
use App\Models\Master\Pemasok;
use App\Models\Pengadaan\PermintaanPengadaan;
use App\Models\Pengadaan\PesananPembelian;
use App\Models\Persediaan\PenyesuaianStok;
use App\Models\User;
use App\Services\Notifikasi\FakePengirimPush;
use App\Services\Notifikasi\PengirimPush;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pemicu notifikasi via Model Observer: penerima benar, tanpa lintas unit/gudang, tanpa pelaku.
 * Data uji dari seeder: unit IGD(1)/ICU(3), gudang 1 dan 2.
 */
class PemicuNotifikasiTest extends TestCase
{
    use DatabaseTransactions;

    private const UNIT_A = 1;
    private const UNIT_B = 3;
    private const GUDANG_A = 1;
    private const GUDANG_B = 2;

    private int $pemasokId;
    private int $asetId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(PengirimPush::class, new FakePengirimPush());

        // Data referensi dicari/dibuat sendiri agar tes tidak bergantung pada id tetap hasil seeder.
        $this->pemasokId = Pemasok::query()->value('id')
            ?? Pemasok::create(['kode' => $this->kode('VDR'), 'nama' => 'Pemasok Uji Notifikasi', 'status' => 'AKTIF'])->id;
        $this->asetId = Aset::where('unit_id', self::UNIT_A)->value('id')
            ?? Aset::create([
                'barang_id' => Barang::where('tipe', 'ASET')->value('id') ?? Barang::query()->value('id'),
                'unit_id' => self::UNIT_A, 'kode_aset' => $this->kode('AST'), 'tanggal_perolehan' => now()->toDateString(),
                'nilai_perolehan' => 1000000, 'kondisi' => 'BAIK', 'status' => 'TERSEDIA',
            ])->id;
    }

    /** Jumlah notifikasi seorang pengguna yang merujuk dokumen uji tertentu. */
    private function notifUntuk(string $username, string $tipe, int $ref): int
    {
        return Notifikasi::where('pengguna_id', $this->u($username)->id)
            ->where('tipe', $tipe)->where('referensi_id', (string) $ref)->count();
    }

    private function u(string $username): User
    {
        return User::where('username', $username)->firstOrFail();
    }

    private function kode(string $p): string
    {
        return $p . '-' . Str::upper(Str::random(8));
    }

    /** Id penerima untuk (tipe, referensi) terurut. */
    private function penerima(string $tipe, int $ref): array
    {
        return Notifikasi::where('tipe', $tipe)->where('referensi_id', (string) $ref)->orderBy('pengguna_id')->pluck('pengguna_id')->all();
    }

    private function ids(string ...$usernames): array
    {
        $ids = array_map(fn ($n) => $this->u($n)->id, $usernames);
        sort($ids);

        return $ids;
    }

    private function pr(array $o = []): PermintaanPengadaan
    {
        return PermintaanPengadaan::create(array_merge([
            'kode' => $this->kode('PR'), 'unit_id' => self::UNIT_A, 'diajukan_oleh' => $this->u('petugasunit')->id,
            'tanggal_permintaan' => now()->toDateString(), 'urgensi' => 'NORMAL', 'status' => 'DIAJUKAN',
        ], $o));
    }

    private function amprah(array $o = []): PermintaanDistribusi
    {
        return PermintaanDistribusi::create(array_merge([
            'kode' => $this->kode('REQ'), 'unit_id' => self::UNIT_A, 'gudang_id' => self::GUDANG_A,
            'diajukan_oleh' => $this->u('petugasunit')->id, 'tanggal_permintaan' => now()->toDateString(),
            'urgensi' => 'NORMAL', 'status' => 'DIAJUKAN',
        ], $o));
    }

    private function tiket(array $o = []): PermintaanMaintenance
    {
        return PermintaanMaintenance::create(array_merge([
            'kode' => $this->kode('MNT'), 'aset_id' => $this->asetId, 'unit_id' => self::UNIT_A,
            'dilaporkan_oleh' => $this->u('petugasunit')->id, 'deskripsi_masalah' => 'Rusak', 'prioritas' => 'SEDANG',
            'status' => 'DILAPORKAN',
        ], $o));
    }

    private function pinjam(array $o = []): PermintaanPeminjaman
    {
        return PermintaanPeminjaman::create(array_merge([
            'kode' => $this->kode('PINJ'), 'tipe_penyedia' => 'RUANGAN', 'unit_penyedia_id' => self::UNIT_B, 'unit_id' => self::UNIT_A,
            'diajukan_oleh' => $this->u('petugasunit')->id, 'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(3)->toDateString(), 'status' => 'DIAJUKAN',
        ], $o));
    }

    public function test_pr_diajukan_ke_karu_unit_sama_dan_admin_logistik_tanpa_pelaku(): void
    {
        $pr = $this->pr();

        $this->assertSame($this->ids('kepalaunit', 'adminlogistik'), $this->penerima('APPROVAL_PR', $pr->id));
        $n = Notifikasi::where('tipe', 'APPROVAL_PR')->where('referensi_id', (string) $pr->id)->first();
        $this->assertStringContainsString($pr->kode, $n->pesan);
        $this->assertStringContainsString('Instalasi Gawat Darurat', $n->pesan);
        $this->assertNull(Notifikasi::where('pengguna_id', $this->u('karu_icu')->id)->first());

        // Pelaku admin logistik tidak menerima atas aksinya sendiri.
        $this->actingAs($this->u('adminlogistik'));
        $pr2 = $this->pr();
        $this->assertSame($this->ids('kepalaunit'), $this->penerima('APPROVAL_PR', $pr2->id));
    }

    public function test_pr_dari_gudang_ke_kepala_gudang_sama_bukan_gudang_lain(): void
    {
        $pr = $this->pr(['unit_id' => null, 'gudang_id' => self::GUDANG_B]);

        $this->assertSame($this->ids('adminlogistik', 'kagud_ipsrs'), $this->penerima('APPROVAL_PR', $pr->id));
    }

    public function test_pr_diteruskan_ke_pengadaan_dan_pengaju_menerima_keputusan(): void
    {
        $pr = $this->pr();
        $this->actingAs($this->u('kepalaunit'));
        $pr->update(['status' => 'DITERUSKAN_KE_PENGADAAN']);

        $this->assertSame($this->ids('pengadaan'), Notifikasi::where('tipe', 'APPROVAL_PR')->where('referensi_id', (string) $pr->id)->where('judul', 'PR Diteruskan ke Pengadaan')->pluck('pengguna_id')->all());
        $this->assertSame($this->ids('petugasunit'), $this->penerima('PR_DIPUTUSKAN', $pr->id));
    }

    public function test_pr_ditolak_ke_pengaju_saja(): void
    {
        $pr = $this->pr();
        $this->actingAs($this->u('kepalaunit'));
        $pr->update(['status' => 'DITOLAK']);

        $this->assertSame($this->ids('petugasunit'), $this->penerima('PR_DIPUTUSKAN', $pr->id));
    }

    public function test_pr_diubah_tanpa_ganti_status_tidak_memicu(): void
    {
        $pr = $this->pr();
        Notifikasi::query()->delete();
        $pr->update(['catatan' => 'revisi']);

        $this->assertSame(0, Notifikasi::count());
    }

    public function test_po_terbit_ke_staf_gudang_pemohon_saja(): void
    {
        $pr = $this->pr(['unit_id' => null, 'gudang_id' => self::GUDANG_A, 'status' => 'DIPROSES']);
        $this->actingAs($this->u('pengadaan'));
        $po = PesananPembelian::create([
            'kode' => $this->kode('PO'), 'pemasok_id' => $this->pemasokId, 'permintaan_pengadaan_id' => $pr->id, 'tanggal_po' => now()->toDateString(),
            'status' => 'DITERBITKAN', 'dibuat_oleh' => $this->u('pengadaan')->id,
        ]);

        $this->assertSame($this->ids('kepalagudang', 'petugasgudang'), $this->penerima('PO_TERBIT', $po->id));
    }

    public function test_amprah_diajukan_ke_karu_unit_sama_bukan_unit_lain(): void
    {
        $a = $this->amprah();

        $this->assertSame($this->ids('kepalaunit'), $this->penerima('APPROVAL_AMPRAH', $a->id));
    }

    public function test_amprah_disetujui_karu_ke_kepala_gudang_sama_bukan_gudang_lain(): void
    {
        $a = $this->amprah();
        $this->actingAs($this->u('kepalaunit'));
        $a->update(['status' => 'DISETUJUI_KARU']);

        // APPROVAL_AMPRAH juga berisi notifikasi Diajukan ke Karu; kepala gudang ikut menerima saat DISETUJUI_KARU.
        $this->assertSame($this->ids('kepalaunit', 'kepalagudang'), $this->penerima('APPROVAL_AMPRAH', $a->id));
        $this->assertSame(0, $this->notifUntuk('kagud_ipsrs', 'APPROVAL_AMPRAH', $a->id));
    }

    public function test_amprah_ditolak_ke_pengaju(): void
    {
        $a = $this->amprah();
        $this->actingAs($this->u('kepalaunit'));
        $a->update(['status' => 'DITOLAK']);

        $this->assertSame($this->ids('petugasunit'), $this->penerima('AMPRAH_DIPUTUSKAN', $a->id));
    }

    public function test_sppb_dikirim_ke_pengaju_amprah_dan_karu_unit_tujuan(): void
    {
        $a = $this->amprah(['status' => 'SEDANG_DIKIRIM']);
        $this->actingAs($this->u('petugasgudang'));
        $sppb = PengirimanBarang::create([
            'kode' => $this->kode('SPPB'), 'permintaan_distribusi_id' => $a->id, 'gudang_id' => self::GUDANG_A, 'unit_id' => self::UNIT_A,
            'tanggal_kirim' => now()->toDateString(), 'status' => 'DIKIRIM', 'dikirim_oleh' => $this->u('petugasgudang')->id,
        ]);

        $this->assertSame($this->ids('kepalaunit', 'petugasunit'), $this->penerima('SPPB_DIKIRIM', $sppb->id));
        $this->assertSame(0, $this->notifUntuk('karu_icu', 'SPPB_DIKIRIM', $sppb->id));
    }

    public function test_tiket_baru_ke_kepala_teknisi_dan_admin_logistik_tanpa_pelapor(): void
    {
        $this->actingAs($this->u('petugasunit'));
        $t = $this->tiket();

        $this->assertSame($this->ids('adminlogistik', 'karu_ipsrs'), $this->penerima('TIKET_BARU', $t->id));
        $this->assertSame(0, $this->notifUntuk('petugasunit', 'TIKET_BARU', $t->id));
    }

    public function test_spk_ditugaskan_ke_teknisi_dan_bukan_ke_penugas(): void
    {
        $t = $this->tiket(['status' => 'MENUNGGU_SPK']);
        $this->actingAs($this->u('karu_ipsrs'));
        $spk = PenugasanMaintenance::create([
            'kode' => $this->kode('SPK'), 'permintaan_maintenance_id' => $t->id, 'teknisi_id' => $this->u('teknisi')->id,
            'ditugaskan_oleh' => $this->u('karu_ipsrs')->id, 'tanggal_penugasan' => now()->toDateString(), 'status' => 'DITUGASKAN',
        ]);

        $this->assertSame($this->ids('teknisi'), $this->penerima('SPK_DITUGASKAN', $spk->id));

        // Penugas menugaskan dirinya sendiri: tidak menerima.
        $spk2 = PenugasanMaintenance::create([
            'kode' => $this->kode('SPK'), 'permintaan_maintenance_id' => $t->id, 'teknisi_id' => $this->u('karu_ipsrs')->id,
            'ditugaskan_oleh' => $this->u('karu_ipsrs')->id, 'tanggal_penugasan' => now()->toDateString(), 'status' => 'DITUGASKAN',
        ]);
        $this->assertSame([], $this->penerima('SPK_DITUGASKAN', $spk2->id));
    }

    public function test_perbaikan_selesai_ke_pelapor_dan_karu_unit_tiket(): void
    {
        $t = $this->tiket(['status' => 'SEDANG_DIKERJAKAN']);
        $this->actingAs($this->u('teknisi'));
        $t->update(['status' => 'SELESAI']);

        $this->assertSame($this->ids('kepalaunit', 'petugasunit'), $this->penerima('PERBAIKAN_SELESAI', $t->id));
    }

    public function test_pinjam_baru_ke_karu_unit_penyedia_bukan_peminjam(): void
    {
        $p = $this->pinjam();

        $this->assertSame($this->ids('karu_icu'), $this->penerima('PINJAM_BARU', $p->id));
        $this->assertSame(0, $this->notifUntuk('kepalaunit', 'PINJAM_BARU', $p->id));
    }

    public function test_pinjam_baru_dari_gudang_ke_kepala_gudang_penyedia(): void
    {
        $p = $this->pinjam(['tipe_penyedia' => 'GUDANG', 'unit_penyedia_id' => null, 'gudang_penyedia_id' => self::GUDANG_B]);

        $this->assertSame($this->ids('kagud_ipsrs'), $this->penerima('PINJAM_BARU', $p->id));
    }

    public function test_pinjam_diputuskan_ke_pengaju_untuk_setuju_dan_tolak(): void
    {
        $p = $this->pinjam();
        $this->actingAs($this->u('karu_icu'));
        $p->update(['status' => 'DISETUJUI']);
        $this->assertSame($this->ids('petugasunit'), $this->penerima('PINJAM_DIPUTUSKAN', $p->id));

        $p2 = $this->pinjam();
        $p2->update(['status' => 'DITOLAK']);
        $this->assertSame($this->ids('petugasunit'), $this->penerima('PINJAM_DIPUTUSKAN', $p2->id));

        // Peralihan ke DIPINJAM bukan keputusan baru.
        $p->update(['status' => 'DIPINJAM']);
        $this->assertSame(1, Notifikasi::where('tipe', 'PINJAM_DIPUTUSKAN')->where('referensi_id', (string) $p->id)->count());
    }

    public function test_pengalihan_masuk_ke_karu_unit_tujuan_saja(): void
    {
        $this->actingAs($this->u('petugasunit'));
        $t = PengalihanAset::create([
            'kode' => $this->kode('TRF'), 'tipe_asal' => 'RUANGAN', 'unit_asal_id' => self::UNIT_A, 'tipe_tujuan' => 'RUANGAN',
            'unit_tujuan_id' => self::UNIT_B, 'tanggal_pengalihan' => now()->toDateString(), 'alasan' => 'uji',
            'status' => 'DIAJUKAN', 'diajukan_oleh' => $this->u('petugasunit')->id,
        ]);

        $this->assertSame($this->ids('karu_icu'), $this->penerima('PENGALIHAN_MASUK', $t->id));
    }

    public function test_otorisasi_stok_ke_kepala_gudang_sama_dan_admin_logistik(): void
    {
        $this->actingAs($this->u('petugasgudang'));
        $adj = PenyesuaianStok::create([
            'kode' => $this->kode('ADJ'), 'gudang_id' => self::GUDANG_A, 'tanggal_penyesuaian' => now()->toDateString(),
            'alasan' => 'selisih', 'status' => 'MENUNGGU', 'diajukan_oleh' => $this->u('petugasgudang')->id,
        ]);

        $this->assertSame($this->ids('adminlogistik', 'kepalagudang'), $this->penerima('OTORISASI_STOK', $adj->id));
        $this->assertSame(0, $this->notifUntuk('kagud_ipsrs', 'OTORISASI_STOK', $adj->id));
    }

    public function test_pengguna_nonaktif_tidak_menerima(): void
    {
        $this->u('kepalaunit')->update(['status' => 'NONAKTIF']);
        $a = $this->amprah();

        $this->assertSame([], $this->penerima('APPROVAL_AMPRAH', $a->id));
    }
}
