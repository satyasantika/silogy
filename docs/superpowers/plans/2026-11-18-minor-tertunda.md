# Minor Tertunda Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tutup lima catatan minor: kalkulator dua-BoK, efisiensi agregator, UI gerbang CPMK, H1 ganda, komentar basi.

**Architecture:** Tiga tugas mandiri pada berkas berbeda (Kalkulasi; matriks CPL×CPMK; halaman Auth).

**Tech Stack:** Laravel, Filament v4, Livewire, Pest di container `silogy-php`.

**Spec:** `docs/superpowers/specs/2026-11-18-minor-tertunda-design.md`

## Global Constraints

- Jalankan HANYA berkas tes yang disentuh: `docker exec silogy-php php -d memory_limit=2G vendor/bin/pest <berkas>`; suite penuh hanya sekali di akhir oleh pengendali.
- FK, kolom `cpl_bok.bobot`, `AnalisisMkProdiService` tidak disentuh. Teks UI/dokumen Indonesia.
- Pint pada berkas yang diubah; PHPStan pada berkas kode yang diubah (`docker exec silogy-php php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress <berkas>`).
- Commit dengan `git add`/`git commit` berpathspec eksplisit, ulangi bila `index.lock`; jangan stage `PROPOSAL_BILLING_RATE.md`.

## Review Focus

- Satu CPL ke MK lewat dua BoK dengan CPMK kembar di kedua tautan → CPMK dihitung sekali; satu baris `hasil_cpl_mk` per (cpl, mahasiswa), tanpa kunci ganda.
- CPL dengan satu tautan → hasil identik dengan sebelumnya (tes lama hijau).
- Pengguna berwenang (Tim Kurikulum) atau semester tanpa CPMK → gerbang terbuka → kotak centang aktif dan tanpa spanduk.
- Gerbang tertutup → tidak ada `wire:click` pada kotak centang dan `toggle()` tetap menolak jika dipanggil langsung.
- Halaman Pilih Peran tetap merender semua kartu peran dan langkah pilih unit tanpa H1 ganda.

---

### Task 1: Kalkulator dua-BoK dan efisiensi agregator

**Files:**
- Modify: `app/Modules/Kalkulasi/Services/CplMkCalculator.php` (loop `$cplMks`)
- Modify: `app/Modules/Kalkulasi/Services/CplUnitAggregator.php` (`aggregate`, `agregatDariMkUnits`, `bobotCplPerMk`)
- Modify: `docs/DEPLOYMENT_VPS.md` (satu kalimat: hitung ulang juga menyegarkan `nilai_akhir` CPL yang terhubung ke MK lewat dua BoK)
- Test: `tests/Unit/Kalkulasi/CplMkCalculatorTest.php`, `tests/Unit/Kalkulasi/CplUnitAggregatorTest.php`

**Interfaces:**
- Produces: `CplMkCalculator::calculate` menghasilkan satu baris per (`cpl_id`, `kelas_mk_mahasiswa_id`): `nilai_akhir` = rata-rata `HasilCpmk.nilai_akhir` (non-null) atas `array_unique` gabungan `cpmk_id` semua `cpl_mk` pada CPL itu; `nilai_berbobot = round(nilai_akhir × Σ cpl_mk.bobot / 100, 2)`; CPL tanpa CPMK terpetakan dilewati seperti sekarang. Pada `CplUnitAggregator`, `bobotCplPerMk` diganti memuat peta `cpl_id => (mk_id => float)` sekali di `aggregate()` dan diteruskan ke `agregatDariMkUnits`; signature publik/return `agregatDariMkUnits` tetap bentuk lama (tambahkan parameter peta bobot).

- [ ] **Step 1: Tulis tes gagal** di `CplMkCalculatorTest.php`: `'satu cpl ke mk lewat dua bok menggabungkan cpmk kedua tautan'` — dua `cpl_bok` untuk CPL yang sama pada MK yang sama (`cpl_mk` bobot 30 dan 20), CPMK A di tautan 1 dan CPMK B di tautan 2 dengan `HasilCpmk` nilai 100 dan 60 → satu baris, `nilai_akhir` 80.0, `nilai_berbobot` 40.0; `'cpmk kembar di dua tautan dihitung sekali'` — CPMK yang sama dipetakan di kedua tautan (nilai 90) plus CPMK lain di tautan 2 (nilai 70) → `nilai_akhir` 80.0. Di `CplUnitAggregatorTest.php` tidak perlu tes baru; tes lama menjaga perilaku.
- [ ] **Step 2: Jalankan** `docker exec silogy-php php -d memory_limit=2G vendor/bin/pest tests/Unit/Kalkulasi/CplMkCalculatorTest.php` — Expected: dua tes baru FAIL (nilai dari tautan terakhir saja).
- [ ] **Step 3: Implementasi** pengelompokan per `cplId` di `CplMkCalculator` (union CPMK, Σbobot). Lalu refaktor efisiensi agregator: peta bobot semua CPL satu query (`CplMk` join `cpl_bok` `whereIn cpl_id`), hapus `with('mkUnit')` dan ganti pemetaan MK memakai `$mkUnitIds = MkUnit::...->pluck('mk_id', 'id')`.
- [ ] **Step 4: Jalankan** `CplMkCalculatorTest.php`, `CplUnitAggregatorTest.php`, dan berkas tes lain di `tests/Unit/Kalkulasi` yang memuat `CplMkCalculator` atau `CplUnitAggregator` (grep) — Expected: PASS semua. PHPStan pada kedua berkas servis.
- [ ] **Step 5: Commit** terpisah: `fix(kalkulasi): gabungkan CPMK tautan BoK ganda pada nilai CPL×MK` dan `refactor(kalkulasi): muat bobot CPL sekali di agregator prodi` (+ kalimat `DEPLOYMENT_VPS.md` pada commit pertama).

