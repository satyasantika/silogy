# SILOGY — Persiapan Pra-Vibe-Coding (v6)

**Sebelum jari menyentuh keyboard untuk vibe-coding, semua hal di bawah ini WAJIB beres.**
Dokumen ini adalah checklist yang dapat dipakai untuk setiap iterasi vibe-coding pada proyek SILOGY. Stack utama: **Docker · Laravel 13 · MySQL 8 · Filament v3**.

| Item | Nilai |
|---|---|
| Proyek | SILOGY – Siliwangi Learning Outcomes & Quality Analytics |
| Paradigma | Outcome-Based Education (OBE) |
| Versi Target | 6.0 (sesuai PRD/ERD/System Design v6) |
| Stack Utama | Docker · Laravel 13 · MySQL 8 · Redis 7 · Filament v3 |
| Tanggal | 17 Mei 2026 |

---

## 1. Validasi Scope & MVP

### 1.1 Prinsip MVP

> *"Kirim nilai dulu, kirim lengkap nanti."*
> MVP = minimum yang membuat Tim Kurikulum dapat **menyusun kurikulum prodi → memasukkan nilai → melihat ketercapaian CPL**. Selebihnya = backlog.

### 1.2 Daftar Fitur MVP (7 Core, lock-in)

| # | Modul MVP | Outcome bisnis | Catatan v6 |
|---|---|---|---|
| 1 | **Auth & RBAC** | User dapat login (username/email); role & permission UUID dipetakan via `academic_unit_users` | Wajib lengkap di Sprint 1 |
| 2 | **Manajemen Institusi (`academic_units`)** | Super Admin/Admin Unit mengelola hierarki universitas → fakultas → jurusan → program studi pada satu tabel UUID | Tidak ada lagi tabel terpisah univ/fak/jur/prodi |
| 3 | **Manajemen Mahasiswa** | Admin Prodi mengelola data mahasiswa per `academic_unit_id` (study_program) | Tanpa login mahasiswa |
| 4 | **Kurikulum + Workflow State** | Tim Kurikulum membangun kurikulum: `draft → profil_lulusan* → cpl → bok → mk → setdosenmk → aktif` | `mk_units` untuk penawaran MK per unit |
| 5 | **Kelas MK + Penetapan Dosen** | Admin Unit/Tim Kurikulum membuat kelas (`mk_unit_id` + `semester_id`) dan menetapkan dosen pengampu + Koordinator MK | `setdosen_mk` permission |
| 6 | **Penilaian (Komponen + Input Nilai)** | Koordinator MK menyusun `komponen_penilaian` & `subcpmk_komponenpenilaian`; Dosen Pengampu input nilai | Bobot default 100 |
| 7 | **Kalkulasi & Dashboard CPL** | Job 5 tahap menghasilkan `hasil_subcpmk → hasil_cpmk → hasil_cpl_mk → hasil_cpl_mk_unit → hasil_cpl_unit`; Filament Dashboard menampilkan CPL per unit | Per `academic_unit_id` (semua level) |

### 1.3 Backlog Nice-to-Have (Fase 2+)

- **AI Analisis** (Anthropic Claude API + `analisis_ai`) — *nice-to-have, butuh API key produksi & cost guard*.
- **Indikator Profil Lulusan** detail (`profil_indikators` lanjutan, mapping ke CPL).
- **Bloom Taxonomy fields** (`bloom_kognitif/afektif/psikomotorik`) — disimpan, belum dipakai analitik.
- **Import nilai dari Excel/CSV massal** (`import_nilai`).
- **Ekspor laporan PDF kompleks** (template multi-halaman, signature box).
- **State machine MK 6 state** lengkap (MVP cukup 3 state: `draft → penilaian → selesai`).
- **Audit Log Viewer di Filament** (versi MVP cukup tersimpan di DB).
- **Monitoring Horizon** & metrik Prometheus.
- **Multi-tahun komparasi CPL** & rekomendasi otomatis.

### 1.4 User Flow Ringkas per Fitur MVP

```text
F1 — Auth & RBAC
   Login (username/email + password)
     → cek hashed password
     → load roles & permissions (Spatie UUID)
     → redirect ke /admin (Filament panel)

F2 — Manajemen Institusi
   Super Admin → menu "Academic Units"
     → tambah Universitas (parent=null, type=university)
     → tambah Fakultas (parent=univ, type=faculty)
     → tambah Jurusan/Prodi (parent=fak/jur)
     → assign user via pivot academic_unit_users (set status_pimpinan / status_tim_kurikulum)

F3 — Manajemen Mahasiswa
   Admin Prodi → menu "Mahasiswa" (scoped academic_unit_id)
     → tambah/import mahasiswa
     → atribut: nim, nama, jenis_kelamin, angkatan, email, nomor_wa, status

F4 — Kurikulum
   Tim Kurikulum (status_tim_kurikulum=1) → "Kurikulum"
     → buat kurikulum (academic_unit_id, tahun, target_capaian_lulusan)
     → state draft → (profil_lulusan*) → cpl → bok → mk → setdosenmk → aktif
     → tiap transisi tercatat di state_transitions

F5 — Kelas MK
   Admin Unit → "Kelas MK"
     → pilih mk_unit_id + semester_id
     → tetapkan dosen_pengampu_id & koordinator_mk_id
     → daftarkan mahasiswa via kelas_mk_mahasiswa

F6 — Penilaian
   Koordinator MK → "Komponen Penilaian"
     → buat komponen per kelas_mk (UTS/UAS/Quiz/Tugas)
     → mapping ke subcpmk via subcpmk_komponenpenilaian
   Dosen Pengampu → "Input Nilai"
     → isi nilai_mahasiswas (subcpmk_komponenpenilaian_id, kelas_mk_mahasiswa_id, nilai)
     → trigger RecalkulasiCplJob

F7 — Dashboard CPL
   Pimpinan/Auditor → "Dashboard"
     → filter: academic_unit_id, semester
     → chart hasil_cpl_unit (% tercapai vs target)
     → drill-down ke hasil_cpl_mk_unit (per MK)
```

