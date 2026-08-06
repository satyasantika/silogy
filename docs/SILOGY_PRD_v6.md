# SILOGY — Dokumen Kebutuhan Produk (PRD)

**Siliwangi Learning Outcomes & Quality Analytics**
*Universitas Siliwangi*

---

| Item | Nilai |
|---|---|
| Sistem Operasional | SILARIS – Siliwangi Learning & Quality Assurance System |
| Paradigma | Outcome-Based Education (OBE) |
| Stack Teknologi | Laravel 13 · Filament v4 · MySQL 8 · Redis · Google Gemini API |
| Versi Dokumen | 6.0 |
| Tagline | *"From learning data to academic quality"* |

---

## 1. Ringkasan Eksekutif

SILOGY (Siliwangi Learning Outcomes & Quality Analytics) adalah paradigma manajemen mutu akademik berbasis OBE untuk Universitas Siliwangi. Implementasi operasionalnya, **SILARIS**, adalah aplikasi web Laravel 13 + Filament v4 yang mengukur, menganalisis, dan meningkatkan ketercapaian CPL (Capaian Pembelajaran Lulusan) secara sistematis pada **semua level institusi** (universitas, fakultas, jurusan, program studi).

### 1.1 Tiga Pilar SILOGY

- **Pengukuran:** Data nilai mahasiswa → subCPMK → CPMK → CPL secara terstruktur dan dapat ditelusuri.
- **Analitik:** Mesin kalkulasi 5 tahap + AI Google Gemini API untuk wawasan mutu di setiap level unit, termasuk dashboard KPI rollup lintas-kurikulum untuk Pimpinan.
- **Peningkatan:** Rekomendasi berbasis data untuk perbaikan kurikulum dan pembelajaran lintas unit.

### 1.2 Perubahan Arsitektur Utama v6

- **`academic_units` sebagai sumber kebenaran tunggal hierarki institusi** dengan PK **UUID**. Tabel `universitas`, `fakultas`, `jurusan`, `prodis` dihapus; seluruh profil kelembagaan (visi, misi, akreditasi, SK) dipindahkan ke `academic_units`.
- **`academic_unit_users` + `status_tim_kurikulum`** menentukan tim kurikulum tiap unit.
- **`mk_units`** sebagai pivot baru antara `mk` dan `academic_units`: kolom `kode` dan `semester_ke` MK dipindahkan ke sini agar satu MK dapat ditawarkan di banyak unit/prodi dengan kode dan semester yang berbeda.
- **`mk`** ditambah `sks_teori`, `sks_praktik`, `sks_lapangan`, dan `is_active` (menggantikan `status`).
- **`kelas_mk.mk_unit_id`** menggantikan `mk_id` agar kelas terikat pada penawaran MK di prodi spesifik.
- **`subcpmk`** menghapus `cpmk_id` (akses lewat `mk_cpmk_id`).
- **`hasil_cpl_unit`** menggantikan `hasil_cpl_prodi`; ditambah **`hasil_cpl_mk_unit`** untuk agregat CPL level non-prodi.
- **`analisis_ai.academic_unit_id`** menggantikan `prodi_id` agar AI bekerja pada semua level.
- **RBAC disederhanakan**: 7 role generik (Super Admin, Admin, Tim Kurikulum, Koordinator Mata Kuliah, Dosen Pengampu, Pimpinan, Auditor Mutu); level/unit kerja ditentukan lewat penugasan `academic_unit_users` (`jabatan`, `status_pimpinan`, `status_tim_kurikulum`), bukan role terpisah per tipe unit; `kelola_komponen_penilaian` dipindah dari Dosen Pengampu ke Koordinator Mata Kuliah.
- **Workflow kurikulum** tetap: `draft → profil_lulusan* → cpl → bok → mk → setdosenmk → aktif` (*: hanya untuk unit prodi).

---

## 2. Pemangku Kepentingan & Pengguna

