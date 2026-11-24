# Perbaikan Kontras Mode Gelap Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tidak ada teks yang sulit dibaca pada mode gelap di halaman aplikasi (keluhan: modal edit MK, tabel "Penawaran mata kuliah").

**Architecture:** Akar masalah: banyak blade memakai inline style dengan latar terang tetap (`background:#fff`) atau warna teks gelap tetap (`rgb(15,23,42)`), sementara teks lain mewarisi warna tema (terang di mode gelap). Inline style tidak bisa ditimpa oleh aturan `.dark` tanpa `!important`. Perbaikan: pindahkan warna ke kelas CSS yang punya pasangan `.dark`, mengikuti pola yang sudah dipakai di `resources/views/filament/hooks/*-styles.blade.php`.

**Tech Stack:** Blade, CSS pada hook Filament, Pest, agent-browser untuk verifikasi visual.

**Spec:** tidak ada dokumen desain terpisah; dasar temuan di bagian "Temuan awal" di bawah.

## Global Constraints

- WCAG AA: rasio kontras teks normal ≥ 4.5:1, teks besar (≥ 18.66px tebal atau 24px) ≥ 3:1, pada mode terang DAN gelap.
- Mode terang tidak boleh berubah tampilannya.
- Pasangan `.dark` mengikuti selector yang sudah dipakai di `resources/views/filament/hooks/kurikulum-cards-styles.blade.php`; jangan membuat mekanisme tema baru.
- Kerjakan di branch terpisah dari `main` setelah fitur validasi nilai per kurikulum selesai, agar diff tidak tercampur.
- Commit hanya setelah pengguna memintanya.

## Temuan awal

**Terkonfirmasi bermasalah** (inline style, tanpa pasangan dark):
- `resources/views/filament/modules/mk/partials/mk-edit-penawaran.blade.php`: dua pembungkus tabel `background:#fff` (baris 46 dan 121) dengan teks `th`/`td` tanpa warna eksplisit, sehingga putih di atas putih pada mode gelap. Selain itu judul, angka statistik, dan tombol "Detail/Tutup" memakai warna gelap tetap (`rgb(15,23,42)`, `rgb(51,65,85)`) di atas panel yang di mode gelap menjadi abu gelap, dan lencana biru `rgb(29,78,216)` di atas latar tembus pandang.
- `resources/views/filament/modules/penilaian/pages/validasi-nilai.blade.php:14` dan `.../laporan-koordinator.blade.php:27`: `<select>` dengan `background:#fff` tanpa `color`; teks mewarisi warna terang di mode gelap.

**Perlu diverifikasi di browser** (ada aturan `.dark` di file yang sama, jadi mungkin sudah aman): `input-nilai.blade.php:357`, `laporan-kelas-tabs.blade.php:337`, `status-badge-styles.blade.php:97`, `footer.blade.php:4`, `cpl-mk-matrix.blade.php:349`, `unsil-theme-styles.blade.php:122`, `kurikulum-cards-styles.blade.php` (315, 549, 661, 1437), `pilih-peran-unit.blade.php:67`.

**Dianggap aman:** `kode-keterangan-trigger.blade.php` (latar dan warna teks sama-sama eksplisit), banner "terpilih" dengan latar gradien berwarna.

## Review Focus

1. Mode terang tidak berubah piksel-demi-piksel pada tiga file yang diperbaiki (Task 1).
2. Baris tabel yang terbuka (`$terbuka`, latar biru tembus pandang) tetap terbaca di mode gelap (Task 1).
3. `<select>` memperlihatkan opsi dan nilai terpilih terbaca saat dropdown terbuka di mode gelap (Task 1).
4. Audit tidak melewatkan modal/dialog Filament yang dirender di luar kontainer halaman (Task 2).
5. Penjaga regresi tidak memblokir pemakaian sah (latar putih dengan warna teks eksplisit) (Task 3).

---

### Task 1: Perbaiki tiga file yang sudah terkonfirmasi

**Files:**
- Create: `resources/views/filament/hooks/kontras-tema-styles.blade.php` (kelas `.silogy-permukaan`, `.silogy-teks`, `.silogy-lencana`, `.silogy-isian` beserta pasangan `.dark`), didaftarkan di tempat hook styles lain didaftarkan (cari `unsil-theme-styles` di `app/Providers`).
- Modify: `resources/views/filament/modules/mk/partials/mk-edit-penawaran.blade.php`, `.../penilaian/pages/validasi-nilai.blade.php`, `.../penilaian/pages/laporan-koordinator.blade.php`
- Test: `tests/Feature/Mk/MkEditPenawaranTemaTest.php`