### 1.5 Definition of MVP "Selesai"

MVP dianggap **selesai** jika **satu jalur end-to-end** berfungsi: login → buat unit & user → buat kurikulum → buat kelas & komponen → input nilai → muncul ketercapaian CPL di dashboard. Selesai itu **bukan** "fitur ada"; selesai itu "fitur jalan saat di-demo ke Kaprodi".

---

## 2. Breakdown Engineering Task

Format task: `[Module] – [Action] – [Outcome]`
Estimasi rough: **S** ≤ 4 jam · **M** 4–8 jam · **L** 1–2 hari · **XL** > 2 hari.

### Sprint 0 — Setup & Infrastruktur (Wajib selesai sebelum Sprint 1)

| # | Task | Est |
|---|---|---|
| S0-01 | Infra – Docker Compose – PHP-FPM 8.3 + Nginx + MySQL 8 + Redis 7 + Mailpit jalan di `docker compose up` | M |
| S0-02 | Repo – Inisialisasi Git – Branching `main` / `dev` / `feature/*`, conventional commits, CODEOWNERS | S |
| S0-03 | Laravel – `composer create-project laravel/laravel:^13.0 silogy` di dalam container | S |
| S0-04 | Quality – Install Pint, Larastan (lvl 6), PHPUnit, Pest – pipeline jalan | M |
| S0-05 | Filament – Install Filament v3 + FilamentShield – panel `/admin` jalan | M |
| S0-06 | RBAC – Install Spatie Permission – publish migration UUID – cache reset works | M |
| S0-07 | CI – GitHub Actions: `composer install` → `php artisan test` → `pint --test` → `phpstan` | M |
| S0-08 | Env – `.env.example` lengkap; `.env.docker` untuk dev container | S |

### Sprint 1 — F1 Auth + F2 Institusi

| # | Task | Est |
|---|---|---|
| S1-01 | DB – Migration `academic_units` (UUID, tree, profil lengkap) | M |
| S1-02 | DB – Migration `users` UUID + `academic_unit_users` + Spatie tables | M |
| S1-03 | Model – `AcademicUnit` (HasUuids, parent/children, scope per type) | S |
| S1-04 | Model – `User` (HasUuids, hasMany academicUnitUsers, hasRoles) | S |
| S1-05 | Seeder – `AcademicUnitSeeder` (UNSIL + 1 fakultas + 1 jurusan + 1 prodi minimal) | S |
| S1-06 | Seeder – `RolePermissionSeeder` lengkap v6 (sesuai ERD §13) | L |
| S1-07 | Filament – Resource `AcademicUnitResource` (tree view, form profil) | L |
| S1-08 | Filament – Resource `UserResource` + assign role + assign unit pivot | L |
| S1-09 | Auth – Login username/email + reset password | M |
| S1-10 | Policy – `AcademicUnitPolicy` cek `status_pimpinan` / `status_tim_kurikulum` | M |

### Sprint 2 — F3 Mahasiswa + F4 Kurikulum

| # | Task | Est |
|---|---|---|
| S2-01 | DB – Migration `mahasiswas` (nomor_wa, academic_unit_id study_program) | S |
| S2-02 | DB – Migration `semesters` + `kurikulum` + `profil_lulusan` + `profil_indikators` + `state_transitions` | M |
| S2-03 | DB – Migration `cpl` + `bok` + `mk` + `mk_units` | M |
| S2-04 | DB – Migration `cpl_profil_lulusan` + `cpl_bok` + `cpl_mk` | S |
| S2-05 | State – `KurikulumState` 7 state (laravel-state) + transition guards | L |
| S2-06 | Filament – Resource `MahasiswaResource` (scoping per academic_unit_id) | M |
| S2-07 | Filament – Resource `KurikulumResource` + stepper widget | L |
| S2-08 | Filament – Resource `CplResource`, `BokResource`, `MkResource`, `MkUnitResource` | XL |

### Sprint 3 — F5 Kelas + F6 Penilaian

| # | Task | Est |
|---|---|---|
| S3-01 | DB – Migration `cpmk` + `mk_cpmk` + `subcpmk` (tanpa cpmk_id) | M |
| S3-02 | DB – Migration `kelas_mk` (mk_unit_id) + `kelas_mk_mahasiswa` | M |
| S3-03 | DB – Migration `evaluasi` + `komponen_penilaian` (bobot default 100) + `subcpmk_komponenpenilaian` + `nilai_mahasiswas` | M |
| S3-04 | Seeder – `EvaluasiSeeder` (9 jenis evaluasi default) | S |
| S3-05 | Filament – Resource `KelasMkResource` + setdosen action | L |
| S3-06 | Filament – Resource `KomponenPenilaianResource` (Koordinator MK) | M |
| S3-07 | Filament – Page `InputNilaiPage` (matriks mahasiswa × subcpmk_komponen) | XL |

### Sprint 4 — F7 Kalkulasi & Dashboard

| # | Task | Est |
|---|---|---|
| S4-01 | DB – Migration `hasil_subcpmk` + `hasil_cpmk` + `hasil_cpl_mk` (mk_unit_id) + `hasil_cpl_mk_unit` + `hasil_cpl_unit` | M |
| S4-02 | Service – `SubcpmkCalculator` + `CpmkCalculator` | M |
| S4-03 | Service – `CplMkCalculator` (per mk_unit) + `CplMkUnitCalculator` (per mk_id × unit) | L |
| S4-04 | Service – `CplUnitAggregator` (prodi & non-prodi branch) | L |
| S4-05 | Job – `RecalkulasiCplJob` (queue cpl-calculation) + observer di `NilaiMahasiswa::saved` | M |
| S4-06 | Filament – Widget `CplProdiChart` + `CplFakultasChart` + `CplUniversitasChart` | XL |

### Sprint 5 — Hardening & Demo

