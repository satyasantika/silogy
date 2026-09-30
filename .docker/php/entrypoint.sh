#!/usr/bin/env bash
# SILOGY — entrypoint PRODUKSI untuk container app (php-fpm).
# Menjalankan release tasks sekali saat start, lalu exec CMD (php-fpm / queue:work).
set -euo pipefail

cd /var/www/html

# Sinkron asset public/ dari salinan pristine ke named volume (selalu, tiap start).
# Menjamin asset Vite/Filament ikut ter-update setiap deploy image baru.
if [ -d /usr/local/share/silogy-public ]; then
  cp -a /usr/local/share/silogy-public/. /var/www/html/public/ 2>/dev/null || true
fi

# Jalankan release tasks HANYA di container app utama (bukan queue worker).
# docker-compose.prod.yml men-set RUN_RELEASE=1 hanya pada service `app`.
if [ "${RUN_RELEASE:-0}" = "1" ]; then
  echo "[entrypoint] Menunggu database siap..."
  until php artisan db:show >/dev/null 2>&1; do
    echo "[entrypoint] DB belum siap, retry 3s..."
    sleep 3
  done

  echo "[entrypoint] php artisan migrate --force"
  php artisan migrate --force

  # storage:link (idempotent) — abaikan bila sudah ada
  php artisan storage:link 2>/dev/null || true

  # Gambar panduan ke public/manual. Tanpa ini seluruh gambar 404: nginx
  # menangkap permintaan berakhiran .png sebelum sampai ke PHP, jadi rute
  # Laravel tidak pernah dipanggil untuk berkas gambar.
  #
  # WAJIB --salin, bukan tautan simbolik: nginx berjalan di container terpisah
  # yang hanya me-mount volume public/. Tautan ke /var/www/html/docs/... akan
  # menggantung di sana dan tetap berujung 404.
  php artisan panduan:tautkan-aset --salin 2>/dev/null || true

  echo "[entrypoint] Membangun cache konfigurasi/route/view + Filament"
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  php artisan filament:optimize || true

  echo "[entrypoint] Release tasks selesai."
fi

exec "$@"
