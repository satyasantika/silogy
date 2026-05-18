# Skrip Demo SILOGY (15 Menit)

**Audiens:** Kaprodi, dekan, tim mutu, stakeholder akademik  
**Prasyarat:** `make up` + `make fresh` selesai · URL: http://localhost:8080/admin  
**Password semua akun demo:** `Silogy2026!`

---

## Persiapan (sebelum audiens masuk)

- [ ] Browser incognito, tab tunggal
- [ ] Container jalan: `docker compose ps` (semua `running`, MySQL `healthy`)
- [ ] Queue worker aktif (`silogy_queue`) untuk kalkulasi CPL setelah input nilai
- [ ] Semester aktif sudah ada (dari `SemesterSeeder`)
- [ ] Siapkan slide 1 halaman: tiga pilar SILOGY (Pengukuran · Analitik · Peningkatan)

---

## Step 1 — Pengenalan SILOGY & OBE (2 menit)

**Narasi:**

> SILOGY adalah sistem capaian pembelajaran Universitas Siliwangi. Kita tidak hanya menyimpan nilai, tetapi menelusuri **bukti capaian** dari tingkat terkecil (sub-CPMK di kelas) hingga **CPL program studi**.
>
> Paradigma **Outcome-Based Education (OBE)** berarti setiap mata kuliah dirancang agar mahasiswa mencapai profil lulusan yang terukur. SILOGY mendukung tiga pilar:
>
> 1. **Pengukuran** — nilai terstruktur dan dapat diaudit  
> 2. **Analitik** — agregasi otomatis 5 tahap hingga dashboard CPL  
> 3. **Peningkatan** — data untuk revisi kurikulum berikutnya

**Tidak buka aplikasi dulu** — pastikan audiens paham istilah: CPL, CPMK, sub-CPMK, `academic_unit`.

---

## Step 2 — Super Admin: institusi & pengguna (3 menit)

**Login:** `superadmin` / `Silogy2026!`  
**URL:** http://localhost:8080/admin/login

### 2.1 Tour Academic Units (1,5 menit)

1. Menu **Institusi → Unit akademik** (`/admin/academic-units`)
2. Tunjukkan hierarki tree: **Universitas Siliwangi → Fakultas Teknik → Jurusan Informatika → S1 Teknik Informatika**
3. Jelaskan: satu tabel `academic_units` dengan `type` (university / faculty / department / study_program) — tidak ada tabel fakultas/jurusan terpisah

**Narasi:**

> Super Admin menyiapkan peta organisasi. Semua laporan CPL nanti terikat ke unit ini.

### 2.2 User management (1,5 menit)

1. Menu **Pengguna** (`/admin/users`)
2. Buka satu user contoh (mis. `kaprodi` atau `timkur`)
3. Tunjukkan: role Spatie + penugasan unit via pivot `academic_unit_users` (status pimpinan / tim kurikulum)

**Narasi:**

> RBAC menentukan siapa boleh mengubah kurikulum, siapa hanya membaca dashboard, dan siapa menginput nilai.

**Logout** (ikon profil → Keluar) sebelum step berikutnya.

---

## Step 3 — Tim Kurikulum: bangun kurikulum (4 menit)

**Login:** `timkur` / `Silogy2026!`

### 3.1 Buat / buka kurikulum (1 menit)

1. Menu **Kurikulum** (`/admin/kurikulums`)
2. Buka kurikulum prodi yang ada, atau tunjukkan wizard pembuatan: tahun, target capaian lulusan (mis. 75%)

### 3.2 Alur state (2,5 menit)

Jelaskan workflow sambil menunjukkan badge state di record kurikulum:

| State | Yang ditunjukkan |
|---|---|
| `draft` | Metadata kurikulum |
| `profil_lulusan` | Profil lulusan + indikator (khusus prodi) |
| `cpl` | Daftar CPL + mapping ke profil |
| `bok` | Bahan kajian + bobot `cpl_bok` |
| `mk` | Mata kuliah + penawaran `mk_units` |
| `setdosenmk` | Kelas MK sudah punya dosen pengampu |
| `aktif` | Kurikulum dipakai untuk perhitungan dashboard |

