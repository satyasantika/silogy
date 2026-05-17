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

## Tooling Kualitas Kode (tanpa Docker)

Stack: **Pint** · **Larastan (level 6)** · **Pest** · test paralel via SQLite `:memory:`.

| Perintah | Fungsi |
|---|---|
| `composer pint` | Format kode (PSR-12 / Laravel preset) |
| `composer pint -- --test` | Cek format tanpa menulis file |
| `composer stan` | Static analysis Larastan level 6 |
| `composer test:parallel` | Test paralel (Pest + PHPUnit) |

### Helper PowerShell (`scripts/`)

Jalankan dari root proyek dengan FlyEnv PHP di PATH:

```powershell
pwsh scripts/test.ps1    # php artisan test --parallel
pwsh scripts/lint.ps1    # pint --test + phpstan
pwsh scripts/fresh.ps1   # migrate:fresh --seed
pwsh scripts/serve.ps1   # artisan serve :8000 (alternatif FlyEnv)
```

Contoh validasi cepat sebelum PR:

```powershell
composer pint -- --test
composer stan
pwsh scripts/test.ps1
```

## Lisensi

Proprietary — Universitas Siliwangi.