| # | Task | Est |
|---|---|---|
| S5-01 | Test – Feature test: login → buat unit → kurikulum → input nilai → cek hasil_cpl_unit | L |
| S5-02 | Test – Unit test Calculators (5 tahap, edge cases nilai null) | L |
| S5-03 | Docs – README + onboarding 30 menit untuk developer baru | M |
| S5-04 | DevOps – Backup script harian + healthcheck endpoint | S |
| S5-05 | Demo – Skenario demo Kaprodi (15 menit script) | S |

---

## 3. Tech Stack Fix (Lock-in)

> **Aturan emas:** stack di bawah ini **tidak boleh** diganti setelah Sprint 1. Penambahan library boleh, penggantian core stack = re-review PRD/ERD.

### 3.1 Stack Inti

| Lapisan | Teknologi | Versi | Alasan |
|---|---|---|---|
| Container | **Docker** + Docker Compose | 26.x | Standardisasi env dev/staging/prod, sekali setup jalan di Linux/macOS/Windows WSL2 |
| Backend | **Laravel** | 13.x | Ekosistem PHP terlengkap untuk Filament; PHP 8.3+; anonymous-class migrations; streamlined bootstrap |
| Admin Panel | **Filament** | 3.x | Memenuhi 80% kebutuhan UI admin tanpa coding manual; native Livewire 3 |
| Database | **MySQL** | 8.0 | InnoDB + utf8mb4; UUID support; familier tim DBA UNSIL |
| Cache/Queue/Session | **Redis** | 7.x | Queue cpl-calculation cepat; session sharable antar worker |
| Process Manager | Supervisor | 4.x | Standar untuk worker queue Laravel di Ubuntu |
| Web Server | Nginx | 1.26 | Reverse proxy ke PHP-FPM; gzip/brotli |
| PHP Runtime | PHP-FPM | 8.3 | Wajib untuk Laravel 13 |
| RBAC | spatie/laravel-permission | ^6.0 | UUID-aware role/permission |
| Workflow | spatie/laravel-model-states | ^2.0 | State machine deklaratif untuk kurikulum & MK |
| Audit Log | spatie/laravel-activitylog | ^4.0 | Polymorphic, kompatibel UUID |
| AI (fase 2) | anthropic-ai/sdk | latest | Claude Opus 4.6 |
| Test | PHPUnit + Pest | latest | DSL ringkas, paralel runner |
| Static Analysis | Larastan (PHPStan) | level 6 | Tangkap bug sebelum runtime |
| Formatter | Laravel Pint | latest | PSR-12 + opini Laravel |

### 3.2 Yang **TIDAK** Dipakai (untuk menghindari kebingungan)

- ❌ **PostgreSQL** — kita pakai MySQL 8.
- ❌ **Inertia/React/Vue SPA terpisah** — Filament (Livewire 3) sudah cukup untuk MVP.
- ❌ **Microservices** — modular monolith (Vertical Slice) dulu.
- ❌ **Sanctum token API publik** — internal use, session-based; Sanctum opsional untuk Mobile fase 3.
- ❌ **Octane / Swoole** — belum perlu untuk skala UNSIL.

### 3.3 Stack Frontend (Untuk Filament v3)

Tidak ada SPA framework terpisah. Yang dipakai: **Livewire 3**, **Alpine.js**, **Tailwind CSS** (bundled Filament). Build aset via `npm run build` di Sprint 0.

---

## 4. Development Environment Setup (Docker-First)

### 4.1 Struktur Folder Awal

```text
silogy/
├── .docker/
│   ├── nginx/
│   │   └── default.conf
│   ├── php/
│   │   ├── Dockerfile
│   │   └── php.ini
│   └── mysql/
│       └── my.cnf
├── .github/
│   └── workflows/
│       └── ci.yml
├── app/
│   ├── Modules/             # Vertical Slice (ERD §System Design)
│   │   ├── Institusi/
│   │   ├── Auth/
│   │   ├── Mahasiswa/
│   │   ├── Kalender/
│   │   ├── Kurikulum/
│   │   ├── CPL/
│   │   ├── BoK/
│   │   ├── MK/
│   │   ├── Kelas/
│   │   ├── Penilaian/
│   │   ├── Kalkulasi/
│   │   ├── AI/
│   │   └── Audit/
│   ├── Models/              # Re-export untuk Filament
│   └── Providers/
├── bootstrap/app.php
├── config/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── resources/
├── routes/
├── storage/
├── tests/
│   ├── Feature/
│   └── Unit/
├── docker-compose.yml
├── .env.example
├── .env.docker
├── .gitignore
├── .gitattributes
├── pint.json
├── phpstan.neon
├── README.md
└── CONTRIBUTING.md
```

### 4.2 `docker-compose.yml` (untuk dev lokal)

```yaml
services:
  app:
    build:
      context: .
      dockerfile: .docker/php/Dockerfile
    container_name: silogy_app
    working_dir: /var/www/html
    volumes:
      - ./:/var/www/html
      - ./.docker/php/php.ini:/usr/local/etc/php/conf.d/silogy.ini:ro
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_started
    environment:
      PHP_IDE_CONFIG: serverName=silogy

  nginx:
    image: nginx:1.26-alpine
    container_name: silogy_nginx
    ports:
      - "8080:80"
    volumes:
      - ./:/var/www/html
      - ./.docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - app

  mysql:
    image: mysql:8.0
    container_name: silogy_mysql
    ports:
      - "3306:3306"
    environment:
      MYSQL_ROOT_PASSWORD: rootsecret
      MYSQL_DATABASE: silogy
      MYSQL_USER: silogy
      MYSQL_PASSWORD: silogy
    volumes:
      - mysql_data:/var/lib/mysql
      - ./.docker/mysql/my.cnf:/etc/mysql/conf.d/silogy.cnf:ro
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "127.0.0.1", "-uroot", "-prootsecret"]
      interval: 5s
      timeout: 3s
      retries: 20

  redis:
    image: redis:7-alpine
    container_name: silogy_redis
    ports:
      - "6379:6379"
    command: ["redis-server", "--appendonly", "yes"]
    volumes:
      - redis_data:/data

  mailpit:
    image: axllent/mailpit:latest
    container_name: silogy_mailpit
    ports:
      - "8025:8025"     # UI
      - "1025:1025"     # SMTP
    environment:
      MP_MAX_MESSAGES: 5000

  queue:
    build:
      context: .
      dockerfile: .docker/php/Dockerfile
    container_name: silogy_queue
    working_dir: /var/www/html
    command: ["php", "artisan", "queue:work", "redis", "--queue=cpl-calculation,ai-analysis,default", "--tries=3", "--timeout=180"]
    volumes:
      - ./:/var/www/html
    depends_on:
      - app
      - redis

volumes:
  mysql_data:
  redis_data:
```

