# Changelog

Semua perubahan penting pada proyek SILOGY didokumentasikan di file ini.

Format mengacu [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), dan proyek ini mengikuti [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [6.3.0] - 2026-09-15

CPMK, Sub-CPMK, dan Asesmen kini **berlaku per semester** dan dapat **dipakai ulang dengan ID yang sama** lintas semester — bukan lagi disalin menjadi baris baru berisi kode lama. Perubahan CPMK berada di bawah persetujuan Tim Kurikulum.

### Added

- **Pivot semester** — `cpmk_semester`, `subcpmk_semester`, `komponen_penilaian_semester`. Satu baris dapat berlaku di banyak semester, sehingga capaian satu Sub-CPMK dapat ditelusuri lintas semester lewat satu identitas.
- **Gerbang keputusan per semester** — aksi "Gunakan data semester lain" pada langkah CPMK, Sub-CPMK, dan Asesmen, dengan pilihan **sepaket** atau **sebagian**.
- **Kaskade per-CPMK** (`KaskadeReuseSemester`) — turunan hanya boleh dipakai ulang bila induknya juga dipakai ulang. CPMK yang baru disusun untuk semester berjalan mewajibkan Sub-CPMK dan Asesmen baru.
- **Persetujuan perubahan CPMK** — tabel `perubahan_cpmk_requests`, kotak masuk Tim Kurikulum, permission `setujui_perubahan_cpmk`, dan gerbang `GerbangPerubahanCpmk`. Tim Kurikulum unit **induk** kini juga berwenang (`AcademicUnitScope::userIsTimKurikulumOnUnitOrAncestor()`).
- **`php artisan mk:konsolidasi-lintas-semester`** — melaporkan (dry-run) atau melebur baris kembar lintas semester menjadi satu baris kanonik.

### Changed

- **Pakai ulang menggantikan salin.** `SubcpmkSalinSemesterService` dan `KomponenPenilaianSalinSemesterService` diganti `…PakaiUlangSemesterService`: baris lama dilampirkan ke semester tujuan, ID-nya tetap.
- **Bobot pindah ke pivot.** Kolom `subcpmk.bobot` dan `komponen_penilaian.bobot` dihapus; keduanya milik pasangan (entitas, semester). Pengakses lamanya sengaja melempar `LogicException` agar pemanggil yang terlewat gagal nyaring, bukan diam-diam membaca 0.
- **Reset melepas, tidak menghapus.** Reset Sub-CPMK/Asesmen/CPMK per semester kini melepas lampiran semester; barisnya hanya dihapus bila tidak lagi dipakai semester mana pun. Sebelumnya bulk delete — yang, dengan ID dipakai bersama, akan ikut menghapus data semester lain beserta nilainya.
- **`subcpmk_komponenpenilaian.semester_id` menjadi NOT NULL** dan masuk UNIQUE `(subcpmk_id, komponen_penilaian_id, semester_id)`; kolom inilah penentu semester yang dipakai `SubcpmkCalculator`.
- **`komponen_penilaian.kode` menjadi NOT NULL** dengan UNIQUE `(mk_id, kode)`; baris tanpa kode diberi kode turunan dari namanya saat konsolidasi.
- **Daftar CPMK disaring per semester** untuk Koordinator MK. Admin/Tim Kurikulum/Auditor tetap melihat lintas semester, dengan kolom "Berlaku pada semester".

### Fixed

- `SubcpmkKomponenPenilaianRelationManager` membuat baris pemetaan tanpa `semester_id` (NULL) karena relasi HasMany Filament tidak mengetahui konteks semester.

### Migration notes

1. Jalankan `php artisan mk:konsolidasi-lintas-semester` lebih dulu pada salinan data produksi dan **baca laporannya** — terutama bila ia menyebut nilai mahasiswa yang akan dibuang atau asesmen tanpa kode.
2. `php artisan migrate` menjalankan konsolidasi lalu memindahkan kolomnya. `hasil_subcpmk`/`hasil_cpmk` untuk kelas terdampak dihapus karena murni turunan — **jalankan ulang kalkulasi CPL** setelahnya agar terisi kembali.
3. Arah `down()` tidak setia: baris yang sudah dilebur tidak bisa dipecah kembali.

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

[6.3.0]: https://github.com/unsil/silogy/releases/tag/v6.3.0
[6.0.0]: https://github.com/unsil/silogy/releases/tag/v6.0.0
