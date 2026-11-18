# Sisa minor: pemangkasan hasil turunan, celah Pilih Peran, cek visual spanduk — Rancangan singkat

Tanggal: 2026-11-18 · Status: disetujui pengguna ("oke silakan") · Ukuran: perubahan kecil, dua kelompok mandiri.

## Maksud
Menutup tiga sisa minor: baris hasil turunan basi, celah kosmetik ±80px, dan verifikasi visual spanduk gerbang CPMK.

## Tujuan / bukan tujuan
- Tujuan: tiga butir di bawah. Bukan tujuan: mengubah rumus nilai, tabel sumber, FK, alur persetujuan CPMK.
- Anti-tujuan: menghapus baris kelas/unit/semester lain; menghapus data sumber.

## Rancangan
1. **Pemangkasan baris turunan** (`hasil_cpl_mk`, `hasil_cpl_mk_unit`, `hasil_cpl_unit`):
   - `CplMkCalculator::calculate`: setelah menyusun `$rows`, hapus baris `hasil_cpl_mk` untuk (mk_unit, semester, mahasiswa kelas ini) yang kuncinya (`cpl_id|kmm`) tidak ada di `$rows`. Jalur `return` awal (tanpa pemetaan / tanpa baris) turun ke pemangkasan dengan himpunan kosong.
   - `CplMkUnitCalculator::calculateForUnit`: hapus baris `hasil_cpl_mk_unit` untuk (unit, semester) yang kunci (`cpl_id|mk_id`) tidak dihasilkan; jalur `return` awal turun ke pemangkasan.
   - `CplUnitAggregator::aggregate`: hapus baris `hasil_cpl_unit` untuk (unit, semester) yang `cpl_id`-nya tidak dihasilkan; jalur `return` awal (tanpa CPL / tanpa baris) turun ke pemangkasan.
   - Cakupan sesempit mungkin; unit/kelas/semester lain tidak tersentuh. Baris yang nilainya kini null ikut hilang (bukan membeku). Data pulih lewat `kalkulasi:hitung-ulang-cpl`.
2. **Celah Pilih Peran**: CSS khusus halaman (di `<style>` view `pilih-peran-unit.blade.php`) mengecilkan `padding-block`/margin `.fi-simple-main` agar celah atas ±80px hilang; periksa di browser.
3. **Cek visual spanduk gerbang CPMK**: di dev, siapkan keadaan gerbang tertutup dengan rekaman sementara baru (bukan mengubah baris lama), login `korma`, tangkap layar spanduk dan kotak centang, lalu hapus rekaman sementara dan laporkan persis apa yang dibuat dan dikembalikan. Bila ditemukan cacat tampilan, perbaiki minimal; bila tidak, ubah tidak ada.

## Pilihan penulis (bukan kesepakatan)
Pemangkasan memakai penghapusan berkunci eksplisit (kunci tidak ada di hasil), bukan menghapus-semua-lalu-isi-ulang, supaya tidak ada jendela kosong dan baris yang tidak berubah mempertahankan `id`/`created_at`.

## Diserahkan ke pelaksana
Bentuk tes, nama method pembantu, nilai CSS persis.
