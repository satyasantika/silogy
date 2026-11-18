# Agregat CPL Prodi Tertimbang Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Jalur prodi `CplUnitAggregator` menghitung nilai CPL per mahasiswa sebagai rata-rata tertimbang Σ(nilai_akhir·w)/Σw sehingga sebanding dengan target 0–100.

**Architecture:** Hanya `agregatDariMkUnits` yang berubah. Bobot w diambil dari `cpl_mk` (jumlah lewat semua `cpl_bok` milik CPL) dalam satu query per CPL; nilai per mahasiswa dihitung dalam PHP dengan fallback rata-rata sederhana, meniru `AnalisisMkProdiService::nilaiCplTertimbang`.

**Tech Stack:** Laravel, Pest. Tes dijalankan di container `silogy-php`.

**Spec:** `docs/superpowers/specs/2026-11-18-agregat-cpl-prodi-tertimbang-design.md`

## Global Constraints

- Jalur non-prodi (`agregatDariMkUnit`), `CplMkUnitCalculator`, `AnalisisMkProdiService` tidak berubah; kolom `hasil_cpl_mk.nilai_berbobot` tidak dihapus/diubah.
- Filter yang ada dipertahankan: penawaran (`MkUnit`) kurikulum yang sama dengan CPL, mahasiswa milik prodi itu, semester tunggal.
- Syarat baris hasil: `nilai_akhir` tidak null (bukan lagi `nilai_berbobot`).
- Tes: Pest di `silogy-php`; jalankan per berkas `docker exec silogy-php php -d memory_limit=2G vendor/bin/pest <berkas>`. Teks dokumen Indonesia.
- Akhir: PHPStan bersih, Pint bersih pada berkas yang diubah, suite penuh hijau.

## Review Focus

- MK tanpa baris `cpl_mk` untuk CPL itu (w = 0) tidak boleh membagi dengan nol; bila semua MK tak berbobot → rata-rata sederhana `nilai_akhir`.
- Mahasiswa dengan dua kelas pada MK yang sama → dirata-ratakan per MK dulu, bobot MK dihitung sekali.
- CPL yang terhubung ke MK lewat dua `cpl_bok` → w = jumlah kedua bobot.
- Baris `nilai_akhir` null tidak ikut; semua null → hasil `null` (tidak ada baris `hasil_cpl_unit`), seperti perilaku lama.
- Mahasiswa prodi lain dan penawaran kurikulum lain tetap tidak ikut (tes lama tetap hijau).

---

### Task 1: Hitung nilai CPL prodi per mahasiswa secara tertimbang

**Files:**
- Modify: `app/Modules/Kalkulasi/Services/CplUnitAggregator.php` (`agregatDariMkUnits`, plus method pembantu baru)
- Test: `tests/Unit/Kalkulasi/CplUnitAggregatorTest.php`

**Interfaces:**
- Produces: `protected function bobotCplPerMk(string $cplId): array` — `mk_id => float` (jumlah `cpl_mk.bobot` untuk CPL itu lewat semua `cpl_bok`, satu query); `protected function nilaiCplMahasiswa(Collection $baris, array $bobotPerMk): float` — Σ(rata `nilai_akhir` per MK × w)/Σw atas MK dengan w > 0, fallback `avg('nilai_akhir')` bila tak ada MK berbobot. Return shape `agregatDariMkUnits` tidak berubah: `array{rata_rata: float, persentase_tercapai: float, jumlah_mahasiswa: int}|null`.

