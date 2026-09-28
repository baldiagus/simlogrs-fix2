# Master Design System & UI/UX Prompt: SIMLOG RS Mobile Companion (v2)
## Enterprise Hospital Logistics & IPSRS Mobile Application

> **Instruksi Penggunaan**: Salin seluruh teks di bawah ini dan berikan ke AI Designer / Open Design / Claude Designer / v0 / Lovable / Figma AI untuk menghasilkan antarmuka mobile (iOS & Android) yang profesional, ergonomis, dan siap produksi.

---

```markdown
Anda adalah Lead Mobile Product Designer (UI/UX) spesialis Enterprise Healthcare & Hospital Logistics.
Tugas Anda adalah merancang sistem antarmuka mobile lengkap (High-Fidelity UI Screens & Component System) untuk aplikasi "SIMLOG RS Mobile Companion (SIMLOG RS Non-Medis v2)".

Aplikasi ini digunakan oleh staf rumah sakit dalam kondisi lapangan bertekanan tinggi: lorong IGD, gudang logistik basement tanpa sinyal stabil, ruang rawat inap, dan bengkel teknisi IPSRS.

---

### 1. PRINSIP DESAIN UTAMA (ANTISLOP & HEALTHCARE ERGONOMICS)

1. **Thumb-Zone Optimization (Operasi Satu Tangan)**:
   - Seluruh tombol aksi utama (CTA), tombol kamera, pemindai QR code, dan tombol persetujuan wajib berada di 1/3 bagian bawah layar (Bottom Sheet, Floating Action Button, Bottom Navigation).
   - Area atas hanya untuk ringkasan status, judul halaman, dan identitas pengguna.
2. **Keterbacaan Lapangan & Kontras Tinggi (WCAG 2.1 AA)**:
   - Kontras rasio teks minimal 4.5:1. Hindari teks abu-abu pudar di atas latar abu-abu muda.
   - Ukuran font isi minimal 14px, angka kuantitas/harga menggunakan font monospaced (JetBrains Mono / Inter) berbobot tebal.
   - Target sentuh (Touch Target) tombol minimal 48 x 48 dp untuk mencegah salah tekan saat memakai sarung tangan medis.
3. **Resilience & Offline-Ready Awareness**:
   - Banner status koneksi persisten jika berada di ruang basement/kedap sinyal ("Koneksi Offline - Perubahan disimpan lokal").
   - Skeleton loader halus saat sinkronisasi API, tombol aksi wajib memiliki indikator loading spinner saat ditekan.
4. **Palet Warna Sistem (Role-Based Semantic Accents)**:
   - Warna Netral: Background Light `#F8FAFC` / Dark `#0F172A`, Card Light `#FFFFFF` / Dark `#1E293B`.
   - Aksen Aksi Primer: Hospital Blue `#2563EB` (Navigasi & Operasional Standar).
   - Aksen Persetujuan & Sukses: Emerald `#059669` (Approval & Verifikasi FEFO Cocok).
   - Aksen Peringatan & Jatuh Tempo: Amber `#D97706` (Hampir Kedaluwarsa & Kebutuhan Reguler).
   - Aksen Kritis & CITO: Crimson Rose `#E11D48` (Urgensi CITO, Kerusakan Parah, dan Salah Batch).

---

### 2. ARSITEKTUR INFORMASI & 4 PERAN PENGGUNA MOBILE

Aplikasi ini melayani 4 alur peran utama:

#### Alur 1: Kepala Ruangan / Karu (One-Handed Quick Approval)
- **Tujuan**: Menyetujui permintaan barang (Amprah) atau pengadaan (PR) ruangan dalam hitungan detik di sela visite dokter.
- **Karakteristik**: Butuh melihat total anggaran/kuantitas, tingkat urgensi (CITO vs REGULER), dan tombol aksi tegas: "Setujui" (Hijau) atau "Tolak" (Merah) dengan alasan cepat.

#### Alur 2: Petugas Gudang (Rugged Scanner & Stock Opname Companion)
- **Tujuan**: Memverifikasi nomor batch dan tanggal kedaluwarsa (FEFO) saat picking barang di rak, serta melakukan hitung fisik (*blind count*) stock opname.
- **Karakteristik**: Viewfinder scanner kamera aktif responsif, kartu validasi hijau/merah ukuran besar di bawah kamera, keypad angka kalkulator besar (*large numeric keypad*) tanpa perlu melihat angka sistem.

