# Rincian Nilai per Sub-CPMK dan Penanda Sub-CPMK Lemah — Rancangan

Tanggal: 2026-11-14
Status: disetujui pengguna 2026-11-14 (ada dosen yang memegang skor terpisah per Sub-CPMK)

## Maksud

Ketika satu asesmen (misalnya UTS) dibebankan ke beberapa Sub-CPMK, dosen dan
prodi ingin tahu **Sub-CPMK mana yang lemah**. Saat ini itu tidak bisa dijawab:
dosen mengisi satu nilai per asesmen, dan nilai itu disalin ke semua Sub-CPMK
yang dibebankan, sehingga semua Sub-CPMK di bawah asesmen yang sama selalu
mendapat angka yang sama.

Contoh dari pengguna: asesmen01 dan asesmen02 sama-sama dibebankan ke tiga
Sub-CPMK yang sama. Ketiga Sub-CPMK itu sekarang pasti bernilai identik.

## Keadaan sekarang (hasil pembacaan kode)

- Penyimpanan **sudah** per Sub-CPMK: `nilai_mahasiswas` menyimpan satu baris
  per (`subcpmk_komponenpenilaian_id`, `kelas_mk_mahasiswa_id`), dan pivot
  `subcpmk_komponenpenilaian.bobot` adalah kontribusi nyata Sub-CPMK itu.
- Yang menyamakan nilai adalah **jalur masukan**: `InputNilai::save` menulis
  nilai yang sama ke semua pivot sebuah asesmen
  (`PenilaianMatrixService::pivotIdsByKomponen`), dan
  `PenilaianMatrixService::nilaiUntukMatrix` hanya membaca pivot pertama.
- Kalkulasi dan laporan **sudah** per Sub-CPMK: `SubcpmkCalculator` menghitung
  rata-rata berbobot per pivot, dan bagian C2 Laporan Lengkap sudah menampilkan
  rerata nilai per Sub-CPMK per asesmen.
- Satu-satunya penulis nilai di jalur produksi adalah `InputNilai::save`, yang
  selalu menulis seragam. Pengecualiannya data contoh simulasi:
  `SimulasiAkademikBuilder` mengisi angka acak per pivot, sehingga nilai
  asesmen yang tampil di kelas contoh bergantung pada pivot mana yang terbaca
  lebih dulu (`nilaiUntukMatrix` tidak mengurutkan).
- Nilai akhir mahasiswa dihitung dari nilai asesmen × bobot asesmen
  (`hitungNilaiAkhirMahasiswa`), bukan dari pivot.

Jadi tidak perlu perubahan skema nilai. Yang kurang hanya: cara dosen mengisi
skor terpisah, dan penanda "lemah" di laporan.

## Tujuan

1. Dosen bisa, **per asesmen per kelas**, memilih merinci nilai per Sub-CPMK.
2. Laporan menandai Sub-CPMK yang lemah, dan jujur menyebut mana yang tidak
   dapat dibedakan karena asesmennya tidak dirinci.
3. Dosen yang tidak merinci tidak merasakan perubahan apa pun.

## Bukan tujuan

- Bank soal, skor per butir soal, atau rubrik per kriteria. Satuan terkecil
  tetap Sub-CPMK.
- Mewajibkan rincian.
- Mengubah rumus CPMK/CPL, bobot, atau pemetaan asesmen ke Sub-CPMK.

## Anti-tujuan

- Laporan yang menampilkan "Sub-CPMK lemah" dari angka hasil salinan. Lebih
  baik tertulis "tidak dapat dibedakan" daripada peringkat palsu.
- Nilai akhir mahasiswa berubah hanya karena dosen menyalakan rincian tanpa
  mengubah angka.

## Pendekatan yang dipertimbangkan

1. **Rincian opsional per asesmen per kelas (dipilih).** Bawaan tetap satu
   nilai; dosen menyalakan rincian hanya pada asesmen yang ingin dibedakan.
   Beban masukan bertambah hanya bagi yang memerlukan.
2. **Selalu per Sub-CPMK.** Setiap asesmen yang dibebankan ke lebih dari satu
   Sub-CPMK wajib diisi per Sub-CPMK. Data paling kaya, tetapi melipatgandakan
   kolom isian semua dosen dan memutus alur tempel-dari-Excel yang sekarang.
3. **Menebak dari bobot atau asesmen lain.** Tanpa masukan baru. Ditolak:
   hasilnya hanya angka yang sama dibagi ulang.

Pengguna menyetujui pendekatan 1.

## Rancangan

### 1. Penanda rincian

Tabel baru `rincian_nilai_kelas`: satu baris per (`kelas_mk_id`,
`komponen_penilaian_id`) yang dirinci, dengan siapa dan kapan menyalakannya.
Ada baris = asesmen itu dirinci di kelas itu. Ikut terhapus bersama kelas atau
asesmennya.

Penanda perlu disimpan, tidak cukup disimpulkan dari "nilai antar-pivot
berbeda": dosen yang merinci tetapi kebetulan mengisi angka sama tetap harus
terbaca sebagai dirinci, dan kolom isian harus tetap terbuka sebelum ada angka.

Hanya asesmen yang dibebankan ke **dua Sub-CPMK atau lebih** yang bisa dirinci.

### 2. Masukan nilai (tab Input)

- Kepala kolom asesmen yang bisa dirinci mendapat tombol kecil
  **Rinci per Sub-CPMK**.
