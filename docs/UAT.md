# DOKUMEN USER ACCEPTANCE TESTING (UAT)
## SISTEM INFORMASI MANAJEMEN LOGISTIK RUMAH SAKIT (SIMLOG RS NON-MEDIS v2)

---

### Informasi Dokumen
* **Versi Sistem**: SIMLOG RS Non-Medis v2.0
* **Lingkungan Pengujian**: Staging / QA Docker Environment (`http://localhost:8000`)
* **Metode Pengujian**: Black Box Testing, Role-Based Access Control (RBAC) Testing, Exception & Boundary Testing
* **Status**: Siap Eksekusi (Ready for Sign-off)

---

### Daftar Akun & Kredensial Uji Coba

Semua akun pengujian menggunakan kata sandi standar kedinasan: `password`

| No | Peran Pengguna (Role) | Email Pengujian | Portal URL | Ruang Lingkup Wewenang |
|:---:|---|---|---|---|
| 1 | **Super Administrator** | `superadmin@simlogrs.local` | `/super-admin` | Kontrol penuh sistem, manajemen user, konfigurasi RBAC matriks |
| 2 | **Admin Logistik** | `adminlogistik@simlogrs.local` | `/admin-logistik` | Manajemen master komoditas, alokasi gudang sentral, tracking sirkulasi |
| 3 | **Kepala Gudang** | `kepalagudang@simlogrs.local` | `/kepala-gudang` | Otorisasi penyesuaian opname, pengawasan FEFO, approval distribusi gudang |
| 4 | **Petugas Gudang** | `petugasgudang@simlogrs.local` | `/petugas-gudang` | Inbound PO vendor, picking barcode FEFO, blind count fisik opname |
| 5 | **Bagian Pengadaan** | `pengadaan@simlogrs.local` | `/pengadaan` | Verifikasi PR, penerbitan PO rekanan vendor, evaluasi kinerja supplier |
| 6 | **Kepala Unit / Karu** | `kepalaunit@simlogrs.local` | `/kepala-unit` | Otorisasi amprah ruangan, persetujuan pinjam aset, approval PR unit |
| 7 | **Petugas / Staf Ruangan** | `petugasunit@simlogrs.local` | `/petugas-unit` | Pengajuan amprah rutin, terima SPPB, lapor servis, pinjam aset ruangan |
| 8 | **Teknisi Sarana IPSRS** | `teknisi@simlogrs.local` | `/teknisi` | Penerimaan SPK, input lembar kerja teknisi, alokasi suku cadang FEFO |
| 9 | **Manajemen Direksi** | `manajemen@simlogrs.local` | `/manajemen` | Dashboard eksekutif, valuasi persediaan, realisasi serapan pagu anggaran |
| 10 | **Auditor Internal (SPI)** | `auditor@simlogrs.local` | `/auditor` | Forensik digital audit trail, kepatuhan PO-PR, register & closing CAPA |

---

## 1. Modul Pengujian: Super Administrator (`super_admin`)

### UAT-ADM-01: Manajemen Pengguna & Hak Akses (Happy Path)
* **Kondisi**: Normal / Positif
* **Tujuan**: Memastikan Super Admin dapat mendaftarkan akun pegawai baru dengan wewenang yang sesuai.
* **Langkah Pengujian**:
  1. Masuk ke sistem menggunakan `superadmin@simlogrs.local`.
  2. Buka menu **Manajemen Akun Pengguna** (`/super-admin/pengguna`).
  3. Klik tombol **Tambah Pegawai**.
  4. Isi nama: `dr. Ahmad Santoso`, NIP: `198501012010121001`, username: `ahmad_santoso`, email: `ahmad@simlogrs.local`, role: `kepala_unit`, unit: `Instalasi Rawat Inap`.
  5. Masukkan kata sandi dan konfirmasi kata sandi: `Password123!`.
  6. Klik **Simpan**.
* **Ekspektasi**: Data tersimpan, muncul notifikasi sukses, akun muncul di tabel dengan status `AKTIF`, dan user baru dapat login ke portal `/kepala-unit`.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

### UAT-ADM-02: Validasi Duplikasi Data Pengguna (Edge Case)
* **Kondisi**: Batas / Duplikasi
* **Tujuan**: Memastikan sistem menolak pendaftaran akun dengan username atau email yang sudah terdaftar.
* **Langkah Pengujian**:
  1. Di form tambah pegawai, masukkan email `superadmin@simlogrs.local` atau username `superadmin`.
  2. Klik **Simpan**.