| Peran | Deskripsi | Penetapan Oleh | Lingkup Unit | Akses Utama |
|---|---|---|---|---|
| Super Admin | Administrator sistem | Teknis | — (lintas institusi) | Institusi (universitas/fakultas/jurusan/prodi), Master user/role/permission, Semester, Evaluasi |
| Admin | Operasional unit; level (universitas/fakultas/jurusan/prodi) ditentukan penugasan, bukan role terpisah | Super Admin / Admin unit di atasnya | Sesuai `academic_unit_users` | Kelola unit & user (lingkup penugasan), kurikulum, CPL/BoK/MK, kelas, set dosen MK, laporan |
| Tim Kurikulum | Penyusun kurikulum unit (`status_tim_kurikulum=1`) | Admin Unit | Sesuai penugasan, semua tipe unit | Profil Lulusan *(khusus prodi)*, CPL, BoK, MK, mk_units, set dosen MK |
| Koordinator Mata Kuliah | Pengelola CPMK, SubCPMK, Komponen Penilaian | Admin Unit | per kelas MK | Kelola CPMK/SubCPMK/Komponen Penilaian, kelola peserta kelas |
| Dosen Pengampu | Input nilai & kelola kelas | Admin Unit (academic_unit_users) | per kelas MK | Kelas MK, input/import nilai *(tanpa kelola_komponen_penilaian)* |
| Pimpinan | Rektor/Wakil Rektor, Dekan/Wakil Dekan, Ketua/Sekretaris Jurusan, atau Kaprodi (`status_pimpinan=1`); level ditentukan penugasan, bukan role terpisah | Super Admin / Admin unit di atasnya | Sesuai penugasan, semua tipe unit | Laporan, Dashboard, AI, ekspor data — sesuai level unit yang ditugaskan |
| Auditor Mutu | Audit sistem | Super Admin | — (lintas institusi, read-only) | Semua (read-only) |

> ⚠ Mahasiswa **TIDAK** memiliki akun login di SILARIS. Data mahasiswa dikelola via tabel `mahasiswas`.

---

## 3. Kebutuhan Fungsional

### 3.1 FR-AUTH — Autentikasi & Otorisasi

| Kode | Deskripsi | Prioritas |
|---|---|---|
| FR-AUTH-01 | Login via `username` ATAU `email`; password bcrypt | Tinggi |
| FR-AUTH-02 | RBAC: Spatie Permission UUID; FilamentShield per Resource | Tinggi |
| FR-AUTH-03 | Manajemen profil: `nidn`, `prefix`, `suffix`, `jenis_kelamin`, `nomor_wa` | Sedang |
| FR-AUTH-04 | Reset password via email | Tinggi |
| FR-AUTH-05 | Permission `kelola_permission` untuk mengelola daftar permission | Tinggi |

### 3.2 FR-INST — Manajemen Institusi

| Kode | Deskripsi | Prioritas |
|---|---|---|
| FR-INST-01 | `academic_units` (UUID) sebagai tabel tunggal hierarki universitas → fakultas → jurusan → program studi, lengkap dengan profil (visi, misi, SK, akreditasi, dll.) | Tinggi |
| FR-INST-02 | Tabel `universitas`, `fakultas`, `jurusan`, `prodis` **dihapus**; data lama dimigrasikan ke `academic_units` | Tinggi |
| FR-INST-03 | Penetapan pengelola via `academic_unit_users` dengan `status_pimpinan`, `status_tim_kurikulum`, dan `jabatan` | Tinggi |
| FR-INST-04 | Kelola mahasiswa: NIM, nama, angkatan, `academic_unit_id` (study_program), email, `nomor_wa` | Tinggi |
| FR-INST-05 | Kelola semester: kode `YYYYS`, `status_aktif` boolean | Tinggi |
| FR-INST-06 | Permission tunggal `kelola_unit` untuk kelola unit institusi (universitas/fakultas/jurusan/prodi); lingkup ditentukan penugasan `academic_unit_users`, bukan permission terpisah per tipe unit | Tinggi |
| FR-INST-07 | Permission tunggal `kelola_user` untuk manajemen pengguna; lingkup mengikuti unit penugasan admin yang bersangkutan | Tinggi |

