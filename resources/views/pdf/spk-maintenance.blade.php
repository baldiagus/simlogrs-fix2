<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Perintah Kerja - {{ $spk->kode }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 1.5cm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            color: #18181b;
            line-height: 1.4;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #18181b;
            padding-bottom: 8px;
            margin-bottom: 16px;
        }
        .title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            margin: 12px 0 4px 0;
            text-transform: uppercase;
        }
        .subtitle {
            text-align: center;
            font-size: 11pt;
            font-family: monospace;
            font-weight: bold;
            margin-bottom: 16px;
        }
        .section-title {
            font-size: 10pt;
            font-weight: bold;
            background-color: #f4f4f5;
            padding: 4px 8px;
            border-left: 3px solid #2563eb;
            margin: 12px 0 6px 0;
        }
        table.meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table.meta-table td {
            padding: 4px 6px;
            font-size: 10pt;
            vertical-align: top;
        }
        .label {
            width: 25%;
            color: #52525b;
        }
        .colon {
            width: 3%;
            text-align: center;
        }
        .value {
            width: 72%;
            font-weight: 500;
        }
        .box {
            border: 1px solid #d4d4d8;
            border-radius: 4px;
            padding: 8px;
            font-size: 10pt;
            background-color: #fafafa;
            min-height: 40px;
            margin-bottom: 12px;
        }
        .signature-table {
            width: 100%;
            margin-top: 30px;
            border-collapse: collapse;
            text-align: center;
        }
        .signature-table td {
            width: 33.33%;
            vertical-align: top;
            font-size: 10pt;
        }
        .signature-space {
            height: 60px;
        }
        .sign-name {
            font-weight: bold;
            text-decoration: underline;
        }
        .footer-note {
            margin-top: 25px;
            border-top: 1px dashed #d4d4d8;
            padding-top: 6px;
            font-size: 8pt;
            color: #71717a;
            text-align: center;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 70px; vertical-align: middle;">
                <div style="font-size: 24pt; font-weight: bold; color: #2563eb;">RS</div>
            </td>
            <td style="vertical-align: middle;">
                <div style="font-size: 12pt; font-weight: bold;">RUMAH SAKIT UMUM DAERAH</div>
                <div style="font-size: 10pt; color: #52525b;">INSTALASI PEMELIHARAAN SARANA & PRASARANA (IPSRS)</div>
                <div style="font-size: 8pt; color: #71717a;">Jl. Kesehatan Raya No. 1, Telp: (021) 555-1234, Ext. 401</div>
            </td>
        </tr>
    </table>

    <div class="title">Surat Perintah Kerja (SPK) Perbaikan</div>
    <div class="subtitle">Nomor: {{ $spk->kode }}</div>

    <div class="section-title">1. INFORMASI SURAT & PERINTAH TUGAS</div>
    <table class="meta-table">
        <tr>
            <td class="label">Nomor SPK</td>
            <td class="colon">:</td>
            <td class="value" style="font-family: monospace;">{{ $spk->kode }}</td>
        </tr>
        <tr>
            <td class="label">Referensi Tiket Servis</td>
            <td class="colon">:</td>
            <td class="value" style="font-family: monospace;">{{ $spk->permintaanMaintenance?->kode ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Tanggal Penugasan</td>
            <td class="colon">:</td>
            <td class="value">{{ $spk->tanggal_penugasan?->format('d F Y') }}</td>
        </tr>
        <tr>
            <td class="label">Target Selesai</td>
            <td class="colon">:</td>
            <td class="value">{{ $spk->target_selesai ? $spk->target_selesai->format('d F Y') : 'Segera / Sesuai Jadwal' }}</td>
        </tr>
        <tr>
            <td class="label">Tingkat Urgensi / Prioritas</td>
            <td class="colon">:</td>
            <td class="value" style="font-weight: bold; color: {{ $spk->permintaanMaintenance?->prioritas === 'CITO' ? '#dc2626' : '#2563eb' }};">
                {{ $spk->permintaanMaintenance?->prioritas ?? 'SEDANG' }}
            </td>
        </tr>
        <tr>
            <td class="label">Teknisi Pelaksana Ditugaskan</td>
            <td class="colon">:</td>
            <td class="value">{{ $spk->teknisi?->nama ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Pemberi Tugas (Koordinator)</td>
            <td class="colon">:</td>
            <td class="value">{{ $spk->penugast?->nama ?? '-' }}</td>
        </tr>
    </table>

    <div class="section-title">2. IDENTITAS SARANA / PRASARANA YANG DIPERBAIKI</div>
    <table class="meta-table">
        <tr>
            <td class="label">Nama Fasilitas / Alat</td>
            <td class="colon">:</td>
            <td class="value">{{ $spk->permintaanMaintenance?->aset?->barang?->nama ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Kode Register Aset</td>
            <td class="colon">:</td>
            <td class="value" style="font-family: monospace;">{{ $spk->permintaanMaintenance?->aset?->kode_aset ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Nomor Seri Pabrik (SN)</td>
            <td class="colon">:</td>
            <td class="value" style="font-family: monospace;">{{ $spk->permintaanMaintenance?->aset?->nomor_seri ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Lokasi / Unit Pelapor</td>
            <td class="colon">:</td>
            <td class="value">{{ $spk->permintaanMaintenance?->unit?->nama ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Pelapor Kendala</td>
            <td class="colon">:</td>
            <td class="value">{{ $spk->permintaanMaintenance?->pelapor?->nama ?? '-' }}</td>
        </tr>
    </table>

    <div class="section-title">3. DESKRIPSI KELUHAN & INSTRUKSI PENGERJAAN</div>
    <div style="font-size: 9pt; color: #52525b; margin-bottom: 4px;">Keluhan Ruangan:</div>
    <div class="box">
        {{ $spk->permintaanMaintenance?->deskripsi_masalah ?? 'Tidak ada catatan keluhan khusus.' }}
    </div>

    @if ($spk->instruksi_khusus)
        <div style="font-size: 9pt; color: #52525b; margin-bottom: 4px;">Instruksi Khusus Koordinator:</div>
        <div class="box">
            {{ $spk->instruksi_khusus }}
        </div>
    @endif

    <div class="section-title">4. CATATAN PENYELESAIAN & UJI FUNGSI (Diisi Teknisi & Ruangan)</div>
    <table style="width: 100%; border: 1px solid #d4d4d8; border-collapse: collapse; font-size: 9pt; margin-top: 4px;">
        <tr>
            <td style="border: 1px solid #d4d4d8; padding: 6px; width: 50%;">
                <strong>Diagnosa & Tindakan:</strong><br><br><br>
            </td>
            <td style="border: 1px solid #d4d4d8; padding: 6px; width: 50%;">
                <strong>Suku Cadang Digunakan:</strong><br><br><br>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="border: 1px solid #d4d4d8; padding: 6px;">
                <strong>Hasil Uji Kelaikan Fungsi Bersama Ruangan:</strong> [ ] BAIK / NORMAL &nbsp;&nbsp;&nbsp; [ ] MASIH KENDALA &nbsp;&nbsp;&nbsp; [ ] PERLU TIKET LANJUTAN
            </td>
        </tr>
    </table>

    <table class="signature-table">
        <tr>
            <td>
                Pelapor / Staf Ruangan,<br>
                <div class="signature-space"></div>
                <div class="sign-name">({{ $spk->permintaanMaintenance?->pelapor?->nama ?? '..................................' }})</div>
                <div>NIP: .................................</div>
            </td>
            <td>
                Teknisi Pelaksana,<br>
                <div class="signature-space"></div>
                <div class="sign-name">({{ $spk->teknisi?->nama ?? '..................................' }})</div>
                <div>NIP: .................................</div>
            </td>
            <td>
                Koordinator IPSRS,<br>
                <div class="signature-space"></div>
                <div class="sign-name">({{ $spk->penugast?->nama ?? '..................................' }})</div>
                <div>NIP: .................................</div>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        Dokumen resmi SIMLOG RS Non-Medis v2 &bull; Dicetak otomatis pada {{ now()->format('d/m/Y H:i') }} &bull; Halaman 1 dari 1
    </div>
</body>
</html>
