<?php

namespace Database\Seeders;

use App\Models\Gudang;
use App\Models\KategoriBarang;
use App\Models\LokasiGudang;
use App\Models\MerekBarang;
use App\Models\Pemasok;
use App\Models\SatuanBarang;
use App\Models\Barang;
use App\Models\Aset;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Menanam data master kategori, merek, satuan, pemasok, lokasi rak/bin,
     * katalog komoditas barang (90 jenis), dan register aset fisik (40 unit).
     */
    public function run(): void
    {
        // 1. 30 Kategori Barang
        $kategoriList = [
            ['kode' => 'KAT-ATK', 'nama' => 'Alat Tulis Kantor & Cetakan', 'deskripsi' => 'Perlengkapan alat tulis kantor, kertas rekam medis, map, dan formulir administrasi RS.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-RT', 'nama' => 'Rumah Tangga & Sanitasi', 'deskripsi' => 'Perlengkapan kebersihan ruangan, deterjen medis, sabun cuci tangan, dan kantong sampah.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-SPK', 'nama' => 'Suku Cadang & Sparepart IPSRS', 'deskripsi' => 'Komponen mekanik, suku cadang alat medis, sensor, filter O2, dan aksesori fasilitas.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-ELK', 'nama' => 'Elektronik & Perangkat Komputer', 'deskripsi' => 'Toner printer, kabel jaringan, UPS, peripheral komputer, dan perangkat IT pendukung.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-LIN', 'nama' => 'Linen & Tekstil Fasilitas', 'deskripsi' => 'Sprei bed pasien, selimut rawat inap, sarung bantal, dan tirai pembatas tindakan.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-DIS', 'nama' => 'Disinfektan & Antiseptik Medis', 'deskripsi' => 'Cairan sterilisasi lantai, antiseptik handrub bedah, dan alkohol swab medis.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-APD', 'nama' => 'Alat Pelindung Diri (APD)', 'deskripsi' => 'Masker bedah, sarung tangan steril/non-steril, gaun operasi, dan pelindung mata.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-BHP', 'nama' => 'Bahan Habis Pakai Perawatan Luka', 'deskripsi' => 'Kasa gulung, perban elastis, plester medis, kassa steril, dan kapas alkohol.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-GAS', 'nama' => 'Gas Medis & Aksesori Penunjang', 'deskripsi' => 'Regulator O2, flowmeter tabung oksigen, nasal kanul, dan sungkup oksigen.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-CSSD', 'nama' => 'Sarana CSSD & Sterilisasi', 'deskripsi' => 'Pouch sterilisasi indikator, pita autoklaf, dan cairan enzimatik instrumen.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-LAB', 'nama' => 'Bahan Penunjang Laboratorium', 'deskripsi' => 'Tabung vakum darah, pot urine, pipet plastik sekali pakai, dan kaca objek.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-RAD', 'nama' => 'Bahan Radiologi Non-Zat Kimia', 'deskripsi' => 'Amplop rontgen film, grid pelindung, dan kertas printer USG termal.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-GIZI', 'nama' => 'Perlengkapan Gizi & Dapur RS', 'deskripsi' => 'Plastik wrap makanan pasien, tempat makan bersekat isolasi, dan peralatan saji.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-FARM', 'nama' => 'Bahan Pengemas & Penunjang Farmasi', 'deskripsi' => 'Plastik klip obat, botol sirup kosong, mortir puyer, dan kertas puyer.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-GIGI', 'nama' => 'Bahan Gigi & Mulut Habis Pakai', 'deskripsi' => 'Bib pasien dental, saliva ejector plastik, dan kapas rol dokter gigi.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-OK', 'nama' => 'Perlengkapan Kamar Bedah Non-Steril', 'deskripsi' => 'Surgical cap, shoe cover, sprei disposabel meja operasi, dan matras gel.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-HD', 'nama' => 'Sarana Penunjang Hemodialisa', 'deskripsi' => 'Selang infus dialisis, garam briket water treatment, dan filter karbon HD.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-IPAL', 'nama' => 'Sarana Sanitasi Lingkungan & IPAL', 'deskripsi' => 'Kaporit tablet kolam aerasi, tawas penjernih air, dan strip uji pH limbah.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-K3', 'nama' => 'Keamanan Kerja & K3RS', 'deskripsi' => 'Rambu K3, apar portable, safety cone licin, dan helm evakuasi bencana.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-JEN', 'nama' => 'Sarana Perawatan Kamar Jenazah', 'deskripsi' => 'Kantong jenazah tebal anti-bocor, kain mori, dan desinfektan meja otopsi.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-HVAC', 'nama' => 'Perawatan Pendingin Ruang & HVAC', 'deskripsi' => 'Filter AC split, freon pendingin, kapasitor kompresor, dan thermistor suhu.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-PLUMB', 'nama' => 'Perpipaan, Sanitair & Plumbing', 'deskripsi' => 'Kran air siku bedah, pipa PVC medis, seal karet kran, dan pelampung tandon.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-ELEC', 'nama' => 'Kelistrikan, Genset & Daya RS', 'deskripsi' => 'Lampu LED medis hemat energi, saklar otomatis, MCB listrik, dan aki genset.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-MEK', 'nama' => 'Komponen Mekanik & Bengkel Logistik', 'deskripsi' => 'Roda bed pasien, oli pelumas engsel pintu, baut stainless, dan bearing roda.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-TRANS', 'nama' => 'Alat Evakuasi & Transportasi Pasien', 'deskripsi' => 'Brankar dorong, kursi roda transfer, scoop stretcher, dan sabuk pengaman.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-MON', 'nama' => 'Instrumen Pemantau Tanda Vital', 'deskripsi' => 'Patient monitor modular, pulse oximeter meja, dan tensimeter digital mobile.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-RES', 'nama' => 'Instrumen Resusitasi & Kegawatdaruratan', 'deskripsi' => 'Defibrillator biphasic, suction pump transport, dan laringoskop set.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-DIAG', 'nama' => 'Peralatan Diagnostik Ruangan', 'deskripsi' => 'EKG 12 lead portable, USG mobile, dan otoskop ophtalmoskop dinding.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-TERAP', 'nama' => 'Alat Terapi Cairan & Infus Presisi', 'deskripsi' => 'Infusion pump volume mikro, syringe pump neonatal, dan blood warmer.', 'status' => 'AKTIF'],
            ['kode' => 'KAT-FUR', 'nama' => 'Mebel & Perlengkapan Kamar Perawatan', 'deskripsi' => 'Bedside cabinet pasien, overbed table dorong, kursi penunggu, dan tiang infus.', 'status' => 'AKTIF'],
        ];

        foreach ($kategoriList as $kat) {
            KategoriBarang::firstOrCreate(['kode' => $kat['kode']], $kat);
        }

        // 2. 30 Merek Barang
        $merekList = [
            ['kode' => 'MRK-SIDU', 'nama' => 'Sinar Dunia (SiDU)', 'status' => 'AKTIF'],
            ['kode' => 'MRK-YURI', 'nama' => 'Yuri Medika', 'status' => 'AKTIF'],
            ['kode' => 'MRK-HMLT', 'nama' => 'Hamilton Medical', 'status' => 'AKTIF'],
            ['kode' => 'MRK-EPSN', 'nama' => 'Epson', 'status' => 'AKTIF'],
            ['kode' => 'MRK-PARAM', 'nama' => 'Paramount Bed', 'status' => 'AKTIF'],
            ['kode' => 'MRK-PONE', 'nama' => 'PaperOne', 'status' => 'AKTIF'],
            ['kode' => 'MRK-DTTL', 'nama' => 'Dettol Hospital', 'status' => 'AKTIF'],
            ['kode' => 'MRK-MNDR', 'nama' => 'Mindray Healthcare', 'status' => 'AKTIF'],
            ['kode' => 'MRK-GE', 'nama' => 'GE Healthcare', 'status' => 'AKTIF'],
            ['kode' => 'MRK-PHLP', 'nama' => 'Philips Medical', 'status' => 'AKTIF'],
            ['kode' => 'MRK-CANON', 'nama' => 'Canon', 'status' => 'AKTIF'],
            ['kode' => 'MRK-HP', 'nama' => 'Hewlett-Packard (HP)', 'status' => 'AKTIF'],
            ['kode' => 'MRK-TRMO', 'nama' => 'Terumo Corporation', 'status' => 'AKTIF'],
            ['kode' => 'MRK-ONEM', 'nama' => 'OneMed Health', 'status' => 'AKTIF'],
            ['kode' => 'MRK-3M', 'nama' => '3M Healthcare', 'status' => 'AKTIF'],
            ['kode' => 'MRK-BBRAUN', 'nama' => 'B. Braun Medical', 'status' => 'AKTIF'],
            ['kode' => 'MRK-KMBR', 'nama' => 'Kimberly-Clark Professional', 'status' => 'AKTIF'],
            ['kode' => 'MRK-DAIKIN', 'nama' => 'Daikin Industries', 'status' => 'AKTIF'],
            ['kode' => 'MRK-PANA', 'nama' => 'Panasonic Eco', 'status' => 'AKTIF'],
            ['kode' => 'MRK-SCHN', 'nama' => 'Schneider Electric', 'status' => 'AKTIF'],
            ['kode' => 'MRK-PLGT', 'nama' => 'Philips Lighting', 'status' => 'AKTIF'],
            ['kode' => 'MRK-MSPN', 'nama' => 'Maspion Industrial', 'status' => 'AKTIF'],
            ['kode' => 'MRK-SHARP', 'nama' => 'Sharp Corporation', 'status' => 'AKTIF'],
            ['kode' => 'MRK-LION', 'nama' => 'Lion Metal Works', 'status' => 'AKTIF'],
            ['kode' => 'MRK-JYKO', 'nama' => 'Joyko Stationeries', 'status' => 'AKTIF'],
            ['kode' => 'MRK-KNKO', 'nama' => 'Kenko Precision', 'status' => 'AKTIF'],
            ['kode' => 'MRK-ZBRA', 'nama' => 'Zebra Medical Printers', 'status' => 'AKTIF'],
            ['kode' => 'MRK-STBL', 'nama' => 'Stabilo Boss', 'status' => 'AKTIF'],
            ['kode' => 'MRK-DRGR', 'nama' => 'Drägerwerk Medical', 'status' => 'AKTIF'],
            ['kode' => 'MRK-NHON', 'nama' => 'Nihon Kohden', 'status' => 'AKTIF'],
        ];

        foreach ($merekList as $mrk) {
            MerekBarang::firstOrCreate(['kode' => $mrk['kode']], $mrk);
        }

        // 3. 30 Satuan Barang Standar
        $satuanList = [
            ['kode' => 'SAT-PCS', 'nama' => 'Pieces / Buah', 'simbol' => 'Pcs', 'status' => 'AKTIF'],
            ['kode' => 'SAT-BOX', 'nama' => 'Box / Kotak', 'simbol' => 'Box', 'status' => 'AKTIF'],
            ['kode' => 'SAT-RIM', 'nama' => 'Rim (500 Lembar)', 'simbol' => 'Rim', 'status' => 'AKTIF'],
            ['kode' => 'SAT-BTL', 'nama' => 'Botol Cairan', 'simbol' => 'Btl', 'status' => 'AKTIF'],
            ['kode' => 'SAT-ROL', 'nama' => 'Gulung / Roll', 'simbol' => 'Rol', 'status' => 'AKTIF'],
            ['kode' => 'SAT-SET', 'nama' => 'Set Lengkap', 'simbol' => 'Set', 'status' => 'AKTIF'],
            ['kode' => 'SAT-PCK', 'nama' => 'Pack / Bungkus', 'simbol' => 'Pck', 'status' => 'AKTIF'],
            ['kode' => 'SAT-KRT', 'nama' => 'Karton / Kardus', 'simbol' => 'Krt', 'status' => 'AKTIF'],
            ['kode' => 'SAT-TUB', 'nama' => 'Tube / Salep', 'simbol' => 'Tub', 'status' => 'AKTIF'],
            ['kode' => 'SAT-SCH', 'nama' => 'Sachet / Kantong', 'simbol' => 'Sch', 'status' => 'AKTIF'],
            ['kode' => 'SAT-LBR', 'nama' => 'Lembar', 'simbol' => 'Lbr', 'status' => 'AKTIF'],
            ['kode' => 'SAT-GLN', 'nama' => 'Galon (5 Liter / 19 Liter)', 'simbol' => 'Gln', 'status' => 'AKTIF'],
            ['kode' => 'SAT-CAN', 'nama' => 'Can / Kaleng Semprot', 'simbol' => 'Can', 'status' => 'AKTIF'],
            ['kode' => 'SAT-MTR', 'nama' => 'Meter Panjang', 'simbol' => 'Mtr', 'status' => 'AKTIF'],
            ['kode' => 'SAT-UNT', 'nama' => 'Unit Perangkat Fisik', 'simbol' => 'Unt', 'status' => 'AKTIF'],
            ['kode' => 'SAT-PSG', 'nama' => 'Pasang', 'simbol' => 'Psg', 'status' => 'AKTIF'],
            ['kode' => 'SAT-DZN', 'nama' => 'Lusin (12 Pcs)', 'simbol' => 'Dzn', 'status' => 'AKTIF'],
            ['kode' => 'SAT-DRM', 'nama' => 'Drum (200 Liter)', 'simbol' => 'Drm', 'status' => 'AKTIF'],
            ['kode' => 'SAT-AMP', 'nama' => 'Ampul Kaca', 'simbol' => 'Amp', 'status' => 'AKTIF'],
            ['kode' => 'SAT-VIA', 'nama' => 'Vial Karet', 'simbol' => 'Via', 'status' => 'AKTIF'],
            ['kode' => 'SAT-STR', 'nama' => 'Strip Tablet', 'simbol' => 'Str', 'status' => 'AKTIF'],
            ['kode' => 'SAT-TBG', 'nama' => 'Tabung Silinder Gas', 'simbol' => 'Tbg', 'status' => 'AKTIF'],
            ['kode' => 'SAT-KG', 'nama' => 'Kilogram', 'simbol' => 'Kg', 'status' => 'AKTIF'],
            ['kode' => 'SAT-LTR', 'nama' => 'Liter Cairan', 'simbol' => 'Ltr', 'status' => 'AKTIF'],
            ['kode' => 'SAT-BTG', 'nama' => 'Batang Lonjor', 'simbol' => 'Btg', 'status' => 'AKTIF'],
            ['kode' => 'SAT-BKS', 'nama' => 'Bungkus Kertas', 'simbol' => 'Bks', 'status' => 'AKTIF'],
            ['kode' => 'SAT-PAL', 'nama' => 'Pail Ember (20 Liter)', 'simbol' => 'Pal', 'status' => 'AKTIF'],
            ['kode' => 'SAT-KIT', 'nama' => 'Kit Uji Pengujian', 'simbol' => 'Kit', 'status' => 'AKTIF'],
            ['kode' => 'SAT-DUS', 'nama' => 'Dus Sedang', 'simbol' => 'Dus', 'status' => 'AKTIF'],
            ['kode' => 'SAT-BND', 'nama' => 'Bundel Arsip Formulir', 'simbol' => 'Bnd', 'status' => 'AKTIF'],
        ];

        foreach ($satuanList as $sat) {
            SatuanBarang::firstOrCreate(['kode' => $sat['kode']], $sat);
        }

        // 4. 30 Pemasok / Rekanan Vendor RS
        $pemasokList = [
            ['kode' => 'VND-2026-001', 'nama' => 'PT Medika Sarana Abadi', 'npwp' => '01.234.567.8-012.000', 'alamat' => 'Jl. Jenderal Sudirman Kav. 45-46', 'kota' => 'Jakarta Selatan', 'no_telepon' => '021-5551234', 'email' => 'sales@medikasarana.co.id', 'nama_kontak' => 'Hendro Wibowo, S.T.', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-002', 'nama' => 'CV Graha Logistik Medis', 'npwp' => '02.345.678.9-023.000', 'alamat' => 'Jl. Gatot Subroto No. 88', 'kota' => 'Bandung', 'no_telepon' => '022-7772345', 'email' => 'kontak@grahalogistik.com', 'nama_kontak' => 'Anita Rahmawati, S.Farm', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-003', 'nama' => 'PT Global Sarana Tehnik', 'npwp' => '03.456.789.0-034.000', 'alamat' => 'Jl. Pemuda No. 12', 'kota' => 'Semarang', 'no_telepon' => '024-8883456', 'email' => 'info@globalsarana.id', 'nama_kontak' => 'Rian Firmansyah, A.Md.T.', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-004', 'nama' => 'PT Sumber Alam Kertasindo', 'npwp' => '04.567.890.1-045.000', 'alamat' => 'Kawasan Industri Rungkut Blok E-20', 'kota' => 'Surabaya', 'no_telepon' => '031-9994567', 'email' => 'order@sumberkertas.co.id', 'nama_kontak' => 'David Santoso', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-005', 'nama' => 'CV Sinar Chemindo Higienis', 'npwp' => '05.678.901.2-056.000', 'alamat' => 'Jl. Kaliurang Km. 8, Sinduharjo', 'kota' => 'Sleman (D.I. Yogyakarta)', 'no_telepon' => '0274-6665678', 'email' => 'chemindo@sinarchem.com', 'nama_kontak' => 'Sri Wahyuni, S.Si', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-006', 'nama' => 'PT Mindray Medical Indonesia', 'npwp' => '06.789.012.3-067.000', 'alamat' => 'Treasury Tower Lt. 18, SCBD', 'kota' => 'Jakarta Selatan', 'no_telepon' => '021-5256789', 'email' => 'service.id@mindray.com', 'nama_kontak' => 'Budi Prasetyo', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-007', 'nama' => 'PT Terumo Indonesia', 'npwp' => '07.890.123.4-078.000', 'alamat' => 'Wisma Keiai Lt. 10, Jl. Sudirman', 'kota' => 'Jakarta Pusat', 'no_telepon' => '021-5723456', 'email' => 'customer.care@terumo.co.id', 'nama_kontak' => 'dr. Maya Kusuma', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-008', 'nama' => 'CV Karya Medika Utama', 'npwp' => '08.901.234.5-089.000', 'alamat' => 'Jl. Raya Darmo No. 45', 'kota' => 'Surabaya', 'no_telepon' => '031-5678901', 'email' => 'karyamedika@yahoo.com', 'nama_kontak' => 'Agus Budiman', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-009', 'nama' => 'PT B. Braun Medical Devices', 'npwp' => '09.012.345.6-090.000', 'alamat' => 'Kawasan Industri GIIC Cikarang', 'kota' => 'Bekasi', 'no_telepon' => '021-8991234', 'email' => 'order.id@bbraun.com', 'nama_kontak' => 'Sisca Handayani', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-010', 'nama' => 'PT Aneka Gas Industri (Samator)', 'npwp' => '10.123.456.7-101.000', 'alamat' => 'Jl. Raya Kalimalang No. 19', 'kota' => 'Jakarta Timur', 'no_telepon' => '021-8612345', 'email' => 'medgas@samator.com', 'nama_kontak' => 'Eko Sulistyo', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-011', 'nama' => 'CV Prima Tehnik HVAC', 'npwp' => '11.234.567.8-112.000', 'alamat' => 'Jl. Magelang Km. 5, Kutu Dukuh', 'kota' => 'Sleman', 'no_telepon' => '0274-567123', 'email' => 'primatehnik@gmail.com', 'nama_kontak' => 'Bambang Irawan', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-012', 'nama' => 'PT Nusantara Linen Sejahtera', 'npwp' => '12.345.678.9-123.000', 'alamat' => 'Kawasan Industri Rancaekek', 'kota' => 'Bandung', 'no_telepon' => '022-7894561', 'email' => 'marketing@nusantaralinen.com', 'nama_kontak' => 'Rina Sugiarto', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-013', 'nama' => 'PT Surya Multi Elektronik', 'npwp' => '13.456.789.0-134.000', 'alamat' => 'Harco Mangga Dua Blok B-12', 'kota' => 'Jakarta Pusat', 'no_telepon' => '021-6123456', 'email' => 'surya.elektronik@cbn.net.id', 'nama_kontak' => 'Tommy Setiawan', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-014', 'nama' => 'CV Mandiri Sanitasi Husada', 'npwp' => '14.567.890.1-145.000', 'alamat' => 'Jl. Veteran No. 20', 'kota' => 'Solo (Surakarta)', 'no_telepon' => '0271-654321', 'email' => 'mandirisanitasi@solo.co.id', 'nama_kontak' => 'Hj. Mardiyah', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-015', 'nama' => 'PT Paramount Bed Indonesia', 'npwp' => '15.678.901.2-156.000', 'alamat' => 'MM2100 Industrial Town Cikarang', 'kota' => 'Bekasi', 'no_telepon' => '021-8987654', 'email' => 'sales@paramount.co.id', 'nama_kontak' => 'Katsuhiko Tanaka', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-016', 'nama' => 'PT OneMed HealthCare', 'npwp' => '16.789.012.3-167.000', 'alamat' => 'Jl. Raya Bypass Krian Km. 32', 'kota' => 'Sidoarjo', 'no_telepon' => '031-8971122', 'email' => 'distribusi@onemed.co.id', 'nama_kontak' => 'Ir. Rachmat Hidayat', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-017', 'nama' => 'PT 3M Indonesia', 'npwp' => '17.890.123.4-178.000', 'alamat' => 'Perkantoran Hijau Arkadia Tower F', 'kota' => 'Jakarta Selatan', 'no_telepon' => '021-7893333', 'email' => 'healthcare.id@3m.com', 'nama_kontak' => 'Denny Prabowo', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-018', 'nama' => 'PT Daikin Airconditioning Indonesia', 'npwp' => '18.901.234.5-189.000', 'alamat' => 'Wisma Keiai Lt. 18, Jl. Sudirman', 'kota' => 'Jakarta Pusat', 'no_telepon' => '021-5724321', 'email' => 'commercial.sales@daikin.co.id', 'nama_kontak' => 'Ferry Hermawan', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-019', 'nama' => 'CV Indo Elektro Medika', 'npwp' => '19.012.345.6-190.000', 'alamat' => 'Jl. Brigjen Katamso No. 70', 'kota' => 'Medan', 'no_telepon' => '061-4567890', 'email' => 'indomedika@medan.net', 'nama_kontak' => 'dr. Zulkifli Lubis', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-020', 'nama' => 'PT Schneider Electric Indonesia', 'npwp' => '20.123.456.7-201.000', 'alamat' => 'Ventura Building Lt. 7, TB Simatupang', 'kota' => 'Jakarta Selatan', 'no_telepon' => '021-7504444', 'email' => 'support.id@se.com', 'nama_kontak' => 'Gunawan Susanto', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-021', 'nama' => 'PT Philips Indonesia Commercial', 'npwp' => '21.234.567.8-212.000', 'alamat' => 'Gedung Philips Jl. Buncit Raya No. 99', 'kota' => 'Jakarta Selatan', 'no_telepon' => '021-7940040', 'email' => 'philips.healthcare.id@philips.com', 'nama_kontak' => 'Dr. Andrew Wijaya', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-022', 'nama' => 'CV Cahaya Mitra Gas', 'npwp' => '22.345.678.9-223.000', 'alamat' => 'Jl. Solo Km. 11, Kalasan', 'kota' => 'Sleman', 'no_telepon' => '0274-496789', 'email' => 'cahayamitragas@gmail.com', 'nama_kontak' => 'H. Suryadi', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-023', 'nama' => 'PT Asahi Medika Farma', 'npwp' => '23.456.789.0-234.000', 'alamat' => 'Jl. Dr. Cipto Mangunkusumo No. 34', 'kota' => 'Semarang', 'no_telepon' => '024-3567890', 'email' => 'asahi.farma@asahi.co.id', 'nama_kontak' => 'Lestari Pujiastuti', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-024', 'nama' => 'PT Kimberly Clark Indonesia', 'npwp' => '24.567.890.1-245.000', 'alamat' => 'Menara Astra Lt. 29, Jl. Sudirman', 'kota' => 'Jakarta Pusat', 'no_telepon' => '021-5088900', 'email' => 'kcp.indonesia@kcc.com', 'nama_kontak' => 'Yohanes Hartanto', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-025', 'nama' => 'CV Mega Utama Perdana', 'npwp' => '25.678.901.2-256.000', 'alamat' => 'Jl. Pahlawan No. 8', 'kota' => 'Yogyakarta', 'no_telepon' => '0274-512345', 'email' => 'megautama@ymail.com', 'nama_kontak' => 'Aryo Wibisono', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-026', 'nama' => 'PT Nihon Kohden Indonesia', 'npwp' => '26.789.012.3-267.000', 'alamat' => 'Midplaza 2 Lt. 15, Jl. Sudirman', 'kota' => 'Jakarta Pusat', 'no_telepon' => '021-5707788', 'email' => 'service@nihonkohden.co.id', 'nama_kontak' => 'Kenjiro Sato', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-027', 'nama' => 'PT Duta Sarana Medika', 'npwp' => '27.890.123.4-278.000', 'alamat' => 'Jl. Diponegoro No. 101', 'kota' => 'Denpasar (Bali)', 'no_telepon' => '0361-223456', 'email' => 'dutamedika@bali.net', 'nama_kontak' => 'I Ketut Suartana', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-028', 'nama' => 'PT Lion Metal Works Tbk', 'npwp' => '28.901.234.5-289.000', 'alamat' => 'Jl. Raya Bekasi Km. 24, Cakung', 'kota' => 'Jakarta Timur', 'no_telepon' => '021-4600778', 'email' => 'hospital.furniture@lionmetal.co.id', 'nama_kontak' => 'Stefanus Gunawan', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-029', 'nama' => 'CV Bio Kimia Instrument', 'npwp' => '29.012.345.6-290.000', 'alamat' => 'Jl. Kaliurang Km. 5 No. 14', 'kota' => 'Yogyakarta', 'no_telepon' => '0274-589012', 'email' => 'biokimia@biokimia.com', 'nama_kontak' => 'Dra. Endang Sulistyowati', 'status' => 'AKTIF'],
            ['kode' => 'VND-2026-030', 'nama' => 'PT Sarana Prima Oksigen', 'npwp' => '30.123.456.7-301.000', 'alamat' => 'Kawasan Industri Pulogadung Blok III', 'kota' => 'Jakarta Timur', 'no_telepon' => '021-4615566', 'email' => 'sales@primaoksigen.com', 'nama_kontak' => 'Agus Prihatin', 'status' => 'AKTIF'],
        ];

        foreach ($pemasokList as $pem) {
            Pemasok::firstOrCreate(['kode' => $pem['kode']], $pem);
        }

        // 5. 30 Lokasi Rak/Bin/Pallet Gudang
        $gudangSentral = Gudang::where('kode', 'GUD_SENTRAL')->first() ?? Gudang::first();
        $gudangIpsrs   = Gudang::where('kode', 'GUD_IPSRS')->first() ?? $gudangSentral;
        $gudangAtk     = Gudang::where('kode', 'GUD_ATK')->first() ?? $gudangSentral;
        $gudangB3      = Gudang::where('kode', 'GUD_B3')->first() ?? $gudangSentral;

        $rakList = [
            ['gudang_id' => $gudangAtk->id,     'kode' => 'RAK-A1', 'nama' => 'Rak A1 - Kertas HVS & Map Rekam Medis', 'baris' => 'A', 'kolom' => '1', 'level' => '1', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangAtk->id,     'kode' => 'RAK-A2', 'nama' => 'Rak A2 - ATK, Pulpen, Klip & Binder',   'baris' => 'A', 'kolom' => '2', 'level' => '2', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangAtk->id,     'kode' => 'RAK-A3', 'nama' => 'Rak A3 - Formulir Pasien & Surat Dinas', 'baris' => 'A', 'kolom' => '3', 'level' => '1', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangAtk->id,     'kode' => 'RAK-A4', 'nama' => 'Rak A4 - Amplop & Sampul Berkas RS',     'baris' => 'A', 'kolom' => '4', 'level' => '2', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangAtk->id,     'kode' => 'RAK-A5', 'nama' => 'Rak A5 - Buku Register & Ekspedisi',     'baris' => 'A', 'kolom' => '5', 'level' => '3', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangSentral->id, 'kode' => 'RAK-B1', 'nama' => 'Rak B1 - Linen Medis & Sprei Pasien',   'baris' => 'B', 'kolom' => '1', 'level' => '1', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangSentral->id, 'kode' => 'RAK-B2', 'nama' => 'Rak B2 - Sabun Antiseptik & Hand Sanitizer', 'baris' => 'B', 'kolom' => '2', 'level' => '1', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangSentral->id, 'kode' => 'RAK-B3', 'nama' => 'Rak B3 - Masker Bedah & APD Perawat',  'baris' => 'B', 'kolom' => '3', 'level' => '2', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangSentral->id, 'kode' => 'RAK-B4', 'nama' => 'Rak B4 - Sarung Tangan Steril & Non-Steril', 'baris' => 'B', 'kolom' => '4', 'level' => '2', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangSentral->id, 'kode' => 'RAK-B5', 'nama' => 'Rak B5 - Kassa, Verban & Plester Luka', 'baris' => 'B', 'kolom' => '5', 'level' => '3', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangSentral->id, 'kode' => 'RAK-B6', 'nama' => 'Rak B6 - Selang Oksigen & Nasal Kanul', 'baris' => 'B', 'kolom' => '6', 'level' => '1', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangIpsrs->id,   'kode' => 'BIN-C1', 'nama' => 'Bin C1 - Sparepart Cepat Gerak & Sensor O2', 'baris' => 'C', 'kolom' => '1', 'level' => '1', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangIpsrs->id,   'kode' => 'BIN-C2', 'nama' => 'Bin C2 - Kabel Power & Fuse Pengaman',   'baris' => 'C', 'kolom' => '2', 'level' => '2', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangIpsrs->id,   'kode' => 'BIN-C3', 'nama' => 'Bin C3 - Komponen Elektronik & Relay',   'baris' => 'C', 'kolom' => '3', 'level' => '3', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangIpsrs->id,   'kode' => 'BIN-C4', 'nama' => 'Bin C4 - Baut, Mur & Seal Stainless',   'baris' => 'C', 'kolom' => '4', 'level' => '1', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangIpsrs->id,   'kode' => 'RAK-C5', 'nama' => 'Rak C5 - Freon R32 & R410 Tabung',      'baris' => 'C', 'kolom' => '5', 'level' => '1', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangIpsrs->id,   'kode' => 'RAK-C6', 'nama' => 'Rak C6 - Motor Fan, Kapasitor & Kompresor', 'baris' => 'C', 'kolom' => '6', 'level' => '2', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangIpsrs->id,   'kode' => 'RAK-C7', 'nama' => 'Rak C7 - Roda Bed Pasien & Kastor HD',   'baris' => 'C', 'kolom' => '7', 'level' => '1', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangIpsrs->id,   'kode' => 'BIN-C8', 'nama' => 'Bin C8 - Lampu Operasi & Halogen Bedah', 'baris' => 'C', 'kolom' => '8', 'level' => '2', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangSentral->id, 'kode' => 'PAL-D1', 'nama' => 'Pallet D1 - Kantong Biohazard Infeksius', 'baris' => 'D', 'kolom' => '1', 'level' => '1', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangSentral->id, 'kode' => 'PAL-D2', 'nama' => 'Pallet D2 - Kantong Sampah Domestik Hitam', 'baris' => 'D', 'kolom' => '2', 'level' => '1', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangSentral->id, 'kode' => 'PAL-D3', 'nama' => 'Pallet D3 - Jerigen Desinfektan Lantai 5L', 'baris' => 'D', 'kolom' => '3', 'level' => '1', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangSentral->id, 'kode' => 'RAK-D4', 'nama' => 'Rak D4 - Wadah Jarum Safety Box Kuning', 'baris' => 'D', 'kolom' => '4', 'level' => '2', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangSentral->id, 'kode' => 'RAK-D5', 'nama' => 'Rak D5 - Selimut Tebal Pasien Ruang VIP', 'baris' => 'D', 'kolom' => '5', 'level' => '3', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangB3->id,      'kode' => 'B3-E1',  'nama' => 'B3 Area E1 - Kaporit & Klorin Kolam IPAL', 'baris' => 'E', 'kolom' => '1', 'level' => '1', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangB3->id,      'kode' => 'B3-E2',  'nama' => 'B3 Area E2 - Cairan Formalin & Fixatif PA', 'baris' => 'E', 'kolom' => '2', 'level' => '1', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangB3->id,      'kode' => 'B3-E3',  'nama' => 'B3 Area E3 - Alkohol Teknis 96% Jerigen',  'baris' => 'E', 'kolom' => '3', 'level' => '1', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangSentral->id, 'kode' => 'POOL-ASET1', 'nama' => 'Pool Cadangan Aset - Bed & Brankar Dorong', 'baris' => 'P', 'kolom' => '1', 'level' => '1', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangSentral->id, 'kode' => 'POOL-ASET2', 'nama' => 'Pool Cadangan Aset - Pompa Infus & Syringe', 'baris' => 'P', 'kolom' => '2', 'level' => '2', 'status' => 'AKTIF'],
            ['gudang_id' => $gudangSentral->id, 'kode' => 'POOL-ASET3', 'nama' => 'Pool Cadangan Aset - Monitor & EKG Pasien', 'baris' => 'P', 'kolom' => '3', 'level' => '3', 'status' => 'AKTIF'],
        ];

        foreach ($rakList as $rak) {
            LokasiGudang::firstOrCreate(
                ['gudang_id' => $rak['gudang_id'], 'kode' => $rak['kode']],
                $rak
            );
        }

        // 6. Master Barang: 90 Jenis (30 HABIS_PAKAI, 30 SPAREPART, 30 ASET)
        $kats = KategoriBarang::all()->keyBy('kode');
        $sats = SatuanBarang::all()->keyBy('kode');
        $mrks = MerekBarang::all()->keyBy('kode');
        $pems = Pemasok::all()->keyBy('kode');

        $barangItems = [
            // A. 30 BARANG HABIS PAKAI (BHP)
            ['kode' => 'BRG-2026-001', 'nama' => 'Kertas HVS A4 70gr SiDU', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-ATK', 'sat' => 'SAT-RIM', 'mrk' => 'MRK-SIDU', 'pem' => 'VND-2026-004', 'min' => 20, 'max' => 150, 'reorder' => 40, 'beli' => 48000, 'est' => 55000],
            ['kode' => 'BRG-2026-004', 'nama' => 'Kertas HVS F4 70gr PaperOne', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-ATK', 'sat' => 'SAT-RIM', 'mrk' => 'MRK-PONE', 'pem' => 'VND-2026-004', 'min' => 20, 'max' => 120, 'reorder' => 35, 'beli' => 52000, 'est' => 59000],
            ['kode' => 'BRG-2026-005', 'nama' => 'Map Rekam Medis Pasien Berlogo RS', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-ATK', 'sat' => 'SAT-PCS', 'mrk' => null, 'pem' => 'VND-2026-004', 'min' => 500, 'max' => 3000, 'reorder' => 800, 'beli' => 2500, 'est' => 3000],
            ['kode' => 'BRG-2026-006', 'nama' => 'Pulpen Gel Hitam Joyko 0.5mm', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-ATK', 'sat' => 'SAT-BOX', 'mrk' => 'MRK-JYKO', 'pem' => 'VND-2026-004', 'min' => 15, 'max' => 80, 'reorder' => 25, 'beli' => 28000, 'est' => 35000],
            ['kode' => 'BRG-2026-007', 'nama' => 'Toner Cartridge HP LaserJet 85A', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-ELK', 'sat' => 'SAT-PCS', 'mrk' => 'MRK-HP', 'pem' => 'VND-2026-013', 'min' => 5, 'max' => 30, 'reorder' => 10, 'beli' => 380000, 'est' => 450000],
            ['kode' => 'BRG-2026-008', 'nama' => 'Toner Cartridge Canon 325', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-ELK', 'sat' => 'SAT-PCS', 'mrk' => 'MRK-CANON', 'pem' => 'VND-2026-013', 'min' => 5, 'max' => 25, 'reorder' => 8, 'beli' => 360000, 'est' => 420000],
            ['kode' => 'BRG-2026-009', 'nama' => 'Hand Sanitizer Gel 500ml Pump', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-DIS', 'sat' => 'SAT-BTL', 'mrk' => 'MRK-YURI', 'pem' => 'VND-2026-005', 'min' => 50, 'max' => 300, 'reorder' => 80, 'beli' => 32000, 'est' => 40000],
            ['kode' => 'BRG-2026-010', 'nama' => 'Sabun Cuci Tangan Medis Antiseptik 5L', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-RT', 'sat' => 'SAT-GLN', 'mrk' => 'MRK-YURI', 'pem' => 'VND-2026-005', 'min' => 10, 'max' => 50, 'reorder' => 15, 'beli' => 95000, 'est' => 120000],
            ['kode' => 'BRG-2026-011', 'nama' => 'Deterjen Disinfektan Linen Rumah Sakit 20L', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-RT', 'sat' => 'SAT-PAL', 'mrk' => 'MRK-DTTL', 'pem' => 'VND-2026-005', 'min' => 8, 'max' => 40, 'reorder' => 12, 'beli' => 310000, 'est' => 360000],
            ['kode' => 'BRG-2026-012', 'nama' => 'Cairan Karbol Pembersih Lantai Cemara 5L', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-RT', 'sat' => 'SAT-GLN', 'mrk' => 'MRK-YURI', 'pem' => 'VND-2026-005', 'min' => 15, 'max' => 60, 'reorder' => 20, 'beli' => 55000, 'est' => 70000],
            ['kode' => 'BRG-2026-013', 'nama' => 'Masker Bedah 3-Ply Earloop OneMed', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-APD', 'sat' => 'SAT-BOX', 'mrk' => 'MRK-ONEM', 'pem' => 'VND-2026-016', 'min' => 100, 'max' => 600, 'reorder' => 150, 'beli' => 28000, 'est' => 35000],
            ['kode' => 'BRG-2026-014', 'nama' => 'Masker Respirator N95 3M 1860 Medis', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-APD', 'sat' => 'SAT-BOX', 'mrk' => 'MRK-3M', 'pem' => 'VND-2026-017', 'min' => 20, 'max' => 100, 'reorder' => 30, 'beli' => 285000, 'est' => 330000],
            ['kode' => 'BRG-2026-015', 'nama' => 'Sarung Tangan Pemeriksaan Non-Steril Size M', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-APD', 'sat' => 'SAT-BOX', 'mrk' => 'MRK-ONEM', 'pem' => 'VND-2026-016', 'min' => 80, 'max' => 500, 'reorder' => 120, 'beli' => 45000, 'est' => 55000],
            ['kode' => 'BRG-2026-016', 'nama' => 'Sarung Tangan Bedah Steril Bebas Bedak Size 7.5', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-APD', 'sat' => 'SAT-PSG', 'mrk' => 'MRK-TRMO', 'pem' => 'VND-2026-007', 'min' => 150, 'max' => 800, 'reorder' => 250, 'beli' => 12500, 'est' => 16000],
            ['kode' => 'BRG-2026-017', 'nama' => 'Kasa Steril Hidrofil 16x16cm Kotak', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-BHP', 'sat' => 'SAT-BOX', 'mrk' => 'MRK-ONEM', 'pem' => 'VND-2026-016', 'min' => 50, 'max' => 300, 'reorder' => 80, 'beli' => 14000, 'est' => 18000],
            ['kode' => 'BRG-2026-018', 'nama' => 'Perban Gulung Elastis 4 Inch', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-BHP', 'sat' => 'SAT-ROL', 'mrk' => 'MRK-ONEM', 'pem' => 'VND-2026-016', 'min' => 40, 'max' => 200, 'reorder' => 60, 'beli' => 16000, 'est' => 22000],
            ['kode' => 'BRG-2026-019', 'nama' => 'Plester Kertas Medis Micropore 1 Inch 3M', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-BHP', 'sat' => 'SAT-ROL', 'mrk' => 'MRK-3M', 'pem' => 'VND-2026-017', 'min' => 30, 'max' => 150, 'reorder' => 50, 'beli' => 24000, 'est' => 30000],
            ['kode' => 'BRG-2026-020', 'nama' => 'Alkohol Swab 70% Kotak Isi 100', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-DIS', 'sat' => 'SAT-BOX', 'mrk' => 'MRK-ONEM', 'pem' => 'VND-2026-016', 'min' => 60, 'max' => 400, 'reorder' => 100, 'beli' => 15000, 'est' => 20000],
            ['kode' => 'BRG-2026-021', 'nama' => 'Alkohol Teknis 70% Galon 5 Liter', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-DIS', 'sat' => 'SAT-GLN', 'mrk' => 'MRK-YURI', 'pem' => 'VND-2026-005', 'min' => 12, 'max' => 60, 'reorder' => 20, 'beli' => 125000, 'est' => 150000],
            ['kode' => 'BRG-2026-022', 'nama' => 'Nasal Kanul Oksigen Pasien Dewasa', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-GAS', 'sat' => 'SAT-PCS', 'mrk' => 'MRK-ONEM', 'pem' => 'VND-2026-016', 'min' => 100, 'max' => 600, 'reorder' => 150, 'beli' => 6500, 'est' => 8500],
            ['kode' => 'BRG-2026-023', 'nama' => 'Sungkup Oksigen Non-Rebreathing Mask Dewasa', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-GAS', 'sat' => 'SAT-PCS', 'mrk' => 'MRK-ONEM', 'pem' => 'VND-2026-016', 'min' => 40, 'max' => 200, 'reorder' => 60, 'beli' => 18000, 'est' => 24000],
            ['kode' => 'BRG-2026-024', 'nama' => 'Sterilization Pouches Reel 100mm x 200m', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-CSSD', 'sat' => 'SAT-ROL', 'mrk' => 'MRK-3M', 'pem' => 'VND-2026-017', 'min' => 5, 'max' => 25, 'reorder' => 8, 'beli' => 280000, 'est' => 340000],
            ['kode' => 'BRG-2026-025', 'nama' => 'Autoclave Chemical Indicator Tape 19mm 3M', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-CSSD', 'sat' => 'SAT-ROL', 'mrk' => 'MRK-3M', 'pem' => 'VND-2026-017', 'min' => 15, 'max' => 75, 'reorder' => 25, 'beli' => 68000, 'est' => 85000],
            ['kode' => 'BRG-2026-026', 'nama' => 'Sprei Bed Pasien Polos Katun Putih Bersih', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-LIN', 'sat' => 'SAT-PCS', 'mrk' => null, 'pem' => 'VND-2026-012', 'min' => 40, 'max' => 200, 'reorder' => 60, 'beli' => 75000, 'est' => 95000],
            ['kode' => 'BRG-2026-027', 'nama' => 'Sarung Bantal Medis Katun Putih', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-LIN', 'sat' => 'SAT-PCS', 'mrk' => null, 'pem' => 'VND-2026-012', 'min' => 50, 'max' => 250, 'reorder' => 80, 'beli' => 22000, 'est' => 30000],
            ['kode' => 'BRG-2026-028', 'nama' => 'Kantong Plastik Kuning Sampah Infeksius Medis 60x100cm', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-RT', 'sat' => 'SAT-PCK', 'mrk' => null, 'pem' => 'VND-2026-002', 'min' => 30, 'max' => 150, 'reorder' => 50, 'beli' => 42000, 'est' => 52000],
            ['kode' => 'BRG-2026-029', 'nama' => 'Safety Box Limbah Jarum Suntik 5 Liter', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-RT', 'sat' => 'SAT-PCS', 'mrk' => 'MRK-ONEM', 'pem' => 'VND-2026-016', 'min' => 25, 'max' => 120, 'reorder' => 40, 'beli' => 18500, 'est' => 24000],
            ['kode' => 'BRG-2026-030', 'nama' => 'Kertas Thermal Rekam USG Video Printer 110HG Sony', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-RAD', 'sat' => 'SAT-ROL', 'mrk' => null, 'pem' => 'VND-2026-001', 'min' => 10, 'max' => 50, 'reorder' => 15, 'beli' => 220000, 'est' => 260000],
            ['kode' => 'BRG-2026-031', 'nama' => 'Gel USG Transmisi Akustik Aquasonic 5 Liter', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-RAD', 'sat' => 'SAT-GLN', 'mrk' => 'MRK-ONEM', 'pem' => 'VND-2026-016', 'min' => 8, 'max' => 40, 'reorder' => 12, 'beli' => 140000, 'est' => 175000],
            ['kode' => 'BRG-2026-032', 'nama' => 'Tisu Gulung Roll Khusus Ruang Rawat Pasien', 'tipe' => 'HABIS_PAKAI', 'kat' => 'KAT-RT', 'sat' => 'SAT-ROL', 'mrk' => 'MRK-KMBR', 'pem' => 'VND-2026-024', 'min' => 80, 'max' => 400, 'reorder' => 120, 'beli' => 8500, 'est' => 11000],

            // B. 30 SUKU CADANG / SPAREPART IPSRS
            ['kode' => 'BRG-2026-003', 'nama' => 'Freon AC R32 Refrigerant Tabung 3Kg', 'tipe' => 'SPAREPART', 'kat' => 'KAT-SPK', 'sat' => 'SAT-BTL', 'mrk' => 'MRK-DAIKIN', 'pem' => 'VND-2026-018', 'min' => 5, 'max' => 25, 'reorder' => 8, 'beli' => 150000, 'est' => 180000],
            ['kode' => 'BRG-2026-033', 'nama' => 'Freon AC R410A Refrigerant Tabung 11.3Kg', 'tipe' => 'SPAREPART', 'kat' => 'KAT-SPK', 'sat' => 'SAT-TBG', 'mrk' => 'MRK-DAIKIN', 'pem' => 'VND-2026-018', 'min' => 3, 'max' => 12, 'reorder' => 5, 'beli' => 850000, 'est' => 980000],
            ['kode' => 'BRG-2026-034', 'nama' => 'Sensor Oksigen Medis (O2 Cell) Ventilator Hamilton', 'tipe' => 'SPAREPART', 'kat' => 'KAT-SPK', 'sat' => 'SAT-PCS', 'mrk' => 'MRK-HMLT', 'pem' => 'VND-2026-003', 'min' => 2, 'max' => 10, 'reorder' => 4, 'beli' => 3200000, 'est' => 3600000],
            ['kode' => 'BRG-2026-035', 'nama' => 'Filter HEPA Ruang Operasi Ukuran 610x610x292mm', 'tipe' => 'SPAREPART', 'kat' => 'KAT-SPK', 'sat' => 'SAT-PCS', 'mrk' => null, 'pem' => 'VND-2026-003', 'min' => 4, 'max' => 16, 'reorder' => 6, 'beli' => 2400000, 'est' => 2800000],
            ['kode' => 'BRG-2026-036', 'nama' => 'Filter Antibakteri / HMEF Sirkuit Ventilator', 'tipe' => 'SPAREPART', 'kat' => 'KAT-SPK', 'sat' => 'SAT-BOX', 'mrk' => 'MRK-HMLT', 'pem' => 'VND-2026-003', 'min' => 10, 'max' => 50, 'reorder' => 15, 'beli' => 350000, 'est' => 420000],
            ['kode' => 'BRG-2026-037', 'nama' => 'Baterai Pengganti Defibrillator Biphasic Mindray Li-ion', 'tipe' => 'SPAREPART', 'kat' => 'KAT-SPK', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-MNDR', 'pem' => 'VND-2026-006', 'min' => 2, 'max' => 8, 'reorder' => 3, 'beli' => 4500000, 'est' => 5200000],
            ['kode' => 'BRG-2026-038', 'nama' => 'Baterai Kering UPS Rumah Sakit 12V 7.2Ah Yuasa', 'tipe' => 'SPAREPART', 'kat' => 'KAT-ELEC', 'sat' => 'SAT-PCS', 'mrk' => null, 'pem' => 'VND-2026-013', 'min' => 10, 'max' => 40, 'reorder' => 15, 'beli' => 220000, 'est' => 260000],
            ['kode' => 'BRG-2026-039', 'nama' => 'Lampu Halogen Meja Operasi Bedah 24V 150W Osram', 'tipe' => 'SPAREPART', 'kat' => 'KAT-SPK', 'sat' => 'SAT-PCS', 'mrk' => 'MRK-PHLP', 'pem' => 'VND-2026-003', 'min' => 5, 'max' => 25, 'reorder' => 8, 'beli' => 340000, 'est' => 410000],
            ['kode' => 'BRG-2026-040', 'nama' => 'Lampu LED Tindakan Medis 12V 50W Warm White', 'tipe' => 'SPAREPART', 'kat' => 'KAT-ELEC', 'sat' => 'SAT-PCS', 'mrk' => 'MRK-PLGT', 'pem' => 'VND-2026-021', 'min' => 10, 'max' => 50, 'reorder' => 15, 'beli' => 180000, 'est' => 220000],
            ['kode' => 'BRG-2026-041', 'nama' => 'Motor Fan Indoor AC Split 1.5 - 2 PK Panasonic', 'tipe' => 'SPAREPART', 'kat' => 'KAT-HVAC', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-PANA', 'pem' => 'VND-2026-011', 'min' => 3, 'max' => 12, 'reorder' => 5, 'beli' => 480000, 'est' => 570000],
            ['kode' => 'BRG-2026-042', 'nama' => 'Kapasitor Kompresor AC 35uF 450V Daikin', 'tipe' => 'SPAREPART', 'kat' => 'KAT-HVAC', 'sat' => 'SAT-PCS', 'mrk' => 'MRK-DAIKIN', 'pem' => 'VND-2026-018', 'min' => 10, 'max' => 40, 'reorder' => 15, 'beli' => 65000, 'est' => 85000],
            ['kode' => 'BRG-2026-043', 'nama' => 'Selang Silikon Food/Medical Grade Suction Pump 8x12mm', 'tipe' => 'SPAREPART', 'kat' => 'KAT-SPK', 'sat' => 'SAT-MTR', 'mrk' => null, 'pem' => 'VND-2026-003', 'min' => 20, 'max' => 100, 'reorder' => 30, 'beli' => 35000, 'est' => 45000],
            ['kode' => 'BRG-2026-044', 'nama' => 'Filter Inlet Suction Pump Bakterial Hydrophobic', 'tipe' => 'SPAREPART', 'kat' => 'KAT-SPK', 'sat' => 'SAT-PCS', 'mrk' => null, 'pem' => 'VND-2026-003', 'min' => 15, 'max' => 60, 'reorder' => 20, 'beli' => 75000, 'est' => 95000],
            ['kode' => 'BRG-2026-045', 'nama' => 'Kabel Power Medis Grade Rumah Sakit 3 Pin Hospital Grade', 'tipe' => 'SPAREPART', 'kat' => 'KAT-ELEC', 'sat' => 'SAT-PCS', 'mrk' => null, 'pem' => 'VND-2026-013', 'min' => 15, 'max' => 60, 'reorder' => 20, 'beli' => 85000, 'est' => 110000],
            ['kode' => 'BRG-2026-046', 'nama' => 'Relay Kontaktor Magnetik 3 Fasa Schneider LC1D25', 'tipe' => 'SPAREPART', 'kat' => 'KAT-ELEC', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-SCHN', 'pem' => 'VND-2026-020', 'min' => 3, 'max' => 12, 'reorder' => 5, 'beli' => 520000, 'est' => 620000],
            ['kode' => 'BRG-2026-047', 'nama' => 'MCB Listrik 1 Phase 16A Schneider Domae', 'tipe' => 'SPAREPART', 'kat' => 'KAT-ELEC', 'sat' => 'SAT-PCS', 'mrk' => 'MRK-SCHN', 'pem' => 'VND-2026-020', 'min' => 15, 'max' => 60, 'reorder' => 20, 'beli' => 65000, 'est' => 80000],
            ['kode' => 'BRG-2026-048', 'nama' => 'Roda Kastor Bed Pasien 5 Inch Swivel Central Lock Paramount', 'tipe' => 'SPAREPART', 'kat' => 'KAT-MEK', 'sat' => 'SAT-PCS', 'mrk' => 'MRK-PARAM', 'pem' => 'VND-2026-015', 'min' => 8, 'max' => 32, 'reorder' => 12, 'beli' => 650000, 'est' => 780000],
            ['kode' => 'BRG-2026-049', 'nama' => 'Hand Switch Remote Kontrol Bed Pasien Elektrik Paramount', 'tipe' => 'SPAREPART', 'kat' => 'KAT-SPK', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-PARAM', 'pem' => 'VND-2026-015', 'min' => 2, 'max' => 10, 'reorder' => 4, 'beli' => 1450000, 'est' => 1750000],
            ['kode' => 'BRG-2026-050', 'nama' => 'Kran Cuci Tangan Medis Siku Dinding Stainless SUS304', 'tipe' => 'SPAREPART', 'kat' => 'KAT-PLUMB', 'sat' => 'SAT-PCS', 'mrk' => null, 'pem' => 'VND-2026-003', 'min' => 5, 'max' => 25, 'reorder' => 8, 'beli' => 320000, 'est' => 390000],
            ['kode' => 'BRG-2026-051', 'nama' => 'Seal Gasket Karet Silikon Autoclave Pintu Sterilisasi', 'tipe' => 'SPAREPART', 'kat' => 'KAT-SPK', 'sat' => 'SAT-PCS', 'mrk' => null, 'pem' => 'VND-2026-003', 'min' => 3, 'max' => 12, 'reorder' => 5, 'beli' => 450000, 'est' => 540000],
            ['kode' => 'BRG-2026-052', 'nama' => 'Lead Wire Kabel EKG 10 Lead Nihon Kohden Asli', 'tipe' => 'SPAREPART', 'kat' => 'KAT-SPK', 'sat' => 'SAT-SET', 'mrk' => 'MRK-NHON', 'pem' => 'VND-2026-026', 'min' => 3, 'max' => 12, 'reorder' => 5, 'beli' => 2800000, 'est' => 3250000],
            ['kode' => 'BRG-2026-053', 'nama' => 'Manset Tensimeter NIBP Dewasa Adult Reusable Mindray', 'tipe' => 'SPAREPART', 'kat' => 'KAT-SPK', 'sat' => 'SAT-PCS', 'mrk' => 'MRK-MNDR', 'pem' => 'VND-2026-006', 'min' => 8, 'max' => 30, 'reorder' => 12, 'beli' => 420000, 'est' => 510000],
            ['kode' => 'BRG-2026-054', 'nama' => 'Sensor SpO2 Finger Probe Dewasa Mindray 6 Pin', 'tipe' => 'SPAREPART', 'kat' => 'KAT-SPK', 'sat' => 'SAT-PCS', 'mrk' => 'MRK-MNDR', 'pem' => 'VND-2026-006', 'min' => 6, 'max' => 25, 'reorder' => 10, 'beli' => 750000, 'est' => 890000],
            ['kode' => 'BRG-2026-055', 'nama' => 'Bohlam Mikroskop Laboratorium Halogen 6V 30W Philips', 'tipe' => 'SPAREPART', 'kat' => 'KAT-ELEC', 'sat' => 'SAT-PCS', 'mrk' => 'MRK-PHLP', 'pem' => 'VND-2026-021', 'min' => 5, 'max' => 20, 'reorder' => 8, 'beli' => 120000, 'est' => 150000],
            ['kode' => 'BRG-2026-056', 'nama' => 'Pipa Tembaga AC Set 1/4 + 3/8 Tebal 0.7mm Kembla', 'tipe' => 'SPAREPART', 'kat' => 'KAT-HVAC', 'sat' => 'SAT-ROL', 'mrk' => null, 'pem' => 'VND-2026-011', 'min' => 3, 'max' => 15, 'reorder' => 5, 'beli' => 680000, 'est' => 790000],
            ['kode' => 'BRG-2026-057', 'nama' => 'Oli Pelumas Kompresor Refrigeran Emkarate RL68H 1L', 'tipe' => 'SPAREPART', 'kat' => 'KAT-HVAC', 'sat' => 'SAT-BTL', 'mrk' => null, 'pem' => 'VND-2026-011', 'min' => 4, 'max' => 20, 'reorder' => 8, 'beli' => 195000, 'est' => 240000],
            ['kode' => 'BRG-2026-058', 'nama' => 'Fuse Tabung Kaca Cepat Putus 5x20mm 2A Kotak', 'tipe' => 'SPAREPART', 'kat' => 'KAT-ELEC', 'sat' => 'SAT-BOX', 'mrk' => null, 'pem' => 'VND-2026-013', 'min' => 10, 'max' => 40, 'reorder' => 15, 'beli' => 35000, 'est' => 45000],
            ['kode' => 'BRG-2026-059', 'nama' => 'Pompa Peristaltik Head Pengganti Infusion Pump Terumo', 'tipe' => 'SPAREPART', 'kat' => 'KAT-SPK', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-TRMO', 'pem' => 'VND-2026-007', 'min' => 2, 'max' => 8, 'reorder' => 3, 'beli' => 1850000, 'est' => 2200000],
            ['kode' => 'BRG-2026-060', 'nama' => 'Saklar Darurat Emergency Push Button Schneider IP65', 'tipe' => 'SPAREPART', 'kat' => 'KAT-ELEC', 'sat' => 'SAT-PCS', 'mrk' => 'MRK-SCHN', 'pem' => 'VND-2026-020', 'min' => 4, 'max' => 16, 'reorder' => 6, 'beli' => 140000, 'est' => 175000],
            ['kode' => 'BRG-2026-061', 'nama' => 'Kran Regulator Tabung Oksigen Medis Brass Kuningan', 'tipe' => 'SPAREPART', 'kat' => 'KAT-GAS', 'sat' => 'SAT-PCS', 'mrk' => null, 'pem' => 'VND-2026-010', 'min' => 5, 'max' => 20, 'reorder' => 8, 'beli' => 420000, 'est' => 500000],
            ['kode' => 'BRG-2026-062', 'nama' => 'Pembersih Kontak Elektronik Contact Cleaner Spray 400ml', 'tipe' => 'SPAREPART', 'kat' => 'KAT-ELEC', 'sat' => 'SAT-CAN', 'mrk' => null, 'pem' => 'VND-2026-013', 'min' => 8, 'max' => 30, 'reorder' => 12, 'beli' => 65000, 'est' => 85000],

            // C. 30 BARANG ASET MODAL INVENTARIS
            ['kode' => 'BRG-2026-002', 'nama' => 'Printer Multifungsi Epson EcoTank L3110 / L3210', 'tipe' => 'ASET', 'kat' => 'KAT-ELK', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-EPSN', 'pem' => 'VND-2026-013', 'min' => 2, 'max' => 10, 'reorder' => 3, 'beli' => 2300000, 'est' => 2800000],
            ['kode' => 'BRG-2026-063', 'nama' => 'Printer Barcode Gelang Pasien & Label Zebra ZD220', 'tipe' => 'ASET', 'kat' => 'KAT-ELK', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-ZBRA', 'pem' => 'VND-2026-013', 'min' => 2, 'max' => 8, 'reorder' => 3, 'beli' => 3100000, 'est' => 3600000],
            ['kode' => 'BRG-2026-064', 'nama' => 'Bed Pasien Elektrik 3 Crank Paramount Bed Qualitas', 'tipe' => 'ASET', 'kat' => 'KAT-FUR', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-PARAM', 'pem' => 'VND-2026-015', 'min' => 3, 'max' => 15, 'reorder' => 5, 'beli' => 28000000, 'est' => 32000000],
            ['kode' => 'BRG-2026-065', 'nama' => 'Bed Pasien Manual 2 Crank Standard dengan Matras', 'tipe' => 'ASET', 'kat' => 'KAT-FUR', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-LION', 'pem' => 'VND-2026-028', 'min' => 5, 'max' => 20, 'reorder' => 8, 'beli' => 8500000, 'est' => 10500000],
            ['kode' => 'BRG-2026-066', 'nama' => 'Patient Monitor 6 Parameter Mindray uMEC10', 'tipe' => 'ASET', 'kat' => 'KAT-MON', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-MNDR', 'pem' => 'VND-2026-006', 'min' => 2, 'max' => 10, 'reorder' => 4, 'beli' => 35000000, 'est' => 41000000],
            ['kode' => 'BRG-2026-067', 'nama' => 'Mesin Rekam Jantung EKG 12 Lead Nihon Kohden Cardiofax M', 'tipe' => 'ASET', 'kat' => 'KAT-DIAG', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-NHON', 'pem' => 'VND-2026-026', 'min' => 2, 'max' => 6, 'reorder' => 3, 'beli' => 42000000, 'est' => 48000000],
            ['kode' => 'BRG-2026-068', 'nama' => 'Defibrillator Monitor Biphasic Mindray BeneHeart D3', 'tipe' => 'ASET', 'kat' => 'KAT-RES', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-MNDR', 'pem' => 'VND-2026-006', 'min' => 1, 'max' => 5, 'reorder' => 2, 'beli' => 85000000, 'est' => 98000000],
            ['kode' => 'BRG-2026-069', 'nama' => 'Infusion Pump Terumo TE-LM700 Volumetrik', 'tipe' => 'ASET', 'kat' => 'KAT-TERAP', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-TRMO', 'pem' => 'VND-2026-007', 'min' => 3, 'max' => 15, 'reorder' => 5, 'beli' => 18500000, 'est' => 21000000],
            ['kode' => 'BRG-2026-070', 'nama' => 'Syringe Pump Terumo TE-SS700 Presisi Mikro', 'tipe' => 'ASET', 'kat' => 'KAT-TERAP', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-TRMO', 'pem' => 'VND-2026-007', 'min' => 3, 'max' => 15, 'reorder' => 5, 'beli' => 17000000, 'est' => 19500000],
            ['kode' => 'BRG-2026-071', 'nama' => 'Suction Pump Portable Medis OneMed 7A-23D', 'tipe' => 'ASET', 'kat' => 'KAT-RES', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-ONEM', 'pem' => 'VND-2026-016', 'min' => 2, 'max' => 10, 'reorder' => 4, 'beli' => 4200000, 'est' => 5100000],
            ['kode' => 'BRG-2026-072', 'nama' => 'Lampu Tindakan Operasi Minor LED Mobile Stand Maspion Med', 'tipe' => 'ASET', 'kat' => 'KAT-OK', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-PLGT', 'pem' => 'VND-2026-021', 'min' => 2, 'max' => 8, 'reorder' => 3, 'beli' => 7800000, 'est' => 9200000],
            ['kode' => 'BRG-2026-073', 'nama' => 'Kursi Roda Pasien Standar Stainless Steel OneMed', 'tipe' => 'ASET', 'kat' => 'KAT-TRANS', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-ONEM', 'pem' => 'VND-2026-016', 'min' => 5, 'max' => 25, 'reorder' => 8, 'beli' => 1450000, 'est' => 1800000],
            ['kode' => 'BRG-2026-074', 'nama' => 'Brankar Pasien Emergency Dorong Ruang Tindakan IGD', 'tipe' => 'ASET', 'kat' => 'KAT-TRANS', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-LION', 'pem' => 'VND-2026-028', 'min' => 2, 'max' => 10, 'reorder' => 4, 'beli' => 12500000, 'est' => 14800000],
            ['kode' => 'BRG-2026-075', 'nama' => 'AC Split Daikin Inverter 2 PK R32 Thailand', 'tipe' => 'ASET', 'kat' => 'KAT-HVAC', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-DAIKIN', 'pem' => 'VND-2026-018', 'min' => 2, 'max' => 8, 'reorder' => 3, 'beli' => 8200000, 'est' => 9500000],
            ['kode' => 'BRG-2026-076', 'nama' => 'AC Split Panasonic Eco Inverter 1 PK Standard', 'tipe' => 'ASET', 'kat' => 'KAT-HVAC', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-PANA', 'pem' => 'VND-2026-011', 'min' => 2, 'max' => 10, 'reorder' => 4, 'beli' => 4800000, 'est' => 5600000],
            ['kode' => 'BRG-2026-077', 'nama' => 'Lemari Pendingin Kulkas Farmasi Obat Reagen Sharp 200L', 'tipe' => 'ASET', 'kat' => 'KAT-FUR', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-SHARP', 'pem' => 'VND-2026-013', 'min' => 1, 'max' => 6, 'reorder' => 2, 'beli' => 6500000, 'est' => 7800000],
            ['kode' => 'BRG-2026-078', 'nama' => 'Autoclave Sterilisasi Basah Meja Tabletop 24 Liter', 'tipe' => 'ASET', 'kat' => 'KAT-CSSD', 'sat' => 'SAT-UNT', 'mrk' => null, 'pem' => 'VND-2026-003', 'min' => 1, 'max' => 5, 'reorder' => 2, 'beli' => 16500000, 'est' => 19500000],
            ['kode' => 'BRG-2026-079', 'nama' => 'Centrifuge Klinik Kecepatan Tinggi 8 Lubang Tabung', 'tipe' => 'ASET', 'kat' => 'KAT-LAB', 'sat' => 'SAT-UNT', 'mrk' => null, 'pem' => 'VND-2026-029', 'min' => 1, 'max' => 5, 'reorder' => 2, 'beli' => 9500000, 'est' => 11500000],
            ['kode' => 'BRG-2026-080', 'nama' => 'Mikroskop Binokuler Biologi Laboratorium LED Olympus', 'tipe' => 'ASET', 'kat' => 'KAT-LAB', 'sat' => 'SAT-UNT', 'mrk' => null, 'pem' => 'VND-2026-029', 'min' => 1, 'max' => 5, 'reorder' => 2, 'beli' => 14000000, 'est' => 16800000],
            ['kode' => 'BRG-2026-081', 'nama' => 'USG Portable Doppler Berwarna Chison SonoBook', 'tipe' => 'ASET', 'kat' => 'KAT-DIAG', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-GE', 'pem' => 'VND-2026-001', 'min' => 1, 'max' => 3, 'reorder' => 1, 'beli' => 145000000, 'est' => 165000000],
            ['kode' => 'BRG-2026-082', 'nama' => 'Phototherapy Unit Blue LED Terapi Ikterus Bayi Neonatal', 'tipe' => 'ASET', 'kat' => 'KAT-TERAP', 'sat' => 'SAT-UNT', 'mrk' => null, 'pem' => 'VND-2026-001', 'min' => 1, 'max' => 4, 'reorder' => 2, 'beli' => 24000000, 'est' => 28500000],
            ['kode' => 'BRG-2026-083', 'nama' => 'Nebulizer Medis Kompresor Omron Heavy Duty', 'tipe' => 'ASET', 'kat' => 'KAT-RES', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-ONEM', 'pem' => 'VND-2026-016', 'min' => 2, 'max' => 12, 'reorder' => 4, 'beli' => 1250000, 'est' => 1550000],
            ['kode' => 'BRG-2026-084', 'nama' => 'Timbangan Badan Digital Medis + Pengukur Tinggi Badan', 'tipe' => 'ASET', 'kat' => 'KAT-DIAG', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-ONEM', 'pem' => 'VND-2026-016', 'min' => 2, 'max' => 10, 'reorder' => 4, 'beli' => 2800000, 'est' => 3400000],
            ['kode' => 'BRG-2026-085', 'nama' => 'Tensimeter Digital Stand Beroda Khusus Ruang ICU Omron', 'tipe' => 'ASET', 'kat' => 'KAT-DIAG', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-ONEM', 'pem' => 'VND-2026-016', 'min' => 3, 'max' => 12, 'reorder' => 5, 'beli' => 4500000, 'est' => 5200000],
            ['kode' => 'BRG-2026-086', 'nama' => 'Tiang Infus Stainless Steel 4 Kait Beroda Lion Metal', 'tipe' => 'ASET', 'kat' => 'KAT-FUR', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-LION', 'pem' => 'VND-2026-028', 'min' => 10, 'max' => 50, 'reorder' => 15, 'beli' => 650000, 'est' => 850000],
            ['kode' => 'BRG-2026-087', 'nama' => 'Bedside Cabinet Pasien Meja Samping Laci Stainless', 'tipe' => 'ASET', 'kat' => 'KAT-FUR', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-LION', 'pem' => 'VND-2026-028', 'min' => 5, 'max' => 30, 'reorder' => 10, 'beli' => 1750000, 'est' => 2200000],
            ['kode' => 'BRG-2026-088', 'nama' => 'Trolley Obat Emergency Tindakan Resusitasi Kunci Segel', 'tipe' => 'ASET', 'kat' => 'KAT-FUR', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-LION', 'pem' => 'VND-2026-028', 'min' => 2, 'max' => 8, 'reorder' => 3, 'beli' => 8900000, 'est' => 10500000],
            ['kode' => 'BRG-2026-089', 'nama' => 'Komputer PC Desktop Core i5 SIMRS Rekam Medis Elektronik', 'tipe' => 'ASET', 'kat' => 'KAT-ELK', 'sat' => 'SAT-SET', 'mrk' => 'MRK-HP', 'pem' => 'VND-2026-013', 'min' => 3, 'max' => 15, 'reorder' => 5, 'beli' => 9800000, 'est' => 11500000],
            ['kode' => 'BRG-2026-090', 'nama' => 'UPS Online Sinewave 2000VA / 1800W Perangkat Server Medis', 'tipe' => 'ASET', 'kat' => 'KAT-ELEC', 'sat' => 'SAT-UNT', 'mrk' => 'MRK-SCHN', 'pem' => 'VND-2026-020', 'min' => 2, 'max' => 8, 'reorder' => 3, 'beli' => 8500000, 'est' => 9900000],
            ['kode' => 'BRG-2026-091', 'nama' => 'Alat Pemadam Api Ringan (APAR) Powder ABC 6 Kg Yamato', 'tipe' => 'ASET', 'kat' => 'KAT-K3', 'sat' => 'SAT-TBG', 'mrk' => null, 'pem' => 'VND-2026-003', 'min' => 5, 'max' => 30, 'reorder' => 10, 'beli' => 750000, 'est' => 950000],
        ];

        foreach ($barangItems as $b) {
            $katId = isset($b['kat']) ? ($kats->get($b['kat'])?->id ?? 1) : 1;
            $satId = isset($b['sat']) ? ($sats->get($b['sat'])?->id ?? 1) : 1;
            $mrkId = isset($b['mrk']) ? ($mrks->get($b['mrk'])?->id ?? null) : null;
            $pemId = isset($b['pem']) ? ($pems->get($b['pem'])?->id ?? null) : null;

            Barang::firstOrCreate(
                ['kode' => $b['kode']],
                [
                    'nama'                => $b['nama'],
                    'tipe'                => $b['tipe'],
                    'kategori_id'         => $katId,
                    'satuan_id'           => $satId,
                    'merek_id'            => $mrkId,
                    'pemasok_default_id'  => $pemId,
                    'stok_minimum'        => $b['min'],
                    'stok_maksimum'       => $b['max'],
                    'titik_pesan'         => $b['reorder'],
                    'harga_beli_terakhir' => $b['beli'],
                    'harga_estimasi'      => $b['est'],
                    'status'              => 'AKTIF',
                ]
            );
        }

        // 7. Register Aset Fisik Lengkap (40 Unit Aset Medis & Non-Medis)
        $units = Unit::all()->keyBy('kode');
        $barangs = Barang::where('tipe', 'ASET')->get()->keyBy('kode');

        $unitIgd     = $units->get('IGD')?->id;
        $unitIcu     = $units->get('ICU')?->id;
        $unitRawat1  = $units->get('RAWAT_1')?->id;
        $unitLab     = $units->get('LAB')?->id;
        $unitFarmasi = $units->get('FARMASI')?->id;
        $unitLogistik= $units->get('LOGISTIK')?->id;
        $unitDireksi = $units->get('DIREKSI')?->id;
        $unitSpi     = $units->get('SPI')?->id;
        $unitIpsrs   = $units->get('IPSRS')?->id;
        $gudangId    = $gudangSentral->id;

        $daftarAsetFisik = [
            // IGD
            ['kode' => 'AST-2026-001', 'seri' => 'PRINTER-IGD-1',   'brg' => 'BRG-2026-002', 'unit' => $unitIgd,     'gudang' => null,     'rak' => null,       'tgl' => '2026-01-15', 'nilai' => 2300000,  'kondisi' => 'BAIK', 'status' => 'DIGUNAKAN'],
            ['kode' => 'AST-2026-002', 'seri' => 'PRINTER-IGD-2',   'brg' => 'BRG-2026-002', 'unit' => $unitIgd,     'gudang' => null,     'rak' => null,       'tgl' => '2026-02-10', 'nilai' => 2300000,  'kondisi' => 'BAIK', 'status' => 'TERSEDIA'],
            ['kode' => 'AST-2026-005', 'seri' => 'MON-IGD-01',      'brg' => 'BRG-2026-066', 'unit' => $unitIgd,     'gudang' => null,     'rak' => null,       'tgl' => '2026-01-10', 'nilai' => 35000000, 'kondisi' => 'BAIK', 'status' => 'DIGUNAKAN'],
            ['kode' => 'AST-2026-006', 'seri' => 'DEFIB-IGD-01',    'brg' => 'BRG-2026-068', 'unit' => $unitIgd,     'gudang' => null,     'rak' => null,       'tgl' => '2026-01-05', 'nilai' => 85000000, 'kondisi' => 'BAIK', 'status' => 'DIGUNAKAN'],
            ['kode' => 'AST-2026-007', 'seri' => 'EKG-IGD-01',      'brg' => 'BRG-2026-067', 'unit' => $unitIgd,     'gudang' => null,     'rak' => null,       'tgl' => '2026-01-12', 'nilai' => 42000000, 'kondisi' => 'BAIK', 'status' => 'TERSEDIA'],
            ['kode' => 'AST-2026-008', 'seri' => 'SUCT-IGD-01',     'brg' => 'BRG-2026-071', 'unit' => $unitIgd,     'gudang' => null,     'rak' => null,       'tgl' => '2026-02-01', 'nilai' => 4200000,  'kondisi' => 'BAIK', 'status' => 'DIGUNAKAN'],
            ['kode' => 'AST-2026-009', 'seri' => 'BRK-IGD-01',      'brg' => 'BRG-2026-074', 'unit' => $unitIgd,     'gudang' => null,     'rak' => null,       'tgl' => '2026-01-20', 'nilai' => 12500000, 'kondisi' => 'BAIK', 'status' => 'DIGUNAKAN'],
            ['kode' => 'AST-2026-010', 'seri' => 'KR-IGD-01',       'brg' => 'BRG-2026-073', 'unit' => $unitIgd,     'gudang' => null,     'rak' => null,       'tgl' => '2026-01-25', 'nilai' => 1450000,  'kondisi' => 'BAIK', 'status' => 'TERSEDIA'],
            ['kode' => 'AST-2026-011', 'seri' => 'INF-IGD-01',      'brg' => 'BRG-2026-069', 'unit' => $unitIgd,     'gudang' => null,     'rak' => null,       'tgl' => '2026-02-15', 'nilai' => 18500000, 'kondisi' => 'BAIK', 'status' => 'TERSEDIA'],

            // ICU
            ['kode' => 'AST-2026-012', 'seri' => 'BED-ICU-01',      'brg' => 'BRG-2026-064', 'unit' => $unitIcu,     'gudang' => null,     'rak' => null,       'tgl' => '2026-01-08', 'nilai' => 28000000, 'kondisi' => 'BAIK', 'status' => 'DIGUNAKAN'],
            ['kode' => 'AST-2026-013', 'seri' => 'BED-ICU-02',      'brg' => 'BRG-2026-064', 'unit' => $unitIcu,     'gudang' => null,     'rak' => null,       'tgl' => '2026-01-08', 'nilai' => 28000000, 'kondisi' => 'BAIK', 'status' => 'DIGUNAKAN'],
            ['kode' => 'AST-2026-014', 'seri' => 'MON-ICU-01',      'brg' => 'BRG-2026-066', 'unit' => $unitIcu,     'gudang' => null,     'rak' => null,       'tgl' => '2026-01-10', 'nilai' => 35000000, 'kondisi' => 'BAIK', 'status' => 'DIGUNAKAN'],
            ['kode' => 'AST-2026-015', 'seri' => 'SYR-ICU-01',      'brg' => 'BRG-2026-070', 'unit' => $unitIcu,     'gudang' => null,     'rak' => null,       'tgl' => '2026-02-12', 'nilai' => 17000000, 'kondisi' => 'BAIK', 'status' => 'DIGUNAKAN'],
            ['kode' => 'AST-2026-016', 'seri' => 'INF-ICU-01',      'brg' => 'BRG-2026-069', 'unit' => $unitIcu,     'gudang' => null,     'rak' => null,       'tgl' => '2026-02-12', 'nilai' => 18500000, 'kondisi' => 'BAIK', 'status' => 'DIGUNAKAN'],
            ['kode' => 'AST-2026-017', 'seri' => 'USG-ICU-01',      'brg' => 'BRG-2026-081', 'unit' => $unitIcu,     'gudang' => null,     'rak' => null,       'tgl' => '2026-01-30', 'nilai' => 145000000,'kondisi' => 'BAIK', 'status' => 'TERSEDIA'],

            // Rawat Inap Teratai
            ['kode' => 'AST-2026-018', 'seri' => 'BED-RWT-01',      'brg' => 'BRG-2026-065', 'unit' => $unitRawat1,  'gudang' => null,     'rak' => null,       'tgl' => '2026-01-18', 'nilai' => 8500000,  'kondisi' => 'BAIK', 'status' => 'DIGUNAKAN'],
            ['kode' => 'AST-2026-019', 'seri' => 'BED-RWT-02',      'brg' => 'BRG-2026-065', 'unit' => $unitRawat1,  'gudang' => null,     'rak' => null,       'tgl' => '2026-01-18', 'nilai' => 8500000,  'kondisi' => 'BAIK', 'status' => 'DIGUNAKAN'],
            ['kode' => 'AST-2026-020', 'seri' => 'KR-RWT-01',       'brg' => 'BRG-2026-073', 'unit' => $unitRawat1,  'gudang' => null,     'rak' => null,       'tgl' => '2026-02-20', 'nilai' => 1450000,  'kondisi' => 'BAIK', 'status' => 'TERSEDIA'],
            ['kode' => 'AST-2026-021', 'seri' => 'TNS-RWT-01',      'brg' => 'BRG-2026-085', 'unit' => $unitRawat1,  'gudang' => null,     'rak' => null,       'tgl' => '2026-03-01', 'nilai' => 4500000,  'kondisi' => 'BAIK', 'status' => 'DIGUNAKAN'],
            ['kode' => 'AST-2026-022', 'seri' => 'NEB-RWT-01',      'brg' => 'BRG-2026-083', 'unit' => $unitRawat1,  'gudang' => null,     'rak' => null,       'tgl' => '2026-03-05', 'nilai' => 1250000,  'kondisi' => 'BAIK', 'status' => 'TERSEDIA'],

            // Laboratorium
            ['kode' => 'AST-2026-023', 'seri' => 'CENT-LAB-01',     'brg' => 'BRG-2026-079', 'unit' => $unitLab,     'gudang' => null,     'rak' => null,       'tgl' => '2026-01-22', 'nilai' => 9500000,  'kondisi' => 'BAIK', 'status' => 'DIGUNAKAN'],
            ['kode' => 'AST-2026-024', 'seri' => 'MIKR-LAB-01',     'brg' => 'BRG-2026-080', 'unit' => $unitLab,     'gudang' => null,     'rak' => null,       'tgl' => '2026-01-22', 'nilai' => 14000000, 'kondisi' => 'BAIK', 'status' => 'DIGUNAKAN'],
            ['kode' => 'AST-2026-025', 'seri' => 'KLK-LAB-01',      'brg' => 'BRG-2026-077', 'unit' => $unitLab,     'gudang' => null,     'rak' => null,       'tgl' => '2026-02-05', 'nilai' => 6500000,  'kondisi' => 'BAIK', 'status' => 'DIGUNAKAN'],

            // Farmasi
            ['kode' => 'AST-2026-026', 'seri' => 'KLK-FRM-01',      'brg' => 'BRG-2026-077', 'unit' => $unitFarmasi, 'gudang' => null,     'rak' => null,       'tgl' => '2026-01-15', 'nilai' => 6500000,  'kondisi' => 'BAIK', 'status' => 'DIGUNAKAN'],
            ['kode' => 'AST-2026-027', 'seri' => 'PC-FRM-01',       'brg' => 'BRG-2026-089', 'unit' => $unitFarmasi, 'gudang' => null,     'rak' => null,       'tgl' => '2026-02-01', 'nilai' => 9800000,  'kondisi' => 'BAIK', 'status' => 'DIGUNAKAN'],

            // Direksi, SPI & Administrasi
            ['kode' => 'AST-2026-003', 'seri' => 'PRINTER-MJN-1',   'brg' => 'BRG-2026-002', 'unit' => $unitDireksi, 'gudang' => null,     'rak' => null,       'tgl' => '2026-01-20', 'nilai' => 2300000,  'kondisi' => 'BAIK', 'status' => 'TERSEDIA'],
            ['kode' => 'AST-2026-004', 'seri' => 'PRINTER-AUD-1',   'brg' => 'BRG-2026-002', 'unit' => $unitSpi,     'gudang' => null,     'rak' => null,       'tgl' => '2026-02-05', 'nilai' => 2300000,  'kondisi' => 'BAIK', 'status' => 'TERSEDIA'],
            ['kode' => 'AST-2026-028', 'seri' => 'PC-LOG-01',       'brg' => 'BRG-2026-089', 'unit' => $unitLogistik,'gudang' => null,     'rak' => null,       'tgl' => '2026-01-10', 'nilai' => 9800000,  'kondisi' => 'BAIK', 'status' => 'DIGUNAKAN'],
            ['kode' => 'AST-2026-029', 'seri' => 'PRT-LOG-01',      'brg' => 'BRG-2026-063', 'unit' => $unitLogistik,'gudang' => null,     'rak' => null,       'tgl' => '2026-01-10', 'nilai' => 3100000,  'kondisi' => 'BAIK', 'status' => 'DIGUNAKAN'],
            ['kode' => 'AST-2026-030', 'seri' => 'AC-IPSRS-01',     'brg' => 'BRG-2026-075', 'unit' => $unitIpsrs,   'gudang' => null,     'rak' => null,       'tgl' => '2026-01-15', 'nilai' => 8200000,  'kondisi' => 'BAIK', 'status' => 'DIGUNAKAN'],

            // POOL ASET GUDANG SENTRAL (Siap Dipinjamkan Antar-Unit)
            ['kode' => 'AST-2026-031', 'seri' => 'POOL-BED-01',     'brg' => 'BRG-2026-064', 'unit' => null,         'gudang' => $gudangId,'rak' => 'POOL-ASET1','tgl' => '2026-02-01', 'nilai' => 28000000, 'kondisi' => 'BAIK', 'status' => 'TERSEDIA'],
            ['kode' => 'AST-2026-032', 'seri' => 'POOL-BED-02',     'brg' => 'BRG-2026-065', 'unit' => null,         'gudang' => $gudangId,'rak' => 'POOL-ASET1','tgl' => '2026-02-01', 'nilai' => 8500000,  'kondisi' => 'BAIK', 'status' => 'TERSEDIA'],
            ['kode' => 'AST-2026-033', 'seri' => 'POOL-INF-01',     'brg' => 'BRG-2026-069', 'unit' => null,         'gudang' => $gudangId,'rak' => 'POOL-ASET2','tgl' => '2026-02-15', 'nilai' => 18500000, 'kondisi' => 'BAIK', 'status' => 'TERSEDIA'],
            ['kode' => 'AST-2026-034', 'seri' => 'POOL-SYR-01',     'brg' => 'BRG-2026-070', 'unit' => null,         'gudang' => $gudangId,'rak' => 'POOL-ASET2','tgl' => '2026-02-15', 'nilai' => 17000000, 'kondisi' => 'BAIK', 'status' => 'TERSEDIA'],
            ['kode' => 'AST-2026-035', 'seri' => 'POOL-MON-01',     'brg' => 'BRG-2026-066', 'unit' => null,         'gudang' => $gudangId,'rak' => 'POOL-ASET3','tgl' => '2026-03-01', 'nilai' => 35000000, 'kondisi' => 'BAIK', 'status' => 'TERSEDIA'],
            ['kode' => 'AST-2026-036', 'seri' => 'POOL-SUCT-01',    'brg' => 'BRG-2026-071', 'unit' => null,         'gudang' => $gudangId,'rak' => 'POOL-ASET2','tgl' => '2026-03-05', 'nilai' => 4200000,  'kondisi' => 'BAIK', 'status' => 'TERSEDIA'],
            ['kode' => 'AST-2026-037', 'seri' => 'POOL-KR-01',      'brg' => 'BRG-2026-073', 'unit' => null,         'gudang' => $gudangId,'rak' => 'POOL-ASET1','tgl' => '2026-03-10', 'nilai' => 1450000,  'kondisi' => 'BAIK', 'status' => 'TERSEDIA'],
            ['kode' => 'AST-2026-038', 'seri' => 'POOL-TNS-01',     'brg' => 'BRG-2026-085', 'unit' => null,         'gudang' => $gudangId,'rak' => 'POOL-ASET3','tgl' => '2026-03-15', 'nilai' => 4500000,  'kondisi' => 'BAIK', 'status' => 'TERSEDIA'],
            ['kode' => 'AST-2026-039', 'seri' => 'POOL-NEB-01',     'brg' => 'BRG-2026-083', 'unit' => null,         'gudang' => $gudangId,'rak' => 'POOL-ASET2','tgl' => '2026-03-20', 'nilai' => 1250000,  'kondisi' => 'BAIK', 'status' => 'TERSEDIA'],
            ['kode' => 'AST-2026-040', 'seri' => 'POOL-LAMP-01',    'brg' => 'BRG-2026-072', 'unit' => null,         'gudang' => $gudangId,'rak' => 'POOL-ASET1','tgl' => '2026-03-25', 'nilai' => 7800000,  'kondisi' => 'BAIK', 'status' => 'TERSEDIA'],
        ];

        $raks = LokasiGudang::all()->keyBy('kode');

        foreach ($daftarAsetFisik as $ast) {
            $brgObj = $barangs->get($ast['brg']);
            if (! $brgObj) continue;

            $rakId = isset($ast['rak']) ? ($raks->get($ast['rak'])?->id ?? null) : null;

            Aset::firstOrCreate(
                ['kode_aset' => $ast['kode']],
                [
                    'nomor_seri'        => $ast['seri'],
                    'barang_id'         => $brgObj->id,
                    'unit_id'           => $ast['unit'],
                    'gudang_id'         => $ast['gudang'],
                    'lokasi_gudang_id'  => $rakId,
                    'tanggal_perolehan' => $ast['tgl'],
                    'nilai_perolehan'   => $ast['nilai'],
                    'kondisi'           => $ast['kondisi'],
                    'status'            => $ast['status'],
                ]
            );
        }
    }
}
