# Backlog & Inventaris Evaluasi Berkas Kode (> 300 Baris)

> **Dokumen Acuan:** `Code Organization & File Size Rules` (Rule 1 s.d. Rule 10).
> Dokumen ini mencatat seluruh file dan metode yang melebihi batas ideal (100–200 baris) dan batas normal (300 baris kode bermakna), sebagai panduan terstruktur agar tidak ada komponen yang terlewat dalam iterasi refaktorisasi berikutnya.

---

## 1. Status Ringkasan Kepatuhan Repositori

| Kategori Metrik | Ambang Batas Aturan | Kondisi Terkini | Status Kepatuhan |
|---|:---:|:---:|:---:|
| **Pelanggaran Keras File (> 500 Baris Kode)** | Maks. 500 baris | **0 berkas kode aplikasi** | Lulus (100% Bersih) |
| **Berkas Evaluasi (301–500 Baris Kode)** | Maks. 300 baris | **33 berkas kode aplikasi** | Perlu Dekomposisi Bertahap |
| **Fungsi / Metode Panjang (> 75 Baris)** | Ideal 30–50 baris | **65 metode** | Perlu Pemecahan Sub-metode |
| **Template Menumpuk Modal Inline** | 1 modal/file terpisah | **14 template Blade** | Perlu Ekstraksi `partials/` |

---

## 2. Berkas Controller API (6 Berkas > 300 Baris Kode)

Sesuai **Rule 1 (Single Responsibility)** dan **Rule 5 (Backend Separation)**, controller seharusnya hanya menangani HTTP request/response dan mendelegasikan business logic ke layer Service:

| Baris Kode | Baris Raw | Path Controller | Sub-domain yang Bercampur | Rekomendasi Pemisahan Target |
|:---:|:---:|---|---|---|
| **444** | 548 | `app/Http/Controllers/Api/V1/PengadaanApiController.php` | Katalog master barang, pengajuan PR ruangan, dan penerbitan PO vendor | Pecah menjadi `PengadaanPrApiController` (~180 baris) & `PengadaanPoApiController` (~220 baris) |
| **427** | 511 | `app/Http/Controllers/Api/V1/MaintenanceSpkApiController.php` | Disposisi SPK teknisi, riwayat SPK, dan pencatatan lembar kerja servis | Pecah menjadi `MaintenanceSpkApiController` (~210 baris) & `MaintenanceLembarKerjaApiController` (~200 baris) |
| **367** | 440 | `app/Http/Controllers/Api/V1/AsetPengalihanApiController.php` | Kueri inventaris asal, komoditas tersedia, dan format respons JSON | Ekstrak kueri format respons ke DTO/Resource `PengalihanAsetFormatter` (~110 baris) |
| **312** | 374 | `app/Http/Controllers/Api/V1/AuditForensikApiController.php` | Audit trail sistem, kepatuhan PO, dan analisis selisih opname | Pecah menjadi `AuditTrailApiController` (~140 baris) & `AuditKepatuhanApiController` (~170 baris) |
| **310** | 379 | `app/Http/Controllers/Api/V1/IpsrsApiController.php` | Jadwal pemeliharaan preventif dan katalog rekam medis sarana | Pecah menjadi `IpsrsJadwalApiController` (~160 baris) & `IpsrsRekamMedisApiController` (~150 baris) |
| **309** | 350 | `app/Http/Controllers/Api/V1/AsetKatalogApiController.php` | Scan QR fisik, rincian aset, dan riwayat sirkulasi | Pindahkan helper `getRiwayat*` ke Query Scope Model Aset (~230 baris) |

---

## 3. Komponen Livewire (11 Berkas > 300 Baris Kode)

Sesuai **Rule 4 (Component & UI Separation)**, hindari menumpuk seluruh tabel, filter, modal, form, dan query dalam satu kelas komponen:

| Baris Kode | Baris Raw | Path Komponen Livewire | Tanggung Jawab yang Menumpuk | Rekomendasi Ekstraksi Concerns / Trait |
|:---:|:---:|---|---|---|
| **488** | 578 | `app/Livewire/Aset/PeminjamanAsetIndex.php` | Antrean pinjam aktif, form perpanjangan, pembatalan, dan serah terima | Ekstrak form perpanjangan & serah terima ke `HasAksiPeminjaman` (~130 baris) |
| **458** | 537 | `app/Livewire/Aset/PengalihanAsetIndex.php` | Tabel multi-tab, otorisasi karu/gudang, dan BAST fisik | Ekstrak form & approval ke `HasAksiPengalihan` (~120 baris) |
| **425** | 495 | `app/Livewire/Persediaan/PengalihanBarangIndex.php` | Form mutasi BHP, alokasi batch gudang, dan otorisasi stok | Ekstrak wizard batch ke `HasPenyediaPengalihanBarang` (~170 baris) |
| **404** | 490 | `app/Livewire/User/PenggunaIndex.php` | Validasi form user, filter pencarian, dan modal reset password | Ekstrak form user ke Form Object / `HasFormPengguna` (~140 baris) |
| **403** | 490 | `app/Livewire/Distribusi/StokRuanganIndex.php` | Konsumsi BHP ruangan dan inventaris aset operasional | Pecah query ke `HasStokRuanganKatalog` (~150 baris) |
| **377** | 445 | `app/Livewire/Master/AsetIndex.php` | Register aset, filter gedung/rak, dan aksi CRUD | Ekstrak form validasi ke `HasFormAset` (~120 baris) |
| **353** | 442 | `app/Livewire/Pengadaan/PermintaanPengadaanIndex.php` | Antrean PR ruangan, keranjang draft barang, dan tolak PR | Ekstrak draft keranjang ke `HasDraftPr` (~130 baris) |
| **334** | 407 | `app/Livewire/Distribusi/PermintaanBarangIndex.php` | Antrean amprah dan keranjang multi-item ruangan | Ekstrak keranjang ke `HasDraftAmprah` (~120 baris) |
| **334** | 395 | `app/Livewire/Pengadaan/PenerimaanPoIndex.php` | Tabel riwayat RCV dan filter pencarian penerimaan | Ekstrak filter kueri ke query builder helper (~110 baris) |
| **321** | 393 | `app/Livewire/Persediaan/StockOpnameIndex.php` | Inisiasi sesi opname, blind count, dan tabel hasil opname | Ekstrak form blind count ke `HasFormOpname` (~120 baris) |
| **318** | 389 | `app/Livewire/Ipsrs/LembarKerjaPengerjaanIndex.php` | Antrean SPK dan rincian lembar kerja teknisi | Ekstrak kueri antrean ke `HasAntreanLembarKerja` (~100 baris) |

---

## 4. Template Blade Views yang Menumpuk Modal Inline (16 Berkas > 300 Baris Kode)

Sesuai **Rule 4 & Rule 6 (Frontend Separation)**, modal dialog dan tab formulir harus diekstrak ke sub-direktori `partials/<halaman>/` menggunakan `@include`:

| Baris Kode | Baris Raw | Path File Template Blade | Modal Dialog yang Masih Menumpuk Inline | Target Pemecahan Partials |
|:---:|:---:|---|---|---|
| **494** | 539 | `resources/views/livewire/user/pengguna-index.blade.php` | 2 modal: Form user baru/edit & Kredensial sementara | Ekstrak ke `partials/pengguna/` (~180 baris induk) |
| **485** | 545 | `resources/views/livewire/persediaan/pengalihan-barang-index.blade.php` | 3 modal: `modal-create`, `modal-detail`, `modal-approval` | Ekstrak ke `partials/pengalihan-barang/` (~190 baris induk) |
| **451** | 509 | `resources/views/livewire/audit/register-temuan-capa-index.blade.php` | 3 modal: `modal-buat-temuan`, `modal-tindak-lanjut`, `modal-evaluasi` | Ekstrak ke `partials/register-temuan/` (~170 baris induk) |
| **446** | 501 | `resources/views/livewire/distribusi/permintaan-barang-index.blade.php` | 2 modal: `modal-permintaan-form`, `modal-permintaan-detail` | Ekstrak ke `partials/permintaan-barang/` (~180 baris induk) |
| **434** | 498 | `resources/views/layouts/portal.blade.php` | Layout utama portal (sidebar 10 peran + navbar) | Ekstrak sidebar navigasi ke komponen partial |
| **417** | 467 | `resources/views/livewire/ipsrs/lembar-kerja-pengerjaan-index.blade.php` | 2 modal: `modal-lembar-kerja`, `modal-detail-spk-log` | Ekstrak ke `partials/lembar-kerja/` (~160 baris induk) |
| **411** | 466 | `resources/views/livewire/ipsrs/daftar-spk-perbaikan-index.blade.php` | 2 modal: `modal-terbitkan-spk`, `modal-detail-spk` | Ekstrak ke `partials/daftar-spk/` (~160 baris induk) |
| **406** | 472 | `resources/views/livewire/aset/pengembalian-peminjaman-index.blade.php` | 2 modal: `modal-inspeksi-pengembalian`, `modal-detail` | Ekstrak ke `partials/pengembalian/` (~170 baris induk) |
| **404** | 440 | `resources/views/livewire/pengadaan/realisasi-anggaran-index.blade.php` | 1 modal: `modal-drilldown-unit` (menumpuk tab sparepart) | Ekstrak ke `partials/realisasi-anggaran/` (~180 baris induk) |
| **391** | 444 | `resources/views/livewire/persediaan/stock-opname-index.blade.php` | 4 modal: `modal-inisiasi`, `modal-hitung-fisik`, `modal-qr`, `modal-detail` | Ekstrak ke `partials/stock-opname/` (~160 baris induk) |
| **385** | 428 | `resources/views/livewire/distribusi/picking-distribusi-index.blade.php` | 2 modal: Modal konfirmasi picking FEFO & rincian SPPB | Ekstrak ke `partials/picking/` (~170 baris induk) |
| **376** | 428 | `resources/views/livewire/ipsrs/lapor-servis-index.blade.php` | 2 modal: `modal-lapor-servis` & `modal-detail-tiket` | Ekstrak ke `partials/lapor-servis/` (~150 baris induk) |
| **363** | 408 | `resources/views/livewire/pengadaan/permintaan-pengadaan-index.blade.php` | 3 modal: `modal-pr-form`, `modal-pr-detail`, `modal-reject` | Ekstrak ke `partials/permintaan-pengadaan/` (~160 baris induk) |
| **346** | 377 | `resources/views/livewire/distribusi/distribusi-approval-index.blade.php` | 2 modal: `modal-review-kuota`, `modal-detail-distribusi` | Ekstrak ke `partials/distribusi-approval/` (~160 baris induk) |
| **342** | 392 | `resources/views/livewire/distribusi/stok-ruangan-index.blade.php` | Menumpuk 2 tab besar (Tab BHP dan Tab Register Aset) | Ekstrak tab BHP & Aset ke partials terpisah |
| **303** | 333 | `resources/views/livewire/aset/approval-peminjaman-index.blade.php` | 1 modal: Modal rincian dokumen & aset pinjam | Ekstrak modal ke `partials/approval-peminjaman/` (~180 baris induk) |

---

## 5. Daftar Fungsi & Metode Panjang (> 75 Baris Kode)

Sesuai **Rule 3 (Function and Class Size)**, metode ideal berukuran maksimal 30–50 baris. Metode di atas 75 baris berikut menggabungkan kueri multi-tahap, kalkulasi, atau mutasi kartu stok yang perlu dipecah:

| Panjang | Nama Metode / Fungsi | Lokasi Berkas | Masalah Tanggung Jawab Campuran |
|:---:|---|---|---|
| **159 baris** | `render()` | `app/Livewire/Aset/PengalihanAsetIndex.php:378` | Kueri 3 tab terpisah + pagination dalam 1 fungsi render |
| **157 baris** | `eksekusiPickingFefo()` | `app/Services/Distribusi/DistribusiService.php:25` | Alokasi FEFO + pemotongan batch + pembuatan SPPB |
| **153 baris** | `render()` | `app/Livewire/Persediaan/PengalihanBarangIndex.php:342` | Kueri tab aktif, otorisasi, riwayat dalam 1 fungsi render |
| **143 baris** | `prosesPenerimaan()` | `app/Services/Pengadaan/PenerimaanPoService.php:24` | Normalisasi item + kartu stok + update status PO & PR |
| **133 baris** | `monitoringFefo()` | `app/Http/Controllers/Api/V1/PersediaanMonitoringApiController.php:20` | Kueri kedaluwarsa 3 batch + sortir stok kritis + format JSON |
| **132 baris** | `render()` | `app/Livewire/Distribusi/StokRuanganIndex.php:358` | Menggabungkan kueri persediaan BHP dan register aset ruangan |
| **127 baris** | `eksekusiLogPengerjaan()` | `app/Http/Controllers/Api/V1/MaintenanceSpkApiController.php:383` | Log teknisi + alokasi suku cadang FEFO + update status aset |
| **119 baris** | `simpanHitungFisik()` | `app/Http/Controllers/Api/V1/PersediaanOpnameApiController.php:169` | Validasi blind count + hitung selisih + mutasi detail opname |
| **118 baris** | `getDomains()` | `app/Support/Rbac/PagePermissionRegistry.php:13` | Array statis deklaratif matriks perizinan (dikecualikan secara logis) |
| **118 baris** | `eksekusiSimpanLembarKerja()` | `app/Livewire/Ipsrs/LembarKerjaPengerjaanIndex.php:185` | Log pengerjaan + pemotongan batch FEFO suku cadang |
| **116 baris** | `pending()` | `app/Http/Controllers/Api/V1/ApprovalController.php:18` | Kueri antrean PR + antrean Amprah digabung |
| **115 baris** | `buildPetugasUnit()` | `app/Services/Dashboard/RuanganDashboardBuilder.php:141` | Kueri metrik 4 sub-domain staf ruangan |
| **115 baris** | `buildKaru()` | `app/Services/Dashboard/RuanganDashboardBuilder.php:22` | Kueri antrean 4 sub-domain persetujuan kepala ruangan |
| **109 baris** | `savePinjam()` | `app/Livewire/Aset/PeminjamanAsetIndex.php:236` | Validasi input + alokasi aset fisik + cek maintenance |
| **107 baris** | `ajukanPengalihan()` | `app/Services/Persediaan/PengalihanBarangService.php:20` | Validasi fasilitas + reservasi stok batch gudang/ruangan |
| **107 baris** | `prosesPengembalian()` | `app/Services/Aset/PeminjamanAsetService.php:114` | Validasi fisik + mutasi status aset + tiket maintenance |
| **105 baris** | `terbitkanPo()` | `app/Http/Controllers/Api/V1/PengadaanApiController.php:401` | Alokasi nomor PO + kalkulasi biaya + update status PR |
| **105 baris** | `eksekusiPenerimaanPo()` | `app/Http/Controllers/Api/V1/GudangPenerimaanPoApiController.php:180` | Penerimaan barang + pembuatan batch baru + mutasi kartu stok |
| **104 baris** | `savePenerimaan()` | `app/Livewire/Pengadaan/PenerimaanPoIndex.php:148` | Validasi baris barang penerimaan sebelum delegasi service |
| **104 baris** | `detailBarang()` | `app/Http/Controllers/Api/V1/MasterApiController.php:97` | Master barang + batch gudang + stok ruangan + valuasi |
| **103 baris** | `prosesApprovePr()` | `app/Http/Controllers/Api/V1/ApprovalController.php:144` | Validasi scope unit + otorisasi multi-role + update detail |
| **100 baris** | `buildKepalaGudang()` | `app/Services/Dashboard/GudangDashboardBuilder.php:106` | Kueri metrik & aktivitas kepala gudang/admin logistik |
| **99 baris** | `saveHitungFisik()` | `app/Livewire/Persediaan/StockOpnameIndex.php:171` | Input hitung fisik + kalkulasi selisih opname |
| **99 baris** | `detailRak()` | `app/Http/Controllers/Api/V1/GudangTataLetakApiController.php:80` | Detail rak + batch tersimpan + kapasitas okupansi |
| **98 baris** | `buildDefault()` | `app/Services/Dashboard/PengadaanDashboardBuilder.php:21` | Kueri antrean PR, Amprah, PO, Pinjam default |
| **98 baris** | `daftarPengalihanBarang()` | `app/Http/Controllers/Api/V1/GudangPengalihanBarangApiController.php:23` | Kueri daftar pengalihan barang multi-tab |
| **98 baris** | `simpanPeminjaman()` | `app/Http/Controllers/Api/V1/AsetPeminjamanApiController.php:108` | Validasi unit fisik aset + pembuatan dokumen pinjam |
| **98 baris** | `scanQr()` | `app/Http/Controllers/Api/V1/AsetKatalogApiController.php:22` | Parsing QR fisik + pencarian unit + garansi |
| **98 baris** | `realisasi()` | `app/Http/Controllers/Api/V1/AnggaranApiController.php:20` | Realisasi anggaran unit + breakdown beban distribusi |
| **97 baris** | `simpanPenerimaanPo()` | `app/Http/Controllers/Api/V1/GudangPenerimaanPoApiController.php:82` | Validasi HTTP penerimaan PO gudang |