### 4.3 `Dockerfile` — `.docker/php/Dockerfile`

```dockerfile
FROM php:8.3-fpm-alpine

# System deps
RUN apk add --no-cache \
      git curl bash zip unzip libzip-dev \
      icu-dev oniguruma-dev libxml2-dev \
      mysql-client mariadb-connector-c-dev \
      libpng-dev jpeg-dev freetype-dev \
      supervisor

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) \
      pdo pdo_mysql mysqli mbstring exif pcntl bcmath gd intl zip opcache

# Redis ext
RUN apk add --no-cache --virtual .build-deps autoconf gcc g++ make \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del .build-deps

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Sane defaults
RUN mkdir -p /var/www/html/storage/logs \
 && chown -R www-data:www-data /var/www/html

USER www-data
EXPOSE 9000
CMD ["php-fpm"]
```

### 4.4 `.docker/nginx/default.conf`

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php;

    client_max_body_size 32m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.ht { deny all; }
    location ~ /\.git { deny all; }
}
```

### 4.5 `.env.docker` (untuk dev di Docker)

```ini
APP_NAME=SILOGY
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8080
APP_LOCALE=id

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=silogy
DB_USERNAME=silogy
DB_PASSWORD=silogy

REDIS_HOST=redis
REDIS_PORT=6379
REDIS_CLIENT=phpredis

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_FROM_ADDRESS=silogy@unsil.test
MAIL_FROM_NAME="SILOGY DEV"

ANTHROPIC_API_KEY=
AI_MODEL=claude-opus-4-6
```

### 4.6 Branching Strategy

| Branch | Tujuan | Aturan |
|---|---|---|
| `main` | Production-ready | PR-only; protected; tag `vX.Y.Z` |
| `dev` | Integrasi sprint berjalan | Default base PR; auto-deploy ke staging |
| `feature/<modul>-<ticket>` | Pengerjaan task | Squash-merge ke `dev` |
| `hotfix/<ticket>` | Patch produksi | Merge ke `main` & `dev` |
| `release/<x.y.z>` | Stabilisasi sebelum tag | Hanya bugfix & docs |

**Conventional Commits:** `feat:`, `fix:`, `refactor:`, `docs:`, `test:`, `chore:`, `ci:`, `perf:`.
Contoh: `feat(kurikulum): tambah state setdosenmk`.

### 4.7 Linter / Formatter / Test

- **Pint** — `vendor/bin/pint` (PSR-12 + opsi Laravel). Konfigurasi `pint.json`:

  ```json
  { "preset": "laravel" }
  ```

- **Larastan** — `vendor/bin/phpstan analyse --level=6 app`.
- **Pest/PHPUnit** — `php artisan test --parallel`.
- **Pre-commit hook** (`.git/hooks/pre-commit`):

  ```bash
  #!/bin/sh
  docker compose exec -T app vendor/bin/pint --test || exit 1
  docker compose exec -T app vendor/bin/phpstan analyse --no-progress --memory-limit=512M || exit 1
  ```

### 4.8 Make-targets (Opsional, tapi sangat membantu)

`Makefile`:

```makefile
.PHONY: up down logs sh migrate fresh test pint stan seed

up:        ; docker compose up -d
down:      ; docker compose down
logs:      ; docker compose logs -f --tail=200
sh:        ; docker compose exec app sh
migrate:   ; docker compose exec app php artisan migrate
fresh:     ; docker compose exec app php artisan migrate:fresh --seed
test:      ; docker compose exec app php artisan test --parallel
pint:      ; docker compose exec app vendor/bin/pint
stan:      ; docker compose exec app vendor/bin/phpstan analyse
seed:      ; docker compose exec app php artisan db:seed
```

### 4.9 Bootstrapping Project (30 menit)

```bash
git clone git@github.com:unsil/silogy.git && cd silogy
cp .env.docker .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
# Buka http://localhost:8080/admin → login superadmin / Silogy2026!
```

---

## 5. Contract API & Data Flow

> SILOGY MVP **server-rendered** via Filament/Livewire, jadi "API publik" yang dibahas di sini adalah **internal HTTP endpoint** (untuk integrasi PDDIKTI, mobile dosen di fase 2, dan webhook AI). Semua endpoint tetap konsisten dengan kontrak di bawah ini agar tidak ada surprise saat ekspansi.

### 5.1 Response Envelope (Wajib untuk semua endpoint JSON)

**Success:**

```json
{
  "success": true,
  "data": { /* payload sesuai resource */ },
  "meta": {
    "request_id": "01J9G4D8M2N9V3X1Y7P5R6T8K0",
    "page": 1,
    "per_page": 25,
    "total": 132
  },
  "message": "OK"
}
```

**Error:**

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "Data tidak valid.",
    "details": {
      "nim": ["Field nim wajib diisi."],
      "academic_unit_id": ["Unit harus bertipe study_program."]
    }
  },
  "meta": {
    "request_id": "01J9G4D8M2N9V3X1Y7P5R6T8K0"
  }
}
```

### 5.2 HTTP Status Code

| Kategori | Code | Kapan dipakai |
|---|---|---|
| 200 OK | sukses GET/PATCH/PUT |
| 201 Created | sukses POST resource baru |
| 204 No Content | sukses DELETE |
| 400 Bad Request | request malformed (bukan validation) |
| 401 Unauthorized | belum login |
| 403 Forbidden | login tapi policy gagal |
| 404 Not Found | resource tidak ada / scope policy menyembunyikan |
| 409 Conflict | UQ collision (mis. `(kelas_mk_id, mahasiswa_id)`) |
| 422 Unprocessable Entity | validation error (FormRequest gagal) |
| 429 Too Many Requests | rate limit |
| 500 Server Error | exception tidak tertangani |

