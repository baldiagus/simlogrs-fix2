# DOKUMEN USER ACCEPTANCE TESTING (UAT)
## SISTEM INFORMASI MANAJEMEN LOGISTIK RUMAH SAKIT (SIMLOG RS NON-MEDIS v2)

---

### Informasi Dokumen
* **Versi Sistem**: SIMLOG RS Non-Medis v2.0
* **Lingkungan Pengujian**: Staging / QA Docker Environment (`http://localhost:8000`)
* **Metode Pengujian**: Black Box Testing, Role-Based Access Control (RBAC) Testing, Concurrency & Pessimistic Lock Testing
* **Struktur Kriteria**:
  - **Kriteria Utama (Primary Criteria)**: Alur sukses operasional baku (*Happy Path*).
  - **Kriteria Alternatif (Alternative Criteria)**: Alur cabang, parsial, kontinjensi, perpanjangan, atau penolakan bersyarat (*Alternate & Fallback Flows*).
  - **Kriteria Pengecualian (Exception / Security Criteria)**: Penolakan sistem atas pelanggaran wewenang atau batasan integritas data (*Negative Path*).
* **Status**: Siap Eksekusi (Ready for User Sign-off)

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

### UAT-ADM-01: Manajemen Akun Pegawai & Penugasan Fasilitas
* **Tujuan**: Memastikan Super Admin dapat mendaftarkan dan memperbarui profil pegawai rumah sakit.
* **Langkah Pengujian**:
  1. Masuk ke sistem menggunakan `superadmin@simlogrs.local`.
  2. Buka menu **Manajemen Akun Pengguna** (`/super-admin/pengguna`).
  3. Klik tombol **Tambah Pegawai**.
  4. Isi data: Nama `dr. Ahmad Santoso`, NIP `198501012010121001`, username `ahmad_santoso`, email `ahmad@simlogrs.local`, role `kepala_unit`, penugasan tipe `unit`, pilih unit `Instalasi Rawat Inap`.
  5. Masukkan kata sandi dan konfirmasi: `Password123!`. Klik **Simpan**.
* **Kriteria Utama**:
  - Akun terdaftar dengan status `AKTIF`. Pegawai dapat login dan otomatis dialihkan ke `/kepala-unit/dashboard`.
* **Kriteria Alternatif**:
  - *Alternatif A (Pegawai Non-Fasilitas)*: Jika pegawai adalah Auditor atau Direksi, opsi penugasan disetel `none` (tanpa unit dan tanpa gudang). Akun tetap berhasil terdaftar dan memiliki wewenang lintas fasilitas.
  - *Alternatif B (Reset Kata Sandi)*: Jika pegawai lupa sandi, admin menekan tombol **Reset Kata Sandi**. Sandi direset secara aman dan log audit mencatat mutasi reset.
* **Kriteria Pengecualian**:
  - Sistem menolak pendaftaran jika email atau username sudah digunakan pengguna lain (Pesan: *"Username/Email sudah terdaftar"*).
  - Admin non-super_admin dicegah menunjuk peran `super_admin` pada user lain (HTTP 403).
* **Hasil Pengujian**: [ ] Pass &nbsp;&nbsp; [ ] Fail &nbsp;&nbsp;&nbsp; **Catatan**: ........................................

---

### UAT-ADM-02: Konfigurasi Matriks Hak Akses Dinamis (Role Manager)
* **Tujuan**: Menyesuaikan wewenang peran pada matriks 4 kolom (Akses Halaman, Lihat Data, Buat/Ubah, Hapus) tanpa hardcoded code.
* **Langkah Pengujian**:
  1. Buka menu **Matriks Wewenang Peran** (`/super-admin/roles`).
  2. Pilih peran `petugas_unit`.
  3. Nonaktifkan izin `create_layanan_unit::lapor::servis`. Klik **Simpan Wewenang**.
  4. Login sebagai `petugasunit@simlogrs.local` dan periksa tombol Lapor Servis.
* **Kriteria Utama**:
  - Tombol Lapor Servis hilang dari tampilan dan akses form menghasilkan HTTP 403 Forbidden.
* **Kriteria Alternatif**:
  - *Alternatif A (Quick Action Domain)*: Admin menggunakan tombol **Lihat Saja (View Only)** pada Domain Master Data. Seluruh izin buat/ubah/hapus otomatis nonaktif, menyisakan izin baca saja dalam satu klik.
  - *Alternatif B (Anti Self-Lockout)*: Jika peran `super_admin` dipilih dan dicoba dihapus seluruh izinnya, sistem secara otomatis mempertahankan izin inti `page_Dashboard` dan `user::role` agar admin tidak terkunci selamanya.
