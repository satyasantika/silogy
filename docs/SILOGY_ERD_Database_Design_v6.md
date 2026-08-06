# SILOGY — Desain Basis Data & ERD

**Siliwangi Learning Outcomes & Quality Analytics**
*Universitas Siliwangi*

---

| Item | Nilai |
|---|---|
| Sistem Operasional | SILARIS – Siliwangi Learning & Quality Assurance System |
| Paradigma | Outcome-Based Education (OBE) |
| Stack Teknologi | Laravel 13 · Filament v4 · MySQL 8 · Redis |
| Versi Dokumen | 6.1 |
| Tagline | *"From learning data to academic quality"* |

---

## 1. Prinsip Desain Basis Data v6

**Perubahan Utama dari v5 ke v6:**

- **academic_units menjadi tabel tunggal hierarki institusi.** Tabel `universitas`, `fakultas`, `jurusan`, dan `prodis` **dihapus**. Semua data kelembagaan (visi, misi, akreditasi, SK pendirian, dll.) digabungkan ke `academic_units` dengan id **UUID**.
- **academic_unit_users + status_tim_kurikulum.** Pivot ini menentukan siapa pengelola kurikulum per unit. Tim Kurikulum unit non-prodi mengelola CPL→BoK→MK→setdosenmk; tim kurikulum prodi mengelola Profil Lulusan→CPL→BoK→setdosenmk.
- **mahasiswas.** Kolom `phone` diganti `nomor_wa`; `prodi_id` diganti `academic_unit_id` (filter type=`study_program`).
- **mk dirombak.** Kolom `status` → `is_active`; ditambah `sks_teori`, `sks_praktik`, `sks_lapangan`. Kolom `kode` dan `semester_ke` **dipindah** ke pivot baru `mk_units`.
- **mk_units (BARU).** Pivot `academic_unit_id ↔ mk_id` berisi kode MK & semester_ke yang berlaku spesifik untuk unit tersebut (prodi/jurusan/fakultas/universitas).
- **kelas_mk.** Kolom `mk_id` diganti `mk_unit_id` agar setiap kelas terikat pada penawaran MK di unit prodi tertentu.
- **subcpmk.** Kolom `cpmk_id` **dihapus** — CPMK diakses lewat pivot `mk_cpmk` melalui `mk_cpmk_id`.
- **komponen_penilaian.** Default `bobot = 100`.
- **nilai_mahasiswas.** Pakai kolom **`subcpmk_komponenpenilaian_id`** (rename dari `subcpmk_komponen_id`) agar nama eksplisit.
- **hasil_cpl_mk.** `mk_id` → `mk_unit_id` (CPL prodi melalui penawaran MK di prodi).
- **hasil_cpl_mk_unit (BARU).** Agregat CPL pada level MK murni (`mk_id`) untuk unit non-prodi — memungkinkan dashboard CPL universitas/fakultas/jurusan yang MK-nya tersebar di banyak prodi.
- **hasil_cpl_prodi → hasil_cpl_unit.** Diganti dengan `academic_unit_id` agar dapat melihat agregat CPL di setiap level unit (universitas/fakultas/jurusan/prodi).
- **analisis_ai.** `prodi_id` → `academic_unit_id` agar AI dapat memprediksi pada setiap level unit.
- **Spatie Permission UUID.** Tetap pakai `CHAR(36)` UUID.
- **Laravel 13 + PHP 8.3.** Sintaks migration menggunakan `foreignUuid()`, `cascadeOnDelete()`, anonymous-class migrations.

**Perubahan v6.1:**

- **`kurikulum_id` pada `cpl`, `bok`, `mk`, `mk_units`.** Paket OBE (CPL→BoK→MK→penawaran) diikat ke satu kurikulum. Kurikulum baru di unit yang sama mulai kosong; data historis tidak otomatis ikut. `academic_unit_id` tetap ada sebagai pemilik hierarki (adaptasi lintas unit). Unique kode: `(kurikulum_id, kode)` untuk CPL/BoK/mk_units; `(kurikulum_id, mk_id)` untuk mk_units.

---

## 2. Hierarki Institusi Terpadu (`academic_units`)

`academic_units` menggantikan empat tabel terdahulu (`universitas`, `fakultas`, `jurusan`, `prodis`). Satu tabel ini menampung **profil lengkap** setiap node hierarki: universitas, fakultas, jurusan, dan program studi. Hierarki dibangun melalui `parent_id` yang nullable.

### Tabel: `academic_units`

> Tulang punggung hierarki institusi — universitas → fakultas → jurusan → program studi.

| Kolom | Tipe | NULL | Key | Deskripsi |
|---|---|---|---|---|
| id | CHAR(36) | NO | PK | UUID v4 |
| parent_id | CHAR(36) | YES | FK | → `academic_units.id` (hierarki) |
| type | ENUM('university','faculty','department','study_program') | NO | IDX | Jenis unit akademik |
| code | VARCHAR(30) | YES | IDX | Kode singkat unit (e.g. UNSIL, FT, IF, S1-IF) |
| kode_pddikti | VARCHAR(30) | YES | UQ | Kode resmi PDDIKTI Kemendikbud (study_program) |
| nama | VARCHAR(150) | NO | | Nama resmi unit |
| singkatan | VARCHAR(30) | YES | | Singkatan unit |
| jenjang | VARCHAR(10) | YES | | D3/D4/S1/S2/S3/Profesi (study_program) |
| gelar_lulusan | VARCHAR(50) | YES | | Contoh: S.Kom., M.Kom. (study_program) |
| mapel | VARCHAR(100) | YES | | Mata pelajaran utama (untuk prodi pendidikan) |
| visi | TEXT | YES | | Visi unit |
| misi | TEXT | YES | | Misi unit |
| tujuan | TEXT | YES | | Tujuan unit |
| sasaran_strategis | TEXT | YES | | Sasaran strategis |
| alamat | TEXT | YES | | Alamat unit |
| no_telepon | VARCHAR(20) | YES | | |
| email | VARCHAR(100) | YES | | |
| website | VARCHAR(100) | YES | | |
| logo_path | VARCHAR(200) | YES | | Path logo unit |
| tahun_pendirian | VARCHAR(4) | YES | | |
| sk_pendirian | VARCHAR(100) | YES | | Nomor SK pendirian |
| tahun_akreditasi | VARCHAR(4) | YES | | |
| sk_akreditasi | VARCHAR(100) | YES | | Nomor SK akreditasi |
| peringkat_akreditasi | VARCHAR(20) | YES | | Unggul/A/B/Baik Sekali/Baik |
| tahun_internasional | VARCHAR(4) | YES | | |
| sk_internasional | VARCHAR(100) | YES | | Nomor SK pengakuan internasional |
| status | ENUM('draft','aktif','nonaktif') | NO | | Default: `draft` |
| created_at | TIMESTAMP | YES | | |
| updated_at | TIMESTAMP | YES | | |

**FK:** `parent_id → academic_units.id (CASCADE ON UPDATE / RESTRICT ON DELETE)`
**Indeks:** `idx_au_type (type)` · `idx_au_parent (parent_id)` · `idx_au_code (code)` · UQ `(kode_pddikti)` (sparse)

> ⚠ **PENTING:** Tabel `universitas`, `fakultas`, `jurusan`, dan `prodis` **dihapus** di v6. Seluruh referensi `prodi_id` pada tabel lain diganti menjadi `academic_unit_id` dan divalidasi melalui `type` yang sesuai.

### Tabel: `academic_unit_users`

> Pivot tunggal untuk penetapan user pada unit, termasuk hak kelola kurikulum unit tersebut.

| Kolom | Tipe | NULL | Key | Deskripsi |
|---|---|---|---|---|
| id | CHAR(36) | NO | PK | UUID v4 |
| academic_unit_id | CHAR(36) | NO | FK | → `academic_units.id` |
| user_id | CHAR(36) | NO | FK | → `users.id` |
| status_pimpinan | TINYINT(1) | NO | | 1=pimpinan unit (Rektor/Dekan/Kajur/Kaprodi), default 0 |
| status_tim_kurikulum | TINYINT(1) | NO | | 1=tim kurikulum unit (mengelola CPL→BoK→MK / Profil-CPL-BoK-MK), default 0 |
| jabatan | VARCHAR(100) | YES | | Label jabatan, mis. "Wakil Rektor I", "Sekretaris Jurusan" |
| created_at | TIMESTAMP | YES | | |
| updated_at | TIMESTAMP | YES | | |

**FK:** `academic_unit_id → academic_units.id (CASCADE)` · `user_id → users.id (CASCADE)`
**Indeks:** UQ `(academic_unit_id, user_id)` · `idx_auu_user (user_id)` · `idx_auu_pimpinan (status_pimpinan)` · `idx_auu_timkur (status_tim_kurikulum)`

> Aturan tim kurikulum:
> - Unit **prodi** (`type=study_program`): tim kurikulum mengelola **Profil Lulusan → CPL → BoK → MK → setdosenmk**.
> - Unit **non-prodi** (universitas/fakultas/jurusan): tim kurikulum mengelola **CPL → BoK → MK → setdosenmk** (tanpa Profil Lulusan).
> - `setdosen_mk` boleh dilakukan oleh **Admin Unit** maupun **Tim Kurikulum** pada unit yang sama.

### 2.1 Contoh Data `academic_units`

```text
id='uuid-u01' type='university'    name='Universitas Siliwangi'      parent_id=NULL
id='uuid-f01' type='faculty'       name='Fakultas Teknik'             parent_id='uuid-u01'
id='uuid-d01' type='department'    name='Jurusan Informatika'         parent_id='uuid-f01'
id='uuid-p01' type='study_program' name='S1 Teknik Informatika'       parent_id='uuid-d01'  jenjang='S1'  gelar_lulusan='S.Kom.'
id='uuid-f02' type='faculty'       name='Fakultas Keguruan'           parent_id='uuid-u01'
id='uuid-p02' type='study_program' name='S1 Pendidikan Matematika'    parent_id='uuid-f02'  jenjang='S1'  gelar_lulusan='S.Pd.'  mapel='Matematika'
```