### 5.3 Naming Convention API

- URL **kebab-case** + plural resource: `/api/v1/academic-units`, `/api/v1/kelas-mk`, `/api/v1/nilai-mahasiswas`.
- Field JSON **snake_case** sesuai kolom DB: `academic_unit_id`, `subcpmk_komponenpenilaian_id`.
- Versi prefix: `/api/v1/...`.
- Filter via query: `?academic_unit_id=...&semester_id=...&page=2&per_page=50`.
- Sort: `?sort=-created_at,nama` (minus = desc).
- Include relasi: `?include=mkUnit.mk,dosenPengampu`.

### 5.4 Resource & Form Request Pattern

```php
// app/Http/Resources/MahasiswaResource.php
public function toArray($request): array
{
    return [
        'id'                 => $this->id,
        'nim'                => $this->nim,
        'nama'               => $this->nama,
        'jenis_kelamin'      => $this->jenis_kelamin,
        'angkatan'           => $this->angkatan,
        'academic_unit_id'   => $this->academic_unit_id,
        'academic_unit'      => AcademicUnitResource::make($this->whenLoaded('academicUnit')),
        'email'              => $this->email,
        'nomor_wa'           => $this->nomor_wa,
        'status'             => $this->status,
        'created_at'         => $this->created_at?->toIso8601String(),
        'updated_at'         => $this->updated_at?->toIso8601String(),
    ];
}

// app/Http/Requests/StoreMahasiswaRequest.php
public function rules(): array
{
    return [
        'nim'              => ['required', 'string', 'max:20', 'unique:mahasiswas,nim'],
        'nama'             => ['required', 'string', 'max:150'],
        'academic_unit_id' => [
            'required', 'uuid',
            Rule::exists('academic_units', 'id')->where('type', 'study_program'),
        ],
        'angkatan'  => ['nullable', 'digits:4'],
        'nomor_wa'  => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]+$/'],
        'email'     => ['nullable', 'email'],
        'status'    => ['required', Rule::in(['aktif','cuti','lulus','do','nonaktif'])],
    ];
}
```

### 5.5 Data Flow — Input Nilai → CPL Dashboard

```text
[UI Dosen Pengampu — Filament Form]
        │ (Livewire wire:submit)
        ▼
[NilaiMahasiswaController::store]
        │ FormRequest validation
        ▼
[NilaiMahasiswa::updateOrCreate]
        │ Observer: dispatch(RecalkulasiCplJob)
        ▼
[Queue: cpl-calculation]
        │ Worker (Supervisor) ─┐
        │                     │
        │   SubcpmkCalculator → hasil_subcpmk
        │   CpmkCalculator    → hasil_cpmk
        │   CplMkCalculator   → hasil_cpl_mk (mk_unit_id)
        │   CplMkUnitCalculator → hasil_cpl_mk_unit (mk_id × unit)
        │   CplUnitAggregator → hasil_cpl_unit (per academic_unit_id)
        │
        ▼
[Cache invalidate: dashboard:{academic_unit_id}:{semester_id}]
        │
        ▼
[Filament Dashboard Widget — Livewire poll/refresh]
```

### 5.6 Versioning & Deprecation

- API JSON di-version via URL (`/api/v1`, `/api/v2`).
- Deprecation diumumkan via header `Deprecation: Mon, 01 Jan 2027 00:00:00 GMT` + `Sunset: Tue, 01 Jul 2027 00:00:00 GMT`.
- Minimal **6 bulan** masa transisi sebelum versi dihapus.

---

## 6. Coding Convention & Standard

### 6.1 PHP / Laravel

- **PSR-12** + Laravel Pint preset `laravel`.
- **Class**: `PascalCase` — `AcademicUnit`, `RecalkulasiCplJob`.
- **Method / variabel**: `camelCase` — `hitungBobotCpl()`, `$kelasMkId`.
- **DB column / JSON key**: `snake_case` — `academic_unit_id`, `nomor_wa`, `is_active`.
- **Konstanta enum / config**: `SCREAMING_SNAKE` — `STATUS_AKTIF`, `MAX_TOKEN_AI`.
- **Bahasa nama domain**: **Bahasa Indonesia** untuk istilah domain akademik (mahasiswa, kurikulum, kelas_mk, hasil_cpl). Bahasa Inggris untuk istilah teknis Laravel (Job, Service, Resource, Policy, Observer).
- **File ≤ 300 baris**. Lebih dari itu = refactor jadi service/trait.
- **Method ≤ 30 baris**. Lebih dari itu = ekstrak method privat.

### 6.2 Struktur Modul (Vertical Slice)

```text
app/Modules/<Domain>/
├── Models/
├── Filament/
│   ├── Resources/
│   └── Pages/
├── Http/
│   ├── Controllers/      # untuk API publik
│   ├── Requests/
│   └── Resources/
├── Policies/
├── Services/             # business logic murni
├── Jobs/
├── Observers/
├── States/               # spatie/model-states
└── Tests/                # test domain ini
```

> **Aturan ketergantungan antar modul:** modul boleh memanggil modul lain hanya via **Service** atau **Event** — jangan akses model lintas modul langsung.

### 6.3 Penanganan Error

- **Domain exception** custom — `app/Exceptions/Domain/`:

  ```php
  final class KurikulumTidakDapatDiaktifkan extends DomainException {}
  ```

- **Render Exception** sentral di `bootstrap/app.php` (Laravel 13 style):

  ```php
  ->withExceptions(function (Exceptions $exceptions) {
      $exceptions->render(function (DomainException $e, Request $req) {
          if ($req->expectsJson()) {
              return response()->json([
                  'success' => false,
                  'error' => [
                      'code'    => Str::snake(class_basename($e))->upper(),
                      'message' => $e->getMessage(),
                  ],
              ], 422);
          }
      });
  })
  ```