* **Kriteria Pengecualian**:
  - Pengguna non-admin yang memanggil fungsi simpan wewenang via API/Livewire diblokir seketika dengan respon 403.
* **Hasil Pengujian**: [ ] Pass &nbsp;&nbsp; [ ] Fail &nbsp;&nbsp;&nbsp; **Catatan**: ........................................

---

## 2. Modul Pengujian: Admin Logistik (`admin_logistik`)

### UAT-LOG-01: Pengelolaan Kamus Master Barang & Ambang Batas Stok
* **Tujuan**: Mendaftarkan komoditas baru dan mengatur peringatan titik pesan ulang (*Re-Order Point*).
* **Langkah Pengujian**:
  1. Masuk menggunakan akun `adminlogistik@simlogrs.local`.
  2. Buka menu **Master Barang** (`/admin-logistik/barang`).
  3. Klik **Tambah Barang Baru**.
  4. Isi data: Kategori `Alat Tulis Kantor`, Satuan `Pcs`, Nama `Kertas HVS A4 80gr`, Tipe `HABIS_PAKAI`, Titik Pesan `10`, Stok Minimum `5`.
  5. Masukkan harga beli terakhir: `Rp 55.000`. Klik **Simpan**.
* **Kriteria Utama**:
  - Barang terdaftar dengan kode otomatis `BRG-YYYY-XXX`, status `AKTIF`, dan muncul di pencarian katalog.
* **Kriteria Alternatif**:
  - *Alternatif A (Tanpa Batas Stok)*: Kolom Stok Min, Maks, dan Titik Pesan dikosongkan. Sistem menyimpannya sebagai `0` (tanpa batas / unconstrained) tanpa menampilkan peringatan ROP.
  - *Alternatif B (Nonaktifkan Barang)*: Admin menekan **Ubah Status ke Nonaktif**. Barang berubah status menjadi `NONAKTIF` dan otomatis hilang dari dropdown pengajuan amprah ruangan maupun PR pengadaan.
* **Kriteria Pengecualian**:
  - Kode barang yang diinput manual secara duplikat ditolak oleh validasi unik database.
* **Hasil Pengujian**: [ ] Pass &nbsp;&nbsp; [ ] Fail &nbsp;&nbsp;&nbsp; **Catatan**: ........................................

---

### UAT-LOG-02: Registrasi Master Aset Fisik & Stiker QR Code
* **Tujuan**: Mendaftarkan alat inventaris dan menguji kepatuhan penempatan lokasi eksklusif XOR.
* **Langkah Pengujian**:
  1. Buka menu **Register Master Aset** (`/admin-logistik/aset`).
  2. Klik **Tambah Aset Baru**.
  3. Pilih barang `Kursi Roda Standard`, Nomor Seri `SN-KR-2026-001`, Tanggal Perolehan hari ini, Nilai `Rp 2.500.000`.
  4. Pilih Penempatan Fisik: Tipe `GUDANG`, pilih `Gudang Sentral Logistik Non-Medis`, Rak `Rak B-01`.
  5. Klik **Simpan**. Klik tombol **Cetak QR**.
* **Kriteria Utama**:
  - Aset tersimpan dengan kode `AST-YYYY-XXX`, `unit_id` bernilai NULL, stiker QR SVG ter-render tajam dan dapat dipindai.
* **Kriteria Alternatif**:
  - *Alternatif A (Penempatan di Ruangan)*: Jika tipe dipilih `RUANGAN`, opsi gudang dan rak otomatis dikosongkan dan `unit_id` diisi ID ruangan (lolos check constraint `chk_aset_lokasi_xor`).
  - *Alternatif B (Aset Rusak Awal)*: Jika aset diterima dari hibah dalam kondisi `RUSAK_RINGAN`, status awal otomatis dapat disetel `DALAM_MAINTENANCE` untuk langsung masuk antrean pemeriksaan teknisi.
* **Kriteria Pengecualian**:
  - Database menolak jika terjadi anomali pengisian `unit_id` dan `gudang_id` secara bersamaan (Error Check Constraint).
* **Hasil Pengujian**: [ ] Pass &nbsp;&nbsp; [ ] Fail &nbsp;&nbsp;&nbsp; **Catatan**: ........................................

---

## 3. Modul Pengujian: Kepala Gudang (`kepala_gudang`)

### UAT-GDG-01: Inisiasi Sesi Stock Opname Fisik & Cetak Lembar Hitung
* **Tujuan**: Mengunci saldo buku dan menerbitkan formulir penghitungan fisik di rak gudang.
* **Langkah Pengujian**:
  1. Masuk menggunakan akun `kepalagudang@simlogrs.local`.
  2. Buka menu **Stock Opname Fisik** (`/kepala-gudang/stok-opname`).
  3. Klik **Inisiasi Sesi Opname**. Pilih gudang dan isi catatan: `Opname Akhir Bulan`. Klik **Buka Sesi**.
  4. Klik tombol **Ekspor Lembar Kerja Excel**.