- [ ] **Step 1: Tulis tes gagal** di `CplUnitAggregatorTest.php` (pakai `createKelasPenilaianDasar`, `buatKurikulumAktif`, dan pola pembuatan `HasilCplMk`/`Mahasiswa`/`KelasMkMahasiswa` dari tes yang ada):
  - `'nilai cpl prodi tertimbang memakai nilai_akhir bukan potongan nilai_berbobot'`: `cplMk.bobot = 30`, satu baris `nilai_akhir=90`, `nilai_berbobot=27` → `rata_rata` = `90.0`, `persentase_tercapai` = `100.0` (target 75).
  - `'dua MK berbobot 60 dan 40 dirata-ratakan tertimbang'`: MK kedua pada kurikulum/prodi yang sama dengan `cpl_mk` bobot 40 (MK pertama 60), nilai_akhir 100 dan 50 → rata_rata = `80.0`.
  - `'mk tanpa bobot cpl_mk jatuh ke rata-rata sederhana'`: hapus semua `cpl_mk` untuk CPL itu; dua baris nilai_akhir 80 dan 60 → `70.0`, tanpa pembagian nol.
  - `'dua kelas pada mk yang sama dirata-ratakan dulu per mk'`: satu mahasiswa, dua baris MK sama (nilai 100 dan 60) + MK kedua bobot lain → nilai MK pertama dihitung 80 sebelum ditimbang.
  - `'satu cpl ke mk lewat dua bok menjumlahkan bobot'`: dua `cpl_bok` untuk CPL yang sama dengan `cpl_mk` bobot 30 dan 20 pada MK yang sama bersama MK kedua berbobot 50 → penimbang MK pertama 50.
  - `'baris nilai_akhir null diabaikan'`: baris dengan `nilai_akhir` null → tidak ada `hasil_cpl_unit` bila semuanya null.
  Sesuaikan tes lama hanya bila gagal karena baris yang dibuat hanya mengisi `nilai_berbobot` (isi juga `nilai_akhir`).
- [ ] **Step 2: Jalankan** `docker exec silogy-php php -d memory_limit=2G vendor/bin/pest tests/Unit/Kalkulasi/CplUnitAggregatorTest.php` — Expected: tes baru pertama dan kedua FAIL (rata_rata 27 / bukan 80); lainnya sesuai.
- [ ] **Step 3: Implementasi** `bobotCplPerMk(string $cplId): array` dan `nilaiCplMahasiswa(Collection $baris, array $bobotPerMk): float`; ubah `agregatDariMkUnits`: query `HasilCplMk` memakai `whereNotNull('nilai_akhir')` dan `with('kelasMkMahasiswa', 'mkUnit')`, kelompokkan per `mahasiswa_id`, `rata_rata` = `round(avg(nilaiCplMahasiswa), 2)`, `persentase_tercapai` = proporsi mahasiswa dengan nilai ≥ `$threshold`. Bobot MK dari `CplMk` join `cpl_bok.cpl_id = $cplId`, dijumlahkan per `mk_id`.
- [ ] **Step 4: Jalankan** berkas yang sama dan `tests/Unit/Kalkulasi` (per berkas) — Expected: PASS semua.
- [ ] **Step 5: Commit** `fix(kalkulasi): agregat CPL prodi memakai nilai per mahasiswa tertimbang`

### Task 2: Catatan rilis dan verifikasi akhir

**Files:**
- Modify: `docs/DEPLOYMENT_VPS.md` (bagian hitung ulang CPL yang sudah ada: tambahkan bahwa rilis ini juga mengubah semantik `hasil_cpl_unit` prodi sehingga perintah wajib dijalankan sekali dan persentase akan tampak berubah)

- [ ] **Step 1: Perbarui** bagian hitung ulang di `DEPLOYMENT_VPS.md` (Indonesia, singkat).
- [ ] **Step 2: Verifikasi:** `docker exec silogy-php php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress` (No errors); `docker exec silogy-php vendor/bin/pint --test` (hanya `DaftarKurikulumSuperAdminTest.php` line_ending yang sudah ada sebelumnya boleh muncul); suite penuh `docker exec silogy-php php -d memory_limit=2G vendor/bin/pest` — Expected: semua PASS.
- [ ] **Step 3: Commit** `docs(deploy): catat perubahan semantik agregat CPL prodi`
- [ ] **Step 4: Hitung ulang data dev** — hanya setelah pengguna menyetujui (mengubah `hasil_cpl_unit` di database dev): `docker exec silogy-php php artisan kalkulasi:hitung-ulang-cpl --sync`; laporkan angka sebelum dan sesudah.