* **Ekspektasi**: Form menampilkan pesan validasi *"Username / email sudah terdaftar"* dan transaksi dibatalkan.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

### UAT-ADM-03: Proteksi Akun Diri Sendiri (Negative Path)
* **Kondisi**: Keamanan / Anti Self-Lockout
* **Tujuan**: Mencegah administrator menonaktifkan atau memblokir akunnya sendiri yang sedang aktif digunakan.
* **Langkah Pengujian**:
  1. Pada daftar pegawai, cari baris akun `superadmin@simlogrs.local`.
  2. Klik tombol **Nonaktifkan Akun (Blokir)**.
* **Ekspektasi**: Sistem menolak dengan toast peringatan *"Anda tidak dapat menonaktifkan akun Anda sendiri yang sedang aktif"*.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

### UAT-ADM-04: Penyesuaian Matriks RBAC Spatie (Role Manager)
* **Kondisi**: Normal
* **Tujuan**: Mengubah matriks izin per modul pada suatu peran dinamis.
* **Langkah Pengujian**:
  1. Buka menu **Matriks Wewenang Peran** (`/super-admin/roles`).
  2. Pilih peran `petugas_unit`.
  3. Aktifkan/nonaktifkan permission modul tertentu (misal: `create_layanan_unit::lapor::servis`).
  4. Klik tombol **Simpan Wewenang**.
* **Ekspektasi**: Notifikasi sukses muncul, cache permission Spatie otomatis direset (`forgetCachedPermissions`), dan perubahan hak akses langsung berlaku secara real-time pada sesi pengguna terkait.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

---

## 2. Modul Pengujian: Admin Logistik (`admin_logistik`)

### UAT-LOG-01: Monitoring Stok Sentral & Filter Lokasi Rak
* **Kondisi**: Normal
* **Tujuan**: Memeriksa ketersediaan seluruh komoditas logistik rumah sakit beserta lokasi rak/bin fisiknya.
* **Langkah Pengujian**:
  1. Masuk menggunakan akun `adminlogistik@simlogrs.local`.
  2. Buka menu **Master Barang** (`/admin-logistik/barang`).
  3. Gunakan filter tipe `HABIS_PAKAI`, `SPAREPART`, dan `ASET`.
  4. Buka menu **Tata Letak Rak & Bin** (`/admin-logistik/lokasi-gudang`).
* **Ekspektasi**: Daftar komoditas tampil lengkap beserta stok riil, harga beli terakhir terformat ribuan rupiah, dan kapasitas rak per lantai gedung.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

### UAT-LOG-02: Pendaftaran Master Aset dengan Validasi Lokasi XOR (Constraint Check)
* **Kondisi**: Boundary / Constraint Validation
* **Tujuan**: Menguji aturan ketat bahwa aset hanya boleh ditempatkan di Unit ATAU di Gudang (eksklusif, tidak boleh keduanya atau kosong).
* **Langkah Pengujian**:
  1. Buka menu **Register Master Aset** (`/admin-logistik/aset`).
  2. Klik **Tambah Aset Baru**.
  3. Pilih komoditas barang inventaris (misal: Kursi Roda).
  4. Pada opsi penempatan fisik, pilih tipe `RUANGAN`, pilih unit `IGD`.
  5. Coba manipulasi data agar gudang juga terisi (jika via form, pastikan radio button mengosongkan opsi gudang secara otomatis).
  6. Klik **Simpan**.
* **Ekspektasi**: Aset berhasil didaftarkan dengan kode otomatis `AST-YYYY-XXX`, stiker QR code SVG ter-generate, kolom `gudang_id` bernilai NULL di database (lolos constraint `chk_aset_lokasi_xor`).
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

---

## 3. Modul Pengujian: Kepala Gudang (`kepala_gudang`)

### UAT-GDG-01: Inisiasi Sesi Stock Opname Fisik (Blind Count)
* **Kondisi**: Normal
* **Tujuan**: Membuka sesi penghitungan fisik inventaris gudang logistik non-medis.
* **Langkah Pengujian**:
  1. Masuk menggunakan akun `kepalagudang@simlogrs.local`.
  2. Buka menu **Stock Opname Fisik** (`/kepala-gudang/stok-opname`).
  3. Klik tombol **Inisiasi Sesi Opname**.
  4. Pilih fasilitas `Gudang Sentral Logistik Non-Medis`, tanggal hari ini, dan catatan: `Opname Rutin Triwulan`.
  5. Klik **Simpan & Buka Sesi**.