**Interfaces:**
- Produces: kelas CSS yang dipakai Task 3 sebagai pengganti inline: `.silogy-permukaan` (latar kartu/tabel), `.silogy-teks` (warna teks utama), `.silogy-lencana` (angka biru), `.silogy-isian` (select/input). Tiap kelas mendefinisikan latar dan warna teks sekaligus, mode terang = nilai sekarang, `.dark` = pasangan gelap dengan kontras ≥ 4.5:1.

- [ ] **Step 1: Tulis test gagal**: render partial `mk-edit-penawaran` dengan satu baris penawaran terbuka; assert HTML tidak lagi memuat `background:#fff` inline dan memuat `silogy-permukaan`. Untuk dua halaman penilaian, assert `<select>` memakai `silogy-isian` dan tidak memuat `background:#fff`.
- [ ] **Step 2: Jalankan test, pastikan gagal.**
- [ ] **Step 3: Implementasi** — ganti inline sesuai Interfaces; baris `$terbuka` memakai kelas, bukan `rgba(37,99,235,.04)` inline.
- [ ] **Step 4: Jalankan test, pastikan lolos**, lalu verifikasi visual dengan agent-browser pada halaman edit MK (mode terang dan gelap), simpan tangkapan layar sebelum/sesudah.
- [ ] **Step 5: Commit** — `fix(tema): kontras tabel penawaran MK dan select penilaian pada mode gelap`.

---

### Task 2: Audit kontras otomatis mode gelap

**Files:**
- Create: `scripts/audit-kontras-gelap.mjs` (skrip agent-browser/Playwright), keluaran `.superpowers/` tidak dikomit.

**Interfaces:**
- Produces: daftar temuan `{halaman, selector, teks, warnaTeks, latarEfektif, rasio}` untuk setiap teks yang rasionya < 4.5, diurutkan menurut rasio.

- [ ] **Step 1:** Skrip mengaktifkan mode gelap, login dengan akun demo tiap peran (Super Admin, Admin, Tim Kurikulum, Koordinator, Dosen Pengampu, Pimpinan, Validator, Auditor), mengunjungi halaman inti (edit MK, daftar MK, kurikulum, CPL-MK matrix, Input Nilai, Validasi Nilai, Laporan Koordinator, Pilih Peran, dasbor) termasuk membuka modal dan baris detail.
- [ ] **Step 2:** Pada tiap halaman, untuk setiap simpul teks terlihat, hitung warna teks dan latar efektif (telusuri leluhur sampai latar tidak transparan) lalu rasio WCAG; tulis yang < 4.5.
- [ ] **Step 3:** Jalankan; verifikasi hasilnya menangkap lagi kasus Task 1 sebelum perbaikannya (jalankan pada commit sebelum Task 1 sebagai bukti skrip bekerja), lalu pada kondisi sekarang. Keluarkan daftar temuan sebagai berkas laporan.
- [ ] **Step 4: Commit skrip** — `chore(tema): skrip audit kontras mode gelap`.

---

### Task 3: Perbaiki temuan audit dan pasang penjaga regresi

**Files:**
- Modify: file blade/CSS yang muncul di laporan Task 2 (dan kandidat "perlu diverifikasi" yang terbukti bermasalah).
- Create: `tests/Unit/Arsitektur/TanpaLatarPutihInlineTest.php`

**Interfaces:**
- Consumes: kelas dari Task 1.
- Test penjaga: pindai `resources/views/filament/**/*.blade.php`, gagal bila ada atribut `style="...background(-color)?:(#fff|#ffffff|white)..."` tanpa `color:` eksplisit dalam atribut yang sama; daftar pengecualian eksplisit di dalam test, masing-masing dengan alasan tertulis.

- [ ] **Step 1: Tulis test penjaga** dan jalankan; pastikan gagal pada temuan yang belum diperbaiki.
- [ ] **Step 2: Perbaiki** tiap temuan audit dengan kelas dari Task 1 (tambah kelas hanya bila tidak ada yang cocok).
- [ ] **Step 3:** Jalankan ulang skrip audit; semua teks ≥ 4.5:1 atau tercatat di daftar pengecualian dengan alasan. Jalankan test penjaga dan `tests/Feature/Mk`, `tests/Feature/Penilaian`, `tests/Feature/Kurikulum`.
- [ ] **Step 4: Commit** — `fix(tema): kontras mode gelap pada temuan audit dan penjaga regresi`.