* **Kriteria Utama**:
  - Dokumen `OPN-YYYYMM-XXX` terbit berstatus `BERJALAN`. File `.xlsx` terunduh berisi kolom `ID_Detail`, `Kode_Barang`, `Nomor_Batch`, `Lokasi_Rak`, dan kolom kosong `Jumlah_Fisik`.
* **Kriteria Alternatif**:
  - *Alternatif A (Import Excel Massal)*: Petugas mengunggah kembali file Excel yang telah diisi angka fisik via tombol **Impor Hasil Excel**. Angka fisik ter-update otomatis pada seluruh baris tanpa input manual satu-persatu.
  - *Alternatif B (Sesi Tanpa Selisih)*: Jika seluruh angka fisik 100% sama dengan saldo sistem (selisih = 0), sesi opname otomatis disahkan berstatus `DISETUJUI` tanpa menerbitkan dokumen penyesuaian varians.
* **Kriteria Pengecualian**:
  - Sistem menolak inisiasi sesi baru pada gudang yang saat itu masih memiliki sesi opname lain yang berstatus `BERJALAN`.
* **Hasil Pengujian**: [ ] Pass &nbsp;&nbsp; [ ] Fail &nbsp;&nbsp;&nbsp; **Catatan**: ........................................

---

### UAT-GDG-02: Otorisasi Dokumen Penyesuaian Stok (Variance Approval)
* **Tujuan**: Mengesahkan perubahan saldo fisik riil terhadap selisih hasil opname.
* **Langkah Pengujian**:
  1. Buka menu **Otorisasi Penyesuaian** (`/kepala-gudang/otorisasi-penyesuaian`).
  2. Pilih dokumen penyesuaian `ADJ-YYYYMM-XXX` berstatus `MENUNGGU`.
  3. Periksa rincian selisih kuantitas dan valuasi selisih rupiah.
  4. Klik tombol **Sahkan Penyesuaian Stok**.
* **Kriteria Utama**:
  - Saldo kuantitas pada `stok_batch` bertambah/berkurang sesuai selisih fisik riil. Status batch yang saldonya menjadi 0 berubah menjadi `HABIS`. Kartu mutasi mencatat riwayat transaksi `PENYESUAIAN`.
* **Kriteria Alternatif**:
  - *Alternatif A (Penolakan Selisih / Investigasi Ulang)*: Kepala Gudang menekan tombol **Tolak Penyesuaian** dengan alasan: *"Penghitungan fisik meragukan, lakukan blind count ulang"*. Status dokumen berubah menjadi `DITOLAK` dan saldo batch tidak berubah.
* **Kriteria Pengecualian**:
  - Transaksi otomatis dibatalkan (Rollback) jika saldo batch fisik saat approval lebih kecil dari kuantitas pengurangan yang diminta (Mencegah saldo minus).
* **Hasil Pengujian**: [ ] Pass &nbsp;&nbsp; [ ] Fail &nbsp;&nbsp;&nbsp; **Catatan**: ........................................

---

## 4. Modul Pengujian: Petugas Gudang (`petugas_gudang`)

### UAT-STF-01: Penerimaan Barang Vendor (Inbound PO) & Manajemen Batch
* **Tujuan**: Mencatat fisik barang masuk dari supplier sesuai surat jalan vendor.
* **Langkah Pengujian**:
  1. Masuk menggunakan akun `petugasgudang@simlogrs.local`.
  2. Buka menu **Penerimaan Barang (PO)** (`/petugas-gudang/penerimaan-po`).
  3. Pilih PO berstatus `DIKIRIM_VENDOR`. Klik **Terima Barang**.
  4. Masukkan nomor surat jalan `SJ-VENDOR-01`, pilih rak penyimpanan fisik, input nomor batch, tanggal kedaluwarsa, dan kuantitas diterima.
  5. Klik **Simpan Penerimaan**.
* **Kriteria Utama**:
  - Dokumen `RCV-YYYYMM-XXX` terbit. Batch baru bertambah dengan status `AKTIF`. Status PO berubah menjadi `DITERIMA_LENGKAP`.