- **Validation error** → 422 dengan struktur `error.details` (lihat §5.1).
- **Authorization error** (Policy) → 403 dengan pesan Bahasa Indonesia.

### 6.4 Logging

- Channel default `daily`, retensi 14 hari.
- Channel `cpl-calc` untuk job kalkulasi (rotasi mingguan).
- Channel `ai` untuk integrasi Anthropic (rotasi harian + audit).
- Format: `[YYYY-MM-DD HH:MM:SS] env.LEVEL: message {context}`.
- **Larang**: `dd()`, `dump()`, `error_log()` di kode production.
- **Wajib**: Log `request_id` (ULID) per request (middleware `AddRequestId`).

### 6.5 Migration Convention

- Penamaan: `YYYY_MM_DD_HHMMSS_<verb>_<table>_table.php`.
- Anonymous class (default Laravel 9+, tetap pada 13).
- **Wajib `down()`** untuk semua migration MVP — fasilitasi `migrate:rollback` saat dev.
- **Wajib index pada FK** dan kolom yang sering di-filter (`status`, `is_active`, `semester_id`).
- **Wajib UQ composite** sesuai ERD (mis. `(mk_unit_id, semester_id, kode_kelas)`).

### 6.6 Test Convention

- File: `tests/Unit/<Modul>/<Class>Test.php` atau `tests/Feature/<Modul>/<UseCase>Test.php`.
- Naming test: `it_<kondisi>_<ekspektasi>` (Pest) atau `test_<snake_case>` (PHPUnit).
- Minimal coverage:
  - Service domain: **≥ 80%**
  - Calculators (Tahap 1–5 CPL): **≥ 95%**
  - Policy: **≥ 90%**

### 6.7 Komitmen Git

- 1 PR = 1 task (≤ 400 baris diff murni). Lebih dari itu → split.
- PR template wajib: deskripsi, screenshot UI (jika perubahan Filament), checklist DoD (§10).
- Code review: minimal 1 reviewer di luar pembuat.
- Merge style: **squash** ke `dev` (commit history bersih).

---

## 7. Seed Data / Dummy Data

> Tujuan: setelah `make fresh`, developer langsung bisa demo end-to-end **tanpa input manual**.

### 7.1 Daftar Seeder & Urutan

```text
DatabaseSeeder
 ├─ AcademicUnitSeeder      (3+ unit lintas level)
 ├─ RolePermissionSeeder    (sesuai ERD §13)
 ├─ SemesterSeeder          (8 semester: 20241..20272)
 ├─ EvaluasiSeeder          (9 jenis evaluasi)
 ├─ MahasiswaSeeder         (≥ 30 mahasiswa per prodi)
 ├─ KurikulumSeeder         (1 kurikulum aktif per prodi + profil + CPL + BoK)
 ├─ MkSeeder                (≥ 6 MK per prodi + mk_units)
 ├─ KelasMkSeeder           (≥ 1 kelas per MK + dosen + koordinator)
 ├─ KomponenPenilaianSeeder (UTS/UAS/Quiz/Tugas per kelas)
 └─ NilaiMahasiswaSeeder    (random nilai 60–95 untuk semua kombinasi)
```

### 7.2 Pola Data Sample

**AcademicUnitSeeder:**

```php
$univ = AcademicUnit::factory()->create([
    'type' => 'university',
    'nama' => 'Universitas Siliwangi',
    'code' => 'UNSIL',
    'status' => 'aktif',
]);

$fak = AcademicUnit::factory()->create([
    'parent_id' => $univ->id,
    'type' => 'faculty',
    'nama' => 'Fakultas Teknik',
    'code' => 'FT',
    'status' => 'aktif',
]);

$jur = AcademicUnit::factory()->create([
    'parent_id' => $fak->id,
    'type' => 'department',
    'nama' => 'Jurusan Informatika',
    'code' => 'INF',
    'status' => 'aktif',
]);

AcademicUnit::factory()->create([
    'parent_id' => $jur->id,
    'type'      => 'study_program',
    'nama'      => 'S1 Teknik Informatika',
    'code'      => 'S1-IF',
    'kode_pddikti' => '57201',
    'jenjang'   => 'S1',
    'gelar_lulusan' => 'S.Kom.',
    'status'    => 'aktif',
]);
```

**MahasiswaFactory:**

```php
public function definition(): array
{
    return [
        'id'             => (string) Str::uuid(),
        'nim'            => fake()->numerify('##########'),
        'nama'           => fake('id_ID')->name(),
        'jenis_kelamin'  => fake()->randomElement(['L','P']),
        'angkatan'       => (string) fake()->numberBetween(2021, 2025),
        'email'          => fake()->unique()->safeEmail(),
        'nomor_wa'       => '628'.fake()->numerify('#########'),
        'status'         => 'aktif',
    ];
}
```

**Akun siap pakai (dari `RolePermissionSeeder` v6):**

| Username | Password | Role |
|---|---|---|
| `superadmin` | `Silogy2026!` | Super Admin |
| `rektor` | `Silogy2026!` | Pimpinan Universitas |
| `wakilrektor` | `Silogy2026!` | Pimpinan Universitas |
| `dekan` | `Silogy2026!` | Pimpinan Fakultas |
| `wakildekan` | `Silogy2026!` | Pimpinan Fakultas |
| `kajur` | `Silogy2026!` | Pimpinan Jurusan |
| `sekjur` | `Silogy2026!` | Pimpinan Jurusan |
| `kaprodi` | `Silogy2026!` | Pimpinan Program Studi |
| `adminuniv`/`adminfak`/`adminjur`/`adminprodi` | `Silogy2026!` | Admin per level |
| `timkur` | `Silogy2026!` | Tim Kurikulum |
| `korma` | `Silogy2026!` | Koordinator Mata Kuliah |
| `dosen` | `Silogy2026!` | Dosen Pengampu |
| `auditor` | `Silogy2026!` | Auditor Mutu |

### 7.3 Aturan Seeder

