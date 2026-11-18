# Sisa Minor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pangkas baris hasil turunan yang basi, hilangkan celah atas Pilih Peran, dan verifikasi visual spanduk gerbang CPMK.

**Architecture:** Dua kelompok mandiri: (1) tiga kalkulator di `app/Modules/Kalkulasi/Services`; (2) tampilan/browser (Pilih Peran dan matriks CPL×CPMK).

**Tech Stack:** Laravel, Filament v4, Pest di container `silogy-php`; dev di `http://localhost:8017`.

**Spec:** `docs/superpowers/specs/2026-11-18-sisa-minor-design.md`

## Global Constraints

- Jalankan HANYA berkas tes yang disentuh: `docker exec silogy-php php -d memory_limit=2G vendor/bin/pest <berkas>`; suite penuh sekali di akhir oleh pengendali.
- Rumus nilai, tabel sumber, FK, alur persetujuan CPMK tidak berubah. Teks UI Indonesia.
- Pint pada berkas diubah; PHPStan pada berkas kode diubah. Commit dengan pathspec eksplisit (ulangi bila `index.lock`); jangan stage `PROPOSAL_BILLING_RATE.md`.

## Review Focus

- Baris kelas/unit/semester lain TIDAK terhapus oleh pemangkasan (tes dengan dua kelas / dua semester / dua unit).
- CPL yang kehilangan semua pemetaan CPMK → barisnya hilang di ketiga tabel setelah hitung ulang; jalur return awal juga memangkas.
- Baris yang tidak berubah mempertahankan `id` dan `created_at` (tidak dihapus-lalu-dibuat ulang).
- Semua tes kalkulator/agregator lama tetap hijau.
- Data dev yang dibuat untuk cek visual dibersihkan sepenuhnya.

---

### Task 1: Pangkas baris hasil turunan basi

**Files:**
- Modify: `app/Modules/Kalkulasi/Services/CplMkCalculator.php`, `CplMkUnitCalculator.php`, `CplUnitAggregator.php`
- Test: `tests/Unit/Kalkulasi/CplMkCalculatorTest.php`, `tests/Unit/Kalkulasi/CplMkUnitCalculatorTest.php` (atau berkas tes yang sudah ada untuknya; cari lewat grep), `tests/Unit/Kalkulasi/CplUnitAggregatorTest.php`

**Interfaces:**
- Produces: tiap `calculate`/`calculateForUnit`/`aggregate` menjalankan pemangkasan berkunci eksplisit (hapus baris dalam cakupan yang kuncinya tidak dihasilkan) sebelum/sesudah `upsert`, juga pada jalur `return` awal; cakupan: `hasil_cpl_mk` = (mk_unit_id, semester_id, kelas_mk_mahasiswa_id ∈ kelas ini); `hasil_cpl_mk_unit` = (academic_unit_id, semester_id); `hasil_cpl_unit` = (academic_unit_id, semester_id).

- [ ] **Step 1: Tulis tes gagal** per kalkulator: (a) data dengan hasil lama, lalu hapus semua pemetaan CPMK/`mk_cpmk` untuk CPL itu dan hitung ulang → baris CPL itu hilang; (b) baris kelas/semester/unit lain pada tabel yang sama tetap ada; (c) baris CPL lain yang masih dipetakan tetap ada dengan `id` dan `created_at` yang sama.
- [ ] **Step 2: Jalankan** tiap berkas tes terkait — Expected: tes (a) FAIL, (b)(c) PASS.
- [ ] **Step 3: Implementasi** pemangkasan di tiga kelas sesuai Interfaces; ubah `return` awal menjadi jalur yang tetap memangkas (himpunan hasil kosong).
- [ ] **Step 4: Jalankan** berkas tes kalkulasi terkait satu per satu, termasuk `HitungUlangCplCommandTest.php` dan berkas lain yang memuat ketiga kelas (grep) — Expected: PASS semua. PHPStan pada ketiga servis.
- [ ] **Step 5: Commit** `fix(kalkulasi): pangkas baris hasil CPL turunan yang tidak lagi dihasilkan` (+ satu kalimat di `docs/DEPLOYMENT_VPS.md` bahwa hitung ulang kini juga membuang baris basil CPL tanpa pemetaan).

### Task 2: Celah Pilih Peran dan cek visual spanduk gerbang

**Files:**
- Modify: `resources/views/filament/modules/auth/pages/pilih-peran-unit.blade.php` (CSS khusus halaman)
- Test: `tests/Feature/Auth/PilihPeranUnitTest.php` bila perlu (tes ringan bahwa aturan CSS ada)

**Interfaces:**
- Produces: aturan CSS khusus halaman yang mengecilkan padding-block/margin `.fi-simple-main`; tidak mengubah halaman lain.

- [ ] **Step 1: Ukur** celah atas di browser (skill `agent-browser`, `dosentimkur`/`siliwangi`), simpan tangkapan layar sebelum di `/tmp/claude-1000/-home-satya-code-silogy/4c55d22b-8168-413e-8928-7923bc60b186/scratchpad/`.
- [ ] **Step 2: Implementasi** aturan CSS minimal (cakupan hanya halaman ini), muat ulang, tangkap layar sesudah; pastikan layar sempit (lebar ~400px) tidak rusak.
- [ ] **Step 3: Jalankan** `tests/Feature/Auth/PilihPeranUnitTest.php` — Expected: PASS.
- [ ] **Step 4: Cek visual spanduk gerbang CPMK:** buat rekaman sementara di dev (CPMK baru untuk semester aktif pada MK koordinator `korma`, tanpa usulan disetujui), login `korma`, buka matriks CPL↔CPMK, tangkap layar spanduk dan kotak centang nonaktif; kemudian HAPUS rekaman sementara itu dan konfirmasi bersih dengan query. Laporkan persis apa yang dibuat dan dikembalikan. Perbaiki hanya bila ada cacat tampilan nyata.
- [ ] **Step 5: Commit** `style(auth): kecilkan celah atas halaman Pilih Peran` (dan perbaikan spanduk bila ada).