### 3.3 FR-KUR — Manajemen Kurikulum

| Kode | Deskripsi | Prioritas |
|---|---|---|
| FR-KUR-01 | Workflow state: `draft → profil_lulusan* → cpl → bok → mk → setdosenmk → aktif` (*khusus prodi) | Tinggi |
| FR-KUR-02 | Profil lulusan + indikator (hanya unit `type=study_program`) | Tinggi |
| FR-KUR-03 | Kurikulum: `kode`, `target_capaian_lulusan` (default 75), `academic_unit_id` | Sedang |
| FR-KUR-04 | Tim Kurikulum unit non-prodi hanya mengelola **CPL → BoK → MK → setdosenmk**; unit prodi tambahan **Profil Lulusan** | Tinggi |
| FR-KUR-05 | `setdosen_mk` boleh dilakukan oleh Admin Unit ATAU Tim Kurikulum unit terkait | Tinggi |

### 3.4 FR-CPL — Manajemen CPL

| Kode | Deskripsi | Prioritas |
|---|---|---|
| FR-CPL-01 | CPL satu tabel dengan `academic_unit_id` (dapat di setiap level) | Tinggi |
| FR-CPL-02 | Pemetaan CPL ↔ Profil Lulusan *(khusus prodi)* | Tinggi |
| FR-CPL-03 | Pemetaan CPL → BoK via pivot `cpl_bok` | Tinggi |
| FR-CPL-04 | Pemetaan `cpl_bok → mk` via pivot `cpl_mk` (pakai `cpl_bok_id`) | Tinggi |
| FR-CPL-05 | Validasi total bobot per CPL ≤ 100% | Tinggi |

### 3.5 FR-MK — Manajemen MK, MK-Unit & CPMK

| Kode | Deskripsi | Prioritas |
|---|---|---|
| FR-MK-01 | `mk` satu tabel dengan `academic_unit_id` (pemilik MK di level mana saja); kolom `is_active` menggantikan `status` | Tinggi |
| FR-MK-02 | `mk` ditambah `sks_teori`, `sks_praktik`, `sks_lapangan` (default 0) | Tinggi |
| FR-MK-03 | `mk_units` (BARU): pivot `academic_unit_id ↔ mk_id` berisi `kode`, `semester_ke`, `is_active` spesifik unit | Tinggi |
| FR-MK-04 | `cpmk` per `mk_id`; pivot `mk_cpmk` pakai `cpl_mk_id` | Tinggi |
| FR-MK-05 | `subcpmk` tidak lagi memiliki `cpmk_id` — gunakan `mk_cpmk_id` untuk menelusuri CPMK | Tinggi |
| FR-MK-06 | Penetapan dosen pengampu pada state `setdosenmk` di workflow kurikulum | Tinggi |

### 3.6 FR-KELAS — Kelas Mata Kuliah

| Kode | Deskripsi | Prioritas |
|---|---|---|
| FR-KELAS-01 | `kelas_mk.mk_unit_id` menggantikan `mk_id` agar berlaku spesifik di unit prodi | Tinggi |
| FR-KELAS-02 | Setiap kelas memiliki `dosen_pengampu_id` dan opsional `koordinator_mk_id` | Tinggi |
| FR-KELAS-03 | Pendaftaran mahasiswa via `kelas_mk_mahasiswa` | Tinggi |

### 3.7 FR-NIL — Penilaian & Nilai