- **Idempoten** — pakai `firstOrCreate` / `updateOrCreate` agar `db:seed` ulang aman.
- **Tidak ada hard-coded UUID** — pakai `Str::uuid()` atau factory.
- **Jangan seed data sensitif** (NIDN/NIK asli) di non-production.
- **Skenario lengkap** — minimal satu prodi memiliki: 1 kurikulum aktif, 2 CPL, 3 BoK, 4 MK, 2 kelas/MK, 30 mahasiswa/kelas, nilai lengkap → hasil_cpl_unit muncul di dashboard.

---

## 8. Risk Identification & Mitigation

| # | Risiko | Probabilitas | Impact | Mitigasi |
|---|---|---|---|---|
| R-01 | **Migrasi data v5 → v6 hilang** karena drop tabel `universitas/fakultas/jurusan/prodis` | Sedang | Tinggi | Backup penuh + skrip migrasi UUID lama→baru terdokumentasi (System Architecture §7); jalankan di staging dulu |
| R-02 | **Performa kalkulasi CPL** lambat saat agregat universitas (banyak `mk_units` × mahasiswa) | Tinggi | Tinggi | Index ketat di `hasil_cpl_*`; chunk job per kelas_mk; eager loading; cache `dashboard:*` Redis dengan TTL 10 menit |
| R-03 | **Policy bocor lintas unit** (mis. Tim Kurikulum prodi A bisa edit prodi B) | Sedang | Tinggi | Test feature wajib untuk setiap Resource Filament (scope per `academic_unit_users`); audit log otomatis |
| R-04 | **Konsistensi rantai pivot** rusak (mis. `subcpmk` tanpa `mk_cpmk_id` valid) | Sedang | Sedang | FK CASCADE/RESTRICT sesuai ERD; constraint `NOT NULL`; test factory generator yang lengkap |
| R-05 | **AI Anthropic API cost overrun** saat dipakai banyak Pimpinan | Rendah (MVP off) | Tinggi | Rate-limit per user per hari; tracking token di `analisis_ai.token_digunakan`; circuit breaker bila kuota habis |
| R-06 | **Bobot komponen penilaian salah** (total ≠ 100%) | Tinggi | Sedang | Validator `BobotKomponenSama100Rule` saat save; warning di UI; fix dengan default `bobot=100` (v6) |
| R-07 | **UUID collision / serialisasi UUID antar tabel** (model_morph_key) | Rendah | Tinggi | Set `permission.column_names.model_morph_key='model_uuid'`; trait `HasUuids` di `User` & `Role`; test PHPUnit |
| R-08 | **Docker MySQL volume corrupt** saat dev | Sedang | Sedang | `make fresh` cepat; backup snapshot mingguan; dokumentasikan `docker volume rm silogy_mysql_data` |
| R-09 | **Drift antara migration & seeder** (seeder pakai kolom yang dihapus) | Sedang | Sedang | CI menjalankan `migrate:fresh --seed` di setiap PR |
| R-10 | **Filament breaking change** saat upgrade minor | Rendah | Sedang | Pin versi `^3.0` di composer; baca changelog sebelum `composer update` |

---

## 9. First Vertical Slice (Sprint 1 — Demo 7 hari)

> **Aturan emas vertical slice:** kirim **1 fitur end-to-end** sebelum menyentuh fitur kedua.

### 9.1 Slice yang Dipilih: **Login → Lihat Daftar Mahasiswa Prodi-nya**

Dipilih karena menyentuh **semua lapisan** stack tanpa kompleksitas kalkulasi:

```text
Browser
   │  http://localhost:8080/admin/login
   ▼
[Nginx → PHP-FPM → Laravel 13]
   │  POST /admin/login (username/email + password)
   ▼
[AuthController → User::where(...)]
   │  cek bcrypt password
   │  Session::regenerate()
   ▼
[Middleware: auth, Filament panel]
   │  load roles & permissions (Spatie UUID)
   ▼
[Filament MahasiswaResource → List Page]
   │  Eloquent: Mahasiswa::query()
   │  ↳ scoped via MahasiswaPolicy → academic_unit_users
   ▼
[MySQL: SELECT ... WHERE academic_unit_id IN (...)]
   ▼
[Render Livewire table → Browser]
```

### 9.2 Checklist Lapisan untuk Slice Ini

| Lapisan | Task | Selesai jika… |
|---|---|---|
| **Infra** | Docker compose jalan (`make up`) | `curl http://localhost:8080` → 200 |
| **DB** | Migration `users`, `academic_units`, `academic_unit_users`, `mahasiswas` ter-jalan | `php artisan migrate:status` semua green |
| **Seed** | `RolePermissionSeeder` + `AcademicUnitSeeder` + `MahasiswaSeeder` | Login `adminprodi` ada; ≥ 30 mahasiswa di prodi |
| **Auth** | Login form Filament + custom validator username/email | Berhasil login & redirect ke `/admin` |
| **Model** | `User`, `AcademicUnit`, `AcademicUnitUser`, `Mahasiswa` dengan HasUuids | Relasi `user->academicUnits` dan `mahasiswa->academicUnit` jalan |
| **Policy** | `MahasiswaPolicy::viewAny` filter via `academic_unit_users` | Admin prodi A tidak melihat mahasiswa prodi B |
| **Resource** | `MahasiswaResource` (table: nim, nama, angkatan, status) | List page render & search jalan |
| **Test** | `MahasiswaPolicyTest::test_admin_prodi_hanya_lihat_unitnya` | PHPUnit green di CI |
| **Docs** | README "How to run" + screenshot login | Developer baru bisa setup ≤ 30 menit |

### 9.3 Acceptance Demo (15 menit)

1. `make fresh` — DB di-reset & di-seed.
2. Buka `http://localhost:8080/admin/login`.
3. Login `adminprodi` / `Silogy2026!`.
4. Klik menu **Mahasiswa** — tampil ≥ 30 mahasiswa dari prodi yang ditugaskan.
5. Logout, login `adminprodi-lain` (dummy) — daftar mahasiswa berbeda (scoping bekerja).
6. Login `auditor` — bisa lihat semua mahasiswa (read-only).
7. Lihat audit log: terdapat entri login di `activity_log`.

