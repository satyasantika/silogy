# Validasi nilai per kurikulum: switch + penunjukan validator

Tanggal: 2026-11-24. Status: menunggu persetujuan.

## Maksud dan alasan

Validasi nilai kelas saat ini berlaku untuk semua kelas, dan hak validator
berasal dari toggle "Validator nilai" pada penugasan unit (cakupan unit +
turunannya). Dua kebutuhan tidak terpenuhi:

1. Sebagian prodi tidak memerlukan validasi nilai sama sekali.
2. Prodi yang memerlukannya ingin menentukan siapa yang memvalidasi, idealnya
   UPM/penjamin mutu, tanpa harus mengubah cakupan unit yang berlaku untuk
   semua kurikulum.

Rancangan ini memindahkan penentuan itu ke **kurikulum**: satu switch untuk
mewajibkan validasi, dan satu daftar validator tambahan.

## Perilaku yang diinginkan (keputusan pemilik)

- Halaman edit kurikulum mendapat switch "Kurikulum ini divalidasi".
- **Switch aktif:** validasi nilai kelas pada kurikulum itu diwajibkan agar
  laporan kelas siap cetak (perilaku sekarang). Yang berhak memvalidasi:
  1. pimpinan prodi pemilik kurikulum (otomatis),
  2. tim kurikulum prodi pemilik kurikulum (otomatis),
  3. validator tambahan yang dipilih lewat multi-select (misalnya UPM).
- Multi-select hanya berisi tambahan di luar pimpinan dan tim kurikulum;
  pimpinan dan tim kurikulum tidak perlu dipilih.
- Setiap orang yang berhak otomatis memiliki peran "Validator Nilai"; peran itu
  dicabut bila tidak lagi berhak pada kurikulum mana pun.
- **Switch mati:** proses validasi tidak berjalan untuk kurikulum itu. Laporan
  kelas bebas cetak tanpa validasi. Tidak ada penulisan baru ke
  `validasi_nilai_kelas` (tidak ada validasi, pembatalan, ataupun pengguguran),
  dan kelas kurikulum itu tidak muncul di halaman Validasi Nilai.
- Switch boleh dimatikan sewaktu-waktu. Mematikannya **tidak** menghapus atau
  mengubah baris validasi yang sudah ada; kelas yang pernah tervalidasi tetap
  menampilkan label "Tervalidasi" sebagai riwayat.
- Larangan memvalidasi nilai sendiri tetap: dosen pengampu dan koordinator MK
  suatu kelas tidak boleh memvalidasi kelas itu.

## Tujuan, bukan-tujuan, anti-tujuan

Tujuan: penentuan validator per kurikulum; prodi tanpa validasi tidak terbebani;
hak validator terhitung otomatis dari penugasan yang sudah ada.

Bukan-tujuan: validasi bertingkat atau lebih dari satu validator per kelas;
penunjukan per MK atau per kelas; persetujuan/alur kerja penunjukan; mengubah
cara sidik dihitung.

Anti-tujuan (dianggap gagal walau tampak berfungsi): seseorang bisa memvalidasi
kelasnya sendiri; validasi lama hilang atau berubah status karena switch
dimatikan; pimpinan/tim kurikulum yang sudah berganti masih berhak karena data
disalin, bukan dihitung.

## Kendala di sistem yang sebenarnya

- Kurikulum terikat ke prodi lewat `kurikulum.academic_unit_id`. Kelas terhubung
  ke kurikulum lewat `kelas_mk.mk_unit_id` → `mk_units.kurikulum_id`.
- `ValidasiNilaiPolicy::access()` mensyaratkan `hasRole('Validator Nilai')` dan
  permission `validasi_nilai`. Relasi `User::roles()` difilter oleh peran aktif
  (pemilih peran), sehingga pimpinan/tim kurikulum yang memvalidasi harus
  memilih peran Validator Nilai. Gunakan `ActiveRole` dan pola
  `AcademicUnitUserRoleSync` (pivot langsung, bukan `assignRole`/`hasRole`).
- `AcademicUnitUserRoleSync` saat ini mencabut peran Validator Nilai dari siapa
  pun tanpa `status_validator_nilai`. Tanpa perubahan, peran yang diberikan lewat
  rancangan ini akan terhapus.
- Gerbang cetak laporan ada di satu tempat: `laporanSesuaiValidasi()` pada trait
  `HasLaporanKelasMk`, dipakai halaman Input Nilai dan Validasi Nilai.
- `NilaiMahasiswaObserver` menggugurkan validasi saat nilai berubah;
  `ValidasiNilaiService::periksa()` menggugurkan bila sidik berbeda.
- Mode simulasi/hanya-baca (`HanyaBaca`) menolak validasi; akun simulasi
  `sim-validator` dan `PembangunSimulasi` memakai `status_validator_nilai`.

## Pendekatan yang dipertimbangkan

- **A. Penunjukan per kurikulum, hak dihitung saat dibutuhkan (dipilih).** Kolom
  `validasi_nilai_aktif` pada `kurikulum`, tabel `kurikulum_validator` untuk
  tambahan. Berhak = pimpinan/tim kurikulum pada penugasan unit kurikulum, atau
  ada di tabel. Dipilih karena data tidak basi saat pimpinan berganti dan
  cakupannya tepat per kurikulum. Biaya: `ValidatorNilaiScope` dan
  `ValidasiNilaiPolicy` ditulis ulang.
