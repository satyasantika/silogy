# SILOGY — Runbook Deployment (server ns93 / 103.123.236.93)

Deploy silogy dengan **Docker Compose**, menumpang server yang sama dengan **silaris**.
Disusun dari inspeksi server 2026-08-10. Melengkapi `docs/SILOGY_System_Architecture_v6.md`.

## Topologi server (existing + silogy)

```text
Internet ──> Cloudflare (TLS publik) ──> Origin 103.123.236.93 (host ns93)
                                              │  routing by Host header
                                   ┌──────────┴─────────────────────────┐
                                   ▼                                    ▼
                    silaris-frontend nginx (:80/:443)          (port lain di host)
                    ├─ silaris.unsil.ac.id → SPA + /api → 172.17.0.1:9001
                    └─ silogy.unsil.ac.id  → 172.17.0.1:8081  ◄── TAMBAHAN silogy
                                              │
                                   silogy_nginx (172.17.0.1:8081→:80)
                                              │ FastCGI
                                   silogy_app (php-fpm) ── silogy_queue
                                              │
                                   silogy_mysql + silogy_redis  (terisolasi, internal)
```

**Fakta server:** Debian 13, 2 vCPU, 7.8 GB RAM, 147 GB disk bebas. Docker 29 + Compose v5.
User `lpmpp93` (grup `docker`, **sudo perlu password**). Port host terpakai: 22, 80, 443,
8080 (adminer), 9001 (silaris-api). **silogy pakai 8081** (di-bind ke `172.17.0.1`, bukan publik).
DB silaris eksternal di 10.10.x.x — **tidak disentuh**; silogy pakai container MySQL sendiri.

Artefak produksi di repo:

| File | Fungsi |
|---|---|
| `docker-compose.prod.yml` | Stack silogy (app, nginx@172.17.0.1:8081, queue, mysql, redis) |
| `.docker/php/Dockerfile.prod` | Image multi-stage: build Vite + `composer --no-dev` + opcache |
| `.docker/php/entrypoint.sh` | Sync asset + `migrate --force` + cache saat start |
| `.docker/nginx/silogy-vhost.conf` | Blok vhost untuk ditambahkan ke silaris-frontend |
| `.env.production.example` | Template `.env` produksi |
| `scripts/deploy.sh` | Skrip rilis yang dijalankan CD di server (backup → build → up → health) |
| `.github/workflows/ci.yml` | Pipeline tunggal: `quality` + `assets` → `deploy` |

---

## 1. Setup pertama kali di server

```bash
ssh silogy-server            # = lpmpp93@103.123.236.93 (alias ~/.ssh/config)

mkdir -p ~/docker-apps && cd ~/docker-apps
git clone https://github.com/satyasantika/silogy.git silogy && cd silogy

cp .env.production.example .env
# Generate APP_KEY:
docker compose -f docker-compose.prod.yml run --rm app php artisan key:generate --show
# → tempel "base64:..." ke APP_KEY di .env; isi DB_PASSWORD, DB_ROOT_PASSWORD,
#   GEMINI_API_KEY, MAIL_*. APP_URL sudah = https://silogy.unsil.ac.id.
nano .env

docker compose -f docker-compose.prod.yml up -d --build   # entrypoint auto-migrate + cache

# Seed data awal (HANYA pertama kali)
docker compose -f docker-compose.prod.yml exec app php artisan db:seed --class=RolePermissionSeeder --force

# Verifikasi dari host (via docker0 gateway)
curl -fsS http://172.17.0.1:8081/health
```

Login awal: **`superadmin` / `siliwangi`** — **segera ganti password**.

---

## 2. Menyambungkan domain (menyentuh silaris-frontend — hati-hati)

silogy.unsil.ac.id sudah mengarah ke Cloudflare → origin ns93. Karena **silaris-frontend
memegang 443**, domain silogy harus di-route dari sana. Langkah (butuh koordinasi, sebagian **root**):

1. **Sertifikat origin** untuk silogy.unsil.ac.id. Dua opsi:
   - *Cloudflare Origin Certificate* (paling mudah, 15 th, tak perlu buka port 80):
     dashboard Cloudflare → SSL/TLS → Origin Server → Create. Simpan sebagai
     `/opt/lpmpp-fe/nginx/ssl/silogy-fullchain.pem` & `silogy-privkey.pem`.
     Set mode SSL/TLS Cloudflare = **Full (strict)**.
   - *Let's Encrypt* via `certbot` (perlu root + challenge webroot lewat nginx existing).

