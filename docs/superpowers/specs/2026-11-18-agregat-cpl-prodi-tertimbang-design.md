# Agregat CPL prodi: nilai per mahasiswa tertimbang — Rancangan

Tanggal: 2026-11-18 · Status: disetujui pengguna (percakapan: "ok")

## Maksud dan alasan
`CplUnitAggregator::agregatDariMkUnits` (jalur prodi) merata-ratakan `hasil_cpl_mk.nilai_berbobot`, yaitu potongan nilai MK yang dikreditkan ke satu CPL (`nilai_akhir × cpl_mk.bobot/100`). Karena `cpl_mk.bobot` berjumlah 100 per MK, potongan itu jauh di bawah skala 0–100 (MK yang terbagi ke 3 CPL → sekitar 33), padahal dibandingkan dengan target (mis. 75). Selama ini tersembunyi karena bobot BoK kosong membuat semuanya 0; setelah faktor BoK dibuang dan data dihitung ulang, "persentase tercapai" akan tampak jauh terlalu rendah.

## Tujuan
- `hasil_cpl_unit.rata_rata` dan `persentase_tercapai` (prodi) sebanding dengan skala 0–100 dan dengan target kurikulum.

## Bukan tujuan
- Jalur unit non-prodi (`agregatDariMkUnit`, `CplMkUnitCalculator`) tidak berubah; sudah memakai `nilai_akhir`.
- `AnalisisMkProdiService` (basis penyebut 100%) tidak disentuh.
- Kolom `hasil_cpl_mk.nilai_berbobot` tidak dihapus dan tidak diubah cara hitungnya.

## Anti-tujuan
- Tidak mencampur capaian kurikulum lama dan baru, tidak mencampur mahasiswa prodi lain (filter yang ada dipertahankan).

## Rancangan
1. Untuk tiap mahasiswa pada satu CPL dan semester: kelompokkan baris `hasil_cpl_mk` per MK (`mkUnit.mk_id`), ambil rata-rata `nilai_akhir` per MK, lalu nilai CPL mahasiswa = Σ(nilai_MK × w) / Σw, dengan w = jumlah `cpl_mk.bobot` untuk MK itu pada CPL itu lewat semua `cpl_bok` milik CPL tersebut. MK dengan w ≤ 0 atau tanpa baris `cpl_mk` dilewati; bila tidak ada MK berbobot sama sekali, jatuh ke rata-rata sederhana `nilai_akhir` (sama dengan `AnalisisMkProdiService::nilaiCplTertimbang`).
2. `rata_rata` = rata-rata nilai CPL per mahasiswa; `persentase_tercapai` = proporsi mahasiswa dengan nilai ≥ target; `jumlah_mahasiswa` = jumlah mahasiswa berbeda. Syarat baris: `nilai_akhir` tidak null (menggantikan `nilai_berbobot` tidak null).
3. Penyebut = Σw atas MK yang sudah bernilai pada semester itu (bukan semua MK terpetakan).
4. Setelah rilis: jalankan `kalkulasi:hitung-ulang-cpl`; perbarui catatan di `docs/DEPLOYMENT_VPS.md`.

## Pendekatan yang dipertimbangkan
- (Dipilih) Σ(nilai_akhir·w)/Σw atas MK bernilai: skala benar, sederhana, per semester wajar.
- Basis 100% seperti `nilaiCplTertimbang` bagian utama: menunjukkan kemajuan menuju seluruh CPL, tetapi agregat ini per semester sehingga MK semester lain pasti kosong dan angkanya selalu tampak rendah.
- Membagi `nilai_berbobot` dengan w/100: rapuh bila `nilai_akhir` = 0 dan mewarisi kunci ganda `cpl|kmm`.

## Pilihan penulis (bukan kesepakatan)
Penyebut Σw atas MK bernilai; bobot diambil dari `cpl_mk` langsung, bukan dari `nilai_berbobot`.

## Diserahkan ke pelaksana
Cara memuat bobot secara efisien (satu query per CPL, bukan per baris), nama method pembantu, bentuk tes.
