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

### Pusat Simulasi (data latihan yang bisa dibuat & dihapus kapan saja)

Super Admin dapat menyalakan dan mematikan satu program studi contoh yang lengkap —
dari kurikulum sampai nilai dan hasil kalkulasi — lewat menu **Simulasi** (`/simulasi`).
Gunanya supaya siapa pun bisa berlatih memakai SILOGY tanpa menyentuh data yang
sesungguhnya, dan supaya tangkapan layar manual punya isi yang masuk akal.

```bash
docker compose exec app php artisan simulasi:status
docker compose exec app php artisan simulasi:buat
docker compose exec app php artisan simulasi:hapus            # laporan saja
docker compose exec app php artisan simulasi:hapus --terapkan # benar-benar hapus
```

**Yang dimiliki simulasi.** Pohon unit tersendiri — Universitas Simulasi → Fakultas
Simulasi → Prodi Simulasi — beserta akun `sim-*` (kata sandi `siliwangi`), mahasiswa,
kurikulum, profil lulusan, CPL, BoK, mata kuliah, CPMK, Sub-CPMK, komponen asesmen,
kelas, nilai, hasil kalkulasi, dan satu usulan perubahan CPMK yang menunggu keputusan.

**Yang dipinjam, tidak pernah dihapus.** Peran, izin, semester, dan master evaluasi.
Seluruh foreign key ke `semesters` dan `evaluasi` bersifat RESTRICT, jadi baris yang
terlanjur dibuat simulasi tidak akan pernah bisa dibongkar dengan aman — karena itu
simulasi menolak jalan bila belum ada semester aktif.

**Mengapa penghapusannya aman.** Tabel `simulasi_artefak` mencatat setiap baris yang
BENAR-BENAR dibuat simulasi. Baris yang hanya diadopsi `firstOrCreate` tidak pernah
memancarkan event `created`, sehingga tidak pernah tercatat dan tidak mungkin ikut
terhapus. Pembongkaran berjalan mundur menurut urutan pembuatan, dan urutan terbalik itu
sendirilah yang memenuhi setiap batasan RESTRICT di skema. Baris yang masih dirujuk data
di luar buku besar tidak dipaksa hapus, melainkan dilaporkan.

Uji yang menjaga jaminan itu: `tests/Feature/Simulasi/SimulasiServiceTest.php`
membandingkan **cap jari seluruh tabel** sebelum dan sesudah siklus buat→hapus.

### Panduan pengguna publik

Panduan per peran terbit di **`/panduan`**, tertaut dari beranda, dan dirender langsung
dari `docs/user-manual/*.md` sehingga tidak pernah menyimpang dari sumbernya.

Gambarnya adalah **tangkapan layar aplikasi yang sebenarnya**, diambil Playwright dari
instans yang berjalan, bukan ilustrasi tiruan:

```bash
docker compose exec app php artisan simulasi:buat   # isi dulu datanya
npm install && npx playwright install chromium
APP_URL=http://localhost:8017 npm run tangkap-layar
npm run tangkap-layar -- --periksa                  # manifes vs Markdown
docker compose exec app php artisan panduan:tautkan-aset
```

Di produksi langkah terakhir itu sudah dijalankan entrypoint dengan `--salin`: nginx
berjalan di container terpisah yang hanya me-mount volume `public/`, jadi tautan simbolik
ke `docs/` akan menggantung di sana.

Angka merah penunjuk tombol tidak dibakar ke dalam PNG. Runner menghitung kotak batas
tiap selector saat memotret dan menyimpannya sebagai persentase di
`docs/user-manual/aset/manifes.json`; halaman panduan menggambarnya sebagai overlay CSS.
Akibatnya, ketika sebuah tombol berpindah di antarmuka, penunjuknya ikut berpindah
sendiri pada penangkapan berikutnya. Semua selector hidup di satu berkas:
`scripts/tangkap-layar/adegan.mjs`.

#### Mode latihan: sakelarnya di basis data, bukan di `.env`

Tombol "Coba sebagai ‹peran›" adalah jalur masuk **tanpa kata sandi**, dan **tertutup
secara bawaan**. Membukanya dilakukan Super Admin dari menu **Simulasi** → **Buka mode
latihan**, bukan dengan menyunting `.env`. Alasannya praktis: orang yang berwenang
memutuskan hal ini sering kali tidak punya akses ke berkas konfigurasi di peladen — dan
sakelar yang bisa dicabut dalam hitungan detik lebih aman daripada yang butuh deploy.

Sakelarnya menempel pada **jalan simulasi** (`simulasi_jalan.coba_peran`), sehingga:

- **Menghapus data simulasi otomatis menutup jalur ini.** Tidak ada sakelar yatim yang
  tertinggal menyala tanpa ada yang menyadarinya.
