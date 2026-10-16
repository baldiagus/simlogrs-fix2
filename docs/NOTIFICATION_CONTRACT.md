# Kontrak Notifikasi SIMLOG RS (backend ↔ mobile)

Dokumen ini adalah sumber kebenaran tunggal. Backend (`simlog-fix2`) dan Flutter (`simlogrs-app`)
WAJIB mengikutinya. Perubahan kontrak diubah di sini dahulu.

## 1. Prinsip

- Tabel `notifikasi` (sudah ada) adalah **sumber utama**. Push FCM hanyalah pengiriman tambahan.
  Bila HP offline atau FCM tak terjangkau, notifikasi tetap tampil di inbox.
- Satu pintu pengiriman: `App\Services\Notifikasi\NotifikasiService`.
- Pemicu transaksi memakai **Model Observer** (satu kali untuk jalur web Livewire maupun API).
- Pelaku aksi tidak menerima notifikasi atas aksinya sendiri.
- Penerima = pihak yang sama yang melihat dokumen itu di antrean (aturan visibilitas `ApprovalController`).

## 2. Endpoint API (prefix `/api/v1`, `auth:sanctum`)

Semua respons memakai envelope yang sudah ada: `{ "sukses": bool, "pesan": string, "data": ..., "meta": {...} }`.

| Method | Path | Body / Query | Respons `data` |
|---|---|---|---|
| POST | `/auth/fcm-token` | `{ "token": string(<=255), "device_type": "android" }` | `{ "terdaftar": true }` (idempoten: token yang sama milik pengguna lain dipindah ke pengguna ini) |
| DELETE | `/auth/fcm-token` | `{ "token": string }` (opsional; tanpa token = hapus semua token pengguna di perangkat ini) | `{ "dihapus": int }` |
| GET | `/notifikasi` | `?page=1&hanya_belum_dibaca=0\|1` | `{ "items": [Notifikasi], "paginasi": {total, per_halaman, halaman_ini, total_halaman}, "belum_dibaca": int }` |
| POST | `/notifikasi/{id}/dibaca` | - | `{ "belum_dibaca": int }` (404 bila bukan milik pengguna) |
| POST | `/notifikasi/baca-semua` | - | `{ "belum_dibaca": 0 }` |

`GET /dashboard/summary` menambah field `belum_dibaca` (int) pada `data`.

Objek `Notifikasi`:

```json
{
  "id": 12,
  "judul": "Pengajuan PR Baru",
  "pesan": "PR-2026-0005 dari Ruang Bedah menunggu persetujuan Anda.",
  "tipe": "APPROVAL_PR",
  "referensi_id": "14",
  "tautan": "/kepala-unit/approval",
  "dibaca": false,
  "dibuat_pada": "2026-10-13T10:15:00+07:00"
}
```

Kolom baru pada tabel `notifikasi`: `tipe` (string 40, nullable), `referensi_id` (string 40, nullable).
Baris lama tanpa `tipe` dikembalikan dengan `tipe: "UMUM"`.

## 3. Payload FCM (data message)

Semua nilai string. Backend mengirim **notification + data** agar tampil walau app dimatikan.

```json
{
  "notification": { "title": "Pengajuan PR Baru", "body": "PR-2026-0005 ..." },
  "data": {
    "tipe": "APPROVAL_PR",
    "referensi_id": "14",
    "notifikasi_id": "12",
    "judul": "Pengajuan PR Baru",
    "pesan": "PR-2026-0005 ..."
  },
  "android": { "priority": "high", "notification": { "channel_id": "simlog_approval_channel" } }
}
```

`collapse_key` = `"{tipe}:{referensi_id}"` supaya perubahan status berulang pada dokumen yang sama tidak menumpuk.

## 4. Daftar `tipe` dan tujuan navigasi mobile