* **Ekspektasi**: Dokumen `OPN-YYYYMM-XXX` terbentuk dengan status `BERJALAN`. Saldo sistem terkunci secara snapshot di tabel detail opname.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

### UAT-GDG-02: Otorisasi Penyesuaian Varians Opname (Stok Selisih)
* **Kondisi**: Normal & Bisnis Kritis
* **Tujuan**: Mengesahkan perubahan saldo fisik riil pasca-penghitungan blind count.
* **Langkah Pengujian**:
  1. Buka menu **Otorisasi Penyesuaian** (`/kepala-gudang/otorisasi-penyesuaian`).
  2. Pilih dokumen penyesuaian berstatus `MENUNGGU`.
  3. Tinjau rincian selisih (`TAMBAH` atau `KURANG`).
  4. Klik tombol **Sahkan Penyesuaian Stok**.
  5. Konfirmasi modal dialog persetujuan.
* **Ekspektasi**:
  - Saldo kuantitas pada tabel `stok_batch.jumlah` bertambah/berkurang sesuai selisih fisik riil.
  - Kartu mutasi `pergerakan_stok` mencatat transaksi jenis `PENYESUAIAN` dengan pencatatan saldo sebelum dan sesudah.
  - Status dokumen penyesuaian dan sesi opname berubah menjadi `DISETUJUI`.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

### UAT-GDG-03: Penolakan Otorisasi Saldo Minus Ekstrem (Negative Path)
* **Kondisi**: Integritas Database
* **Tujuan**: Mencegah otorisasi pengurangan stok jika saldo fisik batch saat approval telah berkurang lebih dulu oleh transaksi lain (mencegah saldo minus).
* **Langkah Pengujian**:
  1. Pada skenario di mana batch barang memiliki saldo riil saat ini: 2 unit.
  2. Dokumen penyesuaian meminta pengurangan: 5 unit.
  3. Klik **Sahkan Penyesuaian Stok**.
* **Ekspektasi**: Transaksi ditolak dengan pesan galat *"Saldo stok batch saat ini tidak mencukupi untuk dikurangi 5 unit (Saldo riil: 2 unit)"*. Saldo database tidak berubah (Rollback).
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

---

## 4. Modul Pengujian: Petugas Gudang (`petugas_gudang`)

### UAT-STF-01: Penerimaan Barang Vendor (Inbound PO) & Alokasi Rak
* **Kondisi**: Normal
* **Tujuan**: Menerima fisik barang dari supplier rekanan berdasarkan nomor PO dan menempatkannya ke rak gudang.
* **Langkah Pengujian**:
  1. Masuk menggunakan akun `petugasgudang@simlogrs.local`.
  2. Buka menu **Penerimaan Barang (PO)** (`/petugas-gudang/penerimaan-po`).
  3. Pilih PO berstatus `DIKIRIM_VENDOR`.
  4. Klik **Terima Barang**.
  5. Masukkan nomor surat jalan vendor (misal: `SJ-2026-9988`), pilih rak penyimpanan fisik, masukkan nomor batch, tanggal kedaluwarsa, dan jumlah barang diterima.
  6. Klik **Simpan Penerimaan**.
* **Ekspektasi**:
  - Dokumen penerimaan `RCV-YYYYMM-XXX` terbit.
  - Batch baru bertambah di `stok_batch` dengan status `AKTIF`.
  - Kartu mutasi stok mencatat transaksi `MASUK`.
  - Status PO otomatis terbarui menjadi `DITERIMA_LENGKAP` (jika kuantitas terpenuhi) atau `DITERIMA_SEBAGIAN`.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

### UAT-STF-02: Eksekusi Picking FEFO via Web & Mobile Scanner
* **Kondisi**: Algoritma FEFO (First-Expired, First-Out)
* **Tujuan**: Memastikan alokasi barang distribusi otomatis mengambil nomor batch dengan masa kedaluwarsa terdekat.
* **Langkah Pengujian**:
  1. Buka menu **Picking Distribusi (FEFO)** (`/petugas-gudang/picking-distribusi`).
  2. Pilih permohonan amprah berstatus `DISETUJUI_GUDANG`.
  3. Klik **Eksekusi Picking FEFO**.
* **Ekspektasi**:
  - Sistem otomatis memilih nomor batch yang tanggal kedaluwarsanya paling dekat.
  - Dokumen SPPB (`SPPB-YYYYMMDD-XXXX`) terbit dengan status `DIKIRIM`.
  - Saldo kuantitas batch gudang berkurang seketika (`pessimistic locking`).
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

