# Penguatan Matriks Interaksi & Halaman Pilih Peran Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tutup empat cacat matriks interaksi CPL dan hilangkan sidebar dari halaman Pilih Peran & Unit.

**Architecture:** Penjaga ditaruh di method Livewire matriks yang bermasalah, memakai pola yang sudah ada (`GerbangPerubahanCpmk`, pemeriksaan `subcpmks()->exists()` di `toggle`). Kalkulator berhenti mengalikan bobot CPL×BoK. Halaman Pilih Peran memakai layout Filament `simple`.

**Tech Stack:** Laravel, Filament v4, Livewire, Pest. Dev: container `silogy-php`, port 8017 (lihat memori proyek); tinker butuh `XDG_CONFIG_HOME=/tmp`.

**Spec:** `docs/superpowers/specs/2026-11-18-penguatan-matriks-interaksi-design.md` (termasuk bagian Revisi: FK tidak diubah).

## Global Constraints

- Tes memakai Pest; jalankan di container `silogy-php`.
- Pesan UI berbahasa Indonesia, memakai `Notification::make()->title(...)->body(...)->warning()->send()`.
- Kolom `cpl_bok.bobot` dan FK `mk_cpmk.cpl_mk_id` TIDAK diubah.
- Jalur reset (`KurikulumResetService`, `MkResetService`, `MkUnitResetService`) tidak boleh berubah perilakunya.
- Akhir pekerjaan: seluruh suite, PHPStan, dan Pint hijau.

## Review Focus

- Bobot CPL×MK diturunkan ke nilai ≤ 0 atau non-numerik (bukan hanya string kosong) saat `mk_cpmk` ada → ditolak, baris tetap.
- Mengosongkan sel CPL×MK yang TIDAK punya `mk_cpmk` → tetap menghapus (perilaku lama tidak rusak).
- `toggle` dengan `cpmkId` milik MK lain, `cplId` asing, atau id tidak ada → tidak mengubah data, tanpa error 500 yang membocorkan.
- `toggle` saat gerbang tertutup tetapi pengguna Tim Kurikulum → tetap boleh.
- Halaman Pilih Peran masih dapat dirender untuk pengguna multi-peran dan menampilkan semua kartu peran.

---

### Task 1: Hapus faktor bobot CPL×BoK dari kalkulator

**Files:**
- Modify: `app/Modules/Kalkulasi/Services/CplMkCalculator.php:67-82`
- Test: `tests/Unit/Kalkulasi/CplMkCalculatorTest.php` (ubah tes "menghitung nilai berbobot dari bobot cpl mk dan cpl bok", baris ~36-52)

**Interfaces:**
- Produces: `nilai_berbobot = round(nilai_akhir × cplMk.bobot / 100, 2)`; `cplBok.bobot` tidak dibaca.

- [ ] **Step 1: Ubah tes jadi gagal.** Ganti nama tes menjadi `'menghitung nilai berbobot hanya dari bobot cpl mk, tanpa bobot cpl bok'`. Pertahankan `cplMk.bobot = 50`; hapus `update(['bobot' => 80])` pada `cplBok`. Tambahkan satu tes `'nilai berbobot tidak nol walau bobot cpl bok kosong'`: set `cplBok` `bobot` ke `null`, kalkulasi dengan nilai 100 dan `cplMk.bobot = 50`; assert `nilai_berbobot` = `50.0`. Tes pertama assert `nilai_berbobot` = `50.0`.
- [ ] **Step 2: Jalankan** `docker exec silogy-php php artisan test --filter=CplMkCalculatorTest` — Expected: FAIL (hasil 40.0 dan 0.0).
- [ ] **Step 3: Implementasi.** Di `CplMkCalculator::calculate` hapus variabel `$bobotCplBok` dan faktornya pada `$nilaiBerbobot`.
- [ ] **Step 4: Jalankan** filter yang sama dan `--filter=Kalkulasi` — Expected: PASS. Bila tes lain (agregator, analisis prodi) bergantung pada angka lama, sesuaikan nilai harapannya dan catat di pesan commit.
- [ ] **Step 5: Commit** `fix(kalkulasi): hapus faktor bobot CPL×BoK dari nilai berbobot`