---

## 6. Berkas yang Dikecualikan Secara Sah (Rule 2)

Berkas berikut berukuran > 300 baris namun **dikecualikan dari kewajiban pemisahan** berdasarkan kriteria teknis Rule 2:

1. **Dokumentasi & Perancangan Proyek (`docs/`)**:
   - `docs/prd.md` (1.162 baris kode / 1.389 raw)
   - `docs/prompt.md` (592 baris kode / 716 raw)
   - `docs/UAT.md` (195 baris kode / 417 raw)
   *Alasan:* Berkas teks dokumentasi spesifikasi produk dan rancangan sistem; bukan kode program yang dieksekusi mesin.

2. **Database Dummy Seeders (`database/seeders/`)**:
   - `PengadaanDummySeeder.php` (665 baris)
   - `IpsrsDummySeeder.php` (527 baris)
   - `DistribusiDummySeeder.php` (464 baris)
   - `MasterDataSeeder.php` (395 baris)
   - `PeminjamanAsetDummySeeder.php` (351 baris)
   - `PersediaanDummySeeder.php` (321 baris)
   *Alasan:* Berkas data statis deklaratif untuk inisialisasi lingkungan pengujian. Pemecahan ke banyak kelas kecil justru mengaburkan kronologi relasi foreign key.

3. **Pengujian Integrasi / Feature Tests (`tests/Feature/`)**:
   - `StokRuanganTest.php` (371 baris)
   - `PeminjamanAsetTest.php` (349 baris)
   - `PenerimaanPoTest.php` (336 baris)
   - `DistribusiWebAuthorizationTest.php` (327 baris)
   - `WebAuthorizationConsolidationTest.php` (314 baris)
   - `PengalihanAsetIndexTest.php` (312 baris)
   - `AlurStokKritisTest.php` (302 baris)
   *Alasan:* Berkas uji integrasi skenario end-to-end yang menguji rantai lifecycle transaksi secara utuh.

---

## 7. Rencana Pengerjaan & Verifikasi Bertahap

Bila perbaikan hendak dijalankan pada iterasi berikutnya, gunakan alur bertahap berikut:

1. **Gelombang A (Blade Partials - 14 berkas)**:
   - Ekstrak modal-modal pada tabel Bagian 4 ke `resources/views/livewire/<modul>/partials/<halaman>/`.
   - Verifikasi: `php artisan view:cache` & Livewire test suite.
2. **Gelombang B (Controller API - 6 berkas)**:
   - Pecah controller API menjadi controller sub-domain (tanpa mengubah nama/URI route pada `routes/api.php`).
   - Verifikasi: `php artisan route:list` & API Feature tests.
3. **Gelombang C (Livewire Concerns - 11 berkas)**:
   - Ekstrak form state dan query wizard ke trait `app/Livewire/<Modul>/Concerns/`.
   - Verifikasi: Livewire integration tests.
4. **Gelombang D (Metode > 75 baris)**:
   - Ekstrak validasi dan transaksi ke sub-metode atomik privat.
   - Verifikasi: Unit & Feature tests.

---
*Dokumen ini dibuat otomatis sebagai inventaris resmi backlog refaktorisasi arsitektur SIMLOG RS.*
