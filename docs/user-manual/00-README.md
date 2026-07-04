# Panduan Pengguna SILOGY

SILOGY adalah Sistem Informasi pengelolaan kurikulum berbasis **OBE (Outcome-Based Education)** di lingkungan Universitas Siliwangi. Sistem ini mengelola hirarki institusi, kurikulum (CPL → BoK → MK → CPMK → Sub-CPMK), kelas, penilaian capaian pembelajaran, hingga analisis berbasis AI.

Dokumen ini adalah indeks panduan. Setiap role memiliki panduan terpisah sesuai kewenangannya.

## Daftar panduan per role

| Role | File panduan |
|------|--------------|
| Super Admin | [01-super-admin.md](01-super-admin.md) |
| Admin Universitas / Fakultas / Jurusan / Program Studi | [02-admin-unit.md](02-admin-unit.md) |
| Tim Kurikulum | [03-tim-kurikulum.md](03-tim-kurikulum.md) |
| Koordinator Mata Kuliah | [04-koordinator-mk.md](04-koordinator-mk.md) |
| Dosen Pengampu | [05-dosen-pengampu.md](05-dosen-pengampu.md) |
| Pimpinan (Universitas/Fakultas/Jurusan/Prodi) & Auditor Mutu | [06-pimpinan-auditor.md](06-pimpinan-auditor.md) |

## Cara masuk (login)

1. Buka alamat aplikasi SILOGY di peramban (browser).
2. Masukkan **Username** dan **Password** akun Anda.
3. Klik **Masuk / Sign in**.

> Akun bawaan hasil seeding menggunakan pola username sederhana (mis. `superadmin`, `adminprodi`, `dosen`, `kaprodi`) dengan password awal `siliwangi`. **Segera ganti password** setelah login pertama melalui menu profil di pojok kanan atas.

Jika menggunakan 2FA (autentikasi dua faktor), masukkan kode dari aplikasi authenticator setelah password.

## Konsep penting

- **Unit akademik berjenjang**: Universitas → Fakultas → Jurusan → Program Studi. Kewenangan Anda mengikuti unit tempat Anda ditugaskan.
- **Alur kurikulum OBE**: Profil Lulusan → CPL (Capaian Pembelajaran Lulusan) → BoK (Body of Knowledge) → MK (Mata Kuliah) → CPMK → Sub-CPMK → Komponen Penilaian → Input Nilai → Laporan/Analisis.
- **Menu yang tampil berbeda tiap role.** Jika sebuah menu tidak muncul, berarti role Anda tidak memiliki kewenangan tersebut.

## Navigasi umum (kelompok menu)

Institusi · Autentikasi · Kurikulum · Mata Kuliah · Kelas · Mahasiswa · Penilaian · AI Analisis · Audit. Menu yang aktif bergantung pada role.

## Bantuan

Hubungi Super Admin / Admin unit Anda untuk reset password, perubahan role, atau penambahan akun.
