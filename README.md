# SILOGY

**Siliwangi Learning Outcomes & Quality Analytics** — platform manajemen dan analitik capaian pembelajaran berbasis paradigma **Outcome-Based Education (OBE)** untuk Universitas Siliwangi.

**Versi rilis:** [6.0.0](CHANGELOG.md#600---2026-05-18) (MVP) · Stack: Laravel 13 · MySQL 8 · Redis 7 · Filament v4 · Docker Compose

[![CI](https://github.com/unsil/silogy/actions/workflows/ci.yml/badge.svg?branch=dev)](https://github.com/unsil/silogy/actions/workflows/ci.yml)
[![Release](https://img.shields.io/github/v/release/unsil/silogy?include_prereleases&label=release)](https://github.com/unsil/silogy/releases)
![License](https://img.shields.io/badge/License-Proprietary-red)

---

## Tiga Pilar SILOGY

| Pilar | Fokus | Outcome untuk institusi |
|---|---|---|
| **Pengukuran** | Rantai nilai terstruktur: mahasiswa → sub-CPMK → CPMK → CPL | Setiap capaian dapat ditelusuri ke bukti penilaian di kelas |
| **Analitik** | Mesin kalkulasi 5 tahap + dashboard CPL per `academic_unit`, plus rollup KPI lintas-kurikulum untuk Pimpinan | Pimpinan melihat persentase tercapai vs target kurikulum per semester, lintas unit dalam satu dashboard |
| **Peningkatan** | Rekomendasi berbasis data lintas unit | Tim kurikulum dan prodi punya dasar empiris untuk revisi kurikulum & pembelajaran |

---

## Dokumen Referensi

| Dokumen | Isi |
|---|---|
| [SILOGY_PRD_v6.md](docs/SILOGY_PRD_v6.md) | Fitur MVP, RBAC, user stories |
| [SILOGY_ERD_Database_Design_v6.md](docs/SILOGY_ERD_Database_Design_v6.md) | Skema database & migration |
| [SILOGY_System_Design_v6.md](docs/SILOGY_System_Design_v6.md) | Arsitektur modul & workflow state |
| [SILOGY_System_Architecture_v6.md](docs/SILOGY_System_Architecture_v6.md) | Deployment & monitoring |
| [SILOGY_PreVibeCoding_v6.md](docs/SILOGY_PreVibeCoding_v6.md) | DoD, konvensi kode, sprint breakdown |

**Panduan developer & demo:**

- [ONBOARDING.md](docs/ONBOARDING.md) — setup laptop & alur 30 menit
- [DEMO_SCRIPT.md](docs/DEMO_SCRIPT.md) — skrip presentasi 15 menit

**Panduan pengguna (per role):**

- [docs/user-manual/](docs/user-manual/00-README.md) — indeks panduan, satu file per role (Super Admin, Admin Unit, Tim Kurikulum, Koordinator MK, Dosen Pengampu, Pimpinan & Auditor)

---

## Quick Start (Docker)

Prasyarat: [Docker Desktop](https://www.docker.com/products/docker-desktop/) (atau Docker Engine + Compose v2) dan `make` (Git Bash / WSL / Linux / macOS).

```bash
git clone https://github.com/unsil/silogy.git
cd silogy
cp .env.docker .env
make up
make fresh
```

Buka aplikasi:

| URL | Keterangan |
|---|---|
| http://localhost:8008/ | Landing SILOGY (publik) |
| http://localhost:8008/login | Masuk (Filament) |
| http://localhost:8008/dashboard | Dashboard (setelah masuk; panel Filament kini di akar domain) |
| http://localhost:8008/admin | Alihan permanen menuju `/dashboard` (kompatibilitas URL lama) |

Login awal: **`superadmin`** / **`siliwangi`**

Layanan tambahan setelah `make up`:

| Layanan | URL / Port |
|---|---|
| Mailpit (email dev) | http://localhost:8025 |
| MySQL | `localhost:3306` (user `silogy` / pass `silogy`) |
| Redis | `localhost:6379` |

---

## Akun Siap Pakai (setelah `make fresh`)

Password default semua akun: **`siliwangi`**

| Username | Role |
|---|---|
| `superadmin` | Super Admin |
| `rektor` | Pimpinan (penugasan universitas) |
| `wakilrektor` | Pimpinan (penugasan universitas) |
| `dekan` | Pimpinan (penugasan fakultas) |
| `wakildekan` | Pimpinan (penugasan fakultas) |
| `kajur` | Pimpinan (penugasan jurusan) |
| `sekjur` | Pimpinan (penugasan jurusan) |
| `kaprodi` | Pimpinan (penugasan prodi) |
| `adminuniv` | Admin (penugasan universitas) |
| `adminfak` | Admin (penugasan fakultas) |
| `adminjur` | Admin (penugasan jurusan) |
| `adminprodi` | Admin (penugasan prodi) |
| `timkur` | Tim Kurikulum (prodi) |
| `timkurfak` | Tim Kurikulum (fakultas) |
| `timkuruniv` | Tim Kurikulum (universitas) |
| `dosentimkur` | Dosen Pengampu + Tim Kurikulum (universitas, fakultas, prodi) |
| `korma` | Koordinator Mata Kuliah |
| `dosenuniv` | Dosen Pengampu (penugasan universitas) |
| `dosenfak` | Dosen Pengampu (penugasan fakultas) |
| `dosenjur` | Dosen Pengampu (penugasan jurusan) |
| `dosen` | Dosen Pengampu |
| `auditor` | Auditor Mutu |

Sumber lengkap: [PreVibeCoding §7.2](docs/SILOGY_PreVibeCoding_v6.md).

### Seeder Simulasi Sistem

`make fresh` menjalankan `SimulasiSistemSeeder` — seeder mandiri yang mengisi
seluruh kebutuhan simulasi end-to-end pada **Prodi S1 Pendidikan Matematika
dengan FKIP sebagai induk langsung** (jurusan bersifat opsional dalam hierarki):
unit akademik, role + akun, semester, mahasiswa, kurikulum aktif, profil lulusan,
CPL, BoK, MK (prodi + universitas + fakultas), CPMK, Sub-CPMK, kelas, komponen
penilaian, nilai, hingga hasil kalkulasi CPL beserta agregasinya ke FKIP dan
universitas. Untuk menjalankannya sendiri:

```bash
docker compose exec app php artisan db:seed --class=SimulasiSistemSeeder
```

Catatan: pengaturan pengguna (`/users`) kini eksklusif untuk `superadmin`.

---

## Perintah Make

| Perintah | Fungsi |
|---|---|
| `make up` | Build & jalankan container, `composer install`, generate `APP_KEY` |
| `make migrate` | Jalankan migration saja (tanpa seed) |
| `make fresh` | `migrate:fresh --seed` (data demo siap dipakai) |
| `make seed` | Jalankan seeder saja (tanpa fresh migrate) |
| `make down` | Hentikan container |
| `make logs` | Tail log semua service |
| `make sh` | Shell ke container `app` |
| `make test` | Jalankan test paralel di container |
| `make pint` | Format kode (Pint) |
| `make stan` | Static analysis (Larastan level 6) |

---

## Kualitas Kode & Test

Di dalam container (`make sh`) atau host dengan PHP 8.3+:

```bash
composer pint -- --test
composer stan
composer test:parallel
```

Test E2E MVP (alur lengkap §1.5 PreVibeCoding):

```bash
composer test:parallel -- --filter=MvpEndToEndTest
```

| Perintah | Durasi referensi |
|---|---|
| `php artisan test --filter=MvpEndToEndTest` | ~2 s |
| `composer test:parallel --filter=MvpEndToEndTest` | ~11 s |

Target DoD: **< 30 detik** untuk filter di atas.

---

## Operasional Produksi

### Health check

Endpoint publik (tanpa auth), envelope JSON §5.1 PreVibeCoding:

```bash
curl -s http://localhost:8008/health | jq
```

Contoh respons sehat:

```json
{
  "success": true,
  "data": {
    "db": "ok",
    "redis": "ok",
    "disk_free": "42.15GB"
  },
  "meta": { "request_id": "01J9..." },
  "message": "OK"
}
```

Rate limit: **60 request/menit** per IP.

### Backup harian

Skrip: [`scripts/silogy-backup.sh`](scripts/silogy-backup.sh) — `mysqldump` database `silogy_prod` (single-transaction, routines, triggers) → gzip → enkripsi `openssl aes-256-cbc -pbkdf2`, plus arsip `storage/app/public`, retensi **30 hari**.

1. Salin & sesuaikan variabel di `/etc/silogy/backup.env`:

```bash
SILOGY_DB_USER=silogy
SILOGY_DB_PASS=<password-produksi>
SILOGY_BACKUP_KEY=<passphrase-enkripsi-panjang>
SILOGY_APP_ROOT=/var/www/silogy
SILOGY_BACKUP_DIR=/var/backups/silogy
```

2. Jadwalkan cron **01:00** (user deploy, mis. `www-data` atau `silogy`):

```cron
0 1 * * * . /etc/silogy/backup.env && /var/www/silogy/scripts/silogy-backup.sh >> /var/log/silogy/backup.log 2>&1
```

3. Pastikan direktori log & backup ada:

```bash
sudo mkdir -p /var/log/silogy /var/backups/silogy
sudo chown deploy:deploy /var/log/silogy /var/backups/silogy
chmod +x /var/www/silogy/scripts/silogy-backup.sh
```

Detail recovery: [System Architecture §5](docs/SILOGY_System_Architecture_v6.md).

---

## Kontribusi

Branching, conventional commits, dan DoD PR: [CONTRIBUTING.md](CONTRIBUTING.md).

---

## Lisensi

**Proprietary** — Universitas Siliwangi. Tidak untuk redistribusi tanpa izin tertulis.