```text
// Penetapan user (academic_unit_users)
(academic_unit_id='uuid-u01', user_id='uuid-rektor',  status_pimpinan=1, jabatan='Rektor')
(academic_unit_id='uuid-f01', user_id='uuid-dekan',   status_pimpinan=1, jabatan='Dekan FT')
(academic_unit_id='uuid-p01', user_id='uuid-kaprodi', status_pimpinan=1, jabatan='Kaprodi S1-IF')
(academic_unit_id='uuid-p01', user_id='uuid-timkur',  status_tim_kurikulum=1, jabatan='Anggota Tim Kurikulum')
(academic_unit_id='uuid-p01', user_id='uuid-dosen1',  status_pimpinan=0, jabatan='Dosen Pengampu')
```

---

## 3. Tabel Pengguna & Mahasiswa

### Tabel: `users`

> Pengguna sistem yang dapat login (dosen, admin, kaprodi, dekan, rektor, koordinator MK, dll.).

| Kolom | Tipe | NULL | Key | Deskripsi |
|---|---|---|---|---|
| id | CHAR(36) | NO | PK | UUID v4 |
| username | VARCHAR(50) | NO | UQ | Login identifier utama |
| email | VARCHAR(150) | NO | UQ | Alamat email |
| nidn | VARCHAR(20) | YES | UQ | NIDN/NIP dosen/tendik |
| prefix | VARCHAR(30) | YES | | Gelar depan (Dr., Prof., dll.) |
| full_name | VARCHAR(150) | NO | | Nama lengkap |
| suffix | VARCHAR(50) | YES | | Gelar belakang (M.Kom., Ph.D., dll.) |
| jenis_kelamin | ENUM('L','P') | YES | | L=Laki-laki, P=Perempuan |
| nomor_wa | VARCHAR(20) | YES | | Nomor WhatsApp aktif |
| password | VARCHAR(255) | NO | | Hash bcrypt |
| avatar | VARCHAR(255) | YES | | Path foto profil |
| email_verified_at | TIMESTAMP | YES | | |
| remember_token | VARCHAR(100) | YES | | |
| created_at | TIMESTAMP | YES | | |
| updated_at | TIMESTAMP | YES | | |

**Indeks:** `idx_users_username (username)` · `idx_users_email (email)` · `idx_users_nidn (nidn)`

> `prodi_id` **tidak ada** di `users` — relasi user↔unit murni via `academic_unit_users`.

### Tabel: `mahasiswas`

> Data mahasiswa yang **tidak login**; hanya subjek penilaian.

| Kolom | Tipe | NULL | Key | Deskripsi |
|---|---|---|---|---|
| id | CHAR(36) | NO | PK | UUID v4 |
| nim | VARCHAR(20) | NO | UQ+IDX | Nomor Induk Mahasiswa |
| nama | VARCHAR(150) | YES | | Nama lengkap |
| jenis_kelamin | ENUM('L','P') | YES | | |
| angkatan | VARCHAR(4) | YES | IDX | Tahun angkatan, mis. 2024 |
| academic_unit_id | CHAR(36) | NO | FK | → `academic_units.id` (harus `type=study_program`) |
| email | VARCHAR(100) | YES | | |
| nomor_wa | VARCHAR(20) | YES | | Nomor WhatsApp |
| status | ENUM('aktif','cuti','lulus','do','nonaktif') | NO | | Default: `aktif` |
| created_at | TIMESTAMP | YES | | |
| updated_at | TIMESTAMP | YES | | |

**FK:** `academic_unit_id → academic_units.id (RESTRICT ON DELETE)`
**Indeks:** `idx_mhs_nim (nim)` · `idx_mhs_unit (academic_unit_id)` · `idx_mhs_angkatan (angkatan)`

---

## 4. Spatie Permission (UUID)

| Tabel | Catatan |
|---|---|
| `roles` | `id` = CHAR(36) UUID |
| `permissions` | `id` = CHAR(36) UUID |
| `model_has_roles` | `role_id` UUID, `model_uuid` UUID |
| `model_has_permissions` | `permission_id` UUID, `model_uuid` UUID |
| `role_has_permissions` | `role_id` & `permission_id` UUID |

### 4.1 Daftar Permission v6

```text
// === Institusi ===
kelola_universitas, kelola_fakultas, kelola_jurusan, kelola_prodi
kelola_semester, kelola_evaluasi

// === User Management per Tipe Unit ===
kelola_user, kelola_role, kelola_permission, lihat_audit_log, konfigurasi_sistem
kelola_user_universitas, kelola_user_fakultas, kelola_user_jurusan, kelola_user_prodi

// === Kurikulum ===
kelola_kurikulum, kelola_profil_lulusan
kelola_cpl, kelola_bok, kelola_mk, kelola_mk_unit
kelola_cpmk, kelola_subcpmk, kelola_komponen_penilaian

// === Kelas & Penilaian ===
kelola_kelas, setdosen_mk, input_nilai, import_nilai

// === Laporan & AI ===
lihat_laporan, ekspor_data, minta_analisis_ai, lihat_dashboard
```

### 4.2 Daftar Role v6

| Role | Lingkup | Permission Utama |
|---|---|---|
| Super Admin | Sistem | **Hanya Institusi & Admin** + `kelola_evaluasi`: `kelola_universitas/fakultas/jurusan/prodi`, `kelola_user*`, `kelola_role`, `kelola_permission`, `kelola_semester`, `kelola_evaluasi`, `lihat_audit_log`, `konfigurasi_sistem` |
| Admin Universitas | `type=university` (via pivot) | `kelola_user_universitas/fakultas/jurusan/prodi`, `kelola_fakultas`, `kelola_jurusan`, `kelola_prodi`, `setdosen_mk`, `lihat_laporan`, `ekspor_data` |
| Admin Fakultas | `type=faculty` | `kelola_user_fakultas/jurusan/prodi`, `kelola_jurusan`, `kelola_prodi`, `setdosen_mk`, `lihat_laporan` |
| Admin Jurusan | `type=department` | `kelola_user_jurusan/prodi`, `kelola_prodi`, `setdosen_mk`, `lihat_laporan` |
| Admin Program Studi | `type=study_program` | `kelola_user_prodi`, `kelola_kelas`, `setdosen_mk`, `lihat_laporan` |
| Tim Kurikulum | Unit (status_tim_kurikulum=1) | `kelola_kurikulum`, `kelola_profil_lulusan` *(khusus prodi)*, `kelola_cpl`, `kelola_bok`, `kelola_mk`, `kelola_mk_unit`, `setdosen_mk` |
| Koordinator Mata Kuliah | Per MK / kelas | `kelola_cpmk`, `kelola_subcpmk`, `kelola_komponen_penilaian` |
| Dosen Pengampu | Kelas MK | `kelola_kelas`, `input_nilai`, `import_nilai`, `lihat_dashboard` |
| Pimpinan Universitas | `type=university` (status_pimpinan=1) | `lihat_laporan`, `ekspor_data`, `minta_analisis_ai`, `lihat_dashboard` |
| Pimpinan Fakultas | `type=faculty` | sda — scope fakultas |
| Pimpinan Jurusan | `type=department` | sda — scope jurusan |
| Pimpinan Program Studi | `type=study_program` | sda — scope prodi |
| Auditor Mutu | Read-only | `lihat_laporan`, `ekspor_data`, `lihat_audit_log`, `lihat_dashboard` |

> **Catatan beban tugas:** Sebelumnya `kelola_komponen_penilaian` ada di Dosen Pengampu. Pada v6, hak ini **dipindah ke Koordinator Mata Kuliah** agar dosen pengampu fokus pada penginputan nilai dan pengelolaan kelas.

---

## 5. Semester

### Tabel: `semesters`

| Kolom | Tipe | NULL | Key | Deskripsi |
|---|---|---|---|---|
| id | CHAR(36) | NO | PK | UUID v4 |
| kode | VARCHAR(5) | NO | UQ | Format YYYYS (20241, 20242, dst.) |
| nama | VARCHAR(50) | NO | | Mis. "Ganjil 2024/2025" |
| tahun_mulai | YEAR | NO | | |
| tahun_selesai | YEAR | NO | | |
| jenis | ENUM('ganjil','genap','pendek') | NO | | |
| tanggal_mulai | DATE | YES | | |
| tanggal_selesai | DATE | YES | | |
| status_aktif | TINYINT(1) | NO | IDX | Hanya satu aktif (default 0) |
| created_at | TIMESTAMP | YES | | |
| updated_at | TIMESTAMP | YES | | |

---

## 6. Kurikulum & Profil Lulusan

### Tabel: `kurikulum`

| Kolom | Tipe | NULL | Key | Deskripsi |
|---|---|---|---|---|
| id | CHAR(36) | NO | PK | UUID v4 |
| academic_unit_id | CHAR(36) | NO | FK | → `academic_units.id` (umumnya `type=study_program`) |
| nama | VARCHAR(150) | NO | | |
| kode | VARCHAR(30) | YES | | Mis. KUR-2024-IF |
| tahun | YEAR | NO | | |
| target_capaian_lulusan | TINYINT | YES | | % target (default 75) |
| deskripsi | TEXT | YES | | |
| state | VARCHAR(50) | NO | | laravel-state |
| is_active | TINYINT(1) | NO | | 1 per unit |
| dibuat_oleh | CHAR(36) | YES | FK | → `users.id` |
| created_at | TIMESTAMP | YES | | |
| updated_at | TIMESTAMP | YES | | |

**FK:** `academic_unit_id → academic_units.id (CASCADE)` · `dibuat_oleh → users.id (SET NULL)`
**Indeks:** `idx_kur_unit (academic_unit_id)` · `idx_kur_state (state)`

### Tabel: `profil_lulusan` *(khusus prodi)*

| Kolom | Tipe | NULL | Key | Deskripsi |
|---|---|---|---|---|
| id | CHAR(36) | NO | PK | UUID v4 |
| kurikulum_id | CHAR(36) | NO | FK | → `kurikulum.id` |
| kode | VARCHAR(10) | NO | | Mis. PL-01 |
| nama | VARCHAR(150) | YES | | Mis. Data Scientist |
| deskripsi | TEXT | NO | | |
| urutan | TINYINT | YES | | |
| created_at | TIMESTAMP | YES | | |
| updated_at | TIMESTAMP | YES | | |

