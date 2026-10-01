<?php

namespace Database\Seeders;

use App\Models\Peran;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class PeranSeeder extends Seeder
{
    /**
     * Menanam 12 role resmi sistem ke tabel peran dan Spatie roles.
     */
    public function run(): void
    {
        $roles = [
            ['kode' => 'super_admin', 'nama' => 'Super Administrator', 'deskripsi' => 'Pengelola penuh seluruh konfigurasi sistem, audit, dan wewenang'],
            ['kode' => 'admin_logistik', 'nama' => 'Admin Logistik', 'deskripsi' => 'Pengawas master logistik, alokasi stok sentral, dan sirkulasi gudang'],
            ['kode' => 'kepala_gudang', 'nama' => 'Kepala Gudang', 'deskripsi' => 'Penanggung jawab operasional gudang, FEFO, dan otorisasi penyesuaian opname'],
            ['kode' => 'petugas_gudang', 'nama' => 'Petugas Gudang', 'deskripsi' => 'Pelaksana fisik inbound barang, picking FEFO, packing, dan hitung opname'],
            ['kode' => 'pengadaan', 'nama' => 'Bagian Pengadaan', 'deskripsi' => 'Pelaksana verifikasi PR, penerbitan PO vendor, dan kontrol serapan pagu'],
            ['kode' => 'kepala_unit', 'nama' => 'Kepala Ruangan / Unit', 'deskripsi' => 'Otorisator permohonan pengadaan, amprah ruangan, dan izin pinjam aset'],
            ['kode' => 'petugas_unit', 'nama' => 'Petugas / Staf Ruangan', 'deskripsi' => 'Penginput amprah barang rutin, lapor kerusakan sarana, dan terima distribusi'],
            ['kode' => 'kepala_teknisi', 'nama' => 'Kepala / Koordinator Teknisi IPSRS', 'deskripsi' => 'Pengatur tiket perbaikan, disposisi SPK, pengadaan sparepart/alat, dan pengelola preventif maintenance'],
            ['kode' => 'teknisi', 'nama' => 'Teknisi Sarana & IPSRS', 'deskripsi' => 'Pelaksana SPK perbaikan, lembar kerja teknisi, dan pemeliharaan preventif'],
            ['kode' => 'manajemen', 'nama' => 'Manajemen Direksi', 'deskripsi' => 'Pemantau eksekutif realisasi anggaran, valuasi persediaan, dan CAPA'],
            ['kode' => 'auditor', 'nama' => 'Auditor Internal (SPI)', 'deskripsi' => 'Pengawas kepatuhan prosedur, forensik audit trail, dan register CAPA'],
            ['kode' => 'panel_user', 'nama' => 'Pengguna Portal Terdaftar', 'deskripsi' => 'Role dasar seluruh pegawai untuk otorisasi login portal'],
        ];

        foreach ($roles as $r) {
            Peran::firstOrCreate(['kode' => $r['kode']], $r);
            Role::findOrCreate($r['kode'], 'web');
        }
    }
}
