# Onboarding Developer SILOGY (≤ 30 Menit)

Panduan ini memandu developer baru dari laptop kosong hingga aplikasi jalan dan siap mengirim PR pertama.

**Target waktu:** 30 menit · **Stack dev disarankan:** Docker Compose (lihat [README](../README.md)).

---

## 1. Checklist Setup Laptop

Centang sebelum mulai timer 30 menit:

- [ ] **Git** 2.40+ — [git-scm.com](https://git-scm.com/)
- [ ] **Docker Desktop** 4.x (Windows/macOS) atau Docker Engine + Compose v2 (Linux) — [docker.com](https://www.docker.com/products/docker-desktop/)
- [ ] **Make** — tersedia di Git Bash/WSL/macOS/Linux; Windows native: `choco install make` atau gunakan WSL2
- [ ] **IDE** — VS Code / Cursor / PhpStorm dengan ekstensi PHP, Docker, EditorConfig
- [ ] **Akses repo** — SSH key atau token GitHub ke organisasi `unsil`
- [ ] **Port bebas** — `8008`, `3306`, `6379`, `8025` tidak dipakai aplikasi lain

Opsional (tanpa Docker):

- [ ] **FlyEnv** (Windows) — alternatif lokal; lihat `.env.example` dan `http://silogy.test`

---

## 2. Alur 30 Menit

### Menit 0–5: Clone & environment

```bash
git clone https://github.com/unsil/silogy.git
cd silogy
git checkout dev
cp .env.docker .env
```

Verifikasi Docker berjalan: `docker compose version`

### Menit 5–15: Naikkan stack

```bash
make up
```

Yang terjadi:

1. Build image PHP 8.3-FPM + pull MySQL 8, Redis 7, Nginx, Mailpit
2. Tunggu healthcheck MySQL (`silogy_mysql` healthy)
3. `composer install` di container `app`
4. `php artisan key:generate`

Jika build pertama kali, unduh image bisa memakan 5–10 menit — masih dalam budget 30 menit.

### Menit 15–20: Database & seed

```bash
make fresh
```

Verifikasi cepat:

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8008/admin/login
# Harapan: 200
```

Buka browser: http://localhost:8008/admin/login → login `superadmin` / `siliwangi`

### Menit 20–25: Orientasi codebase

Baca singkat (tidak perlu hafal):

| Path | Isi |
|---|---|
| `app/Modules/` | Vertical slice per domain (Institusi, Kurikulum, Penilaian, Kalkulasi, …) |
| `docs/SILOGY_ERD_Database_Design_v6.md` | Sumber kebenaran skema DB |
| `docs/SILOGY_PreVibeCoding_v6.md` §6 | Konvensi penamaan & test |
| `tests/Feature/MvpEndToEndTest.php` | Jalur E2E MVP referensi |

Jalankan test cepat:

```bash
make test
# atau subset:
docker compose exec app php artisan test --filter=MvpEndToEndTest
```

### Menit 25–30: PR contoh (docs-only)

```bash
git checkout -b feature/onboarding-<inisial-nama>
```

Buat perubahan kecil (misalnya perbaiki typo di komentar atau tambah satu baris di `docs/`), lalu:

```bash
make pint
git add .
git commit -m "docs(onboarding): catatan setup untuk developer baru"
git push -u origin feature/onboarding-<inisial-nama>
```

Buka PR ke branch `dev` di GitHub, isi template PR, centang checklist DoD, minta 1 reviewer.

---

## 3. Troubleshooting Umum

### MySQL healthcheck failed / `make up` gagal di MySQL

**Gejala:** Container `silogy_mysql` status `unhealthy`, `make up` atau `make fresh` error koneksi DB.

**Langkah:**

1. Cek log: `docker compose logs mysql --tail=50`
2. Pastikan port **3306** tidak dipakai MySQL/MariaDB lokal (FlyEnv, XAMPP, Laragon):
   ```bash
   # Windows PowerShell
   netstat -ano | findstr :3306
   ```
3. Reset volume jika corrupt:
   ```bash
   make down
   docker volume rm silogy_mysql_data
   make up
   make fresh
   ```
4. Tunggu ±60 detik pada boot pertama SSD lambat.

### Port 8008 sudah dipakai

**Gejala:** `Bind for 0.0.0.0:8008 failed: port is already allocated`

**Langkah:**

1. Identifikasi proses: `netstat -ano | findstr :8008` (Windows) atau `lsof -i :8008` (macOS/Linux)
2. Hentikan service yang bentrok (IIS, Apache, aplikasi lain), **atau** ubah port di `docker-compose.yml`:
   ```yaml
   ports:
     - "8081:80"   # ganti 8008 → 8081
   ```
   Lalu sesuaikan `APP_URL` di `.env` menjadi `http://localhost:8081` dan akses http://localhost:8081/admin

### `make: command not found` (Windows CMD)

Gunakan **Git Bash** atau **WSL2**, atau jalankan perintah setara:

```bash
docker compose up -d --build
docker compose exec app composer install --no-interaction
docker compose exec app php artisan key:generate --force
docker compose exec app php artisan migrate:fresh --seed
```

### Permission denied pada `storage/` (Linux)

```bash
docker compose exec -u root app chown -R www-data:www-data storage bootstrap/cache
```

### Redis connection refused

Pastikan container `silogy_redis` running: `docker compose ps`. Restart: `docker compose restart redis app queue`

### Login gagal setelah `make fresh`

- Pastikan memakai **`siliwangi`** (huruf kecil semua)
- Username persis: `superadmin` (tanpa spasi)
- Clear cache: `docker compose exec app php artisan optimize:clear`

---

## 4. Setelah Onboarding

| Aktivitas | Referensi |
|---|---|
| Demo ke stakeholder | [DEMO_SCRIPT.md](DEMO_SCRIPT.md) |
| Mulai task sprint | [PreVibeCoding §2](SILOGY_PreVibeCoding_v6.md) |
| Aturan PR & commit | [CONTRIBUTING.md](../CONTRIBUTING.md) |
| Konflik dokumen | Buka issue — jangan tebak struktur DB |

Selamat berkontribusi di SILOGY.
