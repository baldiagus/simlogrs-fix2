# Tuning performa Docker (Windows bind-mount)

## Yang berubah
- `docker-compose.yml`: volume bernama `app_storage_views` di `storage/framework/views` (view Blade terkompilasi tidak lagi lewat bind-mount Windows yang lambat); `docker/php/zz-tuning.ini` di-mount read-only ke service `app`; `APP_DEBUG=false` di environment container.
- `docker/php/zz-tuning.ini`: OPcache (revalidate_freq=60, JIT tracing, enable_file_override), realpath cache, memory_limit=512M.
- `.env` / `.env.example`: `APP_DEBUG=false`, `LOG_LEVEL=warning` (`APP_ENV` tetap `local`).
- `docker/php/Dockerfile`: baris echo `revalidate_freq`/`validate_timestamps` dihapus (digantikan zz-tuning.ini).

## Cara menerapkan
```sh
docker compose up -d --force-recreate app web
docker exec -u root simrslogv2-app chown -R www-data:www-data /var/www/html/storage/framework/views
docker exec simrslogv2-app php artisan optimize
```

## Catatan
- Setelah mengubah route/config, jalankan ulang `php artisan optimize`. Untuk membatalkan: `php artisan optimize:clear`.
- `opcache.validate_timestamps=0` hanya disarankan di production (kode tidak akan reload otomatis).
- Untuk I/O terbaik, pindahkan repo ke dalam filesystem WSL2 (mis. `~/projects/simlog`) dan jalankan Docker dari sana.

## Cara revert
1. Hapus baris `app_storage_views` dan mount `zz-tuning.ini` dari `docker-compose.yml`, kembalikan `APP_DEBUG=true`.
2. `docker compose up -d --force-recreate app web`
3. `docker volume rm <project>_app_storage_views` (opsional), lalu `php artisan optimize:clear`.
4. Kembalikan `.env`: `APP_DEBUG=true`, `LOG_LEVEL=debug`.