- **B. Salin pimpinan/tim kurikulum ke tabel saat switch dinyalakan.** Ditolak:
  data basi saat pimpinan berganti.
- **C. Memakai toggle validator per unit yang sudah ada.** Ditolak: pilihan
  bocor ke semua kurikulum prodi, dan pimpinan/tim kurikulum tidak otomatis ikut.

## Keputusan yang diambil pemilik

- Pendekatan A.
- Switch mati berarti laporan bebas cetak; riwayat validasi lama tetap tampil.

## Panggilan saya (mohon diperiksa)

- **Siapa "pimpinan/tim kurikulum":** hanya penugasan dengan `status_pimpinan`
  atau `status_tim_kurikulum` pada **unit prodi kurikulum itu sendiri**, bukan
  unit induk (fakultas/universitas) dan bukan Admin/Super Admin.
- **Default switch:** kurikulum yang sudah ada diberi **aktif** saat migrasi agar
  perilaku sekarang tidak berubah. Kurikulum baru juga default aktif.
- **Toggle lama `status_validator_nilai`:** tidak lagi menentukan hak
  memvalidasi. Migrasi memindahkan tiap penugasan validator lama menjadi
  validator tambahan pada kurikulum pada unit itu dan turunannya (kecuali yang
  sudah otomatis berhak). Toggle dan kolomnya disembunyikan dari antarmuka,
  kolom dibiarkan di basis data. Isian "Jabatan" tetap dipakai untuk keterangan
  pada laporan tervalidasi, dibaca dari penugasan terdekat tanpa syarat toggle.
- **Daftar pilihan multi-select:** pengguna aktif mana pun, kecuali yang sudah
  otomatis berhak (pimpinan/tim kurikulum prodi itu). Boleh memilih orang yang
  belum berperan Validator Nilai; perannya diberikan otomatis.
- **Siapa yang mengubah switch dan daftar:** pengguna yang sudah berhak
  mengedit kurikulum (Tim Kurikulum prodi, Admin, Super Admin). Koordinator MK
  tidak mengedit kurikulum, jadi tidak bisa menunjuk dirinya.
- **Larangan memvalidasi sendiri** tetap diperiksa saat validasi
  (`ValidasiNilaiPolicy`), bukan saat memilih.
- **Peran dicabut** ketika orang itu tidak lagi berhak pada kurikulum aktif
  mana pun; sinkronisasi peran dipicu saat switch, daftar, atau penugasan
  pimpinan/tim kurikulum berubah.

### Tambahan setelah pemeriksaan pembangun

- **Saat switch mati:** `laporanSesuaiValidasi()` dan gerbang cetak menganggap
  laporan boleh cetak untuk semua kelas kurikulum itu. Label status dibaca
  apa adanya dari baris tersimpan tanpa menghitung sidik; `periksa()` dan
  `NilaiMahasiswaObserver::gugurkan` tidak menulis apa pun. Baris yang sudah
  `PerluUlang` tetap tampil "Perlu validasi ulang" sebagai riwayat, baris
  `Tervalidasi` tetap "Tervalidasi", sekalipun sidiknya sudah berbeda.
- **Kelas tanpa kurikulum** (`mk_units.kurikulum_id` kosong; skema mengizinkan,
  migrasi backfill 2026-07-27 sudah menghilangkannya): diperlakukan seperti
  switch mati, karena tidak ada kurikulum yang bisa menentukan validatornya.
- **Migrasi validator lama bersifat salinan satu kali.** Kurikulum yang dibuat
  kemudian tidak mewarisi validator tambahan; pimpinan dan tim kurikulum
  tetap otomatis berhak.
- **Pencabutan peran:** aturan sinkronisasi yang sudah ada dipertahankan,
  yaitu peran Validator Nilai dicabut dari siapa pun yang tidak berhak, apa pun
  sumber pemberiannya (termasuk pemberian manual). Hak dihitung hanya pada
  kurikulum dengan switch aktif.
- **Penugasan unit tidak punya periode atau status aktif** (kolom hanya
  `status_pimpinan`, `status_tim_kurikulum`, `jabatan`), jadi hak otomatis
  cukup dibaca dari kedua flag itu; tidak ada konsep pengguna nonaktif yang
  perlu dipertimbangkan.
- **Syarat peran tetap:** pimpinan/tim kurikulum memilih peran Validator Nilai
  di pemilih peran; policy tidak diubah agar menerima peran lain.
- **Simulasi:** `sim-validator` menjadi validator tambahan pada kurikulum
  simulasi yang switch-nya aktif; kolom lama tidak lagi ditulis.
- **Kurikulum aktif tanpa seorang pun yang berhak:** form menampilkan teks
  bantuan di bawah switch yang menyebut pimpinan dan tim kurikulum prodi
  otomatis berhak, tanpa memblokir penyimpanan.

## Yang diserahkan ke pembangun

Nama kolom dan tabel pivot (mengikuti konvensi migrasi repo), letak komponen
form pada halaman edit kurikulum, titik pemicu sinkronisasi peran, penyesuaian
akun dan data simulasi, pembaruan teks panduan dan manual pengguna, serta
rincian pengujian: kebijakan, cakupan, sinkronisasi peran, gerbang cetak saat
switch mati, migrasi data, dan memastikan baris validasi lama tidak berubah saat
switch dimatikan.