| Kode | Deskripsi | Prioritas |
|---|---|---|
| FR-NIL-01 | Komponen penilaian: `evaluasi_id`, `kode`, `nama`, `bobot` (default **100%**) | Tinggi |
| FR-NIL-02 | Pivot `subcpmk_komponenpenilaian`: satu komponen ↔ banyak subCPMK | Tinggi |
| FR-NIL-03 | `nilai_mahasiswas` memakai kolom **`subcpmk_komponenpenilaian_id`** & `kelas_mk_mahasiswa_id` | Tinggi |
| FR-NIL-04 | Nilai akhir (`nilai_angka` & `nilai_huruf`) tersimpan di `kelas_mk_mahasiswa` | Tinggi |
| FR-NIL-05 | `kelola_komponen_penilaian` adalah hak **Koordinator Mata Kuliah** (bukan Dosen Pengampu) | Tinggi |

### 3.8 FR-EVAL — Master Evaluasi

| Kode | Deskripsi | Prioritas |
|---|---|---|
| FR-EVAL-01 | Kelola master evaluasi (kode, kategori, workcloud, nama) | Sedang |
| FR-EVAL-02 | Kategori: Pengetahuan/Kognitif, Hasil Proyek/Studi Kasus, Aktivitas Partisipatif | Sedang |
| FR-EVAL-03 | Permission `kelola_evaluasi` ada pada Super Admin | Sedang |

### 3.9 FR-CALC — Mesin Kalkulasi CPL

| Kode | Deskripsi | Prioritas |
|---|---|---|
| FR-CALC-01 | Kalkulasi otomatis 5 tahap via Queue setelah input nilai | Tinggi |
| FR-CALC-02 | Threshold ketercapaian dari `kurikulum.target_capaian_lulusan` | Sedang |
| FR-CALC-03 | `hasil_cpl_mk` memakai **`mk_unit_id`** | Tinggi |
| FR-CALC-04 | `hasil_cpl_mk_unit` (BARU) menyimpan agregat CPL di level MK murni untuk unit non-prodi | Tinggi |
| FR-CALC-05 | `hasil_cpl_unit` (rename) menyimpan agregat CPL pada setiap `academic_unit_id` | Tinggi |

### 3.10 FR-AI — Modul AI

| Kode | Deskripsi | Prioritas |
|---|---|---|
| FR-AI-01 | Integrasi Google Gemini API | Tinggi |
| FR-AI-02 | Analisis ringkasan CPL per semester per unit | Tinggi |
| FR-AI-03 | `analisis_ai.academic_unit_id` (bukan `prodi_id`) — bisa di setiap level | Tinggi |

### 3.11 FR-AUDIT — Audit & Pelaporan

| Kode | Deskripsi | Prioritas |
|---|---|---|
| FR-AUDIT-01 | Audit log seluruh perubahan model (Spatie Activitylog) | Tinggi |
| FR-AUDIT-02 | Laporan PDF & ekspor Excel | Tinggi |
| FR-AUDIT-03 | Dashboard Filament: tren CPL per semester per unit | Tinggi |

### 3.12 FR-DASH — Dashboard KPI Pimpinan Lintas-Kurikulum

| Kode | Deskripsi | Prioritas |
|---|---|---|
| FR-DASH-01 | Dashboard KPI Pimpinan menampilkan rollup capaian CPL lintas-kurikulum untuk unit yang ditugaskan (widget `PimpinanKpiWidget`) | Tinggi |
| FR-DASH-02 | Chart CPL tertinggi per kurikulum untuk Pimpinan (`PimpinanCplTertinggiChartWidget`), termasuk visualisasi donut & radar | Sedang |
| FR-DASH-03 | Halaman `DaftarKurikulumPimpinan`: daftar seluruh kurikulum dalam lingkup unit Pimpinan beserta status ketercapaiannya | Tinggi |

---

## 4. User Stories Utama

