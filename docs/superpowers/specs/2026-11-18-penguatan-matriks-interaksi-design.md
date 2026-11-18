# Penguatan matriks interaksi & halaman Pilih Peran — Rancangan

Tanggal: 2026-11-18 · Status: disetujui pengguna (percakapan)

## Maksud dan alasan
1. Pengguna multi-peran tidak perlu melihat menu apa pun saat memilih peran; sidebar milik peran sebelumnya hanya mengganggu.
2. Penilaian dari sistem lain menunjuk empat cacat pada matriks interaksi SILOGY. Semuanya sudah diperiksa di kode dan benar.

## Tujuan
- Halaman `PilihPeranUnit` tanpa sidebar.
- Matriks CPL×MK dan CPL×CPMK tidak lagi bisa menghapus data turunan tanpa penjaga.
- Kalkulator CPL tidak memakai bobot yang tak bisa diisi.

## Bukan tujuan
- Tidak menghapus kolom `cpl_bok.bobot` (hanya berhenti dipakai).
- Tidak mengubah alur persetujuan perubahan CPMK.

## Anti-tujuan
- Jalur reset yang sah (MkResetService, MkUnitResetService, KurikulumResetService) tidak boleh rusak oleh perubahan FK.

## Pendekatan
Untuk tiap butir hanya ada satu cara yang masuk akal; penjaga memakai pola yang sudah ada di kode (`CplBokMatrix::toggle`, `GerbangPerubahanCpmk`). Menambah UI bobot CPL×BoK ditolak: bertentangan dengan keputusan pengguna bahwa bobot itu bukan bagian model OBE.

## Rancangan
1. **Sidebar** — `PilihPeranUnit` memakai layout tanpa sidebar; topbar/menu pengguna tetap.
2. **CPL×MK (`CplMkMatrix::updateBobot`)** — tolak pengosongan sel atau bobot menjadi 0 bila ada `mk_cpmk` di bawah `cpl_mk` itu ("Lepas pemetaan CPMK dulu"). Penjaga diletakkan di aplikasi (matriks), FK `mk_cpmk.cpl_mk_id` TIDAK diubah (lihat Revisi).
3. **Bobot BoK** — buang faktor `bobotCplBok` dari `CplMkCalculator`; `nilai_berbobot = nilai_akhir × bobotCplMk`. Data lama dihitung ulang lewat `RecalkulasiCplJob`.
4. **Gerbang CPMK (`CplCpmkMatrix::toggle`)** — panggil `GerbangPerubahanCpmk::bolehUbah` di awal.
5. **Kepemilikan id (`toggle`)** — tolak bila `cpmk.mk_id` bukan MK terpilih atau `cplId` bukan CPL milik MK itu.

Pengerjaan TDD per butir; verifikasi: seluruh suite, PHPStan, Pint.

## Pilihan penulis (bukan kesepakatan)
- Kolom `cpl_bok.bobot` dipertahankan dulu.
- Pemeriksaan butir 2 mencakup bobot diturunkan ke 0, bukan hanya sel dikosongkan.

## Diserahkan ke pelaksana
Teks pesan penolakan, bentuk tes, cara memicu kalkulasi ulang data lama.

## Revisi saat menulis rencana (2026-11-18)
Rencana awal mengubah FK `mk_cpmk.cpl_mk_id` menjadi `restrictOnDelete`. Setelah membaca `KurikulumResetService`, ternyata reset kurikulum/MK sengaja mengandalkan cascade DB (mk → cpl_mk → mk_cpmk → subcpmk → nilai). `restrict` di tengah rantai cascade itu berisiko menggagalkan reset sah dan tidak dapat diuji andal di semua driver. Maka perlindungan hanya di lapisan aplikasi: `CplMkMatrix::updateBobot` menolak penghapusan bila ada `mk_cpmk`. Reset sah tetap lewat cascade, tidak tersentuh.
