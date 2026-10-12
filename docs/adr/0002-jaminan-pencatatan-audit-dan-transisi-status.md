# ADR 0002: Jaminan pencatatan audit dan transisi status

Status: Diterima

## A. Transisi status

Setiap kosakata status proses didefinisikan sebagai backed enum di `app/Enums/Status*.php`. Nilai string identik dengan kolom database; model tidak memakai cast enum karena API membandingkan `status` sebagai string.

Transisi yang sah ditentukan oleh `transisiSah()` / `bolehKe()` pada enum. Service dan Livewire memakai enum untuk guard, bukan literal string.

Keputusan atas inkonsistensi web vs API yang ditemukan saat sentralisasi: **perilaku yang lebih ketat dipakai di kedua sisi**, karena melonggarkan guard berarti membuka perubahan data final.

| # | Inkonsistensi | Keputusan |
|---|---|---|
| 1 | Penolakan penyesuaian: opname jadi `DITOLAK` (web) vs `DIBATALKAN` (API) | Samakan ke `DITOLAK`; `DIBATALKAN` untuk opname tidak lagi ditulis |
| 2 | `rejectPenyesuaian` web tanpa guard `MENUNGGU` | Tambah guard `MENUNGGU` (mengikuti API) |
| 3 | `saveHitungFisik` web tanpa guard status | Hanya `BERJALAN`/`PROSES_COUNT` (mengikuti API) |
| 4 | Buat PO dari PR: web hanya `DITERUSKAN_KE_PENGADAAN`, API juga `DIPROSES`/`DISETUJUI` | Dibiarkan; `DIPROSES` (PO tambahan) dan `DISETUJUI` (legacy) tetap sah di API. Dicatat sebagai perbedaan disengaja |
| 5 | `savePenerimaan` tidak cek ulang status PO saat simpan | Tambah cek saat simpan (mengikuti API) |
| 6 | GET daftar PR menulis `SELESAI` | Dicatat sebagai utang teknis; tidak diubah karena kontrak klien mobile |
| 7 | Admin global dapat menolak PR di status apa pun | Dibatasi: tolak hanya dari status non-final (`DIAJUKAN`, `DITERUSKAN_KE_PENGADAAN`, `DISETUJUI`) |

## B. Jaminan pencatatan audit

Prinsip: **proses yang memengaruhi stok, aset, anggaran, akses (RBAC/akun), atau temuan kepatuhan tidak boleh berhasil tanpa jejak audit.** Pencatatan audit dan perubahan data berada dalam satu transaksi database; jika audit gagal, perubahan dibatalkan.

| Kategori | Perilaku jika audit gagal |
|---|---|
| Perubahan model berbasis `HasAuditLog` (stok, aset, pengalihan, distribusi, pengadaan, maintenance, anggaran, temuan, opname, penyesuaian, pengaturan sistem) | **Gagal**: exception diteruskan sehingga transaksi pemanggil dibatalkan |
| Mutasi akun/peran (`RBAC_MUTATION`, tambah/ubah/hapus/reset/status pengguna, ubah peran) | **Gagal**: perubahan dan audit dalam satu transaksi |
| Ganti kata sandi wajib | **Gagal**: perubahan sandi dan audit dalam satu transaksi |
| Pemakaian stok ruangan | **Gagal** (sudah dalam transaksi) |

Pengecualian yang disepakati: **tidak ada**. Audit yang gagal tidak lagi ditelan dengan `Log::warning`.

### Konsekuensi
- Operasi bisa gagal saat tabel `log_audit` bermasalah. Ini disengaja untuk kepatuhan.
- Pemulihan: jika `log_audit` tidak dapat ditulis, perbaiki penyimpanan terlebih dahulu; tidak ada mode "lanjut tanpa audit".
- Model yang diubah di luar transaksi eksplisit tetap atomik per operasi karena event model berjalan dalam proses simpan yang sama.