2. **Pasang vhost** ke silaris-frontend:
   ```bash
   cp ~/docker-apps/silogy/.docker/nginx/silogy-vhost.conf /opt/lpmpp-fe/nginx/conf.d/silogy.conf
   ```
   Pastikan Dockerfile silaris-frontend meng-COPY `nginx/conf.d/` (saat ini hanya COPY
   `nginx.conf`→default.conf & `nginx/ssl`). Bila belum, tambahkan `COPY nginx/conf.d /etc/nginx/conf.d`
   ATAU mount lewat compose. Lalu:
   ```bash
   cd /opt/lpmpp-fe && docker compose up -d --build   # rebuild frontend dgn vhost+cert baru
   ```
   > Ini me-restart silaris-frontend (downtime silaris beberapa detik). Lakukan di jam sepi
   > dan siapkan rollback (`git`/backup config di /opt/lpmpp-fe sebelum mengubah).

3. **Uji**: `curl -I https://silogy.unsil.ac.id/health` → 200 dengan envelope JSON.

---

## 3. Deploy berikutnya (CD)

Satu pipeline: `.github/workflows/ci.yml`. Pada push ke `main`, job `deploy` **hanya jalan
setelah `quality` (Pint, Larastan, Pest di PHP 8.4) dan `assets` (build Vite di Node 22) hijau** —
kode yang gagal uji tidak pernah sampai ke server. Job `deploy` dikunci `concurrency`
sehingga dua rilis tidak saling menyalip di tengah migrasi.

Yang dijalankan di server via SSH:

```bash
cd ~/docker-apps/silogy
git fetch --prune origin main
git reset --hard origin/main
bash scripts/deploy.sh          # skrip rilis ikut ter-versioning di repo
```

`scripts/deploy.sh`: dump DB pra-migrasi → build image → tag `silogy-app:<sha>` →
`up -d` (entrypoint migrate + cache) → **health check ke port mapping asli**
(`docker compose port nginx 80`, mis. `172.17.0.1:8081`) → prune image lama.
Health gagal ⇒ skrip keluar non-zero dan job CD merah, disertai 50 baris log `app`.

GitHub Secrets yang wajib ada (repo → Settings → Secrets → Actions):

| Secret | Isi |
|---|---|
| `SERVER_HOST` | `103.123.236.93` |
| `SERVER_USER` | `lpmpp93` |
| `SSH_PRIVATE_KEY` | isi `~/.ssh/silogy_deploy` (private key; pub sudah di `authorized_keys` server) |

> Tidak ada lagi salinan skrip di `~/docker-apps/deploy.sh`. Bila CD gagal dengan
> `cd: ~/docker-apps/silogy: No such file or directory`, artinya secret `SERVER_HOST`
> masih menunjuk server lama — perbarui secret, jangan buat direktori di host itu.

---

## 4. Operasional

```bash
cd ~/docker-apps/silogy
C="docker compose -f docker-compose.prod.yml"
$C ps ; $C logs -f --tail=200 app ; $C logs -f queue
$C exec app php artisan queue:failed
```

### Rollback silogy
Tiap rilis menyimpan image ber-tag commit, jadi rollback tidak perlu build ulang
(hitungan detik, bukan menit di 2 vCPU):

```bash
cd ~/docker-apps/silogy
bash scripts/deploy.sh --list              # lihat tag yang tersedia
bash scripts/deploy.sh --rollback <sha>    # arahkan :prod ke image lama + recreate
```

Rollback hanya mengembalikan **kode**, bukan skema DB. Bila rilis gagal sempat
memigrasi, restore dump pra-deploy (`~/docker-apps/backups/silogy/pre-deploy_*.sql.gz`):

```bash
gunzip -c ~/docker-apps/backups/silogy/pre-deploy_<stamp>.sql.gz \
  | docker compose -f docker-compose.prod.yml exec -T mysql \
      sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
```

### Backup harian
`scripts/silogy-backup.sh` — sesuaikan `mysqldump` agar lewat container (port DB tidak diekspos):
```bash
docker compose -f docker-compose.prod.yml exec -T mysql \
  mysqldump --single-transaction --routines --triggers -u"$SILOGY_DB_USER" -p"$SILOGY_DB_PASS" silogy_prod
```
Cron 01:00. Retensi 30 hari. Enkripsi AES-256 (lihat skrip).

---

## 5. Checklist pra-produksi

- [ ] `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` terisi
- [ ] `DB_PASSWORD` & `DB_ROOT_PASSWORD` kuat (bukan default)
- [ ] `SESSION_SECURE_COOKIE=true`, `APP_URL=https://silogy.unsil.ac.id`
- [ ] `GEMINI_API_KEY` & `MAIL_*` terisi (bila fitur AI/email dipakai)
- [ ] Sertifikat origin silogy terpasang di silaris-frontend, mode CF = Full (strict)
- [ ] vhost silogy aktif; `curl -I https://silogy.unsil.ac.id/health` → 200
- [ ] silogy nginx hanya di `172.17.0.1:8081` (bukan `0.0.0.0`) — cek `ss -tlnp`
- [ ] GitHub Secrets menunjuk ns93 (bukan server lama); repo ter-clone di `~/docker-apps/silogy`
- [ ] Password `superadmin` diganti dari default
- [ ] Backup terjadwal & sudah diuji restore
- [ ] Konfirmasi silaris tetap normal setelah rebuild frontend
```