* **Kriteria Alternatif**:
  - *Alternatif A (Penerimaan Parsial / Bertahap)*: Vendor baru mengirim sebagian barang (misal: pesan 100, baru datang 40). Petugas menginput kuantitas 40. Status PO menjadi `DITERIMA_SEBAGIAN`, PO tetap terbuka untuk penerimaan sisa 60 unit berikutnya.
  - *Alternatif B (Penerimaan dengan Barang Rusak / Reject)*: Dari 50 unit yang datang, 5 unit rusak dalam perjalanan. Petugas menginput `jumlah_diterima: 50`, `jumlah_rusak: 5`. Sistem mencatat saldo neto yang masuk ke rak gudang adalah 45 unit, dan 5 unit tercatat sebagai barang rusak di dokumen penerimaan.
* **Kriteria Pengecualian**:
  - Petugas dilarang menyimpan penerimaan jika nomor surat jalan kosong atau kuantitas diterima bernilai 0.
* **Hasil Pengujian**: [ ] Pass &nbsp;&nbsp; [ ] Fail &nbsp;&nbsp;&nbsp; **Catatan**: ........................................

---

### UAT-STF-02: Eksekusi Alokasi Pengeluaran Barang FEFO (Picking SPPB)
* **Tujuan**: Menjalankan pengeluaran barang pesanan ruangan berbasis urutan tanggal kedaluwarsa terdekat.
* **Langkah Pengujian**:
  1. Buka menu **Picking Distribusi (FEFO)** (`/petugas-gudang/picking-distribusi`).
  2. Pilih antrean amprah berstatus `DISETUJUI_GUDANG`.
  3. Klik tombol **Eksekusi Picking FEFO**.
* **Kriteria Utama**:
  - Sistem otomatis memilih nomor batch yang masa kedaluwarsanya paling dekat.
  - Dokumen SPPB (`SPPB-YYYYMMDD-XXXX`) terbit berstatus `DIKIRIM`.
  - Saldo batch terpotong secara instan dengan proteksi `lockForUpdate()`.
* **Kriteria Alternatif**:
  - *Alternatif A (Multi-Batch Split Allocation)*: Jika kuantitas yang diminta adalah 50 unit, sedangkan batch terdekat hanya memiliki sisa 20 unit, sistem otomatis membagi alokasi: 20 unit dari batch terdekat (status batch menjadi `HABIS`) dan 30 unit dari batch berikutnya.
* **Kriteria Pengecualian**:
  - Jika total seluruh saldo fisik di gudang kurang dari kuantitas yang disetujui, eksekusi picking dibatalkan dengan pesan: *"Saldo stok fisik tidak mencukupi untuk barang [Nama_Barang]"*.
* **Hasil Pengujian**: [ ] Pass &nbsp;&nbsp; [ ] Fail &nbsp;&nbsp;&nbsp; **Catatan**: ........................................

---

## 5. Modul Pengujian: Bagian Pengadaan (`pengadaan`)

### UAT-PPO-01: Verifikasi PR & Penerbitan Pesanan Pembelian (PO) Vendor
* **Tujuan**: Memproses permohonan pengadaan barang dari unit kerja menjadi pesanan resmi ke rekanan.
* **Langkah Pengujian**:
  1. Masuk menggunakan akun `pengadaan@simlogrs.local`.
  2. Buka menu **Pesanan Pembelian (PO)** (`/pengadaan/pesanan-pembelian`).
  3. Pilih permohonan pengadaan (PR) berstatus `DISETUJUI`. Klik **Terbitkan PO**.
  4. Pilih supplier rekanan aktif, tentukan syarat pembayaran `TEMPO_30_HARI`, estimasi tanggal kirim, dan harga satuan kontrak.
  5. Klik **Terbitkan PO**.
* **Kriteria Utama**:
  - Dokumen PO (`PO-YYYY-XXX`) terbentuk berstatus `DITERBITKAN`. Status PR berubah menjadi `DIPROSES`.
* **Kriteria Alternatif**:
  - *Alternatif A (Penolakan oleh Pengadaan)*: Bagian pengadaan menemukan bahwa harga penawaran rekanan melonjak tidak wajar. Pengadaan menekan tombol **Tolak PR** dengan catatan: *"Harga pasar melampaui pagu, ajukan revisi spesifikasi"*. Status PR berubah menjadi `DITOLAK` dan dikembalikan ke pemohon.
* **Kriteria Pengecualian**:
  - Sistem menolak penerbitan PO jika harga satuan bernilai negatif atau rekanan berstatus `NONAKTIF`.
* **Hasil Pengujian**: [ ] Pass &nbsp;&nbsp; [ ] Fail &nbsp;&nbsp;&nbsp; **Catatan**: ........................................

---

## 6. Modul Pengujian: Kepala Ruangan / Karu (`kepala_unit`)