### Task 2: Penjaga pengosongan sel CPL×MK

**Files:**
- Modify: `app/Modules/Kurikulum/Filament/Pages/CplMkMatrix.php` (`updateBobot`, dua cabang `->delete()`)
- Test: `tests/Feature/Kurikulum/CplMkPenjagaHapusTest.php` (baru; ikuti fixture `tests/Feature/Kurikulum/CplMkKuotaKunciTest.php` untuk login Tim Kurikulum dan kurikulum terpilih)

**Interfaces:**
- Produces: `private function hapusSelAtauTolak(string $mkId, string $cplBokId): void` — menghapus `CplMk` bila tidak ada `MkCpmk` di bawahnya; bila ada, kirim notifikasi warning "Tidak dapat mengosongkan sel" / "Lepas pemetaan CPMK pada MK ini terlebih dahulu." dan tidak menghapus.

- [ ] **Step 1: Tulis tes gagal** (tiga tes): (a) sel punya `MkCpmk`, panggil `updateBobot($mkId, $cplBokId, '')` → `CplMk` dan `MkCpmk` tetap ada; (b) sama dengan input `'0'` dan `'abc'` → tetap ada; (c) sel tanpa `MkCpmk`, input `''` → `CplMk` terhapus.
- [ ] **Step 2: Jalankan** `--filter=CplMkPenjagaHapusTest` — Expected: (a),(b) FAIL, (c) PASS.
- [ ] **Step 3: Implementasi** `hapusSelAtauTolak` dan panggil di dua cabang delete `updateBobot` (kosong/non-numerik/≤0, dan `$nilai <= 0`). Cek `MkCpmk::whereHas('cplMk', fn ($q) => $q->where('mk_id', $mkId)->where('cpl_bok_id', $cplBokId))->exists()`.
- [ ] **Step 4: Jalankan** `--filter=CplMk` — Expected: PASS semua (termasuk `CplMkKuotaKunciTest`, `CplMkNormalisasiSemuaTest`).
- [ ] **Step 5: Commit** `fix(interaksi): tolak pengosongan sel CPL×MK yang masih dipetakan ke CPMK`

### Task 3: Kepemilikan id dan gerbang perubahan pada `CplCpmkMatrix::toggle`

**Files:**
- Modify: `app/Modules/MK/Filament/Pages/CplCpmkMatrix.php` (`toggle`)
- Test: `tests/Feature/MK/InteraksiKoordinatorMatrixTest.php` (tambah tes; gunakan `seedMkKoordinatorContext`, `cpmkUntukSemester` dari `tests/Helpers.php`)

**Interfaces:**
- Consumes: `GerbangPerubahanCpmk::bolehUbah(Mk $mk, ?string $semesterId, ?User $user = null): bool`; `SemesterTerpilih::currentId(string $mkId)` / `SemesterTerpilih::defaultId()` (pola di `ListCpmks.php:164`); `$this->getMkTerpilih()`.
- Produces: `toggle(string $cpmkId, string $cplId): void` yang return diam-diam dengan notifikasi warning bila (i) `cpmk.mk_id !== getMkTerpilih()?->id`, (ii) `$cplId` bukan CPL yang punya `cplMks` pada MK itu, (iii) `bolehUbah` false.

