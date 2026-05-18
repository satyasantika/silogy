# Changelog

Semua perubahan penting pada proyek SILOGY didokumentasikan di file ini.

Format mengacu [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), dan proyek ini mengikuti [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [6.0.0] - 2026-05-18

Rilis MVP pertama — alur end-to-end dari kurikulum hingga dashboard CPL siap didemokan ke pimpinan prodi.

### Added

- **AcademicUnit UUID** — hierarki institusi tunggal (`university` → `faculty` → `department` → `study_program`) dengan pivot `academic_unit_users`.
- **Kurikulum workflow** — state machine 7 tahap: `draft` → `profil_lulusan` → `cpl` → `bok` → `mk` → `setdosenmk` → `aktif`.
- **CPL pipeline 5 tahap** — `hasil_subcpmk` → `hasil_cpmk` → `hasil_cpl_mk` → `hasil_cpl_mk_unit` → `hasil_cpl_unit` via `RecalkulasiCplJob`.
- **Dashboard CPL** — widget Filament capaian per unit, filter semester, drill-down per MK unit.
- **RBAC Spatie** — role & permission UUID; Filament Shield; akun demo seed.
- **Penilaian** — komponen penilaian, mapping sub-CPMK, input nilai matriks (dosen pengampu).
- **Kelas MK** — penawaran per `mk_unit`, penetapan dosen pengampu & koordinator MK.
- **Audit** — Spatie Activitylog + viewer read-only (Super Admin & Auditor Mutu).
- **Operasional** — skrip backup harian terenkripsi, endpoint `GET /health`, test E2E MVP (`MvpEndToEndTest`).
- **Dokumentasi** — README P0.1, onboarding 30 menit, skrip demo 15 menit, Docker Compose + Makefile.

### Changed

- **Migrated** dari skema v5: tabel terpisah `universitas` / `fakultas` / `jurusan` / `prodis` **dihapus**; diganti satu tabel `academic_units`.
- Stack admin: **Laravel 13**, **Filament v4**, **MySQL 8**, **Redis 7**.
- Kalkulasi AI direncanakan fase 2 (MVP memakai Google Gemini API di konfigurasi, belum wajib demo).

### Roles

- **Pimpinan** — Universitas, Fakultas, Jurusan, Program Studi (dashboard & laporan).
- **Koordinator Mata Kuliah** — CPMK, sub-CPMK, komponen penilaian.
- **Admin per level** — Admin Universitas / Fakultas / Jurusan / Program Studi.
- **Tim Kurikulum** — kurikulum, CPL, BoK, MK, `mk_units`.
- **Dosen Pengampu** — kelas & input nilai.
- **Super Admin** — institusi, pengguna, audit log.
- **Auditor Mutu** — baca laporan & audit log.

[6.0.0]: https://github.com/unsil/silogy/releases/tag/v6.0.0