- Saat dinyalakan, kolom itu terbelah menjadi satu sub-kolom per Sub-CPMK
  (berkepala kode Sub-CPMK), ditambah satu sel hanya-baca berisi nilai asesmen
  hasil hitung.
- Nilai asesmen = rata-rata skor Sub-CPMK berbobot `bobot` pivotnya. Nilai
  akhir mahasiswa tetap dihitung dari nilai asesmen itu, jadi rumus nilai
  akhir tidak berubah.
- Menyalakan rincian mengisi tiap sub-kolom dengan **nilai asesmen yang
  sedang tampil** (bukan isi tiap pivot), sehingga nilai asesmen dan nilai
  akhir tidak bergeser. Pada data produksi keduanya sama.
- Untuk asesmen yang tidak dirinci, nilai yang dibaca ditentukan secara pasti
  (pivot diurutkan), bukan kebetulan urutan baris.
- Mematikan rincian meminta konfirmasi, lalu mengisi semua Sub-CPMK dengan
  nilai asesmen hasil hitung (dibulatkan dua desimal). Skor terpisahnya hilang.
- Aturan sel kosong: nilai asesmen dihitung dari sub-kolom yang terisi saja;
  asesmen dianggap lengkap bila semua sub-kolomnya terisi.
- Tempel dari Excel berlaku pada sub-kolom sama seperti kolom biasa.

### 3. Laporan

Di Laporan Lengkap, bagian baru **Ketercapaian Sub-CPMK** (setelah C2):

| Sub-CPMK | Rata-rata kelas | Mahasiswa mencapai target | Status | Dasar |
|---|---|---|---|---|

- **Dasar**: `Terukur terpisah` bila setidaknya satu asesmen penyumbangnya
  dirinci atau hanya dibebankan ke Sub-CPMK itu saja. Selain itu
  `Tidak dapat dibedakan`.
- **Status**: `Tercapai` bila rata-rata kelas ≥ target kelas (target yang sama
  dengan "Target Kelulusan CPL" di laporan itu), selain itu `Lemah`. Status
  tampil untuk semua baris: rata-rata di bawah target adalah fakta tentang
  asesmen yang mengukurnya, dan kolom Dasar menjelaskan seberapa jauh angka
  itu bisa dipercaya untuk Sub-CPMK tersebut.
- **Terlemah**: rata-rata terendah **di antara baris `Terukur terpisah` saja**,
  dan hanya bila ada dua baris atau lebih yang demikian. Baris
  `Tidak dapat dibedakan` tidak pernah diberi tanda Terlemah dan tidak ikut
  dibandingkan. Bila seri, semua yang seri ditandai.
- **Mahasiswa mencapai target**: jumlah dan persentase, misalnya `7/10 (70%)`,
  yaitu mahasiswa dengan nilai Sub-CPMK ≥ angka target yang sama. Penyebutnya
  mahasiswa yang punya nilai Sub-CPMK itu; yang belum dinilai tidak dihitung.
- Grafik E3 (jaring laba-laba Sub-CPMK) tidak berubah.

Hal yang sama tampil di halaman dosen, koordinator, dan validator karena
ketiganya memakai `DataLaporanKelasService` dan partial laporan yang sama.

### 4. Kaitan dengan validasi nilai

- Mengubah skor Sub-CPMK menggugurkan validasi lewat observer yang sudah ada.
- Daftar asesmen yang dirinci ikut dihitung dalam sidik laporan, karena
  mengubah kolom Dasar pada cetakan.
- Kelengkapan nilai (syarat validasi) menghitung sub-kolom sebagai sel.

### 5. Simulasi dan panduan

- Pembangun contoh menulis nilai seragam antar-Sub-CPMK, kecuali pada satu
  asesmen yang ditandai dirinci, supaya bagian baru tampil dengan data yang
  masuk akal dan kelas contoh tidak lagi berangka acak. `VERSI_CONTOH` naik.
- Tidak ada migrasi penyeragaman data lama: data produksi sudah seragam.
- Sidik kelas tanpa rincian tidak boleh berubah, supaya validasi yang sudah ada
  tidak gugur saat rilis.
- Tabel baru masuk daftar baris turunan simulasi.
- Panduan Dosen Pengampu dan Koordinator MK mendapat bagian singkat; catatan
  rilis diperbarui.

## Keputusan saya (boleh dikoreksi)

- Ambang "Lemah" memakai target kelas yang sudah ada, bukan angka baru.
- Rincian diatur per kelas, bukan per mata kuliah: dua dosen kelas paralel
  boleh berbeda.
- Yang boleh menyalakan atau mematikan rincian adalah siapa pun yang boleh
  mengisi nilai kelas itu.
- Penanda lemah dihitung di tingkat kelas. Daftar mahasiswa per Sub-CPMK lemah
  tidak dibuat di tahap ini.
- Angka target yang sama dipakai untuk rata-rata kelas dan untuk ambang per
  mahasiswa.
- Status Lemah tetap tampil pada baris "Tidak dapat dibedakan"; hanya tanda
  Terlemah yang dibatasi pada baris "Terukur terpisah".
- Menyalakan rincian memakai nilai asesmen yang tampil, bukan isi tiap pivot.

## Diserahkan ke pembangun

- Bentuk tepat tombol dan sub-kolom pada matriks Input, mengikuti gaya yang ada.
- Nama kelas, metode, dan susunan tes.
- Cara `PenilaianMatrixService` mengembalikan nilai per pivot tanpa mengubah
  pemanggil yang hanya butuh nilai per asesmen.
