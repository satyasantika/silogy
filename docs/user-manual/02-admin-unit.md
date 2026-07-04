# Panduan Admin Unit (Universitas / Fakultas / Jurusan / Program Studi)

Admin Unit mengelola **pengguna dan sub-unit di bawah cakupannya** serta mendukung operasional akademik (penugasan dosen, laporan). Cakupan kewenangan mengikuti tingkat unit Anda — semakin tinggi unit, semakin luas jangkauannya.

## Ringkasan kewenangan per tingkat

| Tingkat | Kelola user | Kelola sub-unit | Lainnya |
|---------|-------------|-----------------|---------|
| **Admin Universitas** | Universitas, Fakultas, Jurusan, Prodi | Fakultas, Jurusan, Prodi | Set dosen MK, laporan, ekspor, dashboard |
| **Admin Fakultas** | Fakultas, Jurusan, Prodi | Jurusan, Prodi | Set dosen MK, laporan, ekspor, dashboard |
| **Admin Jurusan** | Jurusan, Prodi | Prodi | Set dosen MK, laporan, ekspor, dashboard |
| **Admin Program Studi** | Prodi | — | Kelola **Kelas**, set dosen MK, laporan, ekspor, dashboard |

> Semua Admin Unit hanya dapat mengelola data **dalam cakupan unitnya sendiri** dan unit di bawahnya.

## Menu yang tersedia

- **Autentikasi → Users**: kelola akun pada cakupan unit Anda.
- **Institusi → Unit Akademik**: kelola sub-unit (sesuai tingkat).
- **Kelas → Kelas MK** (khusus Admin Prodi): buat dan atur kelas.
- **Laporan / Dashboard**: pantau capaian dan ekspor data.

## Tugas yang sering dilakukan

### 1. Mengelola akun pengguna di unit Anda
1. Buka **Autentikasi → Users → New**.
2. Isi data pengguna, tetapkan role (mis. Dosen Pengampu, Koordinator MK) dan unit penugasan di bawah Anda.
3. Simpan. Untuk menonaktifkan akun, edit pengguna dan ubah statusnya.

### 2. Menata sub-unit
1. Buka **Institusi → Unit Akademik**.
2. Tambah/ubah jurusan atau prodi sesuai tingkat kewenangan Anda, pastikan unit induk benar.

### 3. Menugaskan dosen ke mata kuliah (set dosen MK)
1. Masuk ke pengelolaan MK / kelas pada unit Anda.
2. Pilih mata kuliah, lalu tetapkan dosen pengampu yang sesuai.

### 4. Mengelola kelas (khusus Admin Program Studi)
1. Buka **Kelas → Kelas MK → New**.
2. Pilih mata kuliah, semester aktif, dan dosen pengampu.
3. Tambahkan mahasiswa/peserta kelas bila diperlukan.

### 5. Laporan & ekspor
- Buka **Dashboard/Laporan** untuk melihat ringkasan capaian.
- Gunakan tombol **Ekspor** untuk mengunduh data (mis. ke Excel) bagi keperluan akreditasi/pelaporan.

## Praktik baik

- Pastikan semester aktif sudah benar sebelum membuat kelas.
- Tetapkan role seminimal mungkin sesuai tugas pengguna.
- Koordinasikan penugasan dosen dengan Kaprodi/Koordinator MK.
- Lakukan ekspor laporan secara berkala sebagai arsip.

## Yang BUKAN kewenangan Admin Unit

Penyusunan isi kurikulum (CPL, BoK, MK, CPMK) dan input nilai bukan tugas Admin Unit. Permintaan analisis AI adalah kewenangan Pimpinan.
