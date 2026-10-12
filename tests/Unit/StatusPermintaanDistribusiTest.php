<?php

namespace Tests\Unit;

use App\Enums\StatusPermintaanDistribusi as S;
use PHPUnit\Framework\TestCase;

class StatusPermintaanDistribusiTest extends TestCase
{
    public function test_hanya_transisi_sah_yang_diizinkan(): void
    {
        $this->assertTrue(S::Diajukan->bolehKe(S::DisetujuiKaru));
        $this->assertTrue(S::Diajukan->bolehKe(S::Ditolak), 'tolak karu / batal pemohon');
        $this->assertTrue(S::DisetujuiKaru->bolehKe(S::DisetujuiGudang));
        $this->assertTrue(S::DisetujuiKaru->bolehKe(S::Ditolak));
        $this->assertTrue(S::DisetujuiKaru->bolehKe(S::SedangDikirim), 'picking FEFO service menerima DISETUJUI_KARU');
        $this->assertTrue(S::DisetujuiGudang->bolehKe(S::SedangDikirim));
        $this->assertTrue(S::DisetujuiGudang->bolehKe(S::DikirimSebagian));
        $this->assertTrue(S::SedangDikirim->bolehKe(S::Selesai));
        $this->assertTrue(S::SedangDikirim->bolehKe(S::DikirimSebagian));
        $this->assertTrue(S::DikirimSebagian->bolehKe(S::Selesai), 'penerimaan lunas / tutup sisa hutang');
        $this->assertTrue(S::DikirimSebagian->bolehKe(S::SedangDikirim));
        $this->assertTrue(S::DikirimSebagian->bolehKe(S::DikirimSebagian));

        $this->assertFalse(S::Diajukan->bolehKe(S::DisetujuiGudang), 'gudang wajib lewat persetujuan karu');
        $this->assertFalse(S::Diajukan->bolehKe(S::SedangDikirim));
        $this->assertFalse(S::DisetujuiGudang->bolehKe(S::Ditolak), 'yang sudah disetujui gudang tidak bisa dibatalkan');
        $this->assertFalse(S::SedangDikirim->bolehKe(S::Ditolak));
    }

    public function test_status_final_tidak_bisa_berpindah(): void
    {
        foreach ([S::Selesai, S::Ditolak] as $final) {
            $this->assertSame([], $final->transisiSah());
        }
    }

    public function test_pengelompokan_status(): void
    {
        $this->assertSame(['DIAJUKAN', 'DISETUJUI_KARU'], S::dapatDibatalkan());
        $this->assertSame(['DISETUJUI_GUDANG', 'DIKIRIM_SEBAGIAN'], S::siapPicking());
        $this->assertSame(['DISETUJUI_GUDANG', 'DIKIRIM_SEBAGIAN', 'DISETUJUI_KARU'], S::dapatDikirim());
        $this->assertSame(['SELESAI', 'DITOLAK'], S::riwayat());
        $this->assertSame([], array_intersect(S::aktif(), S::riwayat()));
        $this->assertCount(count(S::cases()), array_merge(S::aktif(), S::riwayat()));
    }

    public function test_nilai_enum_sama_dengan_nilai_kolom_database(): void
    {
        $this->assertSame(
            ['DIAJUKAN', 'DISETUJUI_KARU', 'DISETUJUI_GUDANG', 'SEDANG_DIKIRIM', 'DIKIRIM_SEBAGIAN', 'SELESAI', 'DITOLAK'],
            array_map(fn (S $s) => $s->value, S::cases())
        );
    }
}