### Tabel: `profil_indikators`

| Kolom | Tipe | NULL | Key | Deskripsi |
|---|---|---|---|---|
| id | CHAR(36) | NO | PK | UUID v4 |
| profil_id | CHAR(36) | NO | FK | → `profil_lulusan.id` |
| nama | TEXT | YES | | |
| deskripsi | TEXT | YES | | |
| created_at | TIMESTAMP | YES | | |
| updated_at | TIMESTAMP | YES | | |

### Tabel: `state_transitions`

Riwayat transisi state pada model bertingkat (Kurikulum, Mk, dll.).

| Kolom | Tipe | NULL | Key | Deskripsi |
|---|---|---|---|---|
| id | CHAR(36) | NO | PK | UUID v4 |
| model_type | VARCHAR(100) | NO | IDX | FQCN model |
| model_id | CHAR(36) | NO | IDX | UUID model |
| from_state | VARCHAR(50) | YES | | |
| to_state | VARCHAR(50) | NO | | |
| actor_id | CHAR(36) | YES | FK | → `users.id` |
| keterangan | TEXT | YES | | |
| created_at | TIMESTAMP | YES | | |

---

## 7. CPL, BoK, dan MK

### Tabel: `cpl`

| Kolom | Tipe | NULL | Key | Deskripsi |
|---|---|---|---|---|
| id | CHAR(36) | NO | PK | UUID v4 |
| academic_unit_id | CHAR(36) | NO | FK | → `academic_units.id` (pemilik hierarki; CPL bisa di level mana saja) |
| kurikulum_id | CHAR(36) | NO | FK | → `kurikulum.id` — paket CPL milik kurikulum ini |
| kode | VARCHAR(15) | NO | | Mis. CPL-P01 |
| deskripsi | TEXT | NO | | |
| domain | JSON | YES | | Array multi-select: `kognitif`/`afektif`/`psikomotorik` |
| created_at | TIMESTAMP | YES | | |
| updated_at | TIMESTAMP | YES | | |

**FK:** `academic_unit_id → academic_units.id (CASCADE)` · `kurikulum_id → kurikulum.id (CASCADE)`
**Indeks:** `idx_cpl_unit (academic_unit_id)` · `idx_cpl_kurikulum (kurikulum_id)` · UQ `(kurikulum_id, kode)`

### Tabel: `bok`

| Kolom | Tipe | NULL | Key | Deskripsi |
|---|---|---|---|---|
| id | CHAR(36) | NO | PK | UUID v4 |
| academic_unit_id | CHAR(36) | NO | FK | → `academic_units.id` |
| kurikulum_id | CHAR(36) | NO | FK | → `kurikulum.id` |
| kode | VARCHAR(15) | NO | | |
| nama | VARCHAR(150) | NO | | |
| deskripsi | TEXT | YES | | |
| created_at | TIMESTAMP | YES | | |
| updated_at | TIMESTAMP | YES | | |

**FK:** `academic_unit_id → academic_units.id (CASCADE)` · `kurikulum_id → kurikulum.id (CASCADE)`
**Indeks:** `idx_bok_unit (academic_unit_id)` · `idx_bok_kurikulum (kurikulum_id)` · UQ `(kurikulum_id, kode)`

### Tabel: `mk`

> Catatan v6: `kode` dan `semester_ke` **dipindah** ke `mk_units`. Status diganti `is_active`.  
> Catatan v6.1: `kurikulum_id` wajib — MK milik satu kurikulum pemilik.

| Kolom | Tipe | NULL | Key | Deskripsi |
|---|---|---|---|---|
| id | CHAR(36) | NO | PK | UUID v4 |
| academic_unit_id | CHAR(36) | NO | FK | → `academic_units.id` (pemilik MK – universitas/fakultas/jurusan/prodi) |
| kurikulum_id | CHAR(36) | NO | FK | → `kurikulum.id` (kurikulum pemilik MK) |
| koordinator_mk_id | CHAR(36) | YES | FK | → `users.id`, `nullOnDelete` — koordinator MK di level `mk` (terpisah dari `kelas_mk.koordinator_mk_id` per kelas) |
| state | VARCHAR(50) | NO | | laravel-state |
| nama | VARCHAR(150) | NO | | Nama mata kuliah |
| sks | TINYINT | NO | | Total SKS (= sks_teori + sks_praktik + sks_lapangan) |
| sks_teori | TINYINT | NO | | Default 0 |
| sks_praktik | TINYINT | NO | | Default 0 |
| sks_lapangan | TINYINT | NO | | Default 0 |
| jenis | ENUM('wajib','pilihan','praktikum') | NO | | |
| is_active | TINYINT(1) | NO | | Default 1 |
| created_at | TIMESTAMP | YES | | |
| updated_at | TIMESTAMP | YES | | |

**FK:** `academic_unit_id → academic_units.id (CASCADE)` · `kurikulum_id → kurikulum.id (CASCADE)` · `koordinator_mk_id → users.id (NULL ON DELETE)`
**Indeks:** `idx_mk_unit (academic_unit_id)` · `idx_mk_kurikulum (kurikulum_id)` · `idx_mk_active (is_active)`

### Tabel: `mk_units` *(BARU)*

> Penawaran MK pada **kurikulum** unit spesifik. Memungkinkan satu MK universitas/fakultas diadaptasi ke kurikulum prodi dengan kode & semester_ke berbeda.

| Kolom | Tipe | NULL | Key | Deskripsi |
|---|---|---|---|---|
| id | CHAR(36) | NO | PK | UUID v4 |
| academic_unit_id | CHAR(36) | NO | FK | → `academic_units.id` (unit yang menawarkan MK – umumnya prodi) |
| kurikulum_id | CHAR(36) | NO | FK | → `kurikulum.id` (kurikulum penawar) |
| mk_id | CHAR(36) | NO | FK | → `mk.id` |
| kode | VARCHAR(20) | NO | IDX | Kode MK pada penawaran ini (mis. IF1234) |
| semester_ke | TINYINT | YES | IDX | Rekomendasi semester (1–8) pada penawaran ini |
| is_active | TINYINT(1) | NO | | Default 1 |
| created_at | TIMESTAMP | YES | | |
| updated_at | TIMESTAMP | YES | | |

**FK:** `academic_unit_id → academic_units.id (CASCADE)` · `kurikulum_id → kurikulum.id (CASCADE)` · `mk_id → mk.id (CASCADE)`
**Indeks:** UQ `(kurikulum_id, mk_id)` · UQ `(kurikulum_id, kode)` · `idx_mu_semester (semester_ke)` · `idx_mu_active (is_active)` · `idx_mk_units_kurikulum (kurikulum_id)`

### 7.1 Pivot Rantai CPL → BoK → MK → CPMK → SubCPMK

**Tabel: `cpl_profil_lulusan`** *(prodi)*

| Kolom | Tipe | NULL | Key |
|---|---|---|---|
| id | CHAR(36) | NO | PK |
| cpl_id | CHAR(36) | NO | FK → cpl.id |
| profil_lulusan_id | CHAR(36) | NO | FK → profil_lulusan.id |
| created_at / updated_at | TIMESTAMP | YES | |

UQ `(cpl_id, profil_lulusan_id)`

**Tabel: `cpl_bok`**

| Kolom | Tipe | NULL | Key |
|---|---|---|---|
| id | CHAR(36) | NO | PK |
| cpl_id | CHAR(36) | NO | FK → cpl.id |
| bok_id | CHAR(36) | NO | FK → bok.id |
| bobot | DECIMAL(5,2) | YES | % BoK terhadap CPL |
| timestamps | | | |

UQ `(cpl_id, bok_id)`

**Tabel: `cpl_mk`**

| Kolom | Tipe | NULL | Key |
|---|---|---|---|
| id | CHAR(36) | NO | PK |
| cpl_bok_id | CHAR(36) | NO | FK → cpl_bok.id |
| mk_id | CHAR(36) | NO | FK → mk.id |
| bobot | DECIMAL(5,2) | NO | % MK terhadap pasangan CPL+BoK |
| timestamps | | | |

UQ `(cpl_bok_id, mk_id)`

**Tabel: `cpmk`**

| Kolom | Tipe | NULL | Key |
|---|---|---|---|
| id | CHAR(36) | NO | PK |
| mk_id | CHAR(36) | NO | FK → mk.id |
| kode | VARCHAR(15) | NO | |
| deskripsi | TEXT | NO | |
| timestamps | | | |

**Tabel: `mk_cpmk`**

| Kolom | Tipe | NULL | Key |
|---|---|---|---|
| id | CHAR(36) | NO | PK |
| cpl_mk_id | CHAR(36) | NO | FK → cpl_mk.id |
| cpmk_id | CHAR(36) | NO | FK → cpmk.id |
| bobot | DECIMAL(5,2) | NO | |
| timestamps | | | |

UQ `(cpl_mk_id, cpmk_id)`

**Tabel: `subcpmk`** *(v6: kolom `cpmk_id` DIHAPUS)*

| Kolom | Tipe | NULL | Key | Deskripsi |
|---|---|---|---|---|
| id | CHAR(36) | NO | PK | UUID v4 |
| mk_cpmk_id | CHAR(36) | NO | FK | → `mk_cpmk.id` (CPMK diakses lewat sini) |
| semester_id | CHAR(36) | YES | FK | → `semesters.id` |
| kode | VARCHAR(15) | NO | | Mis. Sub-01 |
| deskripsi | TEXT | NO | | |
| indikator | TEXT | YES | | |
| evaluasi | TEXT | YES | | |
| bobot | DOUBLE | YES | | % terhadap CPMK |
| bloom_kognitif | ENUM('C1','C2','C3','C4','C5','C6') | YES | | |
| bloom_afektif | ENUM('A1','A2','A3','A4','A5') | YES | | |
| bloom_psikomotorik | ENUM('P1','P2','P3','P4','P5','P6','P7') | YES | | |
| created_at / updated_at | TIMESTAMP | YES | | |

**FK:** `mk_cpmk_id → mk_cpmk.id (CASCADE)` · `semester_id → semesters.id (SET NULL)`
**Indeks:** `idx_subcpmk_mkcpmk (mk_cpmk_id)` · `idx_subcpmk_semester (semester_id)`

