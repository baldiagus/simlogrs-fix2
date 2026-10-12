# ADR 0001: Kebijakan otorisasi web

Status: Diterima

## Konteks

Pemeriksaan akses Livewire dan Blade bercampur antara permission granular, `hasRole()`, dan permission dashboard (`page_Dashboard`, `page_AdminLogistikDashboard`, dst.) yang dipakai sebagai bypass global. Akibatnya role tanpa permission bisa memutasi data, dan permission dashboard membuka data lintas unit/gudang.

## Keputusan

| Jenis pemeriksaan | Aturan |
|---|---|
| Lihat halaman/data | Permission `view_any_*` atau `view_*` modul |
| Aksi kapabilitas (buat, ubah, hapus, eksekusi) | Permission granular aksi tersebut; role saja tidak cukup |
| Tahap workflow terikat jabatan (approval kepala unit/gudang, auditor menerbitkan CAPA) | Role yang sesuai **dan** permission aksi |
| Scope data | Non-global dibatasi ke `unit_id`/`gudang_id` miliknya, untuk baca maupun aksi |
| Bypass scope global operasional | Hanya role `super_admin` dan `admin_logistik` |
| Auditor/manajemen | Baca global hanya lewat permission `view*`; tidak ada mutasi berbasis role |
| IPSRS | Scope departemen via permission `pemeliharaan::*`; `teknisi` hanya SPK/lembar kerja miliknya (`teknisi_id`); `kepala_teknisi` seluruh departemen; penerbitan SPK butuh role `kepala_teknisi` **dan** `create_pemeliharaan::spk::perbaikan`; Lapor Servis dibatasi `unit_id` untuk pengguna unit |
| Permission `page_*Dashboard` | Hanya untuk akses dashboard/portal; bukan bypass aksi atau scope |
| Menu | Ditampilkan dengan permission yang sama dengan guard halaman |

URL dan nama route tidak berubah. Registry RBAC dan seeder tidak diubah oleh keputusan ini; celah izin dilaporkan, bukan ditebak.

## Konsekuensi

- Pengguna yang selama ini bergantung pada role atau permission dashboard tanpa permission granular akan ditolak.
- Setiap modul perlu tes: role saja ditolak, permission diizinkan, batas role workflow, dan akses lintas scope ditolak.