### Task 2: Gerbang CPMK terlihat di UI matriks

**Files:**
- Modify: `app/Modules/MK/Filament/Pages/CplCpmkMatrix.php` (`getViewData` dua kali return awal + utama: tambahkan kunci `gerbangTertutup`, `usulanUrl`)
- Modify: `resources/views/filament/modules/mk/pages/cpl-cpmk-matrix.blade.php`
- Test: `tests/Feature/MK/InteraksiKoordinatorMatrixTest.php`

**Interfaces:**
- Consumes: `GerbangPerubahanCpmk::butuhPersetujuan(Mk $mk, ?string $semesterId, ?User $user = null): bool`; `PerubahanCpmkResource::getUrl('index')`; resolusi semester sama dengan `toggle()` (`SemesterTerpilih::currentId($mk->id) ?? SemesterTerpilih::defaultId()`).
- Produces: view data `gerbangTertutup: bool` (false bila tak ada MK terpilih) dan `usulanUrl: string`; di view, kotak centang `@disabled` tanpa `wire:click` bila `$terkunci || $gerbangTertutup`, dan spanduk peringatan bertaut ke `$usulanUrl` bila `$gerbangTertutup`.

- [ ] **Step 1: Tulis tes gagal** (gunakan `seedMkKoordinatorContext`, `cpmkUntukSemester`, pola tes gerbang yang sudah ada di berkas itu): (a) gerbang tertutup (CPMK terdaftar untuk semester aktif, tanpa usulan disetujui, user `korma`) → `Livewire::test(CplCpmkMatrix::class)->assertSee('CPMK semester ini terkunci')` dan HTML kotak centang tidak memuat `wire:click="toggle`; (b) gerbang terbuka (usulan disetujui seperti tes gerbang-terbuka yang ada) → tidak memuat teks itu dan memuat `wire:click="toggle`; (c) tautan spanduk mengarah ke `PerubahanCpmkResource::getUrl('index')`.
- [ ] **Step 2: Jalankan** `docker exec silogy-php php -d memory_limit=2G vendor/bin/pest tests/Feature/MK/InteraksiKoordinatorMatrixTest.php` — Expected: tes (a) dan (c) FAIL.
- [ ] **Step 3: Implementasi** perhitungan `gerbangTertutup` di `getViewData` (hanya bila MK terpilih ada) dan perubahan view; teks spanduk Indonesia singkat ("CPMK semester ini terkunci. Ajukan perubahan lewat Usulan Perubahan CPMK.").
- [ ] **Step 4: Jalankan** berkas yang sama — Expected: PASS semua (tes lama tidak rusak). PHPStan pada `CplCpmkMatrix.php`.
- [ ] **Step 5: Commit** `feat(interaksi): tampilkan gerbang CPMK tertutup pada matriks CPL×CPMK`

### Task 3: Halaman Pilih Peran: H1 ganda dan komentar basi

**Files:**
- Modify: `app/Modules/Auth/Filament/Pages/PilihPeranUnit.php` (override `getHeading(): string|Htmlable|null` mengembalikan `''`)
- Modify: `resources/views/filament/auth/role-switcher.blade.php:4` (komentar)
- Test: `tests/Feature/Auth/PilihPeranUnitTest.php`

**Interfaces:**
- Produces: halaman Pilih Peran tanpa elemen `<h1>` judul halaman; judul seksi "Pilih peran dan unit Anda" tetap.

- [ ] **Step 1: Tulis tes gagal** `'halaman pilih peran tidak menampilkan judul ganda'`: login `dosentimkur`, GET `PilihPeranUnit::getUrl()` → `assertOk()`, `assertSee('Pilih peran dan unit Anda')`, dan jumlah kemunculan `Pilih Peran &amp; Unit` di dalam elemen `fi-header-heading` adalah 0 (periksa dengan `assertDontSee('fi-header-heading', false)` bila kelas itu hanya muncul di judul; verifikasi dulu kelas yang dipakai Filament pada versi ini).
- [ ] **Step 2: Jalankan** `docker exec silogy-php php -d memory_limit=2G vendor/bin/pest tests/Feature/Auth/PilihPeranUnitTest.php` — Expected: tes baru FAIL.
- [ ] **Step 3: Implementasi** override `getHeading` dan ubah komentar Blade.
- [ ] **Step 4: Jalankan** `PilihPeranUnitTest.php`, `ImpersonatePeranUnitTest.php`, `MasukDenganGoogleTest.php` (satu per satu) — Expected: PASS. Verifikasi visual dengan skill `agent-browser` di `http://localhost:8017` (kredensial `dosentimkur`/`siliwangi`): tangkapan layar tanpa judul ganda dan kartu peran utuh.
- [ ] **Step 5: Commit** `fix(auth): hilangkan judul ganda pada halaman Pilih Peran` (+ komentar role-switcher pada commit yang sama).