Navigasi cepat (sesuai data seed):

- **CPL** → `/admin/cpls`
- **BoK** → `/admin/boks`
- **MK** → `/admin/mks` dan **MK Unit** → `/admin/mk-units`

**Narasi:**

> Tim kurikulum menuntun prodi dari desain capaian hingga struktur mata kuliah. Transisi state memastikan data lengkap sebelum go-live.

### 3.3 Kelas & penugasan dosen (0,5 menit)

Sebutkan bahwa **Admin Prodi** membuat kelas (`/admin/kelas-mks`): pilih `mk_unit` + semester, tetapkan dosen pengampu & koordinator MK, daftarkan mahasiswa.

**Logout.**

---

## Step 4 — Dosen: input nilai (3 menit)

**Login:** `dosen` / `Silogy2026!`

1. Menu **Penilaian → Input Nilai** (`/admin/penilaian/input-nilai`)
2. Pilih **Kelas MK** dari dropdown (hanya kelas yang Anda pengampu)
3. Tunjukkan **matriks**: baris = mahasiswa, kolom = sub-CPMK × komponen (UTS/UAS)
4. Isi contoh nilai (mis. 82, 88) untuk 2–3 mahasiswa
5. Klik **Simpan**

**Narasi:**

> Setiap penyimpanan memicu job kalkulasi CPL di background (`cpl-calculation`). Dosen tidak perlu menghitung CPL manual.

Opsional tunjukkan Mailpit http://localhost:8025 jika ada notifikasi email di masa depan.

**Logout.**

---

## Step 5 — Kaprodi: dashboard CPL & drill-down (3 menit)

**Login:** `kaprodi` / `Silogy2026!`

### 5.1 Dashboard (2 menit)

1. Buka **Dashboard** (`/admin`) — widget **Capaian CPL per Unit**
2. Atur filter: **Unit akademik** = S1 Teknik Informatika, **Semester** = semester aktif
3. Jelaskan chart: batang = rata-rata capaian per kode CPL, garis merah = target kurikulum

**Narasi:**

> Kaprodi melihat apakah mahasiswa secara agregat mendekati target capaian lulusan. Persentase tercapai = proporsi mahasiswa yang nilai akhirnya ≥ target.

### 5.2 Drill-down per MK (1 menit)

1. Scroll ke tabel **Drill-down Capaian per MK & Penawaran (MK Unit)**
2. Pilih CPL pada filter jika ada
3. Tunjukkan baris: nama MK, kode MK unit, rata-rata, jumlah mahasiswa, % tercapai

**Narasi:**

> Dari CPL program studi, pimpinan bisa menelusuri mata kuliah mana yang paling banyak berkontribusi pada gap capaian.

### Penutup (15 detik)

> SILOGY MVP membuktikan alur lengkap: kurikulum → penilaian → ketercapaian CPL. Fase berikutnya: impor nilai massal, analisis AI, dan laporan PDF.

---

## Cheat Sheet Akun Demo

| Step | Username | Role |
|---|---|---|
| 2 | `superadmin` | Super Admin |
| 3 | `timkur` | Tim Kurikulum |
| 4 | `dosen` | Dosen Pengampu |
| 5 | `kaprodi` | Pimpinan Program Studi |

Akun lengkap: [README § Akun Siap Pakai](../README.md) · [PreVibeCoding §7.2](SILOGY_PreVibeCoding_v6.md).

---

## Jika demo gagal di lapangan

| Masalah | Solusi cepat |
|---|---|
| Chart kosong | Pastikan nilai sudah disimpan & queue jalan: `docker compose logs queue --tail=20` |
| Login ditolak | `make fresh` ulang, tunggu MySQL healthy |
| 500 error | `docker compose exec app php artisan optimize:clear` |
| Port salah | Pastikan URL `http://localhost:8080/admin` |

Backup narasi: jalankan test E2E sebagai bukti alur — `docker compose exec app php artisan test --filter=MvpEndToEndTest`
