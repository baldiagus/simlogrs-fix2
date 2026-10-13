<?php

namespace Tests\Unit\Enums\Aset;

use App\Enums\Aset\StatusPeminjaman as S;
use PHPUnit\Framework\TestCase;

class StatusPeminjamanTest extends TestCase
{
    public function test_hanya_transisi_sah_yang_diizinkan(): void
    {
        $this->assertTrue(S::Diajukan->bolehKe(S::Disetujui));
        $this->assertTrue(S::Diajukan->bolehKe(S::Ditolak));
        $this->assertTrue(S::Diajukan->bolehKe(S::Dibatalkan));
        $this->assertTrue(S::Disetujui->bolehKe(S::Dipinjam));
        $this->assertTrue(S::Dipinjam->bolehKe(S::Selesai));

        $this->assertFalse(S::Diajukan->bolehKe(S::Dipinjam), 'serah terima wajib lewat persetujuan');
        $this->assertFalse(S::Disetujui->bolehKe(S::Ditolak), 'permintaan disetujui tidak bisa ditolak');
        $this->assertFalse(S::Disetujui->bolehKe(S::Dibatalkan), 'permintaan disetujui tidak bisa dibatalkan pengaju');
        $this->assertFalse(S::Dipinjam->bolehKe(S::Dibatalkan), 'aset dipinjam hanya selesai lewat pengembalian');
    }

    public function test_status_final_tidak_bisa_berpindah(): void
    {
        foreach ([S::Selesai, S::Ditolak, S::Dibatalkan] as $final) {
            $this->assertSame([], $final->transisiSah());
        }
    }

    public function test_pengelompokan_status(): void
    {
        $this->assertSame(['DIAJUKAN', 'DISETUJUI', 'DIPINJAM'], S::aktif());
        $this->assertSame(['SELESAI', 'DITOLAK', 'DIBATALKAN'], S::riwayat());
        $this->assertSame(['DIAJUKAN', 'DISETUJUI'], S::antreanOtorisasi());
        $this->assertSame(['DISETUJUI', 'DIPINJAM'], S::mengikatAset());
        $this->assertEqualsCanonicalizing(
            array_map(fn (S $s) => $s->value, S::cases()),
            [...S::aktif(), ...S::riwayat()]
        );
    }

    public function test_nilai_enum_sama_dengan_nilai_kolom_database(): void
    {
        $this->assertSame(
            ['DIAJUKAN', 'DISETUJUI', 'DIPINJAM', 'SELESAI', 'DITOLAK', 'DIBATALKAN'],
            array_map(fn (S $s) => $s->value, S::cases())
        );
    }
}