### UAT-STF-03: Peringatan Pemindaian Barcode Non-FEFO (Edge Case)
* **Kondisi**: Mobile Scanner API (`POST /v1/gudang/scan-batch`)
* **Tujuan**: Menguji validasi scanner barcode saat petugas gudang memindai batch yang bukan merupakan urutan expired terdekat di rak.
* **Langkah Pengujian**:
  1. Tembak endpoint API `/v1/gudang/scan-batch` membawa barcode dari batch yang masa kedaluwarsanya masih 1 tahun lagi, padahal di rak terdapat batch yang expired 3 bulan lagi.
* **Ekspektasi**: Respon API mengembalikan payload dengan `status: "NON_FEFO"` dan pesan peringatan *"Peringatan: Bukan batch terdekat. Harap utamakan batch [Nomor_Batch_Terdekat]"*.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

---

## 5. Modul Pengujian: Bagian Pengadaan (`pengadaan`)

### UAT-PPO-01: Verifikasi Permintaan Pengadaan (PR) & Penerbitan PO Vendor
* **Kondisi**: Normal
* **Tujuan**: Mengonversi kebutuhan logistik unit ruangan (PR) yang telah disahkan menjadi Pesanan Pembelian resmi (PO).
* **Langkah Pengujian**:
  1. Masuk menggunakan akun `pengadaan@simlogrs.local`.
  2. Buka menu **Permintaan Pengadaan (PR)** (`/pengadaan/permintaan-pengadaan`).
  3. Pilih PR berstatus `DISETUJUI` yang telah diteruskan oleh pimpinan.
  4. Buka menu **Pesanan Pembelian (PO)** (`/pengadaan/pesanan-pembelian`).
  5. Klik tombol konversi PO dari PR terkait.
  6. Pilih rekanan vendor aktif, tentukan syarat pembayaran (`TEMPO_30_HARI`), estimasi tanggal pengiriman, dan harga satuan penawaran.
  7. Klik **Terbitkan PO**.
* **Ekspektasi**:
  - Dokumen PO resmi (`PO-YYYY-XXX`) terbentuk dengan status `DITERBITKAN`.
  - Nilai subtotal dan total biaya finansial terkalkulasi akurat.
  - Status PR induk terbarui menjadi `DIPROSES`.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

### UAT-PPO-02: Pembaruan Status Pengiriman Rekanan (Vendor In-Transit)
* **Kondisi**: Normal
* **Tujuan**: Mengubah status PO menjadi barang dalam perjalanan pengiriman oleh vendor.
* **Langkah Pengujian**:
  1. Pada daftar PO, cari dokumen berstatus `DITERBITKAN`.
  2. Klik aksi **Kirim ke Vendor / Dalam Pengiriman**.
  3. Konfirmasi tanggal estimasi tiba di gudang RS.
* **Ekspektasi**: Status PO berubah menjadi `DIKIRIM_VENDOR`, dokumen siap diproses inbound oleh petugas gudang.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

### UAT-PPO-03: Penolakan Penerbitan PO Tanpa Dokumen PR Sah (Negative Path)
* **Kondisi**: Keamanan Prosedur Pengadaan
* **Tujuan**: Mencegah penerbitan pesanan pembelian bodong tanpa referensi dokumen permohonan resmi.
* **Langkah Pengujian**:
  1. Kirim payload API `POST /v1/pengadaan/po` membawa `permintaan_pengadaan_id` dari PR yang berstatus masih `DIAJUKAN` atau `DITOLAK`.
* **Ekspektasi**: API menolak dengan HTTP 422 Unprocessable Entity dan pesan *"Hanya PR yang telah disetujui yang dapat diterbitkan PO"*.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

---

## 6. Modul Pengujian: Kepala Ruangan / Karu (`kepala_unit`)

### UAT-KRU-01: Otorisasi Permintaan Amprah Logistik Ruangan
* **Kondisi**: Normal
* **Tujuan**: Menyetujui formulir amprah barang habis pakai yang diajukan oleh staf perawat ruangan.
* **Langkah Pengujian**:
  1. Masuk menggunakan akun `kepalaunit@simlogrs.local`.
  2. Buka menu **Otorisasi Karu** (`/kepala-unit/approval-permintaan`).
  3. Tinjau permohonan amprah berstatus `DIAJUKAN`.
  4. Klik tombol **Setujui Permintaan**.
* **Ekspektasi**: Status amprah berubah menjadi `DISETUJUI_KARU`, kuantitas yang disetujui terisi penuh, dan berkas masuk ke meja antrean admin gudang sentral.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