**Slice dianggap berhasil** bila 7 langkah di atas berjalan tanpa modifikasi kode di tengah demo.

---

## 10. Definition of Done (DoD)

> Sebuah task/fitur **TIDAK** boleh ditutup sampai **SEMUA** poin di bawah ini ✅.

### 10.1 DoD Task / PR

- [ ] **Code** — semua perubahan ada di file yang sesuai modul (Vertical Slice).
- [ ] **Test** — minimal 1 happy path + 1 edge case (`php artisan test --filter=...`).
- [ ] **Migration & Seeder** — `migrate:fresh --seed` jalan tanpa error di CI.
- [ ] **Validation** — FormRequest / Filament Rule lengkap (tidak ada validasi di controller).
- [ ] **Authorization** — Policy / Filament Shield gate ada & ter-test.
- [ ] **Error handling** — domain exception ter-render dengan envelope §5.1.
- [ ] **Logging** — operasi penting (login, kalkulasi, AI call) ter-log dengan `request_id`.
- [ ] **Pint clean** — `vendor/bin/pint --test` exit 0.
- [ ] **Larastan clean** — `vendor/bin/phpstan analyse` exit 0 (level 6).
- [ ] **UI screenshot** (jika ada perubahan Filament) — dilampirkan di PR.
- [ ] **Docs** — README/CHANGELOG updated bila perilaku publik berubah.
- [ ] **Review** — ≥ 1 reviewer approve.

### 10.2 DoD Fitur (Modul MVP)

- [ ] Semua task PR di modul tersebut tertutup.
- [ ] Test E2E (Pest feature test) untuk minimal **1 user-flow lengkap** lewat.
- [ ] Performance check: halaman utama Filament < 2 detik (load time DevTools).
- [ ] Skenario role: setiap role di matriks RBAC v6 sudah dicoba akses fitur (pass/blocked sesuai).
- [ ] Seeder menghasilkan data lengkap agar fitur "terlihat hidup".
- [ ] Backup recovery dicoba (untuk modul yang menyentuh data kritis).
- [ ] Tidak ada `TODO` / `FIXME` tanpa nomor tiket terkait.

### 10.3 DoD Release Sprint

- [ ] Semua fitur MVP sprint berjalan di staging (Docker compose identik produksi).
- [ ] Migrasi data dari v5 ke v6 (jika ada) dicoba minimal sekali di staging.
- [ ] Smoke test 7 user-flow MVP (lihat §1.4) hijau.
- [ ] Tag `vX.Y.Z` di `main` + release notes.
- [ ] Demo ke stakeholder (Kaprodi/Tim Kurikulum) terjadwal.

---

## Lampiran A — Checklist Kesiapan Vibe-Coding (1 Halaman)

```text
[ ] PRD/ERD/System Design/System Architecture v6 sudah diteken
[ ] Repo Git di-init + branching strategy didokumentasi (CONTRIBUTING.md)
[ ] Docker Compose jalan (app, nginx, mysql, redis, mailpit, queue) di laptop semua dev
[ ] Laravel 13 + Filament v3 + Spatie Permission UUID terinstall
[ ] Pint + Larastan + Pest + CI Actions hijau di branch dev
[ ] AcademicUnitSeeder + RolePermissionSeeder + minimal 1 prodi siap demo
[ ] .env.example & .env.docker konsisten (dokumentasi key wajib)
[ ] First Vertical Slice (Login → List Mahasiswa) didefinisikan
[ ] Definition of Done dipajang di README & PR template
[ ] Risk list dibahas tim & owner mitigasi ditunjuk
[ ] Standup harian 15 menit dijadwalkan (sinkron blocker)
[ ] Backlog non-MVP didokumentasi (jangan dicampur ke board sprint)
```

---

## Lampiran B — Snippet Template (Copy-Paste Saat Vibe-Coding)

### B.1 Model UUID Boilerplate

```php
<?php

namespace App\Modules\Institusi\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicUnit extends Model
{
    use HasUuids, HasFactory;

    protected $table = 'academic_units';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(AcademicUnitUser::class);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
```

### B.2 Policy Berbasis `academic_unit_users`

```php
<?php

namespace App\Modules\Mahasiswa\Policies;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Mahasiswa\Models\Mahasiswa;

class MahasiswaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('kelola_user_prodi')
            || $user->hasRole(['Super Admin', 'Auditor Mutu']);
    }

    public function view(User $user, Mahasiswa $mhs): bool
    {
        if ($user->hasRole(['Super Admin', 'Auditor Mutu'])) return true;

        return AcademicUnitUser::where('user_id', $user->id)
            ->where('academic_unit_id', $mhs->academic_unit_id)
            ->exists();
    }
}
```

### B.3 Job Kalkulasi Skeleton

```php
<?php

namespace App\Modules\Kalkulasi\Jobs;

use App\Modules\Kalkulasi\Services\{
    SubcpmkCalculator, CpmkCalculator,
    CplMkCalculator, CplMkUnitCalculator, CplUnitAggregator
};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalkulasiCplJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'cpl-calculation';
    public int $tries = 3;
    public int $timeout = 180;

    public function __construct(
        public string $kelasMkId,
        public string $academicUnitId,
        public string $semesterId,
    ) {}

    public function handle(
        SubcpmkCalculator   $s1,
        CpmkCalculator      $s2,
        CplMkCalculator     $s3,
        CplMkUnitCalculator $s4,
        CplUnitAggregator   $s5,
    ): void {
        $s1->calculate($this->kelasMkId);
        $s2->calculate($this->kelasMkId);
        $s3->calculate($this->kelasMkId, $this->semesterId);
        $s4->calculate($this->academicUnitId, $this->semesterId);
        $s5->aggregate($this->academicUnitId, $this->semesterId);
    }
}
```

---

*Dokumen ini adalah kontrak antara tim & masa depan kita sendiri. Disiplin pada §10 (DoD) adalah cara termudah memastikan vibe-coding tidak berakhir dengan refactor besar di Sprint 4.*