| Kode | Peran | Skenario | Kriteria Penerimaan |
|---|---|---|---|
| US-01 | Super Admin | Mengelola hierarki institusi pada satu tabel tunggal | `academic_units` (UUID) berisi univ/fakultas/jurusan/prodi; tabel terpisah dihapus |
| US-02 | Admin (universitas) | Menetapkan Admin & Pimpinan pada unit fakultas/jurusan/prodi di bawahnya | Permission `kelola_user` + `kelola_unit`; role `Admin` & `Pimpinan` generik, level ditentukan penugasan `academic_unit_users` |
| US-03 | Tim Kurikulum Prodi | Menyusun Profil Lulusan → CPL → BoK → MK → setdosenmk | `status_tim_kurikulum=1` pada unit `study_program`; akses penuh Profil Lulusan |
| US-04 | Tim Kurikulum Fakultas | Menyusun CPL → BoK → MK fakultas tanpa Profil Lulusan | `status_tim_kurikulum=1` pada unit `faculty`; akses CPL, BoK, MK; tidak ada Profil Lulusan |
| US-05 | Tim Kurikulum | Menambah MK universitas dengan kode berbeda di tiap prodi | `mk_units` mencatat `kode` dan `semester_ke` per `academic_unit_id` |
| US-06 | Koordinator Mata Kuliah | Membuat CPMK, SubCPMK, dan Komponen Penilaian satu MK | Role `Koordinator Mata Kuliah` memiliki `kelola_cpmk`, `kelola_subcpmk`, `kelola_komponen_penilaian` |
| US-07 | Dosen Pengampu | Hanya input nilai tanpa membuat komponen | Dosen tidak memiliki `kelola_komponen_penilaian`; komponen disediakan Koordinator MK |
| US-08 | Dosen Pengampu | Input nilai dan lihat kalkulasi CPL otomatis | `nilai_mahasiswas` diisi via `subcpmk_komponenpenilaian_id`; job kalkulasi 5 tahap berjalan otomatis |
| US-09 | Kaprodi (Pimpinan Prodi) | Melihat dashboard ketercapaian CPL prodi | `hasil_cpl_unit` untuk `academic_unit_id` prodi dibanding `target_capaian_lulusan` |
| US-10 | Dekan (Pimpinan Fakultas) | Melihat CPL fakultas yang MK-nya tersebar di banyak prodi | `hasil_cpl_mk_unit` mengagregasi `mk_id` lintas `mk_units` untuk `academic_unit_id` fakultas |
| US-11 | Rektor | Permintaan analisis AI level universitas | `analisis_ai` dengan `academic_unit_id` = id universitas |
| US-12 | Admin Prodi | Mengelola data mahasiswa per prodi tanpa akun login | `mahasiswas.academic_unit_id` → study_program; tanpa autentikasi |

---

## 5. Matriks RBAC (Ringkas)

7 role generik; level/unit kerja tiap akun ditentukan oleh penugasan pada `academic_unit_users` (`jabatan`, `status_pimpinan`, `status_tim_kurikulum`), bukan oleh role terpisah per tipe unit.

| Permission | SAdm | Adm | TimK | Korma | Dos | Pim | Audit |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| kelola_unit | ✓ | ✓ | — | — | — | — | — |
| kelola_semester | ✓ | — | — | — | — | — | — |
| kelola_evaluasi | ✓ | — | — | — | — | — | — |
| kelola_user | ✓ | — | — | — | — | — | — |
| kelola_role | ✓ | — | — | — | — | — | — |
| kelola_permission | ✓ | — | — | — | — | — | — |
| impersonate_user | ✓ | — | — | — | — | — | — |
| kelola_kurikulum | — | ✓ | ✓ | — | — | — | — |
| kelola_profil_lulusan | — | ✓ | ✓* | — | — | — | — |
| kelola_cpl | — | ✓ | ✓ | — | — | — | — |
| kelola_bok | — | ✓ | ✓ | — | — | — | — |
| kelola_mk | — | ✓ | ✓ | — | — | — | — |
| kelola_mk_unit | — | ✓ | ✓ | — | — | — | — |
| kelola_cpmk | — | ✓ | — | ✓ | — | — | — |
| kelola_subcpmk | — | ✓ | — | ✓ | — | — | — |
| kelola_komponen_penilaian | — | ✓ | — | ✓ | — | — | — |
| kelola_kelas | — | ✓ | ✓ | — | ✓ | — | — |
| kelola_peserta_kelas | — | ✓ | — | ✓ | — | — | — |
| setdosen_mk | — | ✓ | ✓ | — | — | — | — |
| input_nilai | — | — | — | — | ✓ | — | — |
| import_nilai | — | — | — | — | ✓ | — | — |
| lihat_laporan | — | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| ekspor_data | — | ✓ | — | — | — | ✓ | ✓ |
| minta_analisis_ai | — | — | — | — | — | ✓ | — |
| lihat_dashboard | — | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| lihat_audit_log | ✓ | — | — | — | — | — | ✓ |
| konfigurasi_sistem | ✓ | — | — | — | — | — | — |