### UAT-KRU-01: Otorisasi Kebutuhan Logistik Ruangan Pelayanan
* **Tujuan**: Memverifikasi kewajaran permohonan amprah rutin ruangan sebelum diajukan ke logistik sentral.
* **Langkah Pengujian**:
  1. Masuk menggunakan akun `kepalaunit@simlogrs.local`.
  2. Buka menu **Otorisasi Karu** (`/kepala-unit/approval-permintaan`).
  3. Pilih permohonan amprah `REQ-YYYYMM-XXX` berstatus `DIAJUKAN`.
  4. Klik **Setujui Permintaan**.
* **Kriteria Utama**:
  - Permintaan disetujui penuh (`DISETUJUI_KARU`), seluruh jumlah barang disetujui sama dengan jumlah diminta, dan berkas masuk ke antrean otorisasi logistik gudang.
* **Kriteria Alternatif**:
  - *Alternatif A (Otorisasi Kuantitas Parsial)*: Karu menilai permohonan berlebihan. Karu mengubah jumlah disetujui dari 20 box menjadi 10 box pada form otorisasi. Kuantitas yang diajukan ke gudang disesuaikan menjadi 10 box.
  - *Alternatif B (Penolakan Permintaan)*: Karu menolak permohonan dengan alasan: *"Stok ruangan masih mencukupi untuk 2 minggu ke depan"*. Status dokumen berubah menjadi `DITOLAK` dan arsip ditutup.
* **Kriteria Pengecualian**:
  - Karu dilarang menyetujui atau melihat permohonan amprah milik unit ruangan lain (Isolasi BOLA terproteksi).
* **Hasil Pengujian**: [ ] Pass &nbsp;&nbsp; [ ] Fail &nbsp;&nbsp;&nbsp; **Catatan**: ........................................

---

## 7. Modul Pengujian: Petugas / Staf Ruangan (`petugas_unit`)

### UAT-UNT-01: Pengajuan Amprah Rutin, CITO, & Pembatalan Mandiri
* **Tujuan**: Mengajukan kebutuhan barang habis pakai ruangan dan membatalkan jika terjadi salah input.
* **Langkah Pengujian**:
  1. Masuk menggunakan akun `petugasunit@simlogrs.local`.
  2. Buka menu **Permintaan Barang (Amprah)** (`/petugas-unit/permintaan-barang`).
  3. Klik **Buat Permintaan Baru**. Pilih urgensi `REGULER`, pilih barang dan jumlah diminta. Klik **Simpan**.
* **Kriteria Utama**:
  - Dokumen `REQ-YYYYMM-XXX` terbentuk dengan status `DIAJUKAN`.
* **Kriteria Alternatif**:
  - *Alternatif A (Permintaan Darurat CITO)*: Jika ruangan menghadapi lonjakan pasien darurat, staf memilih urgensi `CITO`. Dokumen memiliki badge merah berdenyut (`animate-pulse`) dan berada di posisi paling atas antrean picking gudang.
  - *Alternatif B (Pembatalan Mandiri oleh Pemohon)*: Selama status masih `DIAJUKAN`, pemohon dapat menekan tombol **Batalkan Permintaan**. Status berubah menjadi `BATAL` tanpa memerlukan otorisasi Karu.
* **Kriteria Pengecualian**:
  - Sistem menolak pengajuan jika stok komoditas di gudang saat itu bernilai 0 unit (Pesan: *"Stok di gudang kosong, ajukan Permintaan Pengadaan PR"*).
* **Hasil Pengujian**: [ ] Pass &nbsp;&nbsp; [ ] Fail &nbsp;&nbsp;&nbsp; **Catatan**: ........................................

---

### UAT-UNT-02: Konfirmasi Penerimaan Fisik Barang Ruangan (Closing SPPB)
* **Tujuan**: Menerima serah terima fisik barang dari kurir ekspedisi logistik rumah sakit.
* **Langkah Pengujian**:
  1. Buka menu **Penerimaan Barang** (`/petugas-unit/penerimaan-barang`).
  2. Cari dokumen SPPB berstatus `DIKIRIM`. Klik **Konfirmasi Terima Barang**.
* **Kriteria Utama**:
  - Tanda terima `RCV-YYYYMM-XXX` terbit, saldo `stok_ruangan` bertambah, status SPPB menjadi `DITERIMA`, dan nilai rupiah tetap terhitung dalam serapan anggaran ruangan.
* **Kriteria Alternatif**:
  - *Alternatif A (Penerimaan via Scanner HP)*: Perawat memindai barcode SPPB menggunakan gawai mobile via API `POST /v1/distribusi/sppb/{id}/terima`. Respon 200 OK diterima seketika dan status sinkron menjadi `DITERIMA`.
* **Kriteria Pengecualian**:
  - Staf unit dilarang mengonfirmasi penerimaan SPPB yang ditujukan untuk unit ruangan lain.