---

## 8. Kelas MK & Penilaian

### Tabel: `kelas_mk` *(v6: `mk_id` → `mk_unit_id`)*

| Kolom | Tipe | NULL | Key | Deskripsi |
|---|---|---|---|---|
| id | CHAR(36) | NO | PK | UUID v4 |
| mk_unit_id | CHAR(36) | NO | FK | → `mk_units.id` (penawaran MK di prodi) |
| semester_id | CHAR(36) | NO | FK | → `semesters.id` |
| kode_kelas | VARCHAR(10) | NO | | A / B / C |
| dosen_pengampu_id | CHAR(36) | YES | FK | → `users.id` |
| koordinator_mk_id | CHAR(36) | YES | FK | → `users.id` (peran Koordinator MK) |
| kapasitas | SMALLINT | YES | | |
| created_at / updated_at | TIMESTAMP | YES | | |

**FK:** `mk_unit_id → mk_units.id (CASCADE)` · `semester_id → semesters.id (RESTRICT)` · `dosen_pengampu_id → users.id (SET NULL)` · `koordinator_mk_id → users.id (SET NULL)`
**Indeks:** UQ `(mk_unit_id, semester_id, kode_kelas)`

### Tabel: `kelas_mk_mahasiswa`

| Kolom | Tipe | NULL | Key |
|---|---|---|---|
| id | CHAR(36) | NO | PK |
| kelas_mk_id | CHAR(36) | NO | FK → kelas_mk.id |
| mahasiswa_id | CHAR(36) | NO | FK → mahasiswas.id |
| nilai_angka | DECIMAL(5,2) | YES | |
| nilai_huruf | VARCHAR(5) | YES | A/B+/B/C+/C/D/E |
| timestamps | | | |

UQ `(kelas_mk_id, mahasiswa_id)`

### Tabel: `evaluasi`

| Kolom | Tipe | NULL | Key |
|---|---|---|---|
| id | CHAR(36) | NO | PK |
| kode | VARCHAR(30) | NO | UQ |
| kategori | VARCHAR(100) | YES | IDX |
| workcloud | VARCHAR(100) | YES | |
| nama | VARCHAR(150) | NO | |
| timestamps | | | |

### Tabel: `komponen_penilaian` *(v6: default bobot = 100; v6.2: `kelas_mk_id` → `mk_id`+`semester_id`)*

> Catatan v6.2: komponen penilaian tidak lagi milik satu kelas MK, melainkan satu definisi per **MK + semester**, dipakai bersama oleh semua kelas MK pada kombinasi tersebut (migration `2026_07_13_000001_ubah_komponen_penilaian_ke_mk_semester.php`).

| Kolom | Tipe | NULL | Key | Deskripsi |
|---|---|---|---|---|
| id | CHAR(36) | NO | PK | UUID v4 |
| mk_id | CHAR(36) | NO | FK | → `mk.id` |
| semester_id | CHAR(36) | NO | FK | → `semesters.id` |
| evaluasi_id | CHAR(36) | NO | FK | → `evaluasi.id` |
| kode | VARCHAR(30) | YES | | Mis. UTS_TEORI |
| nama | VARCHAR(100) | NO | | |
| bobot | DECIMAL(5,2) | NO | | **Default 100.00** |
| created_at / updated_at | TIMESTAMP | YES | | |

**FK:** `mk_id → mk.id (CASCADE)` · `semester_id → semesters.id (RESTRICT)`
**Indeks:** UQ `(mk_id, semester_id, kode)` — `uq_komponen_mk_semester_kode`

### Tabel: `subcpmk_komponenpenilaian`

| Kolom | Tipe | NULL | Key |
|---|---|---|---|
| id | CHAR(36) | NO | PK |
| subcpmk_id | CHAR(36) | NO | FK → subcpmk.id |
| komponen_penilaian_id | CHAR(36) | NO | FK → komponen_penilaian.id |
| semester_id | CHAR(36) | YES | FK → semesters.id |
| bobot | DOUBLE | NO | Default 100 |
| timestamps | | | |

UQ `(subcpmk_id, komponen_penilaian_id)`

### Tabel: `nilai_mahasiswas` *(v6: kolom `subcpmk_komponenpenilaian_id`)*

| Kolom | Tipe | NULL | Key | Deskripsi |
|---|---|---|---|---|
| id | CHAR(36) | NO | PK | UUID v4 |
| subcpmk_komponenpenilaian_id | CHAR(36) | NO | FK | → `subcpmk_komponenpenilaian.id` |
| kelas_mk_mahasiswa_id | CHAR(36) | NO | FK | → `kelas_mk_mahasiswa.id` |
| nilai | DECIMAL(5,2) | YES | | 0–100 |
| catatan | TEXT | YES | | |
| created_at / updated_at | TIMESTAMP | YES | | |

UQ `(subcpmk_komponenpenilaian_id, kelas_mk_mahasiswa_id)`

---

## 9. Hasil Kalkulasi CPL

### Tabel: `hasil_subcpmk`

| Kolom | Tipe | NULL | Key |
|---|---|---|---|
| id | CHAR(36) | NO | PK |
| subcpmk_id | CHAR(36) | NO | FK |
| kelas_mk_mahasiswa_id | CHAR(36) | NO | FK |
| kelas_mk_id | CHAR(36) | NO | FK |
| nilai_akhir | DECIMAL(5,2) | YES | |
| timestamps | | | |

UQ `(subcpmk_id, kelas_mk_mahasiswa_id)`

### Tabel: `hasil_cpmk`

| Kolom | Tipe | NULL | Key |
|---|---|---|---|
| id | CHAR(36) | NO | PK |
| cpmk_id | CHAR(36) | NO | FK |
| kelas_mk_mahasiswa_id | CHAR(36) | NO | FK |
| kelas_mk_id | CHAR(36) | NO | FK |
| nilai_akhir | DECIMAL(5,2) | YES | |
| timestamps | | | |

UQ `(cpmk_id, kelas_mk_mahasiswa_id)`

### Tabel: `hasil_cpl_mk` *(v6: `mk_id` → `mk_unit_id`)*

| Kolom | Tipe | NULL | Key | Deskripsi |
|---|---|---|---|---|
| id | CHAR(36) | NO | PK | UUID v4 |
| cpl_id | CHAR(36) | NO | FK | → `cpl.id` |
| mk_unit_id | CHAR(36) | NO | FK | → `mk_units.id` |
| kelas_mk_mahasiswa_id | CHAR(36) | NO | FK | → `kelas_mk_mahasiswa.id` |
| semester_id | CHAR(36) | NO | FK | → `semesters.id` |
| nilai_akhir | DECIMAL(5,2) | YES | | |
| nilai_berbobot | DECIMAL(5,2) | YES | | Nilai × bobot cpl_mk |
| created_at / updated_at | TIMESTAMP | YES | | |

UQ `(cpl_id, mk_unit_id, kelas_mk_mahasiswa_id, semester_id)`

### Tabel: `hasil_cpl_mk_unit` *(BARU v6)*

> Agregat CPL pada level MK *(sumber `mk_id`)* untuk unit non-prodi. Memungkinkan dashboard CPL universitas/fakultas/jurusan walaupun MK-nya disebar ke banyak prodi.

| Kolom | Tipe | NULL | Key | Deskripsi |
|---|---|---|---|---|
| id | CHAR(36) | NO | PK | UUID v4 |
| cpl_id | CHAR(36) | NO | FK | → `cpl.id` |
| mk_id | CHAR(36) | NO | FK | → `mk.id` |
| academic_unit_id | CHAR(36) | NO | FK | → `academic_units.id` (unit yang diaggregasi) |
| semester_id | CHAR(36) | NO | FK | → `semesters.id` |
| rata_rata | DECIMAL(5,2) | YES | | Rata-rata seluruh mahasiswa lintas mk_units |
| persentase_tercapai | DECIMAL(5,2) | YES | | |
| jumlah_mahasiswa | SMALLINT | YES | | |
| created_at / updated_at | TIMESTAMP | YES | | |

UQ `(cpl_id, mk_id, academic_unit_id, semester_id)`
**Indeks:** `idx_hcmu_unit (academic_unit_id)`

### Tabel: `hasil_cpl_unit` *(v6: rename dari `hasil_cpl_prodi`)*

> Agregat ketercapaian CPL untuk tiap level unit (universitas / fakultas / jurusan / prodi).
> - Unit `study_program`: perhitungan menggunakan `kelas_mk` lewat `mk_unit_id`.
> - Unit non-prodi: berpatokan pada `mk_id` yang disebar ke `mk_unit_id` setiap prodi.

| Kolom | Tipe | NULL | Key | Deskripsi |
|---|---|---|---|---|
| id | CHAR(36) | NO | PK | UUID v4 |
| cpl_id | CHAR(36) | NO | FK | → `cpl.id` |
| academic_unit_id | CHAR(36) | NO | FK | → `academic_units.id` |
| semester_id | CHAR(36) | NO | FK | → `semesters.id` |
| rata_rata | DECIMAL(5,2) | YES | | |
| persentase_tercapai | DECIMAL(5,2) | YES | | % mahasiswa ≥ `target_capaian_lulusan` |
| jumlah_mahasiswa | SMALLINT | YES | | |
| created_at / updated_at | TIMESTAMP | YES | | |

UQ `(cpl_id, academic_unit_id, semester_id)`

---

## 10. AI Analisis & Audit Log

### Tabel: `analisis_ai` *(v6: `prodi_id` → `academic_unit_id`)*

| Kolom | Tipe | NULL | Key | Deskripsi |
|---|---|---|---|---|
| id | CHAR(36) | NO | PK | UUID v4 |
| academic_unit_id | CHAR(36) | YES | FK | → `academic_units.id` (level mana saja) |
| semester_id | CHAR(36) | YES | FK | → `semesters.id` |
| jenis | ENUM('ringkasan_cpl','rekomendasi_kurikulum','tren_capaian','lainnya') | NO | | |
| konteks | JSON | YES | | |
| prompt | TEXT | NO | | |
| hasil | LONGTEXT | YES | | |
| model_ai | VARCHAR(80) | YES | | Mis. gemini-2.5-pro |
| token_digunakan | INT | YES | | |
| durasi_ms | INT | YES | | |
| dibuat_oleh | CHAR(36) | YES | FK | → `users.id` |
| created_at / updated_at | TIMESTAMP | YES | | |