> *Profil Lulusan hanya berlaku ketika Tim Kurikulum bertugas di unit `study_program` (lihat `academic_unit_users.status_tim_kurikulum`).
> Sumber kebenaran: `database/seeders/RolePermissionSeeder.php`.

---

## 6. Kebutuhan Non-Fungsional

- **Performa.** Halaman Filament < 2 detik (100 user paralel). Kalkulasi CPL 1 kelas (30–50 mhs) < 30 detik via Queue. Respons AI analisis < 15 detik (asinkron).
- **Keamanan.** Spatie Permission UUID; FilamentShield per Resource; row-level scoping via Policy + `academic_unit_users`; HTTPS wajib; CSRF aktif; bcrypt cost ≥ 12.
- **Ketersediaan.** Uptime 99% (≤ 7,2 jam/bulan). Backup harian terenkripsi; RTO ≤ 4 jam.
- **Kegunaan.** Filament v4 responsif (desktop & tablet). Workflow state tampil visual (stepper/badge). Validasi form real-time dalam Bahasa Indonesia.
- **Skalabilitas.** Mendukung multi-fakultas dan banyak prodi; agregat CPL hingga level universitas dapat dihitung secara batch.

---

## 7. Glosarium v6

| Istilah | Kepanjangan / Definisi | Catatan v6 |
|---|---|---|
| SILOGY | Siliwangi Learning Outcomes & Quality Analytics | Nama paradigma |
| SILARIS | Siliwangi Learning & Quality Assurance System | Nama aplikasi |
| CPL | Capaian Pembelajaran Lulusan | `academic_unit_id` (semua level) |
| BoK | Body of Knowledge / Bahan Kajian | `academic_unit_id` |
| CPMK | Capaian Pembelajaran MK | `cpmk.mk_id` |
| SubCPMK | Sub-Capaian Pembelajaran MK | tanpa `cpmk_id`; pakai `mk_cpmk_id` |
| MK | Mata Kuliah | +`sks_teori/praktik/lapangan`; `is_active` |
| `mk_units` | Penawaran MK per unit | BARU v6 – pivot `academic_unit_id ↔ mk_id` |
| Evaluasi | Jenis/tipe penilaian | rename `jenis_penilaian` |
| `academic_units` | Hierarki institusi terpadu | UUID PK – satu tabel untuk univ/fak/jur/prodi |
| `academic_unit_users` | Pivot user ↔ unit | +`status_tim_kurikulum`, +`jabatan` |
| `mahasiswas` | Tabel mahasiswa terpisah | tanpa login; `phone` → `nomor_wa` |
| `setdosenmk` | State kurikulum | Penetapan dosen sebelum kurikulum aktif |
| Koordinator MK | Pengelola CPMK/SubCPMK/Komponen | BARU v6 |
| Pimpinan * | Rektor/Dekan/Kajur/Kaprodi | Role generik `Pimpinan`; level ditentukan penugasan `academic_unit_users.status_pimpinan` |

---

*Selesai — SILARIS v6 siap diimplementasikan di atas Laravel 13 + Filament v4.*
