# SILOGY — Arsitektur Sistem (System Architecture)

**Siliwangi Learning Outcomes & Quality Analytics**
*Universitas Siliwangi*

---

| Item | Nilai |
|---|---|
| Sistem Operasional | SILARIS – Siliwangi Learning & Quality Assurance System |
| Paradigma | Outcome-Based Education (OBE) |
| Infrastruktur | VPS · Ubuntu 24.04 LTS · Nginx · PHP-FPM 8.3 |
| Versi Dokumen | 6.0 |
| Tagline | *"From learning data to academic quality"* |

---

## 1. Gambaran Arsitektur VPS

```text
┌───────────────────── INTERNET ──────────────────────────────┐
│                      HTTPS:443                               │
└──────────────────────┬──────────────────────────────────────┘
                       ▼
┌──────────────── NGINX 1.26 ────────────────────────────────┐
│  SSL/TLS · Gzip/Brotli · Static cache (1y immutable)       │
└──────────────────────┬─────────────────────────────────────┘
                       ▼
┌──────────── PHP-FPM 8.3 (Laravel 13 + Filament v4) ───────┐
│  13 Modul Vertical Slice                                    │
│  academic_units (UUID) sebagai sumber hierarki institusi    │
│  mk_units pivot · kelas_mk.mk_unit_id                      │
│  Rantai: cpl → cpl_bok → cpl_mk → mk_cpmk → subcpmk        │
└──────────┬───────────────────────────────┬─────────────────┘
           ▼                               ▼
┌────── MySQL 8.0 ──────┐      ┌────── Redis 7.x ──────────┐
│  37 Tabel · UUID PK   │      │  Cache · Queue · Session  │
│  CASCADE FK           │      └─────────────┬─────────────┘
└───────────────────────┘                    ▼
                              ┌──── Supervisor Workers ───┐
                              │  cpl-calculation  [4w]    │
                              │  ai-analysis      [2w]    │
                              │  default          [2w]    │
                              └──────────────┬────────────┘
                                             ▼
                              ┌──── Google Gemini API ────┐
                              │  gemini-2.5-pro (HTTPS)   │
                              └───────────────────────────┘
```

---

## 2. Spesifikasi Server & Paket Composer

### 2.1 Spesifikasi Server

| Komponen | Minimum | Rekomendasi |
|---|---|---|
| CPU | 4 vCPU | 8 vCPU |
| RAM | 8 GB | 16 GB |
| Storage | 100 GB SSD | 200 GB NVMe SSD |
| OS | Ubuntu 22.04 LTS | Ubuntu 24.04 LTS |
| PHP | 8.3 | 8.3 |
| MySQL | 8.0 | 8.0 |
| Redis | 7.x | 7.x |
| Node.js | 20 LTS | 22 LTS |
| Nginx | 1.24 | 1.26 |

### 2.2 Paket Composer Wajib v6

| Package | Versi | Fungsi |
|---|---|---|
| `laravel/framework` | ^13.0 | Framework utama (PHP 8.3+) |
| `filament/filament` | ^4.0 | Panel admin Filament v4 |
| `bezhansalleh/filament-shield` | ^4.0 | RBAC guard Filament |
| `spatie/laravel-permission` | ^6.0 | Role & permission (UUID) |
| `spatie/laravel-activitylog` | ^4.0 | Audit log seluruh model |
| `spatie/laravel-model-states` | ^2.0 | Workflow state |
| `google-gemini-php/laravel` | ^2.0 | Google Gemini API |
| `barryvdh/laravel-dompdf` | ^3.0 | Laporan PDF |
| `phpoffice/phpspreadsheet` | ^2.0 | Export Excel |
| `laravel/sanctum` | ^4.0 | Autentikasi session/token |
| `laravel/horizon` | ^5.0 | (opsional) monitoring queue Redis |
| `predis/predis` | ^2.0 | Klien Redis murni PHP |

---

## 3. Konfigurasi `.env` Produksi

