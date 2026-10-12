# ADR 0003: Konfigurasi operasional dan kontrak route

Status: Diterima

## Konteks

`routes/web.php` mendaftarkan seluruh halaman portal lewat satu `foreach` atas 10 portal (`super-admin`, `admin-logistik`, `kepala-gudang`, `petugas-gudang`, `pengadaan`, `kepala-unit`, `petugas-unit`, `teknisi`, `manajemen`, `auditor`). Setiap halaman terdaftar di semua portal dengan prefix URL `/{portal}` dan nama route `{portal}.{halaman}`. Pola ini sempat dipertimbangkan untuk dipecah per portal atau per modul.

Selain itu beberapa nilai operasional (rate limit, batas login, TTL cache dropdown, batas unggahan) tertulis langsung di kode sehingga tidak dapat disesuaikan per lingkungan.

## Keputusan

### 1. Route portal tidak direfaktor

- Satu loop `foreach ($portals ...)` di `routes/web.php` **dipertahankan**. Akses per halaman tetap ditentukan permission di komponen (ADR 0001) dan batas portal oleh middleware `portal.access:{portal}`, bukan oleh pemisahan berkas route.
- URL, nama route, dan middleware dikunci oleh `tests/Feature/RouteContractTest.php` untuk portal web dan API v1. Perubahan URL/nama/middleware harus disengaja dan memperbarui daftar di tes tersebut.
- Pengecualian yang sudah ada dibiarkan apa adanya: `tracking-po` adalah redirect ke `penerimaan-po` tanpa `->name()` (nama efektifnya hanya prefix grup `{portal}.`), dan rute khusus super admin (`pengguna`, `roles`, `scheduler`) didaftarkan di dalam loop yang sama hanya untuk portal `super-admin`.

### 2. Nilai operasional dipindah ke `config/simlog.php`

Semua nilai dibaca lewat `config('simlog.*')`; `env()` hanya dipanggil di dalam `config/` agar aman terhadap `php artisan config:cache`. Default identik dengan nilai hardcoded sebelumnya.

| Key | Variabel lingkungan | Default | Dipakai di |
|---|---|---|---|
| `simlog.api.throttle.max_attempts` | `SIMLOG_API_THROTTLE_MAX_ATTEMPTS` | `60` | Middleware `throttle` grup API v1 terproteksi (`routes/api.php`) |
| `simlog.api.throttle.decay_minutes` | `SIMLOG_API_THROTTLE_DECAY_MINUTES` | `1` | Idem |
| `simlog.auth.max_login_attempts` | `SIMLOG_AUTH_MAX_LOGIN_ATTEMPTS` | `5` | `LoginGateway` (web) dan `AuthController` (API) |
| `simlog.cache.dropdown_ttl` | `SIMLOG_CACHE_DROPDOWN_TTL` | `60` (detik) | `Cache::remember` data dropdown master di komponen Livewire |
| `simlog.upload.max_kb` | `SIMLOG_UPLOAD_MAX_KB` | `10240` (KB) | Aturan validasi `max:` unggahan foto/berkas bukti |
| `simlog.upload.image_max_dimension` | `SIMLOG_UPLOAD_IMAGE_MAX_DIMENSION` | `1600` (px) | `App\Support\File\FileOptimizer` |
| `simlog.upload.image_quality` | `SIMLOG_UPLOAD_IMAGE_QUALITY` | `80` | `App\Support\File\FileOptimizer` |

Default dan override diuji di `tests/Feature/KonfigOperasionalTest.php`.

## Konsekuensi

- Menambah halaman portal berarti menambah satu baris di dalam loop **dan** satu entri di `RouteContractTest`; halaman otomatis tersedia di 10 portal, aksesnya dijaga permission.
- Tidak ada perubahan URL maupun nama route, sehingga tautan, menu, dan klien mobile tetap kompatibel.
- Nilai operasional baru ditambahkan ke `config/simlog.php` dengan default yang menyamai perilaku lama; jangan memanggil `env()` di luar `config/`.
- Kebijakan otorisasi (ADR 0001), enum status, dan atomisitas audit (ADR 0002) tidak berubah oleh keputusan ini.
