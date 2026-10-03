---
target: resources/views/livewire/system/scheduler-notifikasi-index.blade.php
total_score: 20
max_score: 40
na_heuristics: 
p0_count: 1
p1_count: 2
target_identity: "file:C:\\Users\\Arcane\\orca\\projects\\simlog-fix2\\resources\\views\\livewire\\system\\scheduler-notifikasi-index.blade.php"
target_fingerprint: "sha256:c247d9be1be1fbb8b9857372f9b34bb6e5cf95b721c5149824d3c9c32701f4ac"
target_path: "C:\\Users\\Arcane\\orca\\projects\\simlog-fix2\\resources\\views\\livewire\\system\\scheduler-notifikasi-index.blade.php"
timestamp: 2026-10-03T12-04-19Z
slug: stem-scheduler-notifikasi-index-blade-php-f380f2a0
---
### Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|:---:|-----------|
| 1 | Visibility of System Status | 2 | Bergantung pada string cache transien; tidak ada heartbeat daemon cron OS; minim metrik latensi eksekusi. |
| 2 | Match System / Real World | 3 | Bahasa logistik rumah sakit kuat (FEFO, IPSRS, kedaluwarsa), namun tabel bawah menampilkan notifikasi umum alih-alih log eksekusi cron. |
| 3 | User Control and Freedom | 1 | Tidak ada mode simulasi (dry-run); pemicuan instan langsung mengunci data tanpa opsi pembatalan. |
| 4 | Consistency and Standards | 2 | Duplikasi tombol antara header dan kartu (6 tombol untuk 3 aksi yang sama) dengan label berbeda. |
| 5 | Error Prevention | 1 | Tidak ada dialog konfirmasi (`wire:confirm`) sebelum menjalankan task yang mengunci status batch obat/B3 secara permanen. |
| 6 | Recognition Rather Than Recall | 3 | Deskripsi ambang batas bisnis tertulis jelas di kartu, namun tidak ada tautan langsung ke data terdampak. |
| 7 | Flexibility and Efficiency | 2 | Tidak ada kustomisasi jadwal cron, opsi parameter filter, atau eksekusi batch per gudang. |
| 8 | Aesthetic and Minimalist Design | 2 | Tombol header ganda menciptakan redundansi visual; line-clamp salah semantik pada elemen table cell. |
| 9 | Error Recovery | 2 | Penanganan galat menangkap exception ke toast, namun tidak menyediakan riwayat kegagalan task untuk troubleshooting. |
| 10 | Help and Documentation | 2 | Subheading menyebutkan jam eksekusi (00:01, 01:00, 06:00), namun tidak ada dokumentasi integrasi worker cron server. |
| **Total** | | **20/40** | **Mediocre / Needs Attention** |

### Design Specificity Verdict

**LLM Assessment**: Antarmuka memiliki teks domain logistik rumah sakit yang spesifik (FEFO, penguncian status batch, tiket preventif IPSRS), namun struktur tata letak dan hierarki visualnya adalah template SaaS generik (grid 3 kartu standar dengan tombol ganda dan tabel notifikasi standar). Tidak mencerminkan urgensi ruang kendali sistem otomasi rumah sakit yang beroperasi 24/7.

**Deterministic Scan**: Tool detektor Impeccable mendeteksi 0 aturan CSS/HTML kaku yang melanggar standar sintaks dasar, namun audit kode mendeteksi bug tampilan data: referensi `$notif->pengguna?->name` (seharusnya `nama`) yang menyebabkan nama penerima selalu fallback menjadi `User #ID`.

**Visual Overlays**: Inspeksi peramban aktif mengonfirmasi halaman dapat diakses dengan visual Flux UI yang rapi namun dingin, dengan duplikasi tombol aksi yang nyata antara bar header atas dan kaki masing-masing kartu task.

### Overall Impression
Secara visual halaman terlihat bersih dan tertata khas komponen modern Flux UI, namun secara arsitektur informasi dan UX operasional, halaman ini mengalami redundansi kontrol (6 tombol untuk 3 aksi), ketiadaan pengaman konfirmasi pada proses penguncian stok obat, serta ketidaksesuaian fungsi pada tabel riwayat yang menampilkan notifikasi umum alih-alih log eksekusi cron.

