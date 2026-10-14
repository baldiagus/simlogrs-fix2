<?php

namespace Tests\Feature\Notifikasi\Pemicu;

use App\Models\Aset\PengalihanAset;
use App\Models\Master\Gudang;
use App\Models\Master\Notifikasi;
use App\Models\Master\Unit;
use App\Models\Persediaan\PengalihanBarang;
use App\Models\User;
use App\Services\Notifikasi\FakePengirimPush;
use App\Services\Notifikasi\PengirimPush;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Notifikasi PENGALIHAN_MASUK untuk aset dan barang: kepala_teknisi, petugas_unit, kepala_gudang tujuan.
 */
class PengalihanNotifikasiTest extends TestCase
{
    use DatabaseTransactions;

    private Unit $unitAsal;
    private Unit $unitTujuan;
    private User $pengaju;

    /**
     * Siapkan dua unit dan pengaju; push diganti fake.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(PengirimPush::class, new FakePengirimPush());

        [$this->unitAsal, $this->unitTujuan] = Unit::where('status', 'AKTIF')->take(2)->get()->all();
        $this->pengaju = $this->buatUser('pengaju_trf', 'petugas_unit', $this->unitAsal->id);
        $this->actingAs($this->pengaju);
    }

    /**
     * Buat pengguna aktif berperan di unit/gudang tertentu.
     */
    private function buatUser(string $username, string $role, ?int $unitId = null, ?int $gudangId = null): User
    {
        $u = User::create([
            'nama' => $username, 'username' => $username, 'email' => "$username@simlog.test",
            'password' => bcrypt('password'), 'status' => 'AKTIF', 'unit_id' => $unitId, 'gudang_id' => $gudangId,
        ]);
        $u->assignRole($role);

        return $u;
    }

    /**
     * Buat pengalihan aset DIAJUKAN ke unit tujuan.
     */
    private function aset(): PengalihanAset
    {
        return PengalihanAset::create([
            'kode' => 'TRF-' . Str::upper(Str::random(8)), 'tipe_asal' => 'RUANGAN', 'unit_asal_id' => $this->unitAsal->id,
            'tipe_tujuan' => 'RUANGAN', 'unit_tujuan_id' => $this->unitTujuan->id, 'tanggal_pengalihan' => now()->toDateString(),
            'alasan' => 'uji', 'status' => 'DIAJUKAN', 'diajukan_oleh' => $this->pengaju->id,
        ]);
    }

    /**
     * Buat pengalihan barang DIAJUKAN dari gudang ke unit atau ke gudang lain.
     */
    private function barang(bool $keGudang): PengalihanBarang
    {
        [$g1, $g2] = Gudang::where('status', 'AKTIF')->take(2)->get()->all();

        return PengalihanBarang::create([
            'kode' => 'PBR-' . Str::upper(Str::random(8)), 'tipe_asal' => 'GUDANG', 'gudang_asal_id' => $g1->id,
            'tipe_tujuan' => $keGudang ? 'GUDANG' : 'RUANGAN',
            'gudang_tujuan_id' => $keGudang ? $g2->id : null,
            'unit_tujuan_id' => $keGudang ? null : $this->unitTujuan->id,
            'tanggal_pengalihan' => now()->toDateString(), 'alasan' => 'uji', 'status' => 'DIAJUKAN',
            'diajukan_oleh' => $this->pengaju->id,
        ]);
    }

    /**
     * Id penerima PENGALIHAN_MASUK untuk satu referensi, terurut.
     */
    private function penerima(int $ref): array
    {
        return Notifikasi::where('tipe', 'PENGALIHAN_MASUK')->where('referensi_id', (string) $ref)->orderBy('pengguna_id')->pluck('pengguna_id')->all();
    }

    /**
     * Unit tanpa kepala_unit tetap mengirim ke kepala_teknisi dan petugas_unit, aset maupun barang.
     */
    public function test_unit_tanpa_kepala_unit_kirim_ke_kepala_teknisi_dan_petugas_unit(): void
    {
        $kt = $this->buatUser('kt_tujuan', 'kepala_teknisi', $this->unitTujuan->id);
        $pu = $this->buatUser('pu_tujuan', 'petugas_unit', $this->unitTujuan->id);
        $lain = $this->buatUser('kt_lain', 'kepala_teknisi', $this->unitAsal->id);
        $expected = [$kt->id, $pu->id];
        sort($expected);

        $aset = array_values(array_diff($this->penerima($this->aset()->id), $this->idLama()));
        $barang = array_values(array_diff($this->penerima($this->barang(false)->id), $this->idLama()));

        $this->assertEqualsCanonicalizing($expected, array_intersect($aset, [$kt->id, $pu->id, $lain->id]));
        $this->assertEqualsCanonicalizing($expected, array_intersect($barang, [$kt->id, $pu->id, $lain->id]));
        $this->assertNotContains($lain->id, $aset);
        $this->assertNotContains($lain->id, $barang);
    }

    /**
     * Tujuan GUDANG mengirim ke kepala_gudang gudang tujuan saja.
     */
    public function test_tujuan_gudang_kirim_ke_kepala_gudang(): void
    {
        $g2 = Gudang::where('status', 'AKTIF')->skip(1)->first();
        $kg = $this->buatUser('kg_tujuan', 'kepala_gudang', null, $g2->id);

        $this->assertContains($kg->id, $this->penerima($this->barang(true)->id));
    }

    /**
     * Pengguna dengan beberapa peran tidak menerima notifikasi ganda.
     */
    public function test_tanpa_duplikat_penerima(): void
    {
        $u = $this->buatUser('multi_peran', 'kepala_teknisi', $this->unitTujuan->id);
        $u->assignRole('petugas_unit');

        $ids = $this->penerima($this->barang(false)->id);

        $this->assertSame(1, count(array_keys($ids, $u->id)));
    }

    /**
     * Id penerima yang sudah ada di DB dev dikecualikan agar assertion hanya melihat data uji.
     */
    private function idLama(): array
    {
        return User::where('username', 'not like', '%_tujuan')->where('username', 'not like', 'kt_lain')->pluck('id')->all();
    }
}