### Tabel: `activity_log` *(Spatie Activitylog)*

Standar Spatie — `subject_id`/`causer_id` VARCHAR(36) UUID-compatible.

### 10.1 Override Kode CPL/BoK & Impor Sintesys *(BARU, belum ada di v6.0/6.1)*

> Konsekuensi adaptasi MK lintas unit: CPL/BoK milik universitas/fakultas yang terhubung ke MK yang diadaptasi prodi otomatis terlihat di menu CPL/BoK prodi, tapi hanya kode-nya yang boleh diseragamkan per prodi — bukan mengubah kode asli milik unit pemilik. `cpl_kode_overrides`/`bok_kode_overrides` menyimpan override tsb, dibuat lazily hanya saat prodi benar-benar mengubahnya.

**Tabel: `cpl_kode_overrides`**

| Kolom | Tipe | NULL | Key | Deskripsi |
|---|---|---|---|---|
| id | CHAR(36) | NO | PK | UUID v4 |
| academic_unit_id | CHAR(36) | NO | FK | → `academic_units.id` |
| cpl_id | CHAR(36) | NO | FK | → `cpl.id` |
| kode | VARCHAR(15) | NO | | Kode CPL hasil override untuk unit ini |
| created_at / updated_at | TIMESTAMP | YES | | |

**FK:** `academic_unit_id → academic_units.id (CASCADE)` · `cpl_id → cpl.id (CASCADE)`
**Indeks:** UQ `(academic_unit_id, cpl_id)` · UQ `(academic_unit_id, kode)`

**Tabel: `bok_kode_overrides`** — struktur identik, mengganti `cpl_id` dengan `bok_id → bok.id`.

**Tabel: `kelas_mk_sintesys_imports`**

> Melacak status job impor kelas MK & peserta dari Sintesys secara asinkron.

| Kolom | Tipe | NULL | Key | Deskripsi |
|---|---|---|---|---|
| id | CHAR(36) | NO | PK | UUID v4 |
| semester_id | CHAR(36) | YES | FK | → `semesters.id`, `nullOnDelete` |
| academic_unit_id | CHAR(36) | YES | FK | → `academic_units.id`, `nullOnDelete` |
| tahun_akademik | VARCHAR(10) | NO | | |
| kode_prodi | VARCHAR(30) | NO | | |
| status | ENUM('pending','running','completed','failed') | NO | | Default `pending` |
| total | INT UNSIGNED | YES | | |
| processed | INT UNSIGNED | NO | | Default 0 |
| kelas_dibuat | INT UNSIGNED | NO | | Default 0 |
| kelas_diperbarui | INT UNSIGNED | NO | | Default 0 |
| peserta_terdaftar | INT UNSIGNED | NO | | Default 0 |
| peserta_sudah_terdaftar | INT UNSIGNED | NO | | Default 0 |
| errors | JSON | YES | | |
| pesan_gagal | TEXT | YES | | |
| dibuat_oleh | CHAR(36) | YES | FK | → `users.id`, `nullOnDelete` |
| created_at / updated_at | TIMESTAMP | YES | | |

**Tabel: `notifications`** — tabel notifikasi standar Laravel (`id` UUID, `type`, `notifiable_type`/`notifiable_id`, `data` JSON, `read_at`, timestamps).

---

## 11. Ringkasan Seluruh Tabel v6

| # | Nama Tabel | Kelompok | Catatan |
|---|---|---|---|
| 1 | academic_units | Hierarki | **UUID PK** · profil lengkap; tabel `universitas/fakultas/jurusan/prodis` dihapus |
| 2 | academic_unit_users | Hierarki | +`status_tim_kurikulum`, +`jabatan` |
| 3 | users | Pengguna | tanpa `prodi_id` |
| 4 | mahasiswas | Pengguna | `phone` → `nomor_wa`; `prodi_id` → `academic_unit_id` |
| 5 | roles | RBAC | UUID |
| 6 | permissions | RBAC | UUID — `kelola_unit`/`kelola_user` tunggal (bukan per tipe unit), +`kelola_permission`, +`impersonate_user`, +`kelola_evaluasi`, +`kelola_mk_unit`, +`kelola_peserta_kelas`, +`setdosen_mk` |
| 7 | model_has_roles | RBAC | |
| 8 | model_has_permissions | RBAC | |
| 9 | role_has_permissions | RBAC | |
| 10 | semesters | Kalender | |
| 11 | kurikulum | Kurikulum | `academic_unit_id` (ex-`prodi_id`) |
| 12 | profil_lulusan | Kurikulum | khusus prodi |
| 13 | profil_indikators | Kurikulum | |
| 14 | state_transitions | Workflow | |
| 15 | cpl | CPL | `academic_unit_id` (ex-level/level_id); +`kurikulum_id`; `domain` kini **JSON** multi-select |
| 16 | bok | BoK | `academic_unit_id`; +`kurikulum_id` |
| 17 | mk | MK | -`kode`, -`semester_ke`, -`status`; +`is_active`, +`sks_teori/praktik/lapangan`; `academic_unit_id`; +`kurikulum_id`; +`koordinator_mk_id` |
| 18 | mk_units | MK | **BARU** — pivot `academic_unit_id ↔ mk_id` + kode + semester_ke; +`kurikulum_id` |
| 19 | cpl_profil_lulusan | Pivot | |
| 20 | cpl_bok | Pivot | |
| 21 | cpl_mk | Pivot | |
| 22 | cpmk | CPMK | |
| 23 | mk_cpmk | Pivot | |
| 24 | subcpmk | CPMK | **-`cpmk_id`** (akses lewat `mk_cpmk_id`) |
| 25 | kelas_mk | Kelas | `mk_id` → **`mk_unit_id`**, +`koordinator_mk_id` |
| 26 | kelas_mk_mahasiswa | Kelas | |
| 27 | evaluasi | Penilaian | |
| 28 | komponen_penilaian | Penilaian | **default bobot=100**; v6.2: `kelas_mk_id` → **`mk_id`+`semester_id`** (dipakai bersama semua kelas) |
| 29 | subcpmk_komponenpenilaian | Pivot | default bobot=100 |
| 30 | nilai_mahasiswas | Penilaian | kolom **`subcpmk_komponenpenilaian_id`** |
| 31 | hasil_subcpmk | Kalkulasi | |
| 32 | hasil_cpmk | Kalkulasi | |
| 33 | hasil_cpl_mk | Kalkulasi | `mk_id` → **`mk_unit_id`** |
| 34 | hasil_cpl_mk_unit | Kalkulasi | **BARU** — CPL × MK × unit non-prodi |
| 35 | hasil_cpl_unit | Kalkulasi | rename `hasil_cpl_prodi`; `academic_unit_id` |
| 36 | analisis_ai | AI | `prodi_id` → **`academic_unit_id`** |
| 37 | activity_log | Audit | Spatie Activitylog |
| 38 | cpl_kode_overrides | CPL | **BARU** — override kode CPL per unit saat CPL diadaptasi lintas unit |
| 39 | bok_kode_overrides | BoK | **BARU** — override kode BoK per unit saat BoK diadaptasi lintas unit |
| 40 | kelas_mk_sintesys_imports | Kelas | **BARU** — status/progres job impor kelas MK dari Sintesys (async) |
| 41 | notifications | Sistem | **BARU** — tabel notifikasi standar Laravel |

---

## 12. Migrasi Laravel 13 — Lengkap (Siap Salin-Tempel)

Setiap migration berikut adalah file `database/migrations/yyyy_mm_dd_xxxxxx_*.php` Laravel 13 dengan anonymous class. Urutkan penomoran timestamp agar dependensi FK tersedia.

### 12.1 `xxxx_create_academic_units_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('academic_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('parent_id')->nullable()
                  ->constrained('academic_units')
                  ->cascadeOnUpdate()->restrictOnDelete();
            $table->enum('type', ['university','faculty','department','study_program']);
            $table->string('code', 30)->nullable();
            $table->string('kode_pddikti', 30)->nullable();
            $table->string('nama', 150);
            $table->string('singkatan', 30)->nullable();
            $table->string('jenjang', 10)->nullable();
            $table->string('gelar_lulusan', 50)->nullable();
            $table->string('mapel', 100)->nullable();
            $table->text('visi')->nullable();
            $table->text('misi')->nullable();
            $table->text('tujuan')->nullable();
            $table->text('sasaran_strategis')->nullable();
            $table->text('alamat')->nullable();
            $table->string('no_telepon', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('website', 100)->nullable();
            $table->string('logo_path', 200)->nullable();
            $table->string('tahun_pendirian', 4)->nullable();
            $table->string('sk_pendirian', 100)->nullable();
            $table->string('tahun_akreditasi', 4)->nullable();
            $table->string('sk_akreditasi', 100)->nullable();
            $table->string('peringkat_akreditasi', 20)->nullable();
            $table->string('tahun_internasional', 4)->nullable();
            $table->string('sk_internasional', 100)->nullable();
            $table->enum('status', ['draft','aktif','nonaktif'])->default('draft');
            $table->timestamps();

            $table->index('type', 'idx_au_type');
            $table->index('parent_id', 'idx_au_parent');
            $table->index('code', 'idx_au_code');
            $table->unique('kode_pddikti', 'uq_au_pddikti');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_units');
    }
};
```

### 12.2 `xxxx_create_users_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('username', 50)->unique();
            $table->string('email', 150)->unique();
            $table->string('nidn', 20)->nullable()->unique();
            $table->string('prefix', 30)->nullable();
            $table->string('full_name', 150);
            $table->string('suffix', 50)->nullable();
            $table->enum('jenis_kelamin', ['L','P'])->nullable();
            $table->string('nomor_wa', 20)->nullable();
            $table->string('password');
            $table->string('avatar')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('username', 'idx_users_username');
            $table->index('email', 'idx_users_email');
            $table->index('nidn', 'idx_users_nidn');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
