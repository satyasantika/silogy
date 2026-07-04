# Panduan Super Admin

Super Admin adalah pengelola tertinggi sistem. Fokus kewenangannya pada **struktur institusi** dan **administrasi sistem (user, role, permission, audit, konfigurasi)**. Super Admin **tidak** mengelola isi kurikulum/penilaian secara langsung — itu kewenangan Tim Kurikulum, Koordinator MK, dan Dosen.

## Kewenangan utama

- Mengelola seluruh unit akademik: Universitas, Fakultas, Jurusan, Program Studi.
- Mengelola Semester dan Evaluasi.
- Mengelola User, Role, dan Permission di semua tingkat unit.
- Melihat Log Audit dan mengatur konfigurasi sistem.

## Menu yang tersedia

- **Institusi → Unit Akademik**: buat dan susun hirarki universitas hingga prodi.
- **Autentikasi → Users**: kelola akun pengguna seluruh sistem.
- **Autentikasi → Roles / Permissions** (Filament Shield): atur peran dan hak akses.
- **Audit → Log Aktivitas**: pantau seluruh aktivitas pengguna.

## Tugas yang sering dilakukan

### 1. Menyiapkan struktur institusi
1. Buka **Institusi → Unit Akademik**.
2. Klik **New / Buat** untuk membuat unit. Pilih **tipe** unit (university, faculty, department, study program) dan tetapkan **unit induk** agar hirarki benar.
3. Simpan. Ulangi hingga seluruh fakultas, jurusan, dan prodi terdaftar.

### 2. Membuat akun pengguna
1. Buka **Autentikasi → Users → New**.
2. Isi Username, Nama Lengkap, Email, NIDN (untuk dosen), dan password awal.
3. Tetapkan **Role** dan **Unit penugasan** (academic unit) yang sesuai.
4. Untuk pejabat, aktifkan status pimpinan; untuk anggota Tim Kurikulum, aktifkan status tim kurikulum pada unit prodi terkait.

### 3. Mengelola role & permission
1. Buka **Roles**. Pilih role yang ingin disesuaikan.
2. Centang/hapus permission sesuai kebutuhan, lalu simpan.
3. Hindari memberi permission berlebih — terapkan prinsip hak akses minimum.

### 4. Mengelola semester & evaluasi
- Aktifkan semester berjalan agar kelas dan penilaian merujuk ke periode yang benar.
- Buka/tutup periode evaluasi sesuai jadwal.

### 5. Memantau audit
- Buka **Audit → Log Aktivitas** untuk melihat siapa mengubah apa dan kapan. Gunakan filter untuk menelusuri kejadian tertentu.

## Praktik baik

- Buat unit akademik terlebih dahulu sebelum membuat akun, agar penugasan unit bisa langsung dipilih.
- Gunakan satu role per akun sesuai fungsi; tambahkan penugasan unit untuk membatasi cakupan.
- Tinjau log audit secara berkala.
- Wajibkan penggantian password awal dan aktifkan 2FA untuk akun penting.

## Yang BUKAN kewenangan Super Admin

Penyusunan CPL, BoK, MK, CPMK, input nilai, dan permintaan analisis AI dilakukan oleh role akademik terkait, bukan Super Admin.
