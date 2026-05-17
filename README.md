# SILOGY

**Siliwangi Learning Outcomes & Quality Analytics** — platform analitik capaian pembelajaran berbasis paradigma **Outcome-Based Education (OBE)** untuk Universitas Siliwangi.

Stack: Laravel 13 · MySQL 8 · Redis 7 · Filament v3 · FlyEnv (Windows).

[![CI](https://github.com/unsil/silogy/actions/workflows/ci.yml/badge.svg)](https://github.com/unsil/silogy/actions/workflows/ci.yml)

## Dokumen Referensi

| Dokumen | Isi |
|---|---|
| [SILOGY_PRD_v6.md](docs/SILOGY_PRD_v6.md) | Fitur MVP, RBAC, user stories |
| [SILOGY_ERD_Database_Design_v6.md](docs/SILOGY_ERD_Database_Design_v6.md) | Skema database & migration |
| [SILOGY_System_Design_v6.md](docs/SILOGY_System_Design_v6.md) | Arsitektur modul & workflow state |
| [SILOGY_System_Architecture_v6.md](docs/SILOGY_System_Architecture_v6.md) | Deployment & monitoring |
| [SILOGY_PreVibeCoding_v6.md](docs/SILOGY_PreVibeCoding_v6.md) | DoD, konvensi kode, sprint breakdown |

## Menjalankan Lokal (FlyEnv / Windows)

**Opsi A — FlyEnv (disarankan)**

Pastikan site `silogy.test` sudah dikonfigurasi di FlyEnv, lalu buka:

```
http://silogy.test
```

Panel admin Filament: `http://silogy.test/admin`

**Opsi B — Artisan serve**

```bash
php artisan serve
```

Buka `http://127.0.0.1:8000` (atau port yang ditampilkan).

## Setup Awal

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
```

Detail kontribusi, branching, dan DoD: lihat [CONTRIBUTING.md](CONTRIBUTING.md).

## Lisensi

Proprietary — Universitas Siliwangi.
