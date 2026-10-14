# Firebase Push (FCM) — Setup Server & Pemecahan Masalah

Kontrak payload, endpoint token, dan daftar `tipe`: lihat `docs/NOTIFICATION_CONTRACT.md`.

## Server: menjalankan pengirim push

Alur: `NotifikasiService` menulis baris tabel `notifikasi` (lonceng web/inbox app), lalu
men-dispatch job `App\Jobs\KirimPushFcm` ke antrean redis `default`. Push ke HP baru terkirim
saat job itu diproses worker.

1. **Kredensial.** Taruh berkas Service Account Firebase di
   `storage/app/firebase/firebase_credentials.json` (default `config/firebase.php`,
   dapat diganti lewat env `FIREBASE_CREDENTIALS`, path relatif terhadap root proyek).
   Folder ini di-ignore git (`.gitignore`: `/storage/app/firebase/*`). **Jangan pernah
   meng-commit berkas kredensial** atau menyalin isinya ke dokumen/chat. Tanpa berkas ini
   push dilewati, notifikasi tetap tampil di web/inbox.
2. **Muat ulang konfigurasi** setelah menaruh/mengganti kredensial atau mengubah `config/`.
   Lingkungan ini memakai cache config (`bootstrap/cache/config.php`); `config:cache` saja
   menulis ulang isi lama, jadi bersihkan dulu lalu bangun ulang, kemudian restart worker
   (proses jangka panjang memegang config di memori):

   ```sh
   docker exec simrslogv2-app php artisan config:clear
   docker exec simrslogv2-app php artisan config:cache
   docker compose restart queue scheduler
   ```

   Cek hasilnya: `docker exec simrslogv2-queue php artisan tinker --execute="echo config('firebase.default');"`
   harus mencetak `app`. Bila kosong, push dilewati walau kredensial ada.

3. **Worker & scheduler** berjalan sebagai service docker yang hidup otomatis
   (`restart: unless-stopped`, ikut naik saat Docker/host restart):

   | Service | Container | Perintah |
   |---|---|---|
   | `queue` | `simrslogv2-queue` | `php artisan queue:work redis --queue=default --tries=3 ...` (keluar tiap jam, dihidupkan ulang Docker) |
   | `scheduler` | `simrslogv2-scheduler` | `php artisan schedule:work` (cron 00:01 sweep batch, 01:00 preventif, 06:00 overdue, 07:00 H-1) |

   Menyalakan (sekali, atau bila container terhapus):

   ```sh
   docker compose up -d queue scheduler
   ```

   Setelah mengubah kode job/service, worker memuat kode baru paling lambat 1 jam; untuk
   segera: `docker compose restart queue`.

4. **Cek kesehatan:**

   ```sh
   docker compose ps                         # queue & scheduler harus "Up"
   docker logs simrslogv2-queue --tail 50    # baris "App\Jobs\KirimPushFcm ... DONE"/"FAIL"
   docker logs simrslogv2-scheduler --tail 50
   ```

   Atau dari web: portal Super Admin → **Task Scheduler** (`/super-admin/scheduler`) → kartu
   **Status Pengiriman Push**: Antrean Tertunda, Detak Worker Terakhir (cache
   `simlog:queue:last_heartbeat`, ditulis tiap kali `KirimPushFcm` berjalan), Perangkat
   Terdaftar, Pengguna Ber-token. Status **Bermasalah** = ada antrean tertunda tetapi tidak
   ada detak worker dalam 5 menit terakhir.

## Kenapa notifikasi muncul di web tapi tidak di HP

Lonceng web dibaca langsung dari tabel `notifikasi`, sedangkan push butuh worker + token
perangkat. Cek berurutan:

**(a) Worker hidup?** `docker compose ps` → `simrslogv2-queue` harus `Up`. Bila tidak:
`docker compose up -d queue`. Kartu Status Pengiriman Push menunjukkan **Bermasalah**.

**(b) Antrean tertunda?** Kartu "Antrean Tertunda" > 0 dan tidak turun berarti job tidak
diproses. Cek langsung di redis:

```sh
docker exec simrslogv2-redis redis-cli LLEN simlog-rs-non-medis-database-queues:default
```

Job yang menumpuk akan terkirim begitu worker hidup lagi. Bila worker `Up` tetapi log berisi
`FAIL`, periksa kredensial (langkah 1–2) dan `storage/logs/laravel.log` ("Push FCM gagal ...").

**(c) Akun penerima punya token?** Push hanya dikirim ke token di tabel `user_device_tokens`
milik `pengguna_id` penerima notifikasi. Cek:

```sh
docker exec simrslogv2-postgres psql -U simlog_user -d simlog_rs -c \
  "select p.username, t.device_type, t.dibuat_pada, t.last_used_at from user_device_tokens t join pengguna p on p.id = t.pengguna_id order by p.username;"
```

Akun penerima tidak ada di daftar = push untuk akun itu tidak punya tujuan (dilewati diam-diam).

### Aturan penting: satu HP = satu akun penerima

- Token FCM melekat pada **HP/instalasi app**, bukan pada akun. Kolom `fcm_token` unik.
- Saat login, app mendaftarkan token (`POST /api/v1/auth/fcm-token`); bila token itu sudah
  dimiliki akun lain, token **dipindahkan** ke akun yang baru login. Logout menghapus token.
- Jadi satu HP hanya menerima push untuk akun yang **terakhir login** di HP tersebut. Ganti
  akun = token pindah; akun lama berhenti menerima push di HP itu.
- Untuk menerima push sebagai Kepala Unit, akun Kepala Unit harus **sedang login di app** pada
  HP tersebut.

**Contoh nyata:** Teknisi melapor/mengubah status sehingga notifikasi ditujukan ke
`petugasunit`. Lonceng web akun `petugasunit` menampilkannya, tetapi HP yang sedang login
sebagai `teknisi` tidak berbunyi. Ini **benar**: notifikasi untuk `petugasunit`, sedangkan
token HP itu milik `teknisi`. Agar HP menerima, login sebagai `petugasunit` di app pada HP
tersebut (atau pakai HP kedua untuk akun lain saat uji).