```

### 12.3 `xxxx_create_academic_unit_users_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('academic_unit_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_unit_id')
                  ->constrained('academic_units')->cascadeOnDelete();
            $table->foreignUuid('user_id')
                  ->constrained('users')->cascadeOnDelete();
            $table->boolean('status_pimpinan')->default(false);
            $table->boolean('status_tim_kurikulum')->default(false);
            $table->string('jabatan', 100)->nullable();
            $table->timestamps();

            $table->unique(['academic_unit_id', 'user_id'], 'uq_auu_unit_user');
            $table->index('user_id', 'idx_auu_user');
            $table->index('status_pimpinan', 'idx_auu_pimpinan');
            $table->index('status_tim_kurikulum', 'idx_auu_timkur');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_unit_users');
    }
};
```

### 12.4 `xxxx_create_mahasiswas_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mahasiswas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nim', 20)->unique();
            $table->string('nama', 150)->nullable();
            $table->enum('jenis_kelamin', ['L','P'])->nullable();
            $table->string('angkatan', 4)->nullable();
            $table->foreignUuid('academic_unit_id')
                  ->constrained('academic_units')->restrictOnDelete();
            $table->string('email', 100)->nullable();
            $table->string('nomor_wa', 20)->nullable();
            $table->enum('status', ['aktif','cuti','lulus','do','nonaktif'])->default('aktif');
            $table->timestamps();

            $table->index('nim', 'idx_mhs_nim');
            $table->index('academic_unit_id', 'idx_mhs_unit');
            $table->index('angkatan', 'idx_mhs_angkatan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mahasiswas');
    }
};
```

### 12.5 `xxxx_create_permission_tables.php` *(Spatie UUID)*

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $teams = config('permission.teams');
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');

        Schema::create($tableNames['permissions'], function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 125);
            $table->string('guard_name', 125);
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['roles'], function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 125);
            $table->string('guard_name', 125);
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $columnNames) {
            $table->foreignUuid('permission_id')
                  ->constrained($tableNames['permissions'])->cascadeOnDelete();
            $table->string('model_type');
            $table->uuid($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'mhp_model_id_model_type_index');
            $table->primary(['permission_id', $columnNames['model_morph_key'], 'model_type'], 'mhp_pri');
        });

        Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $columnNames) {
            $table->foreignUuid('role_id')
                  ->constrained($tableNames['roles'])->cascadeOnDelete();
            $table->string('model_type');
            $table->uuid($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'mhr_model_id_model_type_index');
            $table->primary(['role_id', $columnNames['model_morph_key'], 'model_type'], 'mhr_pri');
        });

        Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames) {
            $table->foreignUuid('permission_id')
                  ->constrained($tableNames['permissions'])->cascadeOnDelete();
            $table->foreignUuid('role_id')
                  ->constrained($tableNames['roles'])->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id'], 'rhp_pri');
        });

        app('cache')->store(config('permission.cache.store') != 'default'
            ? config('permission.cache.store') : null)->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');
        Schema::drop($tableNames['role_has_permissions']);
        Schema::drop($tableNames['model_has_roles']);
        Schema::drop($tableNames['model_has_permissions']);
        Schema::drop($tableNames['roles']);
        Schema::drop($tableNames['permissions']);
    }
};
```