#### Alur 3: Teknisi Sarana & IPSRS (Work Order & Field Service)
- **Tujuan**: Menerima disposisi Surat Perintah Kerja (SPK), mencatat jam mulai-selesai, menginput tindakan diagnosa, melampirkan foto bukti perbaikan, dan mencatat pergantian suku cadang.
- **Karakteristik**: Daftar kartu SPK bergaya Kanban kartu prioritas, integrasi jepret foto langsung kamera HP, checklist suku cadang yang diambil dari gudang.

#### Alur 4: Staf Ruangan / Perawat (10-Second Quick Damage Reporter)
- **Tujuan**: Melaporkan alat sarana/aset rusak secara instan dengan memindai QR code aset dan menjepret foto fisik kerusakan.
- **Karakteristik**: Minimal input teks (kamera auto-buka, opsi radio tombol besar: "Rusak Ringan", "Rusak Berat", toggle "CITO - Butuh Segera").

---

### 3. SPESIFIKASI RINCIAN TAMPILAN LAYAR (SCREENS TO DESIGN)

Rancanglah rancang bangun layar mobile berikut:

#### SCREEN 1: Gateway Login & Pemilih Peran Otomatis
- Header: Logo minimalis SIMLOG RS, nama rumah sakit, dan indikator keamanan SSL.
- Input fields: Email kedinasan, kata sandi (toggle hide/show), dan tombol biometrik (Face ID / Fingerprint).
- Footer: Status server "Online (Asia/Jakarta / WIB)" dan versi aplikasi "SIMLOG RS Mobile v2.0".

#### SCREEN 2: Dashboard Ringkasan & Tab Bar Navigasi Bawah (Bottom Navigation Bar)
- **Top App Bar**: Avatar pengguna, nama staf, badge peran (contoh: "Kepala Ruangan - IGD"), ikon lonceng notifikasi (dengan badge angka merah belum dibaca).
- **Hero Card**:
  - Untuk Karu: Kartu "Menunggu Persetujuan Anda" (3 CITO, 5 Reguler).
  - Untuk Petugas Gudang: Kartu "Antrean Picking FEFO Hari Ini" & "Status Stok Opname".
  - Untuk Teknisi: Kartu "SPK Aktif Saya" (2 CITO, 1 Dalam Pengerjaan).
- **Aksi Cepat (Quick Action Grid 2x2 atau 4 Kolom Ikon)**:
  - "Scan QR Aset", "Lapor Kerusakan", "Permintaan CITO", "Buku Mutasi".
- **Bottom Navigation Bar (5 Tab)**:
  1. `Beranda` (Ikon: Home)
  2. `Approval / Tugas` (Ikon: Clipboard-Check, dengan counter badge)
  3. `Scan / Kamera` (Tombol FAB Timbul di Tengah: QR Scanner)
  4. `Aset & Stok` (Ikon: Cube)
  5. `Akun / Profil` (Ikon: User)

#### SCREEN 3: Pusat Otorisasi Cepat Karu (`/v1/approval`)
- Tab Filter atas: `Semua`, `CITO (Mendesak)`, `Amprah Ruangan`, `Pengadaan (PR)`.
- List Card Transaksi:
  - Header Card: Nomor Kode (contoh: `REQ-202609-001`), Waktu Diajukan, Badge Urgensi CITO merah tebal.
  - Body: Nama pemohon, ruangan, daftar 3 komoditas utama (+ 2 lainnya), estimasi nilai pagu.
  - Bottom Action Sheet / Tombol Card: Tombol "Tolak" (Outline Rose) dan "Setujui Permintaan" (Solid Emerald Full-Width).
  - Modal Bottom Sheet untuk Penolakan: Opsi radio alasan cepat ("Anggaran Melebihi Pagu", "Stok Ruangan Masih Ada", "Lainnya") + textarea catatan singkat.