* **Hasil Pengujian**: [ ] Pass &nbsp;&nbsp; [ ] Fail &nbsp;&nbsp;&nbsp; **Catatan**: ........................................

---

## 8. Modul Pengujian: Teknisi Sarana IPSRS (`teknisi`)

### UAT-TKN-01: Eksekusi SPK & Pencatatan Suku Cadang FEFO
* **Tujuan**: Mengerjakan perbaikan fasilitas rumah sakit dan mencatat penggantian onderdil/sparepart.
* **Langkah Pengujian**:
  1. Masuk menggunakan akun `teknisi@simlogrs.local`.
  2. Buka menu **Lembar Kerja Pengerjaan** (`/teknisi/lembar-kerja`).
  3. Pilih SPK perbaikan yang ditugaskan kepada diri sendiri. Klik **Isi Lembar Kerja**.
  4. Masukkan diagnosa kendala, tindakan perbaikan, pilih hasil `BERHASIL`, centang **Uji Fungsi Lolos**.
  5. Masukkan sparepart yang digunakan (misal: Ball Bearing 1 Pcs). Klik **Simpan**.
* **Kriteria Utama**:
  - SPK dan tiket servis berubah status menjadi `SELESAI`.
  - Stok suku cadang di gudang IPSRS otomatis terpotong secara FEFO.
  - Aset kembali berstatus `DIGUNAKAN` dan kondisi `BAIK`.
* **Kriteria Alternatif**:
  - *Alternatif A (Pekerjaan Tertunda / Menunggu Sparepart)*: Jika sparepart khusus belum tersedia, teknisi memilih hasil `PENDING` atau tanpa suku cadang. Tiket servis beralih ke status `SEDANG_DIKERJAKAN` / `MENUNGGU_SPAREPART`, dan SPK tetap terbuka untuk penanganan lanjutan.
  - *Alternatif B (Uji Fungsi Kelaikan Gagal)*: Teknisi telah mengganti sparepart namun saat diuji bersama perawat ruangan alat masih belum stabil. Teknisi tidak mencentang uji fungsi. Status tiket menjadi `UJI_FUNGSI` dan aset tetap berstatus `DALAM_MAINTENANCE`.
* **Kriteria Pengecualian**:
  - Teknisi dilarang mengisi atau menyelesaikan lembar kerja atas SPK yang ditugaskan kepada teknisi lain (HTTP 403 IDOR Blocked).
* **Hasil Pengujian**: [ ] Pass &nbsp;&nbsp; [ ] Fail &nbsp;&nbsp;&nbsp; **Catatan**: ........................................

---

## 9. Modul Pengujian: Manajemen Direksi (`manajemen`)

### UAT-MNJ-01: Monitoring Valuasi Stok Persediaan & Pagu Realisasi
* **Tujuan**: Menyediakan dashboard analitik eksekutif kondisi perbekalan rumah sakit.
* **Langkah Pengujian**:
  1. Masuk menggunakan akun `manajemen@simlogrs.local`.
  2. Buka menu **Dashboard Manajemen** (`/manajemen/dashboard`) dan **Pagu & Realisasi** (`/manajemen/realisasi-anggaran`).
* **Kriteria Utama**:
  - Valuasi total persediaan (Rp) dan ringkasan serapan anggaran per unit tertampil real-time dengan status serapan akurat (`Normal`, `Waspada`, `Over-Budget`).
* **Kriteria Alternatif**:
  - *Alternatif A (Filter Lintas Periode)*: Manajemen memilih filter tahun lampau (misal: `2025`). Sistem menampilkan riwayat pembukuan tahun tersebut tanpa mengganggu pagu aktif tahun berjalan.
* **Kriteria Pengecualian**:
  - Portal direksi bersifat *Read-Only*. Seluruh aksi transaksi fisik (Inisiasi Opname, Eksekusi Picking, dll.) tidak memiliki tombol aksi atau terproteksi 403.
* **Hasil Pengujian**: [ ] Pass &nbsp;&nbsp; [ ] Fail &nbsp;&nbsp;&nbsp; **Catatan**: ........................................

---

## 10. Modul Pengujian: Auditor Internal SPI (`auditor`)

### UAT-AUD-01: Digital Forensic Audit Trail & Investigasi Perubahan Data
* **Tujuan**: Memeriksa rekaman forensik aktivitas mutasi data penting sistem.
* **Langkah Pengujian**:
  1. Masuk menggunakan akun `auditor@simlogrs.local`.
  2. Buka menu **Digital Forensic Audit Trail** (`/auditor/audit-trail`).
  3. Filter aksi `UBAH` pada entitas `Barang` atau `PesananPembelian`. Klik **Detail Forensik**.