### UAT-KRU-02: Penolakan Otorisasi Amprah Antar-Unit Lain (Isolasi BOLA/IDOR)
* **Kondisi**: Keamanan Akses
* **Tujuan**: Memastikan Kepala Ruangan IGD tidak dapat menyetujui atau menolak permohonan milik Ruang Rawat Inap.
* **Langkah Pengujian**:
  1. Kepala Unit mencoba memanggil fungsi `approveKaru($id)` membawa ID permohonan amprah milik unit ruangan lain.
* **Ekspektasi**: Akses ditolak dengan HTTP 403 Forbidden: *"Anda hanya berhak mengesahkan permohonan dari unit ruangan Anda sendiri"*.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

---

## 7. Modul Pengujian: Petugas / Staf Ruangan (`petugas_unit`)

### UAT-UNT-01: Pengajuan Formulir Amprah Barang Rutin & CITO
* **Kondisi**: Normal
* **Tujuan**: Mengajukan kebutuhan barang ATK/ART habis pakai untuk kelancaran pelayanan ruangan.
* **Langkah Pengujian**:
  1. Masuk menggunakan akun `petugasunit@simlogrs.local`.
  2. Buka menu **Permintaan Barang (Amprah)** (`/petugas-unit/permintaan-barang`).
  3. Klik **Buat Permintaan Baru**.
  4. Pilih tingkat urgensi: `CITO` (darurat pelayanan), pilih barang (misal: Spuit 5cc atau Kertas EKG), masukkan jumlah diminta: 10 box.
  5. Klik **Ajukan Permintaan**.
* **Ekspektasi**: Dokumen `REQ-YYYYMM-XXX` terbit dengan status `DIAJUKAN` dan badge urgensi berkedip warna merah (`CITO animate-pulse`).
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

### UAT-UNT-02: Konfirmasi Penerimaan Barang Ruangan (Closing SPPB)
* **Kondisi**: Normal & Integritas Anggaran
* **Tujuan**: Menerima serah terima fisik barang dari kurir gudang dan mencatatnya ke saldo inventaris ruangan.
* **Langkah Pengujian**:
  1. Buka menu **Penerimaan Barang** (`/petugas-unit/penerimaan-barang`).
  2. Cari dokumen SPPB berstatus `DIKIRIM`.
  3. Klik tombol **Konfirmasi Terima Barang**.
* **Ekspektasi**:
  - Dokumen serah terima `RCV-YYYYMM-XXX` terbit.
  - Saldo inventaris pada tabel `stok_ruangan` bertambah.
  - Status SPPB berubah menjadi `DITERIMA`.
  - Nilai rupiah barang tetap terhitung dalam kalkulasi realisasi anggaran belanja ruangan.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

### UAT-UNT-03: Pelaporan Kerusakan Sarana Cepat via Mobile / Web
* **Kondisi**: Normal
* **Tujuan**: Melaporkan alat medis/sarana gedung yang rusak lengkap dengan foto bukti kendala.
* **Langkah Pengujian**:
  1. Buka menu **Lapor Servis Ruangan** (`/petugas-unit/lapor-servis`).
  2. Klik **Lapor Kerusakan Baru**.
  3. Pilih sarana aset (misal: Lampu Operasi / Bed Pasien), tingkat urgensi: `TINGGI`.
  4. Tulis deskripsi masalah: *"Roda pengunci patah dan hidrolik tidak naik"*.
  5. Unggah foto kerusakan format JPEG/PNG.
  6. Klik **Kirim Laporan**.
* **Ekspektasi**:
  - Tiket `MNT-YYYYMM-XXX` terbit dengan status `DILAPORKAN`.
  - Berkas gambar terkompresi otomatis menjadi WebP hemat kuota via `FileOptimizer`.
  - Notifikasi tiket masuk terkirim ke dashboard teknisi IPSRS.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

---

## 8. Modul Pengujian: Teknisi Sarana & IPSRS (`teknisi`)