- [ ] **Step 1: Tulis tes gagal:** (a) CPMK milik MK lain (buat MK kedua pada prodi yang sama) → `MkCpmk` tidak bertambah; (b) `cplId` CPL tanpa interaksi pada MK terpilih → tidak ada perubahan; (c) CPMK terdaftar untuk semester aktif via `cpmkUntukSemester`, tanpa `PerubahanCpmkRequest` disetujui, user `korma` → toggle ditolak (tidak membuat dan tidak menghapus `MkCpmk`); (d) sama dengan (c) tetapi login sebagai `timkur` yang berwenang → toggle berhasil; (e) `cpmkId` acak UUID → tidak melempar exception 500 (assert tanpa perubahan data).
- [ ] **Step 2: Jalankan** `--filter=InteraksiKoordinatorMatrixTest` — Expected: (a),(b),(c),(e) FAIL, (d) PASS; tes lama PASS.
- [ ] **Step 3: Implementasi.** Ganti `findOrFail` dengan `find` + penolakan notifikasi; tambahkan tiga pemeriksaan di awal `toggle` sebelum logika yang ada; semester dari `SemesterTerpilih::currentId($mk->id) ?? SemesterTerpilih::defaultId()`; user dari `auth()->user()`.
- [ ] **Step 4: Jalankan** `--filter=InteraksiKoordinatorMatrixTest` dan `--filter=Cpmk` — Expected: PASS. Tes lama `matriks cpl cpmk mencentang pemetaan mk cpmk` harus tetap hijau; bila gagal karena `cpmk` tanpa semester, itu artinya penjaga terlalu ketat — perbaiki penjaga, bukan tesnya.
- [ ] **Step 5: Commit** `fix(interaksi): toggle CPL×CPMK memeriksa kepemilikan id dan gerbang perubahan CPMK`

### Task 4: Halaman Pilih Peran tanpa sidebar

**Files:**
- Modify: `app/Modules/Auth/Filament/Pages/PilihPeranUnit.php`
- Test: `tests/Feature/Auth/PilihPeranUnitTest.php`

**Interfaces:**
- Produces: `PilihPeranUnit::$layout = 'filament-panels::components.layout.simple'` (properti `protected static string $layout`).

- [ ] **Step 1: Tulis tes gagal** `'halaman pilih peran tidak merender sidebar'`: login `dosentimkur`, `$this->get(PilihPeranUnit::getUrl())` → `assertOk()`, `assertDontSee('fi-sidebar', false)`, tetap `assertSee` teks kartu peran yang dipakai tes lain di berkas itu. Tambahkan pembanding: `Dashboard::getUrl()` masih memuat `fi-sidebar`.
- [ ] **Step 2: Jalankan** `--filter=PilihPeranUnitTest` — Expected: tes baru FAIL.
- [ ] **Step 3: Implementasi** properti `$layout` di atas. Karena layout `simple` sempit (Width::Large), set lebar konten lewat `getMaxContentWidth()`/`maxContentWidth` Filament agar kartu persegi tidak menumpuk; pilih nilai terkecil yang membuat kartu tetap sebaris.
- [ ] **Step 4: Jalankan** `--filter=PilihPeranUnitTest` dan `--filter=ImpersonatePeranUnit`, `--filter=MasukDenganGoogle` — Expected: PASS.
- [ ] **Step 5: Verifikasi visual** dengan skill `run`/`agent-browser` di `http://localhost:8017`: login pengguna multi-peran, tangkap layar halaman Pilih Peran (tanpa sidebar, topbar menu pengguna masih ada, kartu rapi) dan Dashboard (sidebar normal). Sampaikan tangkapan layar ke pengguna.
- [ ] **Step 6: Commit** `feat(auth): halaman Pilih Peran & Unit tanpa sidebar`

### Task 5: Verifikasi akhir dan kalkulasi ulang

- [ ] **Step 1:** `docker exec silogy-php php artisan test` — Expected: seluruh suite PASS.
- [ ] **Step 2:** `docker exec silogy-php vendor/bin/phpstan analyse` dan `vendor/bin/pint --test` — Expected: bersih. Perbaiki bila perlu, commit `style:` terpisah.
- [ ] **Step 3: Minta persetujuan pengguna** sebelum menghitung ulang data dev yang sudah ada (mengubah `hasil_cpl_mk.nilai_berbobot`). Setelah disetujui: lewat tinker (`XDG_CONFIG_HOME=/tmp`) dispatch `RecalkulasiCplJob($kelasMkId, $academicUnitId, $semesterId)` untuk tiap `KelasMk` yang punya baris `hasil_cpl_mk`; unit dari `mkUnit.academic_unit_id`. Di lingkungan dev tanpa queue worker, jalankan dengan `dispatchSync`.