* **Kriteria Utama**:
  - Tampil rincian forensik: IP Address pengguna, User-Agent browser/perangkat, serta perbandingan JSON nilai sebelum (*old data*) dan sesudah (*new data*).
* **Kriteria Alternatif**:
  - *Alternatif A (Pencarian Berbasis Rentang Tanggal)*: Auditor memfilter audit trail berdasarkan tanggal tertentu untuk keperluan audit berkala SPI.
* **Kriteria Pengecualian**:
  - Seluruh pengguna non-auditor dan non-direksi diblokir saat mencoba mengakses rute `/audit-trail` atau API `/api/v1/audit/trail` (HTTP 403).
* **Hasil Pengujian**: [ ] Pass &nbsp;&nbsp; [ ] Fail &nbsp;&nbsp;&nbsp; **Catatan**: ........................................

---

### UAT-AUD-02: Register Temuan CAPA & Siklus Penutupan Formal (Closing)
* **Tujuan**: Mengelola siklus tindakan perbaikan atas temuan ketidakpatuhan SOP.
* **Langkah Pengujian**:
  1. Buka menu **Register Temuan & CAPA** (`/auditor/temuan-capa`).
  2. Pilih temuan yang telah diberi tindakan perbaikan oleh unit auditee (`In Review`).
  3. Klik tombol **Evaluasi Closing (Auditor SPI)**.
  4. Pilih hasil evaluasi `Closed`, masukkan catatan evaluasi penutupan, klik **Simpan Evaluasi**.
* **Kriteria Utama**:
  - Status temuan berubah menjadi `Closed`, nama evaluator tercatat permanen, dan tingkat penyelesaian CAPA (*Closing Rate*) meningkat.
* **Kriteria Alternatif**:
  - *Alternatif A (Penolakan Bukti Perbaikan)*: Bukti foto/SPO yang dilampirkan unit dinilai belum memadai. Auditor memilih hasil evaluasi `In Progress` dengan catatan: *"Bukti SPO belum ditandatangani Direktur, lengkapi ulang"*. Status temuan kembali ke `In Progress` dan auditee diminta mengunggah ulang bukti.
* **Kriteria Pengecualian**:
  - Auditee dari unit lain dilarang menghapus bukti perbaikan atau mengubah tanggapan unit terperiksa lain.
* **Hasil Pengujian**: [ ] Pass &nbsp;&nbsp; [ ] Fail &nbsp;&nbsp;&nbsp; **Catatan**: ........................................

---

## 11. Modul Pengujian: Sirkulasi Aset & Penanganan Kerusakan

### UAT-AST-01: Sirkulasi Peminjaman Aset Antar-Ruangan
* **Tujuan**: Memfasilitasi mobilisasi alat medis antar-unit pelayanan dengan pelacakan tanggung jawab operasional.
* **Langkah Pengujian**:
  1. Staf Rawat Inap mengajukan pinjam monitor pasien milik Ruang ICU (`/petugas-unit/peminjaman-aset`). Kode: `PINJ-YYYY-XXX` status `DIAJUKAN`.
  2. Karu ICU menyetujui peminjaman di menu otorisasi. Status: `DISETUJUI`.
  3. Petugas ICU menyerahkan fisik via tombol **Serah Terima Fisik**.
* **Kriteria Utama**:
  - Status transaksi menjadi `DIPINJAM`. Kepemilikan operasional aset berpindah sementara ke Rawat Inap (`unit_id = Rawat Inap`).
* **Kriteria Alternatif**:
  - *Alternatif A (Perpanjangan Masa Pinjam)*: Sebelum batas waktu habis, Rawat Inap menekan tombol **Perpanjang Peminjaman**. Tanggal kembali rencana diperpanjang dan status peminjaman tetap aktif `DIPINJAM`.
  - *Alternatif B (Peminjaman dari Gudang Sentral)*: Aset dipinjam dari stok cadangan Gudang Sentral (`tipe_penyedia = GUDANG`). Serah terima divalidasi oleh petugas gudang.
* **Kriteria Pengecualian**:
  - Ruangan dilarang meminjam aset miliknya sendiri yang sudah berada di ruangannya.
* **Hasil Pengujian**: [ ] Pass &nbsp;&nbsp; [ ] Fail &nbsp;&nbsp;&nbsp; **Catatan**: ........................................

---

### UAT-AST-02: Pengembalian Aset Rusak & Auto-Trigger Tiket Servis
* **Tujuan**: Menangani alat yang kembali pasca-pinjam dalam keadaan rusak secara otomatis.
* **Langkah Pengujian**:
  1. Ruang ICU menerima pengembalian monitor pasien di menu **Pengembalian Aset** (`/kepala-unit/pengembalian-aset`).
  2. Petugas melakukan inspeksi fisik dan memilih kondisi: `RUSAK_BERAT` dengan catatan: *"Layar LCD mati total akibat terjatuh"*.
  3. Klik **Konfirmasi Pengembalian**.