### UAT-TKN-01: Penerimaan Disposisi SPK & Pengerjaan Servis
* **Kondisi**: Normal
* **Tujuan**: Teknisi menerima surat perintah kerja dan memulai diagnosa kerusakan di lokasi.
* **Langkah Pengujian**:
  1. Masuk menggunakan akun `teknisi@simlogrs.local`.
  2. Buka menu **Daftar SPK Perbaikan** (`/teknisi/spk`).
  3. Pilih penugasan `SPK-YYYYMM-XXX` yang ditugaskan kepada diri sendiri.
  4. Buka menu **Lembar Kerja Pengerjaan** (`/teknisi/lembar-kerja`).
  5. Klik **Isi Lembar Kerja**.
  6. Masukkan jam mulai, jam selesai, diagnosa: *"Kapasitor power supply meledak"*, tindakan: *"Penggantian kapasitor 470uF dan kalibrasi ulang"*, hasil: `BERHASIL`, centang **Uji Fungsi Bersama Ruangan Lolos**.
  7. Masukkan kebutuhan suku cadang: Kapasitor (1 unit).
  8. Klik **Simpan Lembar Kerja**.
* **Ekspektasi**:
  - Dokumen SPK dan tiket servis berubah status menjadi `SELESAI`.
  - Stok suku cadang di gudang IPSRS otomatis dipotong secara FEFO.
  - Aset kembali berstatus `DIGUNAKAN` dan kondisi `BAIK`.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

### UAT-TKN-02: Pencegahan IDOR Pengisian Lembar Kerja Teknisi Lain (Negative Path)
* **Kondisi**: Keamanan IDOR
* **Tujuan**: Memastikan Teknisi A tidak dapat mengisi, menyelesaikan, atau mengeluarkan sparepart atas SPK yang ditugaskan kepada Teknisi B.
* **Langkah Pengujian**:
  1. Login sebagai Teknisi A.
  2. Coba kirim request POST `/v1/maintenance/spk/{id_milik_teknisi_B}/log` atau buka modal formulir SPK milik Teknisi B.
* **Ekspektasi**: Sistem memblokir dengan respon HTTP 403 Forbidden: *"Anda hanya diizinkan mengisi lembar kerja untuk SPK yang ditugaskan kepada Anda"*.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

### UAT-TKN-03: Pencegahan Cetak PDF Dokumen SPK Teknisi Lain
* **Kondisi**: Keamanan Dokumen Fisik
* **Tujuan**: Memastikan tautan cetak dokumen `/spk/{id}/cetak-pdf` terproteksi dari akses lintas teknisi.
* **Langkah Pengujian**:
  1. Akses URL `/teknisi/spk/{id_milik_teknisi_B}/cetak-pdf`.
* **Ekspektasi**: Halaman menampilkan pesan HTTP 403 Forbidden *"Anda hanya memiliki wewenang mencetak SPK yang ditugaskan kepada Anda"*.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

---

## 9. Modul Pengujian: Manajemen Direksi (`manajemen`)

### UAT-MNJ-01: Ringkasan Eksekutif Valuasi Stok & Serapan Anggaran Pagu
* **Kondisi**: Normal
* **Tujuan**: Memantau kesehatan keuangan logistik rumah sakit secara makro dan real-time.
* **Langkah Pengujian**:
  1. Masuk menggunakan akun `manajemen@simlogrs.local`.
  2. Buka menu **Dashboard Manajemen** (`/manajemen/dashboard`).
  3. Periksa kartu metrik: Valuasi Total Persediaan Gudang (Rp), Realisasi Pengeluaran Logistik (Amprah), dan Konsumsi Biaya Suku Cadang IPSRS.
  4. Buka menu **Pagu & Realisasi Anggaran** (`/manajemen/realisasi-anggaran`).
* **Ekspektasi**: Angka persentase serapan anggaran terhitung presisi, indikator status pagu menampilkan warna akurat (`Normal` hijau jika $\le 75\%$, `Waspada` kuning jika $> 75\%$, `Over-Budget` merah jika $> 100\%$).
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

### UAT-MNJ-02: Akses Lintas Seluruh Data Logistik Tanpa Wewenang Mutasi (Read-Only)
* **Kondisi**: Batasan Wewenang Peran
* **Tujuan**: Memastikan Direksi memiliki transparansi data ke seluruh unit tanpa tombol eksekusi manipulasi fisik gudang.
* **Langkah Pengujian**:
  1. Buka menu **Stock Opname Fisik** atau **Picking Distribusi**.
* **Ekspektasi**: Seluruh tabel transaksi dapat dilihat, namun tombol aksi mutasi (*Inisiasi Opname*, *Eksekusi Picking*, *Sahkan Penyesuaian*) tidak muncul / terproteksi `403`.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

---

## 10. Modul Pengujian: Auditor Internal SPI (`auditor`)

