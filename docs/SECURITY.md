# Panduan Keamanan Operasional & Rilis SIMLOG RS

Dokumen ini memuat standar prosedur operasional keamanan (*security operational procedures*) untuk rilis produksi aplikasi mobile Flutter (`simlogrs-app`) dan deployment web/API backend Laravel (`simlog-fix2`).

---

## 1. Keamanan Distribusi Aplikasi Mobile (Android & iOS)

### A. Konfigurasi Keystore Rilis Produksi Mandiri (`key.properties`)
Secara default, proses kompilasi rilis lokal menggunakan penandatanganan debug agar dapat dijalankan untuk pengujian internal (`flutter run --release`). Sebelum merilis aplikasi ke server distribusi resmi rumah sakit atau Google Play Console, administrator build wajib menyiapkan keystore rilis mandiri:

1. **Membuat Java Keystore Rilis**:
   ```bash
   keytool -genkey -v -keystore simlogrs-release.jks -keyalg RSA -keysize 2048 -validity 10000 -alias simlogrs_release_key
   ```
   *Simpan berkas `simlogrs-release.jks` di direktori yang aman di luar repositori Git publik.*

2. **Menyiapkan `android/key.properties`**:
   Salin `android/key.properties.example` menjadi `android/key.properties`:
   ```properties
   keyAlias=simlogrs_release_key
   keyPassword=PasswordKeyAndaYangKuat
   storeFile=../keystores/simlogrs-release.jks
   storePassword=PasswordKeystoreAndaYangKuat
   ```
   *Berkas `android/key.properties` dan berkas `.jks` telah terdaftar di `.gitignore` dan tidak akan ter-commit ke Git.*

### B. Kompilasi Produksi dengan R8 Obfuscation & SSL Pinning
Build release telah dikonfigurasi dengan `isMinifyEnabled = true` dan `isShrinkResources = true` pada `build.gradle.kts` serta aturan pelindung di `proguard-rules.pro`.

Jalankan perintah build dengan menyertakan endpoint resmi dan sidik jari SHA-256 sertifikat TLS server:
```bash
flutter build apk --release \
  --dart-define=API_BASE_URL="https://simlog.rs-anda.co.id/api/v1" \
  --dart-define=SSL_FINGERPRINT="AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99:AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99"
```

### C. Proteksi Fisik Gawai & Integritas Lingkungan
1. **Anti-Screenshot & Task Snapshot Masking (`FLAG_SECURE`)**:
   Diaktifkan di `MainActivity.kt`. Mengambil tangkapan layar diblokir oleh OS Android dan tampilan aplikasi otomatis disamarkan (hitam/abu-abu) pada Recent Apps switcher untuk melindungi data medis dan logistik di ruang publik.
2. **Deteksi Root / Developer Test-Keys**:
   Aplikasi mendeteksi keberadaan binary `su` dan build test-keys melalui MethodChannel native. Pada halaman masuk (`LoginScreen`), banner peringatan keamanan akan ditampilkan secara non-blocking jika gawai terindikasi telah di-root.
3. **Timer Inaktivitas Otomatis (30 Menit)**:
   Demi melindungi gawai yang digunakan bersama (*shared mobile device*) di bangsal atau depo, aplikasi otomatis mengakhiri sesi, membersihkan token dan cache memori jika pengguna tidak menyentuh layar selama 30 menit.

---

## 2. Keamanan Web Server & Reverse-Proxy Nginx

### A. Template Konfigurasi Nginx untuk Produksi
Gunakan konfigurasi Nginx berikut pada reverse-proxy yang melayani domain SIMLOG RS:

```nginx
server {
    listen 443 ssl http2;
    server_name simlog.rs-anda.co.id;

    # 1. Konfigurasi Sertifikat TLS Modern
    ssl_certificate /etc/letsencrypt/live/simlog.rs-anda.co.id/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/simlog.rs-anda.co.id/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    # 2. HTTP Strict Transport Security (HSTS)
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # 3. Content Security Policy (CSP) & Defense-in-Depth Headers
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com; font-src 'self' https://fonts.bunny.net https://fonts.gstatic.com; img-src 'self' data: https: blob:; connect-src 'self' https:;" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;

    root /var/www/html/public;
    index index.php index.html;

    # 4. Blokir Akses ke File Konfigurasi Sensitif
    location ~ /\.(env|git|htaccess|svn) {
        deny all;
        return 404;
    }

    # 5. Routing Aplikasi Laravel & API
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }
}
```

### B. Konfigurasi Lingkungan Produksi (`.env`)
Saat transisi ke server produksi resmi:
1. Pastikan `APP_ENV=production`.
2. Pastikan `APP_DEBUG=false` untuk mencegah kebocoran *stack trace* dan query SQL.
3. Set `LOG_LEVEL=info` atau `LOG_LEVEL=notice`.
4. Jalankan `php artisan config:cache`, `php artisan route:cache`, dan `php artisan view:cache`.

---

## 3. Kebijakan Manajemen Sesi & Kredensial

1. **Durasi Token Sanctum**:
   Secara default dikonfigurasi ke 480 menit (8 jam / 1 shift dinas). Nilai dapat diatur ulang via `SANCTUM_EXPIRATION` di `.env`.
2. **Kebijakan Reset Password**:
   Administrator tidak mereset kata sandi ke teks statis. Sistem menghasilkan kata sandi acak sementara 10 karakter yang harus segera diganti oleh pegawai bersangkutan.
3. **Rate Limiting**:
   - Endpoint login dibatasi maksimal 5 percobaan gagal per menit per kombinasi email & IP.
   - Seluruh rute API terproteksi Sanctum dibatasi `throttle:60,1` (60 request/menit).