| `tipe` | Pemicu (model: status baru) | Penerima | Tujuan di app |
|---|---|---|---|
| `APPROVAL_PR` | `PermintaanPengadaan` → `DIAJUKAN` | Karu `unit_id` sama (PR gudang: `kepala_gudang` `gudang_id` sama); `admin_logistik` | Approval Center, tab PR |
| `APPROVAL_PR` | `PermintaanPengadaan` → `DITERUSKAN_KE_PENGADAAN` | role `pengadaan` | Approval Center, tab PR |
| `PR_DIPUTUSKAN` | `PermintaanPengadaan` → `DISETUJUI` / `DITOLAK` | `diajukan_oleh` | Riwayat PR |
| `PO_TERBIT` | `PesananPembelian` → `DITERBITKAN` | `kepala_gudang`, `petugas_gudang`; `gudang_id` = gudang PR asal (`permintaan_pengadaan.gudang_id`), PO tidak punya kolom gudang; bila PR dari unit, semua staf gudang | Inbound PO |
| `APPROVAL_AMPRAH` | `PermintaanDistribusi` → `DIAJUKAN` | Karu `unit_id` sama | Approval Center, tab Amprah |
| `ANTREAN_PICKING` | `PermintaanDistribusi` → `DISETUJUI_KARU` | `kepala_gudang` + `petugas_gudang` `gudang_id` sama | Antrean Picking |
| `AMPRAH_DIPUTUSKAN` | `PermintaanDistribusi` → `DITOLAK` | `diajukan_oleh` | Detail Amprah |
| `SPPB_DIKIRIM` | `PengirimanBarang` → `DIKIRIM` | pengaju amprah (`permintaan_distribusi.diajukan_oleh`) + Karu `pengiriman_barang.unit_id` | SPPB Inbound |
| `TIKET_BARU` | `PermintaanMaintenance` → `DILAPORKAN` | `kepala_teknisi`, `admin_logistik` | Hub IPSRS |
| `SPK_DITUGASKAN` | `PenugasanMaintenance` dibuat (`DITUGASKAN`) | `teknisi_id` | Detail SPK |
| `PERBAIKAN_SELESAI` | `PermintaanMaintenance` → `SELESAI` | pelapor (`dilaporkan_oleh`) + Karu `unit_id` | Detail tiket |
| `PINJAM_BARU` | `PermintaanPeminjaman` → `DIAJUKAN` | Karu `unit_penyedia_id` (unit pemilik aset; `unit_id` = unit peminjam); penyedia GUDANG: `kepala_gudang` `gudang_penyedia_id` | Otorisasi Sirkulasi |
| `PINJAM_DIPUTUSKAN` | `PermintaanPeminjaman` → `DISETUJUI` / `DITOLAK` | `diajukan_oleh` | Peminjaman Saya |
| `PENGALIHAN_MASUK` | `App\Models\Aset\PengalihanAset` → `DIAJUKAN` | Karu `unit_tujuan_id` (tujuan GUDANG: `kepala_gudang` `gudang_tujuan_id`) | Hub Pengalihan |
| `OTORISASI_STOK` | `PenyesuaianStok` → `MENUNGGU` | `kepala_gudang` `gudang_id` sama, `admin_logistik` | Otorisasi Penyesuaian |
| `PINJAM_JATUH_TEMPO` | cron H-1 sebelum `tanggal_kembali_rencana` | peminjam + penyetuju | Peminjaman Saya |
| `PINJAM_TERLAMBAT` | cron harian: lewat `tanggal_kembali_rencana` | peminjam + penyetuju | Peminjaman Saya |
| `SPK_PREVENTIF` | cron preventif menugaskan teknisi | `teknisi_id` | Detail SPK |
| `CAPA_BARU` | `TemuanAudit` dibuat | Karu `unit_id` temuan atau `kepala_gudang` `gudang_id` temuan (auditor pembuat tidak) | Temuan CAPA (auditee) |
| `CAPA_TINDAK_LANJUT` | `TemuanAudit.status_capa` → `In Review` | `auditor_id` temuan | Audit SPI, tab Temuan & CAPA |
| `CAPA_DIEVALUASI` | `TemuanAudit.status_capa` `In Review` → `Closed` / `In Progress` | Karu `unit_id` / `kepala_gudang` `gudang_id` temuan | Temuan CAPA (auditee) |
| `CAPA_JATUH_TEMPO` | cron harian 07:30: `status_capa` = `In Progress` dan `due_date_capa` besok (H-1) atau sudah lewat (judul/pesan berbeda) | Karu `unit_id` / `kepala_gudang` `gudang_id` temuan; dedup harian per pengguna+referensi | Temuan CAPA (auditee) |

`referensi_id` = id baris dokumen yang dimaksud.

Catatan implementasi pemicu (Observer di `app/Observers`, penerima di `PenerimaResolver`):

- Hanya pengguna `status = 'AKTIF'` yang menerima; pelaku (`Auth::id()`) dikeluarkan.
- `PR_DIPUTUSKAN` juga terkirim saat PR `DIAJUKAN` → `DITERUSKAN_KE_PENGADAAN` (persetujuan ruangan/gudang), selain `DISETUJUI`/`DITOLAK`.
- `SPK_DITUGASKAN` hanya untuk SPK yang dibuat oleh pengguna login; SPK buatan cron tanpa pelaku memakai `SPK_PREVENTIF`.

## 5. Aturan pengiriman

- Kirim hanya setelah transaksi DB commit (`DB::afterCommit`).
- Dedup: cron memeriksa `(pengguna_id, tipe, referensi_id, tanggal)` sebelum menulis.
- Token FCM yang dijawab `UNREGISTERED`/`INVALID_ARGUMENT` dihapus dari `user_device_tokens`.
- Kegagalan FCM tidak boleh menggagalkan transaksi bisnis; ditulis ke log dan job di-retry terbatas.

## 6. Operasional

- Push dikirim oleh worker antrean (service docker `queue`); cron notifikasi dijalankan service `scheduler`.
  Keduanya hidup otomatis (`restart: unless-stopped`).
- Setup kredensial, cara cek worker, layar Status Pengiriman Push, dan pemecahan masalah
  "muncul di web tapi tidak di HP" (termasuk aturan satu HP = akun yang terakhir login): lihat
  `docs/FIREBASE_SETUP.md`.