### UAT-AUD-01: Forensik Digital Audit Trail (Snapshot Nilai Sebelum & Sesudah)
* **Kondisi**: Forensik Digital
* **Tujuan**: Melacak riwayat modifikasi data sistem lengkap dengan IP Address, perangkat, dan perubahan field.
* **Langkah Pengujian**:
  1. Masuk menggunakan akun `auditor@simlogrs.local`.
  2. Buka menu **Digital Forensic Audit Trail** (`/auditor/audit-trail`).
  3. Gunakan filter aksi: `UBAH` atau `HAPUS`.
  4. Klik tombol **Detail Forensik** pada salah satu baris log mutasi.
* **Ekspektasi**: Modal menampilkan perbandingan data sebelum (*old values*) dan data sesudah (*new values*) dalam format JSON terstruktur yang tidak dapat dimanipulasi.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

### UAT-AUD-02: Deteksi Kepatuhan Pengadaan (Anomali PO vs PR) & Terbitkan CAPA
* **Kondisi**: Normal
* **Tujuan**: Menemukan PO yang terbit tanpa dokumen PR, PO terlambat dari vendor, atau PO berselisih kuantitas terima.
* **Langkah Pengujian**:
  1. Buka menu **Kepatuhan Pengadaan (PO vs PR)** (`/auditor/kepatuhan-pengadaan`).
  2. Pilih tab filter anomali: `TANPA_PR` atau `TERLAMBAT`.
  3. Pada PO anomali, klik tombol **Terbitkan Temuan CAPA**.
  4. Isi batas waktu perbaikan (Due Date CAPA) dan ringkasan temuan investigasi.
  5. Klik **Simpan Temuan**.
* **Ekspektasi**: Dokumen temuan audit resmi `AUD-YYYYMM-XXX` terdaftar dengan status `In Progress`.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

### UAT-AUD-03: Evaluasi Penutupan Temuan (Closing CAPA) oleh Auditor SPI
* **Kondisi**: Tata Kelola SPI
* **Tujuan**: Memvalidasi bukti perbaikan yang dikirimkan oleh auditee dan menutup temuan secara formal.
* **Langkah Pengujian**:
  1. Buka menu **Register Temuan & CAPA** (`/auditor/temuan-capa`).
  2. Pilih temuan yang statusnya sudah `In Review` (telah ditindaklanjuti oleh kepala unit/gudang terkait bukti perbaikan).
  3. Klik tombol **Evaluasi Closing (Auditor SPI)**.
  4. Tentukan hasil evaluasi: `Closed`, tulis catatan penutupan: *"Tindakan perbaikan dan bukti SPO telah diverifikasi lengkap"*.
  5. Klik **Simpan Evaluasi**.
* **Ekspektasi**: Status CAPA resmi berubah menjadi `Closed`, nama evaluator tercatat, dan closing rate metrik SPI meningkat.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

### UAT-AUD-04: Penolakan Akses Forensik untuk Pengguna Non-Auditor (Negative Path)
* **Kondisi**: Keamanan Data Konfidensial SPI
* **Tujuan**: Memastikan staf ruangan, perawat, atau teknisi biasa tidak dapat mengintip rekaman jejak forensik sistem.
* **Langkah Pengujian**:
  1. Login sebagai `petugas_unit` atau `petugas_gudang`.
  2. Akses endpoint API `GET /api/v1/audit/trail` atau `GET /api/v1/audit/summary`.
* **Ekspektasi**: Respon server memblokir akses dengan status HTTP 403 Forbidden: *"Akses ditolak: Data forensik hanya untuk Auditor Internal SPI & Manajemen"*.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

---

## 11. Modul Pengujian: Sirkulasi Peminjaman Aset Antar-Unit & Kerusakan

### UAT-AST-01: Alur Lengkap Peminjaman Aset (Happy Path)
* **Kondisi**: Siklus Penuh Transaksi Aset
* **Langkah Pengujian**:
  1. Petugas Unit Rawat Inap mengajukan pinjam monitor pasien milik Ruang ICU (`/petugas-unit/peminjaman-aset`). Kode: `PINJ-YYYY-XXX` status `DIAJUKAN`.
  2. Kepala Ruangan ICU menyetujui peminjaman di portal otorisasi (`/kepala-unit/approval-peminjaman`). Status: `DISETUJUI`.
  3. Petugas ICU menyerahkan fisik aset via tombol **Serah Terima Fisik**. Status: `DIPINJAM`.
  4. Periksa tabel master aset: kepemilikan operasional aset berpindah sementara ke Rawat Inap (`unit_id = Rawat Inap`).