### 12.6 `xxxx_create_semesters_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('semesters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 5)->unique();
            $table->string('nama', 50);
            $table->year('tahun_mulai');
            $table->year('tahun_selesai');
            $table->enum('jenis', ['ganjil','genap','pendek']);
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->boolean('status_aktif')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semesters');
    }
};
```

### 12.7 `xxxx_create_kurikulum_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kurikulum', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_unit_id')
                  ->constrained('academic_units')->cascadeOnDelete();
            $table->string('nama', 150);
            $table->string('kode', 30)->nullable();
            $table->year('tahun');
            $table->unsignedTinyInteger('target_capaian_lulusan')->default(75);
            $table->text('deskripsi')->nullable();
            $table->string('state', 50)->default('draft');
            $table->boolean('is_active')->default(false);
            $table->foreignUuid('dibuat_oleh')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('academic_unit_id', 'idx_kur_unit');
            $table->index('state', 'idx_kur_state');
        });

        Schema::create('profil_lulusan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kurikulum_id')
                  ->constrained('kurikulum')->cascadeOnDelete();
            $table->string('kode', 10);
            $table->string('nama', 150)->nullable();
            $table->text('deskripsi');
            $table->unsignedTinyInteger('urutan')->nullable();
            $table->timestamps();
        });

        Schema::create('profil_indikators', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('profil_id')
                  ->constrained('profil_lulusan')->cascadeOnDelete();
            $table->text('nama')->nullable();
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });

        Schema::create('state_transitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('model_type', 100);
            $table->uuid('model_id');
            $table->string('from_state', 50)->nullable();
            $table->string('to_state', 50);
            $table->foreignUuid('actor_id')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->text('keterangan')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['model_type', 'model_id'], 'idx_st_model');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('state_transitions');
        Schema::dropIfExists('profil_indikators');
        Schema::dropIfExists('profil_lulusan');
        Schema::dropIfExists('kurikulum');
    }
};
```

### 12.8 `xxxx_create_cpl_bok_mk_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cpl', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_unit_id')
                  ->constrained('academic_units')->cascadeOnDelete();
            $table->foreignUuid('kurikulum_id')
                  ->constrained('kurikulum')->cascadeOnDelete();
            $table->string('kode', 15);
            $table->text('deskripsi');
            $table->enum('domain', ['kognitif','afektif','psikomotorik','gabungan'])->nullable();
            $table->timestamps();

            $table->index('academic_unit_id', 'idx_cpl_unit');
            $table->index('kurikulum_id', 'idx_cpl_kurikulum');
            $table->unique(['kurikulum_id', 'kode'], 'uq_cpl_kur_kode');
        });

        Schema::create('bok', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_unit_id')
                  ->constrained('academic_units')->cascadeOnDelete();
            $table->foreignUuid('kurikulum_id')
                  ->constrained('kurikulum')->cascadeOnDelete();
            $table->string('kode', 15);
            $table->string('nama', 150);
            $table->text('deskripsi')->nullable();
            $table->timestamps();

            $table->index('academic_unit_id', 'idx_bok_unit');
            $table->index('kurikulum_id', 'idx_bok_kurikulum');
            $table->unique(['kurikulum_id', 'kode'], 'uq_bok_kur_kode');
        });

        Schema::create('mk', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_unit_id')
                  ->constrained('academic_units')->cascadeOnDelete();
            $table->foreignUuid('kurikulum_id')
                  ->constrained('kurikulum')->cascadeOnDelete();
            $table->string('state', 50)->default('draft');
            $table->string('nama', 150);
            $table->unsignedTinyInteger('sks');
            $table->integer('sks_teori')->default(0);
            $table->integer('sks_praktik')->default(0);
            $table->integer('sks_lapangan')->default(0);
            $table->enum('jenis', ['wajib','pilihan','praktikum']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('academic_unit_id', 'idx_mk_unit');
            $table->index('kurikulum_id', 'idx_mk_kurikulum');
            $table->index('is_active', 'idx_mk_active');
        });

        Schema::create('mk_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_unit_id')
                  ->constrained('academic_units')->cascadeOnDelete();
            $table->foreignUuid('kurikulum_id')
                  ->constrained('kurikulum')->cascadeOnDelete();
            $table->foreignUuid('mk_id')
                  ->constrained('mk')->cascadeOnDelete();
            $table->string('kode', 20);
            $table->unsignedTinyInteger('semester_ke')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['kurikulum_id', 'mk_id'], 'uq_mu_kur_mk');
            $table->unique(['kurikulum_id', 'kode'], 'uq_mu_kur_kode');
            $table->index('semester_ke', 'idx_mu_semester');
            $table->index('is_active', 'idx_mu_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mk_units');
        Schema::dropIfExists('mk');
        Schema::dropIfExists('bok');
        Schema::dropIfExists('cpl');
    }
};
```

### 12.9 `xxxx_create_cpl_pivots_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cpl_profil_lulusan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cpl_id')->constrained('cpl')->cascadeOnDelete();
            $table->foreignUuid('profil_lulusan_id')
                  ->constrained('profil_lulusan')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['cpl_id', 'profil_lulusan_id'], 'uq_cpl_profil');
        });

        Schema::create('cpl_bok', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cpl_id')->constrained('cpl')->cascadeOnDelete();
            $table->foreignUuid('bok_id')->constrained('bok')->cascadeOnDelete();
            $table->decimal('bobot', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['cpl_id', 'bok_id'], 'uq_cpl_bok');
        });

        Schema::create('cpl_mk', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cpl_bok_id')->constrained('cpl_bok')->cascadeOnDelete();
            $table->foreignUuid('mk_id')->constrained('mk')->cascadeOnDelete();
            $table->decimal('bobot', 5, 2);
            $table->timestamps();
            $table->unique(['cpl_bok_id', 'mk_id'], 'uq_cpl_mk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpl_mk');
        Schema::dropIfExists('cpl_bok');
        Schema::dropIfExists('cpl_profil_lulusan');
    }
};
```

### 12.10 `xxxx_create_cpmk_subcpmk_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cpmk', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('mk_id')->constrained('mk')->cascadeOnDelete();
            $table->string('kode', 15);
            $table->text('deskripsi');
            $table->timestamps();

            $table->index('mk_id', 'idx_cpmk_mk');
        });

        Schema::create('mk_cpmk', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cpl_mk_id')->constrained('cpl_mk')->cascadeOnDelete();
            $table->foreignUuid('cpmk_id')->constrained('cpmk')->cascadeOnDelete();
            $table->decimal('bobot', 5, 2);
            $table->timestamps();
            $table->unique(['cpl_mk_id', 'cpmk_id'], 'uq_mk_cpmk');
        });

        Schema::create('subcpmk', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // cpmk_id DIHAPUS — diakses lewat mk_cpmk_id
            $table->foreignUuid('mk_cpmk_id')
                  ->constrained('mk_cpmk')->cascadeOnDelete();
            $table->foreignUuid('semester_id')->nullable()
                  ->constrained('semesters')->nullOnDelete();
            $table->string('kode', 15);
            $table->text('deskripsi');
            $table->text('indikator')->nullable();
            $table->text('evaluasi')->nullable();
            $table->double('bobot')->nullable();
            $table->enum('bloom_kognitif', ['C1','C2','C3','C4','C5','C6'])->nullable();
            $table->enum('bloom_afektif', ['A1','A2','A3','A4','A5'])->nullable();
            $table->enum('bloom_psikomotorik', ['P1','P2','P3','P4','P5','P6','P7'])->nullable();
            $table->timestamps();

            $table->index('mk_cpmk_id', 'idx_subcpmk_mkcpmk');
            $table->index('semester_id', 'idx_subcpmk_semester');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcpmk');
        Schema::dropIfExists('mk_cpmk');
        Schema::dropIfExists('cpmk');
    }
};
```

### 12.11 `xxxx_create_kelas_mk_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kelas_mk', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('mk_unit_id')
                  ->constrained('mk_units')->cascadeOnDelete();
            $table->foreignUuid('semester_id')
                  ->constrained('semesters')->restrictOnDelete();
            $table->string('kode_kelas', 10);
            $table->foreignUuid('dosen_pengampu_id')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->foreignUuid('koordinator_mk_id')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('kapasitas')->nullable();
            $table->timestamps();

            $table->unique(['mk_unit_id', 'semester_id', 'kode_kelas'], 'uq_kmk_unit_sem_kls');
        });

        Schema::create('kelas_mk_mahasiswa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kelas_mk_id')
                  ->constrained('kelas_mk')->cascadeOnDelete();
            $table->foreignUuid('mahasiswa_id')
                  ->constrained('mahasiswas')->cascadeOnDelete();
            $table->decimal('nilai_angka', 5, 2)->nullable();
            $table->string('nilai_huruf', 5)->nullable();
            $table->timestamps();

            $table->unique(['kelas_mk_id', 'mahasiswa_id'], 'uq_kmm');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas_mk_mahasiswa');
        Schema::dropIfExists('kelas_mk');
    }
};
```

### 12.12 `xxxx_create_penilaian_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('evaluasi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 30)->unique();
            $table->string('kategori', 100)->nullable()->index();
            $table->string('workcloud', 100)->nullable();
            $table->string('nama', 150);
            $table->timestamps();
        });

        Schema::create('komponen_penilaian', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kelas_mk_id')
                  ->constrained('kelas_mk')->cascadeOnDelete();
            $table->foreignUuid('evaluasi_id')
                  ->constrained('evaluasi')->restrictOnDelete();
            $table->string('kode', 30)->nullable();
            $table->string('nama', 100);
            $table->decimal('bobot', 5, 2)->default(100.00);
            $table->timestamps();

            $table->index('kelas_mk_id', 'idx_komponen_kelas');
        });

        Schema::create('subcpmk_komponenpenilaian', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('subcpmk_id')
                  ->constrained('subcpmk')->cascadeOnDelete();
            $table->foreignUuid('komponen_penilaian_id')
                  ->constrained('komponen_penilaian')->cascadeOnDelete();
            $table->foreignUuid('semester_id')->nullable()
                  ->constrained('semesters')->nullOnDelete();
            $table->double('bobot')->default(100);
            $table->timestamps();

            $table->unique(['subcpmk_id', 'komponen_penilaian_id'], 'uq_skp');
        });

        Schema::create('nilai_mahasiswas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('subcpmk_komponenpenilaian_id')
                  ->constrained('subcpmk_komponenpenilaian')
                  ->cascadeOnDelete();
            $table->foreignUuid('kelas_mk_mahasiswa_id')
                  ->constrained('kelas_mk_mahasiswa')->cascadeOnDelete();
            $table->decimal('nilai', 5, 2)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(
                ['subcpmk_komponenpenilaian_id', 'kelas_mk_mahasiswa_id'],
                'uq_nilai_skp_kmm'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai_mahasiswas');
        Schema::dropIfExists('subcpmk_komponenpenilaian');
        Schema::dropIfExists('komponen_penilaian');
        Schema::dropIfExists('evaluasi');
    }
};
```

### 12.13 `xxxx_create_hasil_kalkulasi_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hasil_subcpmk', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('subcpmk_id')->constrained('subcpmk')->cascadeOnDelete();
            $table->foreignUuid('kelas_mk_mahasiswa_id')
                  ->constrained('kelas_mk_mahasiswa')->cascadeOnDelete();
            $table->foreignUuid('kelas_mk_id')->constrained('kelas_mk')->cascadeOnDelete();
            $table->decimal('nilai_akhir', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['subcpmk_id', 'kelas_mk_mahasiswa_id'], 'uq_hsub');
        });

        Schema::create('hasil_cpmk', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cpmk_id')->constrained('cpmk')->cascadeOnDelete();
            $table->foreignUuid('kelas_mk_mahasiswa_id')
                  ->constrained('kelas_mk_mahasiswa')->cascadeOnDelete();
            $table->foreignUuid('kelas_mk_id')->constrained('kelas_mk')->cascadeOnDelete();
            $table->decimal('nilai_akhir', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['cpmk_id', 'kelas_mk_mahasiswa_id'], 'uq_hcpmk');
        });

        Schema::create('hasil_cpl_mk', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cpl_id')->constrained('cpl')->cascadeOnDelete();
            $table->foreignUuid('mk_unit_id')->constrained('mk_units')->cascadeOnDelete();
            $table->foreignUuid('kelas_mk_mahasiswa_id')
                  ->constrained('kelas_mk_mahasiswa')->cascadeOnDelete();
            $table->foreignUuid('semester_id')->constrained('semesters')->restrictOnDelete();
            $table->decimal('nilai_akhir', 5, 2)->nullable();
            $table->decimal('nilai_berbobot', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(
                ['cpl_id', 'mk_unit_id', 'kelas_mk_mahasiswa_id', 'semester_id'],
                'uq_hcm'
            );
        });

        Schema::create('hasil_cpl_mk_unit', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cpl_id')->constrained('cpl')->cascadeOnDelete();
            $table->foreignUuid('mk_id')->constrained('mk')->cascadeOnDelete();
            $table->foreignUuid('academic_unit_id')
                  ->constrained('academic_units')->cascadeOnDelete();
            $table->foreignUuid('semester_id')->constrained('semesters')->restrictOnDelete();
            $table->decimal('rata_rata', 5, 2)->nullable();
            $table->decimal('persentase_tercapai', 5, 2)->nullable();
            $table->unsignedSmallInteger('jumlah_mahasiswa')->nullable();
            $table->timestamps();
            $table->unique(
                ['cpl_id', 'mk_id', 'academic_unit_id', 'semester_id'],
                'uq_hcmu'
            );
            $table->index('academic_unit_id', 'idx_hcmu_unit');
        });

        Schema::create('hasil_cpl_unit', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cpl_id')->constrained('cpl')->cascadeOnDelete();
            $table->foreignUuid('academic_unit_id')
                  ->constrained('academic_units')->cascadeOnDelete();
            $table->foreignUuid('semester_id')->constrained('semesters')->restrictOnDelete();
            $table->decimal('rata_rata', 5, 2)->nullable();
            $table->decimal('persentase_tercapai', 5, 2)->nullable();
            $table->unsignedSmallInteger('jumlah_mahasiswa')->nullable();
            $table->timestamps();
            $table->unique(
                ['cpl_id', 'academic_unit_id', 'semester_id'],
                'uq_hcu'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hasil_cpl_unit');
        Schema::dropIfExists('hasil_cpl_mk_unit');
        Schema::dropIfExists('hasil_cpl_mk');
        Schema::dropIfExists('hasil_cpmk');
        Schema::dropIfExists('hasil_subcpmk');
    }
};
```

### 12.14 `xxxx_create_analisis_ai_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('analisis_ai', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_unit_id')->nullable()
                  ->constrained('academic_units')->nullOnDelete();
            $table->foreignUuid('semester_id')->nullable()
                  ->constrained('semesters')->nullOnDelete();
            $table->enum('jenis', [
                'ringkasan_cpl', 'rekomendasi_kurikulum', 'tren_capaian', 'lainnya'
            ]);
            $table->json('konteks')->nullable();
            $table->text('prompt');
            $table->longText('hasil')->nullable();
            $table->string('model_ai', 80)->nullable();
            $table->unsignedInteger('token_digunakan')->nullable();
            $table->unsignedInteger('durasi_ms')->nullable();
            $table->foreignUuid('dibuat_oleh')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analisis_ai');
    }
};
```

### 12.15 `xxxx_create_activity_log_table.php` *(Spatie Activitylog)*

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->string('event')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
```

---