* **Kriteria Utama**:
  - Peminjaman selesai (`SELESAI`). Kepemilikan aset kembali ke ICU (`unit_id = ICU`).
  - Status aset menjadi `DALAM_MAINTENANCE` dan kondisi `RUSAK_BERAT`.
  - Sistem **otomatis menerbitkan tiket servis baru (`MNT-YYYYMM-XXX`) prioritas CITO** ke antrean teknisi IPSRS dengan lampiran catatan kerusakan inspeksi.
* **Kriteria Alternatif**:
  - *Alternatif A (Pengembalian Kondisi Baik)*: Jika kondisi fisik normal (`BAIK`), aset langsung berstatus `TERSEDIA` dan siap dipinjamkan kembali ke ruangan lain tanpa membuat tiket servis.
* **Kriteria Pengecualian**:
  - Staf dari unit bukan penyedia dilarang menerima pengembalian aset milik ruangan lain.
* **Hasil Pengujian**: [ ] Pass &nbsp;&nbsp; [ ] Fail &nbsp;&nbsp;&nbsp; **Catatan**: ........................................

---

## 12. Modul Pengujian: Keamanan Sistem & Concurrency

### UAT-SEC-01: Pembatasan Percobaan Masuk (Rate Limiting Brute Force)
* **Tujuan**: Melindungi sistem dari serangan penebakan kredensial.
* **Langkah Pengujian**:
  1. Lakukan percobaan login gagal sebanyak 5 kali berturut-turut pada akun yang sama di web `/login` atau API `POST /v1/auth/login`.
  2. Lakukan percobaan ke-6.
* **Kriteria Utama**:
  - Percobaan ke-6 ditolak seketika dengan respon HTTP 429 Too Many Requests: *"Terlalu banyak percobaan masuk. Coba lagi dalam 60 detik"*.
* **Kriteria Alternatif**:
  - *Alternatif A (Login Berhasil Sebelum Batas)*: Jika pada percobaan ke-3 kata sandi benar dimasukkan, login berhasil dan counter throttle otomatis di-clear (`RateLimiter::clear`). Percobaan berikutnya dihitung dari 0.
* **Kriteria Pengecualian**:
  - Header IP palsu `X-Forwarded-For` dari proxy eksternal tidak dapat memanipulasi rate limiter karena konfigurasi `trustedProxies` yang telah diisolasi.
* **Hasil Pengujian**: [ ] Pass &nbsp;&nbsp; [ ] Fail &nbsp;&nbsp;&nbsp; **Catatan**: ........................................

---

### UAT-SEC-02: Pencegahan Double-Increment Stok (Pessimistic Locking Concurrency)
* **Tujuan**: Mencegah saldo ganda atau saldo minus saat transaksi dieksekusi simultan di milidetik yang sama.
* **Langkah Pengujian**:
  1. Kirim dua request konfirmasi penerimaan SPPB atau picking FEFO secara paralel menggunakan script pengujian pada dokumen yang sama.
* **Kriteria Utama**:
  - Request pertama berhasil dieksekusi.
  - Request kedua tertahan oleh kunci database `lockForUpdate()` dan setelahnya gagal secara aman (Pesan: *"Dokumen telah diproses sebelumnya"*).
  - Saldo stok ruangan maupun gudang terpotong/bertambah tepat satu kali.
* **Kriteria Pengecualian**:
  - Tidak ada deadlock yang menggantung koneksi database server.
* **Hasil Pengujian**: [ ] Pass &nbsp;&nbsp; [ ] Fail &nbsp;&nbsp;&nbsp; **Catatan**: ........................................

---

### Lembar Pengesahan Pengujian (Sign-Off Sheet)

| Peran Penguji | Nama Lengkap | Jabatan Kedinasan | Tanggal Pengujian | Tanda Tangan / Persetujuan |
|---|---|---|:---:|:---:|
| **Lead Auditor / QA** | ........................................ | Auditor SPI RS | ..... / ..... / 2026 | [ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ] |
| **Kepala Instalasi Logistik** | ........................................ | Kepala Gudang Sentral | ..... / ..... / 2026 | [ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ] |
| **Koordinator IPSRS** | ........................................ | Koordinator Teknisi | ..... / ..... / 2026 | [ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ] |
| **Pimpinan SIMRS / IT** | ........................................ | Kepala Instalasi SIMRS | ..... / ..... / 2026 | [ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ] |
