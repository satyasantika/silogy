# Minor tertunda (setelah penguatan matriks & agregat prodi) — Rancangan singkat

Tanggal: 2026-11-18 · Status: disetujui pengguna ("ya") · Ukuran: perubahan kecil, tiga kelompok mandiri.

## Maksud
Menutup lima catatan minor dari tinjauan sebelumnya agar perilaku dan tampilan konsisten.

## Tujuan / bukan tujuan
- Tujuan: lima butir di bawah. Bukan tujuan: menyentuh FK, kolom `cpl_bok.bobot`, alur persetujuan CPMK, `AnalisisMkProdiService`.

## Pendekatan
Satu cara masuk akal per butir; dua butir (A, B) dipilih pengguna setelah ditawarkan.

## Rancangan
- **A. `CplMkCalculator`** — kelompokkan `cpl_mk` per CPL; `nilai_akhir` = rata-rata `HasilCpmk.nilai_akhir` atas gabungan CPMK semua tautan (id kembar dihitung sekali); `nilai_berbobot = round(nilai_akhir × Σbobot/100, 2)`; satu baris per (cpl, mahasiswa). Hitung ulang data lama lewat `kalkulasi:hitung-ulang-cpl` (catat di `DEPLOYMENT_VPS.md`).
- **A2. `CplUnitAggregator`** — muat bobot CPL→MK sekali untuk semua CPL di `aggregate()`; buang `with('mkUnit')` redundan (pakai `pluck('mk_id','id')`). Perilaku identik.
- **B. UI gerbang CPMK** — `CplCpmkMatrix` mengirim `gerbangTertutup` (= `GerbangPerubahanCpmk::butuhPersetujuan($mk, $semesterId, $user)`) ke view; bila true semua kotak centang dinonaktifkan dan spanduk peringatan "CPMK semester ini terkunci…" bertaut ke `PerubahanCpmkResource::getUrl('index')` tampil; `toggle()` tetap menjaga.
- **C1. H1 ganda** — `PilihPeranUnit::getHeading()` mengembalikan string kosong sehingga hanya judul seksi tampil.
- **C2. Komentar** `resources/views/filament/auth/role-switcher.blade.php:4` → "menu pengguna di topbar".

## Pilihan penulis (bukan kesepakatan)
Semester untuk gerbang B memakai resolusi yang sama dengan `toggle()` (`SemesterTerpilih::currentId($mk->id) ?? defaultId()`); tautan spanduk ke daftar usulan, bukan form buat.

## Diserahkan ke pelaksana
Nama method pembantu, bentuk tes, teks persis spanduk.