* **Ekspektasi**: Transaksi sirkulasi berpindah status secara mulus dan lokasi kepemilikan operasional aset tercatat akurat.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

### UAT-AST-02: Pengembalian Aset Kondisi Rusak & Auto-Trigger Tiket Servis
* **Kondisi**: Edge Case / Penanganan Kerusakan Fisik
* **Langkah Pengujian**:
  1. Unit ICU menerima kembali aset di menu **Pengembalian & Inspeksi Fisik** (`/kepala-unit/pengembalian-aset`).
  2. Petugas ICU melakukan inspeksi fisik dan memilih kondisi: `RUSAK_BERAT` dengan catatan: *"Layar LCD pecah terbentur"*.
  3. Klik **Konfirmasi Pengembalian**.
* **Ekspektasi**:
  - Transaksi peminjaman ditutup dengan status `SELESAI`.
  - Kepemilikan aset kembali ke ICU (`unit_id = ICU`).
  - Status aset berubah menjadi `DALAM_MAINTENANCE` dengan kondisi `RUSAK_BERAT`.
  - Sistem **otomatis menerbitkan tiket servis IPSRS baru** (`MNT-YYYYMM-XXX`) prioritas `CITO` ke antrean teknisi dengan melampirkan riwayat kerusakan pasca-peminjaman.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

---

## 12. Modul Pengujian: Keamanan Sistem, Mobile API & Hardening

### UAT-SEC-01: Rate Limiting & Perlindungan Brute Force Login
* **Kondisi**: Serangan Siber (Brute Force)
* **Tujuan**: Mencegah penebakan kata sandi berulang-ulang melalui proteksi `RateLimiter`.
* **Langkah Pengujian**:
  1. Lakukan percobaan login gagal sebanyak 6 kali berturut-turut pada akun yang sama di halaman login gateway (`/login`) atau API mobile (`POST /v1/auth/login`).
* **Ekspektasi**: Pada percobaan ke-6, sistem mengunci login dengan respon HTTP 429 Too Many Requests: *"Terlalu banyak percobaan masuk. Coba lagi dalam 60 detik"*.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

### UAT-SEC-02: Kedaluwarsa Sesi Token Sanctum (Token Expiration)
* **Kondisi**: Masa Berlaku Sesi
* **Tujuan**: Memastikan token personal access client mobile memiliki masa kedaluwarsa 24 jam (`1440 menit`).
* **Langkah Pengujian**:
  1. Periksa konfigurasi `config('sanctum.expiration')`.
  2. Uji token yang diterbitkan: pastikan field `expires_at` terisi tepat 24 jam setelah waktu pembuatan.
* **Ekspektasi**: Token yang berumur lebih dari 24 jam otomatis ditolak dengan status HTTP 401 Unauthenticated.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

### UAT-SEC-03: Integritas Transaksi Bersamaan (Concurrency & Pessimistic Locking)
* **Kondisi**: Race Condition / Akses Simultan
* **Tujuan**: Mencegah pemotongan stok minus atau penambahan stok berlipat ganda saat dua request datang di milidetik yang sama.
* **Langkah Pengujian**:
  1. Lakukan request picking FEFO paralel menggunakan script pada batch barang yang stoknya tersisa tepat 5 unit, di mana kedua request sama-sama meminta 5 unit.
* **Ekspektasi**: Request pertama berhasil mengeluarkan 5 unit (stok sisa 0 dan status `HABIS`). Request kedua otomatis tertahan oleh `lockForUpdate()` dan setelahnya gagal secara aman dengan pesan *"Saldo stok fisik tidak mencukupi"*. Saldo tidak pernah minus.
* **Kriteria Lolos (Pass)**: [ ] Ya &nbsp;&nbsp; [ ] Tidak

---

### Lembar Pengesahan Pengujian (Sign-Off Sheet)

| Peran Penguji | Nama Lengkap | Jabatan Kedinasan | Tanggal Pengujian | Tanda Tangan / Persetujuan |
|---|---|---|:---:|:---:|
| **Lead Auditor / QA** | ........................................ | Auditor SPI RS | ..... / ..... / 2026 | [ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ] |
| **Kepala Instalasi Logistik** | ........................................ | Kepala Gudang Sentral | ..... / ..... / 2026 | [ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ] |
| **Koordinator IPSRS** | ........................................ | Koordinator Teknisi | ..... / ..... / 2026 | [ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ] |
| **Pimpinan SIMRS / IT** | ........................................ | Kepala Instalasi SIMRS | ..... / ..... / 2026 | [ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ] |