#### SCREEN 4: Layar Pemindai Kamera Barcode & FEFO Picker (`/v1/gudang/scan-batch`)
- 60% Layar Atas: Live Camera Viewfinder dengan garis bidik laser merah/hijau dan tombol toggle senter (Flashlight) untuk lorong gudang gelap.
- 40% Layar Bawah (Card Informasi Hasil Scan):
  - **Skenario Valid (Hijau)**:
    - Ikon centang hijau besar: "BATCH VALID SESUAI URUTAN FEFO".
    - Nomor Batch: `BTC-GLU-202609-01` | Kedaluwarsa: `15 Okt 2026` (22 hari lagi).
    - Lokasi Rak: `RAK-A-02-B`.
    - Input kuantitas picking stepper `[-] [ 5 ] [+]` dan tombol "Konfirmasi Masukkan SPPB".
  - **Skenario Salah Urutan / Expired (Merah Alert)**:
    - Ikon silang merah: "PERINGATAN: BUKAN BATCH TERDEKAT!".
    - Pesan: "Ada batch `BTC-GLU-202608` yang kedaluwarsa lebih awal di Rak A-01-A. Mohon ambil batch tersebut sesuai aturan FEFO rumah sakit".

#### SCREEN 5: Layar Hitung Fisik Stock Opname (*Blind Count*) (`/v1/gudang/opname-count`)
- Header: Lokasi Gudang Sentral | Rak B-04.
- Informasi Barang yang Di-scan: Kode Barang, Nama Barang (contoh: "Kasa Steril 10x10 cm"), Satuan: "Box".
- *Blind Count Constraint*: Angka sistem sengaja disembunyikan.
- Input Area: Tampilan angka hitung fisik berukuran raksasa (`text-4xl font-mono`) dengan Large Numeric Keypad 0-9 di bawah layar, tombol "C" (Clear), dan tombol "Simpan Hitungan" (Biru Primer).

#### SCREEN 6: Layar Pelaporan Kerusakan Cepat 10-Detik (`/v1/maintenance/lapor-cepat`)
- Langkah 1: Card Jepret Foto Kerusakan (Tombol kamera besar dengan preview instan foto yang sudah di-resize & auto-orient).
- Langkah 2: Kode & Nama Aset (Bisa scan barcode label aset atau ketik nama aset dengan pencarian cerdas).
- Langkah 3: Radio Tombol Kondisi: `Rusak Ringan (Bisa Digunakan)` vs `Rusak Berat (Mogok Total)`.
- Langkah 4: Toggle Urgensi: `CITO (Pelayanan Pasien Terganggu)`.
- Langkah 5: Textarea mikro / Voice-to-Text untuk ringkasan keluhan fisik.
- Tombol Bawah: "Kirim Laporan ke IPSRS Sekarang".

#### SCREEN 7: Layar Lembar Kerja Teknisi IPSRS (`/v1/maintenance/spk/{id}/log`)
- Header: Nomor SPK `SPK-202609-001`, Nama Aset "Bed Pasien Elektrik VIP #02", Lokasi "Ruang ICU".
- Timeline Status Pengerjaan: `Diterima` -> `Dikerjakan` -> `Uji Fungsi` -> `Selesai`.
- Form Input Teknisi:
  - Tombol Catat Jam: "Mulai Kerja (10:15 WIB)" & "Selesai Kerja (11:45 WIB)".
  - Textarea: Diagnosa Masalah & Tindakan Perbaikan.
  - Dropdown & Selector: Pemakaian Suku Cadang dari Gudang (dengan pemotongan stok FEFO otomatis).
  - Multi-Foto Bukti: Foto Sebelum Servis dan Foto Sesudah Servis.
  - Switch Toggle: "Uji Fungsi Kelaikan Alat Bersama Ruangan Lolos".
  - Tombol Submit: "Selesaikan SPK & Rekam Medis Aset".

---

### 4. MICRO-INTERACTIONS & FEEDBACK HAPTIC
- **Getar Haptik Pendek (Success Haptic)** saat barcode berhasil terbaca.
- **Getar Ganda Cepat (Error Haptic)** jika batch yang diambil bukan urutan FEFO terdekat atau barang sudah kedaluwarsa.
- **Konfirmasi Modal Bawah (Slide-up Bottom Sheet)** untuk setiap aksi destruktif (Penolakan approval atau pembatalan SPK).
- **Indikator Unggah Berkas**: Progress bar persentase saat mengunggah foto kompresi WebP ke server.

---

### 5. FORMAT OUTPUT YANG DIHARAPKAN
1. Tampilkan mock-up visual layout dalam format komponen mobile modern (Tailwind CSS, React Native, atau Flutter UI Code).
2. Sajikan kode layout yang modular, responsif terhadap notch/island kamera smartphone, dan mendukung safe-area-inset atas dan bawah.
3. Cantumkan rincian state interaktif: Normal, Active/Pressed, Loading, Disabled, dan Empty State.
```