```ini
APP_NAME=SILARIS
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://silaris.unsil.ac.id
APP_LOCALE=id
APP_FALLBACK_LOCALE=id

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=silaris_prod
DB_USERNAME=silaris_user
DB_PASSWORD=<strong_password>
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=<redis_password>
REDIS_PORT=6379

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Google Gemini AI
GEMINI_API_KEY=AIza...
GEMINI_MODEL_DEFAULT=gemini-2.5-pro
GEMINI_MODEL_FLASH=gemini-2.5-flash
GEMINI_MAX_TOKENS=4096
GEMINI_MONTHLY_TOKEN_BUDGET=5000000

# Queue names
QUEUE_CPL_CALCULATION=cpl-calculation
QUEUE_AI_ANALYSIS=ai-analysis
QUEUE_DEFAULT=default

# Spatie Permission (UUID)
PERMISSION_TEAMS=false
PERMISSION_MODEL_MORPH_KEY=model_uuid

MAIL_MAILER=smtp
MAIL_HOST=smtp.unsil.ac.id
MAIL_PORT=587
MAIL_USERNAME=silaris
MAIL_PASSWORD=<mail_password>
MAIL_FROM_ADDRESS=silaris@unsil.ac.id
MAIL_FROM_NAME="SILARIS – UNSIL"

LOG_CHANNEL=daily
LOG_LEVEL=warning
```

---

## 4. Prosedur Deployment

### 4.1 Instalasi Awal

```bash
# Clone & dependencies
cd /var/www && git clone https://github.com/unsil/silaris.git && cd silaris
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate

# Database & seeder
php artisan migrate --force
php artisan db:seed --class=DatabaseSeeder
# Seeder mencakup: AcademicUnit (univ + min 1 fak/jur/prodi),
# Semester, Evaluasi, RolePermissionSeeder + akun sementara

# Frontend & cache
npm install && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan filament:optimize

# Permission OS
chown -R www-data:www-data /var/www/silaris
chmod -R 775 storage bootstrap/cache

# Queue workers
supervisorctl reread && supervisorctl update && supervisorctl start all
```

### 4.2 Update / Deployment Berikutnya

```bash
cd /var/www/silaris
php artisan down --secret="bypass_token"
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
npm run build
php artisan filament:optimize
supervisorctl restart all
php artisan up
```

### 4.3 Konfigurasi Supervisor (`/etc/supervisor/conf.d/silaris.conf`)

```ini
[program:silaris-cpl]
command=php /var/www/silaris/artisan queue:work redis --queue=cpl-calculation --sleep=3 --tries=3 --timeout=180
numprocs=4
process_name=%(program_name)s_%(process_num)02d
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/silaris/cpl.log

[program:silaris-ai]
command=php /var/www/silaris/artisan queue:work redis --queue=ai-analysis --sleep=5 --tries=2 --timeout=300
numprocs=2
process_name=%(program_name)s_%(process_num)02d
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/silaris/ai.log

[program:silaris-default]
command=php /var/www/silaris/artisan queue:work redis --queue=default --sleep=3 --tries=3
numprocs=2
process_name=%(program_name)s_%(process_num)02d
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/silaris/default.log

[group:silaris]
programs=silaris-cpl,silaris-ai,silaris-default
```

---

## 5. Backup, Recovery & Monitoring

### 5.1 Skrip Backup Harian

```bash
#!/bin/bash
# /usr/local/bin/silaris-backup.sh — cron @ 01:00
DATE=$(date +%Y%m%d_%H%M)
BACKUP_DIR=/var/backups/silaris
mkdir -p $BACKUP_DIR

# Dump database terenkripsi
mysqldump --single-transaction --routines --triggers \
  -u $DB_USER -p$DB_PASS silaris_prod \
  | gzip | openssl enc -aes-256-cbc -salt -pbkdf2 -k $KEY \
  > $BACKUP_DIR/db_${DATE}.sql.gz.enc

# Storage public
tar -czf $BACKUP_DIR/storage_${DATE}.tar.gz \
        /var/www/silaris/storage/app/public

# Retention 30 hari
find $BACKUP_DIR -mtime +30 -delete
```

### 5.2 Monitoring Cepat

```bash
# Status semua service
systemctl status nginx php8.3-fpm mysql redis-server

# Queue workers
supervisorctl status

# Job gagal
php artisan queue:failed
php artisan queue:retry all

# Log real-time
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log

# Database connection
mysql -u silaris_user -p -e "SHOW PROCESSLIST" silaris_prod
```

### 5.3 Target Recovery (RTO)

| Skenario | RTO | Langkah |
|---|---|---|
| Queue worker mati | ≤ 5 menit | `supervisorctl restart silaris:*` |
| Database corrupt | ≤ 2 jam | Restore dump terenkripsi; `php artisan migrate` |
| Deployment gagal | ≤ 30 menit | `git revert HEAD`; redeploy; `php artisan up` |
| VPS mati total | ≤ 4 jam | Provision VPS baru; restore backup harian |

---