## 13. `RolePermissionSeeder.php` Lengkap (siap salin-tempel)

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\AcademicUnit;
use App\Models\AcademicUnitUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // =========================================================
        // 1. PERMISSIONS
        // =========================================================
        $perms = [
            // ---- Institusi ----
            'kelola_universitas', 'kelola_fakultas', 'kelola_jurusan', 'kelola_prodi',
            'kelola_semester', 'kelola_evaluasi',

            // ---- Admin & Auth ----
            'kelola_user', 'kelola_role', 'kelola_permission',
            'lihat_audit_log', 'konfigurasi_sistem',

            // ---- User-management per tipe unit ----
            'kelola_user_universitas', 'kelola_user_fakultas',
            'kelola_user_jurusan', 'kelola_user_prodi',

            // ---- Kurikulum ----
            'kelola_kurikulum', 'kelola_profil_lulusan',
            'kelola_cpl', 'kelola_bok', 'kelola_mk', 'kelola_mk_unit',

            // ---- CPMK / SubCPMK / Komponen ----
            'kelola_cpmk', 'kelola_subcpmk', 'kelola_komponen_penilaian',

            // ---- Kelas & Penilaian ----
            'kelola_kelas', 'setdosen_mk', 'input_nilai', 'import_nilai',

            // ---- Laporan & AI ----
            'lihat_laporan', 'ekspor_data', 'minta_analisis_ai', 'lihat_dashboard',
        ];

        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        // =========================================================
        // 2. ROLES
        // =========================================================

        // --- Super Admin: HANYA Institusi & Admin + kelola_evaluasi ---
        $super = Role::firstOrCreate(['name' => 'Super Admin']);
        $super->syncPermissions([
            'kelola_universitas', 'kelola_fakultas', 'kelola_jurusan', 'kelola_prodi',
            'kelola_semester', 'kelola_evaluasi',
            'kelola_user', 'kelola_role', 'kelola_permission',
            'lihat_audit_log', 'konfigurasi_sistem',
            'kelola_user_universitas', 'kelola_user_fakultas',
            'kelola_user_jurusan', 'kelola_user_prodi',
        ]);

        // --- Admin per Tipe Unit (kelola sesuai type_unit) ---
        $adminUniv = Role::firstOrCreate(['name' => 'Admin Universitas']);
        $adminUniv->syncPermissions([
            'kelola_user_universitas', 'kelola_user_fakultas',
            'kelola_user_jurusan', 'kelola_user_prodi',
            'kelola_fakultas', 'kelola_jurusan', 'kelola_prodi',
            'setdosen_mk',
            'lihat_laporan', 'ekspor_data',
        ]);

        $adminFak = Role::firstOrCreate(['name' => 'Admin Fakultas']);
        $adminFak->syncPermissions([
            'kelola_user_fakultas', 'kelola_user_jurusan', 'kelola_user_prodi',
            'kelola_jurusan', 'kelola_prodi',
            'setdosen_mk',
            'lihat_laporan', 'ekspor_data',
        ]);

        $adminJur = Role::firstOrCreate(['name' => 'Admin Jurusan']);
        $adminJur->syncPermissions([
            'kelola_user_jurusan', 'kelola_user_prodi',
            'kelola_prodi',
            'setdosen_mk',
            'lihat_laporan', 'ekspor_data',
        ]);

        $adminProdi = Role::firstOrCreate(['name' => 'Admin Program Studi']);
        $adminProdi->syncPermissions([
            'kelola_user_prodi',
            'kelola_kelas',
            'setdosen_mk',
            'lihat_laporan', 'ekspor_data',
        ]);

        // --- Tim Kurikulum (Profil khusus prodi; CPL→BoK→MK untuk semua unit) ---
        $timKur = Role::firstOrCreate(['name' => 'Tim Kurikulum']);
        $timKur->syncPermissions([
            'kelola_kurikulum',
            'kelola_profil_lulusan', // hanya berlaku ketika status_tim_kurikulum=1 pada unit study_program
            'kelola_cpl', 'kelola_bok', 'kelola_mk', 'kelola_mk_unit',
            'setdosen_mk',
            'lihat_laporan',
        ]);

        // --- Koordinator Mata Kuliah ---
        $korma = Role::firstOrCreate(['name' => 'Koordinator Mata Kuliah']);
        $korma->syncPermissions([
            'kelola_cpmk', 'kelola_subcpmk', 'kelola_komponen_penilaian',
            'lihat_laporan', 'lihat_dashboard',
        ]);

        // --- Dosen Pengampu (TANPA kelola_komponen_penilaian) ---
        $dosen = Role::firstOrCreate(['name' => 'Dosen Pengampu']);
        $dosen->syncPermissions([
            'kelola_kelas', 'input_nilai', 'import_nilai',
            'lihat_laporan', 'lihat_dashboard',
        ]);

        // --- Pimpinan * (per tipe unit; permission seperti Kaprodi) ---
        $pimpinanPerms = [
            'lihat_laporan', 'ekspor_data', 'minta_analisis_ai', 'lihat_dashboard',
        ];
        foreach ([
            'Pimpinan Universitas', 'Pimpinan Fakultas',
            'Pimpinan Jurusan', 'Pimpinan Program Studi',
        ] as $rname) {
            Role::firstOrCreate(['name' => $rname])->syncPermissions($pimpinanPerms);
        }

        // --- Auditor Mutu ---
        $auditor = Role::firstOrCreate(['name' => 'Auditor Mutu']);
        $auditor->syncPermissions([
            'lihat_laporan', 'ekspor_data', 'lihat_audit_log', 'lihat_dashboard',
        ]);

        // =========================================================
        // 3. AKUN SEMENTARA + PENETAPAN UNIT
        // =========================================================
        // Asumsi: AcademicUnitSeeder sudah dijalankan dan menyediakan
        //         minimal satu unit pada tiap level.
        $univ  = AcademicUnit::where('type', 'university')->first();
        $fak   = AcademicUnit::where('type', 'faculty')->first();
        $jur   = AcademicUnit::where('type', 'department')->first();
        $prodi = AcademicUnit::where('type', 'study_program')->first();

        $accounts = [
            // Super
            ['username' => 'superadmin', 'full_name' => 'Super Administrator',
             'nidn' => null, 'role' => 'Super Admin', 'unit' => null,
             'pimpinan' => false, 'tim_kur' => false, 'jabatan' => 'Super Admin'],

            // Pimpinan Universitas
            ['username' => 'rektor', 'full_name' => 'Rektor',
             'nidn' => '0000000001', 'role' => 'Pimpinan Universitas', 'unit' => $univ,
             'pimpinan' => true, 'tim_kur' => false, 'jabatan' => 'Rektor'],
            ['username' => 'wakilrektor', 'full_name' => 'Wakil Rektor I',
             'nidn' => '0000000002', 'role' => 'Pimpinan Universitas', 'unit' => $univ,
             'pimpinan' => true, 'tim_kur' => false, 'jabatan' => 'Wakil Rektor I'],

            // Pimpinan Fakultas
            ['username' => 'dekan', 'full_name' => 'Dekan',
             'nidn' => '0000000003', 'role' => 'Pimpinan Fakultas', 'unit' => $fak,
             'pimpinan' => true, 'tim_kur' => false, 'jabatan' => 'Dekan'],
            ['username' => 'wakildekan', 'full_name' => 'Wakil Dekan I',
             'nidn' => '0000000004', 'role' => 'Pimpinan Fakultas', 'unit' => $fak,
             'pimpinan' => true, 'tim_kur' => false, 'jabatan' => 'Wakil Dekan I'],

            // Pimpinan Jurusan
            ['username' => 'kajur', 'full_name' => 'Ketua Jurusan',
             'nidn' => '0000000005', 'role' => 'Pimpinan Jurusan', 'unit' => $jur,
             'pimpinan' => true, 'tim_kur' => false, 'jabatan' => 'Ketua Jurusan'],
            ['username' => 'sekjur', 'full_name' => 'Sekretaris Jurusan',
             'nidn' => '0000000006', 'role' => 'Pimpinan Jurusan', 'unit' => $jur,
             'pimpinan' => true, 'tim_kur' => false, 'jabatan' => 'Sekretaris Jurusan'],

            // Pimpinan Prodi
            ['username' => 'kaprodi', 'full_name' => 'Ketua Program Studi',
             'nidn' => '0000000007', 'role' => 'Pimpinan Program Studi', 'unit' => $prodi,
             'pimpinan' => true, 'tim_kur' => false, 'jabatan' => 'Ketua Program Studi'],

            // Admin per unit
            ['username' => 'adminuniv', 'full_name' => 'Admin Universitas',
             'nidn' => '0000000010', 'role' => 'Admin Universitas', 'unit' => $univ,
             'pimpinan' => false, 'tim_kur' => false, 'jabatan' => 'Admin'],
            ['username' => 'adminfak', 'full_name' => 'Admin Fakultas',
             'nidn' => '0000000011', 'role' => 'Admin Fakultas', 'unit' => $fak,
             'pimpinan' => false, 'tim_kur' => false, 'jabatan' => 'Admin'],
            ['username' => 'adminjur', 'full_name' => 'Admin Jurusan',
             'nidn' => '0000000012', 'role' => 'Admin Jurusan', 'unit' => $jur,
             'pimpinan' => false, 'tim_kur' => false, 'jabatan' => 'Admin'],
            ['username' => 'adminprodi', 'full_name' => 'Admin Program Studi',
             'nidn' => '0000000013', 'role' => 'Admin Program Studi', 'unit' => $prodi,
             'pimpinan' => false, 'tim_kur' => false, 'jabatan' => 'Admin'],

            // Tim Kurikulum (ditetapkan ke unit prodi sebagai default)
            ['username' => 'timkur', 'full_name' => 'Tim Kurikulum Prodi',
             'nidn' => '0000000020', 'role' => 'Tim Kurikulum', 'unit' => $prodi,
             'pimpinan' => false, 'tim_kur' => true, 'jabatan' => 'Anggota Tim Kurikulum'],

            // Koordinator Mata Kuliah
            ['username' => 'korma', 'full_name' => 'Koordinator Mata Kuliah',
             'nidn' => '0000000030', 'role' => 'Koordinator Mata Kuliah', 'unit' => $prodi,
             'pimpinan' => false, 'tim_kur' => false, 'jabatan' => 'Koordinator MK'],

            // Dosen
            ['username' => 'dosen', 'full_name' => 'Dosen Pengampu',
             'nidn' => '0000000040', 'role' => 'Dosen Pengampu', 'unit' => $prodi,
             'pimpinan' => false, 'tim_kur' => false, 'jabatan' => 'Dosen'],

            // Auditor
            ['username' => 'auditor', 'full_name' => 'Auditor Mutu',
             'nidn' => '0000000099', 'role' => 'Auditor Mutu', 'unit' => null,
             'pimpinan' => false, 'tim_kur' => false, 'jabatan' => 'Auditor'],
        ];

        foreach ($accounts as $a) {
            $user = User::firstOrCreate(
                ['username' => $a['username']],
                [
                    'id'        => (string) Str::uuid(),
                    'email'     => $a['username'] . '@silaris.test',
                    'nidn'      => $a['nidn'],
                    'full_name' => $a['full_name'],
                    'password'  => Hash::make('Silaris2026!'),
                ]
            );

            $user->syncRoles([$a['role']]);

            if (!empty($a['unit'])) {
                AcademicUnitUser::updateOrCreate(
                    [
                        'academic_unit_id' => $a['unit']->id,
                        'user_id'          => $user->id,
                    ],
                    [
                        'id'                   => (string) Str::uuid(),
                        'status_pimpinan'      => $a['pimpinan'],
                        'status_tim_kurikulum' => $a['tim_kur'],
                        'jabatan'              => $a['jabatan'],
                    ]
                );
            }
        }
    }
}
```

---

*Selesai — siap diintegrasikan ke proyek Laravel 13 + Filament v4.*
