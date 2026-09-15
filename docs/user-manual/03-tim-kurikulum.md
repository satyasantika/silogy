# Panduan Tim Kurikulum

Tim Kurikulum bertanggung jawab menyusun **isi kurikulum berbasis OBE**: dari Profil Lulusan, Capaian Pembelajaran Lulusan (CPL), Body of Knowledge (BoK), hingga struktur Mata Kuliah (MK) dan pemetaannya antar unit.

## Kewenangan utama

- Mengelola **Kurikulum**.
- Mengelola **Profil Lulusan** — aktif hanya pada unit Program Studi tempat Anda berstatus anggota Tim Kurikulum.
- Mengelola **CPL**, **BoK**, **MK**, dan **MK per unit (MK Unit)**.
- Menugaskan dosen ke MK (set dosen MK).
- Melihat laporan dan dashboard.

## Menu yang tersedia (kelompok **Kurikulum**)

- **Kurikulum**: definisi kurikulum yang berlaku.
- **CPL**: capaian pembelajaran lulusan.
- **BoK**: body of knowledge / bahan kajian.
- **MK** & **MK Unit**: daftar mata kuliah dan penempatannya pada unit.

## Alur kerja yang disarankan

Ikuti urutan OBE agar keterkaitan data konsisten:

### 1. Definisikan Kurikulum
1. Buka **Kurikulum → New**.
2. Isi nama, tahun berlaku, dan prodi terkait. Simpan.

### 2. Susun Profil Lulusan (pada prodi Anda)
1. Buka pengelolaan **Profil Lulusan**.
2. Tambahkan setiap profil lulusan beserta deskripsinya.

### 3. Rumuskan CPL
1. Buka **Kurikulum → CPL → New**.
2. Tuliskan kode dan rumusan CPL, kaitkan dengan profil lulusan.

### 4. Tetapkan BoK
1. Buka **BoK → New**.
2. Tambahkan bahan kajian dan kaitkan ke CPL yang relevan.

### 5. Susun Mata Kuliah & pemetaannya
1. Buka **MK → New**: tambahkan mata kuliah (kode, nama, SKS).
2. Buka **MK Unit**: tempatkan MK pada unit/prodi dan semester yang tepat.
3. Petakan MK ke CPL/BoK sesuai matriks kurikulum.

### 6. Tugaskan dosen ke MK
- Gunakan fitur **set dosen MK** untuk menetapkan pengampu setiap mata kuliah.

## Praktik baik

- Selesaikan CPL sebelum BoK dan MK, karena keduanya merujuk ke CPL.
- Pastikan setiap MK terpetakan ke minimal satu CPL agar laporan ketercapaian lengkap.
- Periksa kembali matriks CPL–MK sebelum kurikulum digunakan untuk penilaian.
- Gunakan dashboard untuk memantau kelengkapan pemetaan.

## Catatan

- Pengelolaan **Profil Lulusan** hanya tersedia di unit prodi tempat Anda terdaftar sebagai anggota Tim Kurikulum.
- Penyusunan CPMK/Sub-CPMK dan komponen penilaian dilakukan oleh **Koordinator Mata Kuliah**, bukan Tim Kurikulum.

## Menyetujui perubahan CPMK

CPMK terpetakan ke CPL lewat `mk_cpmk`, jadi mengubahnya menyentuh kontrak kurikulum —
bukan urusan satu mata kuliah saja. Karena itu Koordinator MK boleh **memakai ulang** CPMK
semester sebelumnya kapan saja, tetapi **mengubah** CPMK yang sudah berjalan pada suatu
semester harus lewat persetujuan Anda.

### Kotak masuk

Menu **Kurikulum → Usulan Perubahan CPMK** menampilkan usulan pada unit Anda. Badge angka
di sampingnya adalah jumlah usulan yang masih menunggu keputusan.

Cakupannya mengikuti penugasan unit: Tim Kurikulum prodi menangani mata kuliah prodinya,
Tim Kurikulum fakultas/universitas menangani mata kuliah di seluruh unit di bawahnya.

### Meninjau

1. Buka usulannya. Anda akan melihat alasan koordinator dan **potret CPMK yang berlaku saat
   usulan diajukan** — inilah yang hendak diganti.
2. **Setujui** bila perubahan itu wajar. Sesudahnya koordinator boleh menyusun ulang CPMK
   mata kuliah tersebut untuk semester itu.
3. **Tolak** bila belum layak. Alasan penolakan **wajib diisi**: tanpa itu koordinator tidak
   tahu apa yang perlu diperbaiki sebelum mengajukan lagi.

### Catatan

- Anda tidak bisa menyetujui usulan yang Anda ajukan sendiri.
- Hanya boleh ada satu usulan terbuka per (mata kuliah, semester).
- Seluruh keputusan tercatat di riwayat transisi dan audit log.