### What's Working
1. **Kejelasan Deskripsi Aturan Bisnis**: Setiap kartu cron menjelaskan secara lugas apa yang dilakukan task di latar belakang (misal: penguncian status KEDALUWARSA dan pelarangan alokasi FEFO).
2. **Indikator Jadwal Spesifik**: Waktu eksekusi harian (00:01 WIB, 01:00 WIB, 06:00 WIB) diletakkan di posisi paling atas kartu dengan tipografi monospace yang mudah dipindai.
3. **Penyelarasan Warna Kategori**: Penggunaan aksen warna atas kartu yang berbeda (Amber untuk batch expired, Rose untuk overdue pinjaman, Indigo untuk preventif maintenance) membantu diferensiasi cepat.

### Priority Issues

- **[P0] Redundansi Tombol Aksi Header vs Kartu**:
  - *Why it matters*: Halaman memuat 6 tombol untuk 3 fungsi identik (3 tombol di header atas dan 3 tombol di kaki kartu). Pengguna mengalami kebingungan keputusan ("apakah tombol atas dan bawah berbeda?").
  - *Fix*: Hapus 3 tombol trigger di header atas. Pertahankan tombol aksi terfokus hanya di dalam masing-masing kartu task yang relevan.
  - *Suggested command*: `$impeccable distill`

- **[P1] Ketiadaan Pengaman Konfirmasi pada Operasi Kritis (Error Prevention)**:
  - *Why it matters*: Mengeklik tombol langsung mengunci status stok obat di gudang dan menerbitkan tiket SPK tanpa dialog konfirmasi atau pratinjau dampak. Klik yang tidak sengaja langsung mengubah data persediaan aktif rumah sakit.
  - *Fix*: Pasang dialog konfirmasi atau `wire:confirm` sebelum eksekusi, serta sediakan informasi jumlah data yang akan terdampak.
  - *Suggested command*: `$impeccable harden`

- **[P1] Bug Tampilan Nama Penerima pada Tabel Notifikasi**:
  - *Why it matters*: Kolom 'Penerima' menampilkan `User #10` alih-alih nama asli staf penerima notifikasi, merusak keterbacaan audit.
  - *Fix*: Ganti `$notif->pengguna?->name` menjadi `$notif->pengguna?->nama` pada template blade.
  - *Suggested command*: `$impeccable clarify`

- **[P2] Tabel Log Notifikasi Menampilkan Data Umum Bukan Riwayat Scheduler**:
  - *Why it matters*: Judul tabel menyatakan "Log Notifikasi Internal Terbitan Scheduler", namun query backend mengambil 10 notifikasi umum terbaru yang tidak semuanya berasal dari cron. Administrator tidak dapat melihat riwayat apakah cron jam 00:01 berhasil atau gagal.
  - *Fix*: Sediakan riwayat eksekusi cron sungguhan (waktu mulai, durasi eksekusi, status sukses/gagal, jumlah data diproses, dan output pesan).
  - *Suggested command*: `$impeccable shape`

### Persona Red Flags
- **Bambang (Super Admin / IT Ops)**: Tidak dapat mengetahui apakah service cron di server Docker sedang berjalan aktif atau mati, karena badge 'Pending' hanya membaca cache statis tanpa daemon heartbeat.
- **Dedy (Kepala Gudang)**: Khawatir menekan tombol manual karena tidak tahu berapa banyak batch obat yang akan langsung terkunci tanpa ada mode simulasi (dry-run).

### Minor Observations
- Badge status pada kartu bertuliskan 'Pending' berwarna abu-abu netral, tidak membedakan antara "Jadwal Siap", "Sedang Berjalan", atau "Gagal".
- Teks deskripsi hasil eksekusi terakhir dipadatkan dalam satu string teks panjang tanpa metrik angka terpisah yang menonjol.
- CSS class `line-clamp-1` dipasang langsung pada elemen `<td>`, yang secara standar HTML table cell tidak bekerja optimal tanpa kontainer wrapper `div`.

### Questions to Consider
- Apakah administrator membutuhkan tombol "Dry Run / Simulasi" agar bisa melihat daftar batch yang akan terkunci sebelum benar-benar dieksekusi?
- Apakah tabel riwayat sebaiknya diubah menjadi tabel riwayat eksekusi job (Log Audit Cron) yang mencatat durasi dan jumlah rekaman yang diproses?