- **Simulasi yang baru dibuat selalu mulai tertutup.** "Bangun Ulang" membawa sakelar
  yang sedang menyala, karena menyegarkan data latihan bukan berarti menutup pintunya.

`SIMULASI_IZINKAN_COBA_PERAN` di `.env` adalah **pemutus keras**, bukan sakelar: ia hanya
bisa *melarang*, tidak pernah *mengizinkan*. Setel `false` bila sebuah instans tidak boleh
membuka mode latihan sama sekali, apa pun yang ditekan Super Admin.

Pagar lengkap jalur masuk itu: instans mengizinkan, Super Admin membukanya, ada simulasi
berjalan, akun sasaran **tercatat di buku besar sebagai buatan simulasi**, dan surelnya di
domain simulasi. Isolasi sebenarnya ada di lapisan data — akun `sim-*` hanya ditugaskan ke
pohon unit simulasi, sehingga seluruh policy berbasis unit memagarinya dari data nyata.

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

### Deploy di root domain vs subpath

Aplikasi mendukung tiga mode deployment tanpa perubahan kode, dikontrol lewat `.env`:

**Root domain** (mis. `https://silogy.unsil.ac.id/`) — konfigurasi default, tidak perlu variabel tambahan:

```bash
APP_URL=https://silogy.unsil.ac.id
SESSION_PATH=/
```

**Subdomain** (mis. `https://silogy.kampus.ac.id/`) — sama persis dengan mode root domain di atas, cukup ganti host di `APP_URL`. Subdomain BUKAN kasus khusus: tidak butuh path apa pun di `APP_URL`, dan `SESSION_DOMAIN` biarkan default (`null`) supaya cookie otomatis mengikuti host mana pun yang diakses.

```bash
APP_URL=https://silogy.kampus.ac.id
SESSION_PATH=/
```

**Subpath** (mis. `https://supportfkip.unsil.ac.id/demo-silogy`) — sertakan path di `APP_URL`:

```bash
APP_URL=https://supportfkip.unsil.ac.id/demo-silogy
SESSION_COOKIE=silogy_session   # opsional, hindari bentrok cookie antar-app di domain yang sama
SESSION_SECURE_COOKIE=true
```

`SESSION_PATH` **tidak perlu diisi manual lagi** untuk mode subpath — bila dibiarkan kosong (masih default `/`), `AppServiceProvider::configureUrlForSubpathDeployment()` otomatis menyamakannya dengan path di `APP_URL`. Tetap boleh disetel eksplisit (mis. `SESSION_PATH=/demo-silogy` atau path lain) bila memang perlu — nilai eksplisit itu selalu dihormati dan tidak akan ditimpa.

Cara kerjanya: `AppServiceProvider::configureUrlForSubpathDeployment()` (lihat `app/Providers/AppServiceProvider.php`) mem-parsing path dari `APP_URL` dan, bila ada, memaksa `URL::forceRootUrl()` + `forceScheme('https')` sehingga semua `url()`/`route()`/`redirect()`/`asset()` menyertakan prefix subpath — termasuk redirect setelah login dan asset Livewire yang di-patch manual. Bila `APP_URL` tidak berisi path (mode root/subdomain), Laravel kembali memakai deteksi host dari request seperti biasa dan fungsi ini tidak melakukan apa-apa.

> **Troubleshooting**: jika setelah login pengguna malah diarahkan ke domain root tanpa prefix subpath (mis. `domain.com/dashboard`, bukan `domain.com/demo-silogy/dashboard`), penyebab paling umum adalah `APP_URL` di `.env` server belum menyertakan path subpath-nya. Perbaiki `APP_URL` (lihat contoh "Subpath" di atas) — jangan mengedit kode redirect, mekanismenya sudah menangani ini otomatis begitu `APP_URL` benar.

Yang perlu disiapkan di luar repo ini:

1. **Reverse proxy harus melepas prefix subpath sebelum meneruskan ke container**, karena Laravel me-routing berdasarkan path yang benar-benar diterima PHP, bukan `APP_URL`. Contoh Apache:
   ```apache
   ProxyPass        /demo-silogy/ http://app-container:8080/
   ProxyPassReverse /demo-silogy/ http://app-container:8080/
   ```
2. Proxy harus meneruskan header `X-Forwarded-Proto/Host/Port` — `bootstrap/app.php` sudah `trustProxies(at: '*')` untuk membacanya, tapi hanya berguna kalau header tersebut memang dikirim.
3. Root domain maupun subdomain (tanpa `ProxyPass` path-stripping) tidak butuh langkah 1-2 di atas selama request langsung ke container/index.php.

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