## 6. Struktur Direktori Proyek v6

```text
silaris/
├── app/
│   ├── Modules/
│   │   ├── Institusi/        # AcademicUnit (UUID), AcademicUnitUser
│   │   ├── Auth/             # User, Policies
│   │   ├── Mahasiswa/        # Mahasiswas
│   │   ├── Kalender/         # Semester
│   │   ├── Kurikulum/
│   │   │   ├── Models/       # Kurikulum, ProfilLulusan, ProfilIndikator
│   │   │   └── States/       # Kurikulum (7 state), Mk (6 state)
│   │   ├── CPL/              # Cpl, CplBoK, CplMk, CplProfilLulusan
│   │   ├── BoK/              # Bok
│   │   ├── MK/               # Mk, MkUnit, Cpmk, MkCpmk, Subcpmk
│   │   ├── Kelas/            # KelasMk (mk_unit_id), KelasMkMahasiswa
│   │   ├── Penilaian/
│   │   │   ├── Models/       # Evaluasi, KomponenPenilaian,
│   │   │   │                 # SubcpmkKomponenPenilaian, NilaiMahasiswas
│   │   │   └── Jobs/
│   │   ├── Kalkulasi/
│   │   │   ├── Calculators/  # Subcpmk, Cpmk, CplMk,
│   │   │   │                 # CplMkUnit, CplUnitAggregator
│   │   │   └── Jobs/RecalkulasiCplJob.php
│   │   ├── AI/
│   │   │   ├── Services/GeminiClientService.php, GeminiCostGuard.php
│   │   │   └── Jobs/RunAnalisisAiJob.php
│   │   └── Audit/
│   ├── Models/               # Re-export model utama (untuk Spatie & Filament)
│   └── Providers/
├── bootstrap/
│   └── app.php               # Laravel 13 streamlined bootstrap
├── database/
│   ├── migrations/           # 15+ migration files (anonymous class)
│   ├── seeders/
│   │   ├── DatabaseSeeder.php
│   │   ├── AcademicUnitSeeder.php   # UNSIL + min 1 fakultas/jurusan/prodi
│   │   ├── SemesterSeeder.php
│   │   ├── EvaluasiSeeder.php       # 9 jenis evaluasi
│   │   └── RolePermissionSeeder.php # Role, Permission, Akun
│   └── factories/
├── config/
│   ├── gemini.php
│   └── permission.php        # UUID & model_morph_key
└── tests/
    ├── Unit/Kalkulasi/
    └── Feature/Modules/
```

---

## 7. Catatan Migrasi dari v5 ke v6

1. **Backup database v5** sebelum eksekusi migrasi v6.
2. **Buat `academic_units` (UUID)** dan seed dari data `universitas`+`fakultas`+`jurusan`+`prodis` lama. Petakan PK lama (UUID prodis) ke UUID `academic_units` melalui kolom transient `legacy_*_id` jika perlu.
3. **Update FK** semua tabel anak: `prodi_id` → `academic_unit_id` (UUID baru).
4. **Buat `mk_units`** dan pindahkan `(kode, semester_ke)` dari `mk` ke `mk_units`, satu baris per kombinasi `(academic_unit_id, mk_id)`.
5. **Update `kelas_mk`**: ganti `mk_id` → `mk_unit_id` berdasarkan `(prodi_lama, mk_id)`.
6. **Update `subcpmk`**: hapus kolom `cpmk_id` setelah memastikan setiap baris memiliki `mk_cpmk_id` valid.
7. **Rename `subcpmk_komponen_id` → `subcpmk_komponenpenilaian_id`** pada `nilai_mahasiswas`.
8. **Rename tabel `hasil_cpl_prodi` → `hasil_cpl_unit`** dan tambah kolom `academic_unit_id` dari `prodis.id` lama.
9. **Tambahkan `hasil_cpl_mk_unit`**, lalu jalankan ulang `RecalkulasiCplJob` untuk seluruh kurikulum aktif.
10. **Update `analisis_ai.prodi_id` → `academic_unit_id`** (lookup ke `academic_units` UUID baru).
11. **Drop tabel lama**: `universitas`, `fakultas`, `jurusan`, `prodis` setelah verifikasi.
12. **Refresh permission cache**: `php artisan permission:cache-reset` dan jalankan `RolePermissionSeeder` v6 untuk menambahkan permission dan role baru.

---

*Selesai — Arsitektur SILARIS v6 disusun untuk Laravel 13 + Filament v4 di atas VPS Ubuntu 24.04.*
