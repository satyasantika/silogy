# Simulasi Penggunaan SILOGY (Urutan End-to-End)

Dokumen ini menyajikan **alur simulasi berurutan** penggunaan sistem SILOGY dari nol hingga menghasilkan laporan dan analisis. Setiap tahap menyebutkan **role pelaksana**, **menu**, dan **langkah**. Ikuti urutannya karena setiap tahap menjadi prasyarat tahap berikutnya.

> Akun contoh (hasil seeding) — password awal `siliwangi`:
> `superadmin`, `adminuniv`, `adminfak`, `adminjur`, `adminprodi`, `timkur`, `korma`, `dosen`, `kaprodi`, `dekan`, `rektor`, `auditor`.

---

## Peta alur singkat

```
Super Admin            Admin Unit         Tim Kurikulum        Koordinator MK        Dosen           Pimpinan/Auditor
─────────────          ──────────         ──────────────       ──────────────        ─────           ────────────────
Institusi & Akun  →    User & Kelas  →    CPL→BoK→MK      →     CPMK→SubCPMK→     →   Input Nilai →   Laporan, Analisis AI,
Semester aktif         Set dosen MK       Profil Lulusan       Komponen Nilai        Import Nilai     Audit
```

---

## TAHAP 0 — Persiapan sistem (Super Admin)

**Login sebagai `superadmin`.**

1. **Institusi → Unit Akademik**: buat hirarki
   - Buat Universitas (tipe *university*).
   - Buat Fakultas (induk: Universitas).
   - Buat Jurusan (induk: Fakultas).
   - Buat Program Studi (induk: Jurusan).
2. **Semester**: buat dan **aktifkan** semester berjalan (mis. Ganjil 2026/2027).
3. **Autentikasi → Roles**: pastikan seluruh role tersedia dan permission-nya sesuai (hasil seeding sudah menyiapkan ini).
4. (Opsional) **Autentikasi → Users**: buat akun admin tiap unit bila belum ada.

✅ *Output tahap ini:* struktur institusi lengkap + semester aktif.

---

## TAHAP 1 — Penyiapan akun & operasional unit (Admin Unit)

**Login sebagai `adminprodi` (atau admin unit terkait).**

1. **Autentikasi → Users**: buat akun untuk:
   - Anggota **Tim Kurikulum** (tetapkan unit = prodi, status tim kurikulum aktif).
   - **Koordinator Mata Kuliah**.
   - **Dosen Pengampu**.
2. **Mahasiswa**: input/import data mahasiswa prodi.
3. *(Kelas dibuat setelah MK tersedia — lihat Tahap 3.)*

✅ *Output:* akun pelaku akademik & data mahasiswa siap.

---

## TAHAP 2 — Penyusunan kurikulum (Tim Kurikulum)

**Login sebagai `timkur`.**

1. **Kurikulum**: buat kurikulum (nama, tahun berlaku, prodi).
2. **Profil Lulusan**: tambahkan profil lulusan prodi.
3. **CPL**: rumuskan CPL, kaitkan ke profil lulusan.
4. **BoK**: tambahkan bahan kajian, kaitkan ke CPL.
5. **MK**: tambahkan mata kuliah (kode, nama, SKS).
6. **MK Unit**: tempatkan MK pada prodi & semester; petakan MK ke CPL/BoK.

✅ *Output:* matriks kurikulum (Profil → CPL → BoK → MK) lengkap.

---

## TAHAP 3 — Pembentukan kelas & penugasan dosen (Admin Prodi + Tim Kurikulum)

1. **Admin Prodi** — **Kelas → Kelas MK**: buat kelas untuk tiap MK pada semester aktif, tambahkan peserta (mahasiswa).
2. **Set dosen MK** (Admin/Tim Kurikulum): tetapkan **Dosen Pengampu** untuk setiap kelas/MK.

✅ *Output:* kelas terbentuk dengan dosen pengampu.

---

## TAHAP 4 — Perancangan penilaian (Koordinator Mata Kuliah)

**Login sebagai `korma`.**

1. **Mata Kuliah → CPMK**: rumuskan CPMK tiap MK, kaitkan ke CPL.
2. **Mata Kuliah → Sub-CPMK**: rincikan Sub-CPMK di bawah tiap CPMK.
3. **Penilaian → Komponen Penilaian**: tetapkan komponen (Tugas, Kuis, UTS, UAS, dll.) + **bobot (%)**, kaitkan ke Sub-CPMK/CPMK. Pastikan total bobot = 100%.

✅ *Output:* struktur penilaian siap diisi nilai.

---

## TAHAP 5 — Pelaksanaan & input nilai (Dosen Pengampu)

**Login sebagai `dosen`.**

1. **Kelas → Kelas MK**: pastikan kelas yang diampu benar.
2. **Penilaian → Input Nilai**: pilih kelas & MK, isi nilai tiap mahasiswa per komponen, **Simpan**.
3. *(Alternatif massal)* **Import Nilai**: unduh template → isi → unggah → konfirmasi → periksa baris gagal.

✅ *Output:* nilai mahasiswa lengkap; ketercapaian CPMK/Sub-CPMK terhitung.

---

## TAHAP 6 — Monitoring, analisis & audit (Pimpinan & Auditor)

1. **Pimpinan** (`kaprodi`/`dekan`/`rektor`) — **Dashboard/Laporan**: tinjau ketercapaian CPL/CPMK pada unit.
2. **Pimpinan** — **AI Analisis → Minta Analisis**: ajukan analisis (pilih lingkup unit/semester/MK) → cek hasil di **Riwayat Analisis**.
3. **Pimpinan/Admin** — **Ekspor data**: unduh laporan untuk akreditasi/rapat.
4. **Auditor Mutu** (`auditor`) — **Audit → Log Aktivitas**: telusuri jejak perubahan; **Laporan**: telaah kelengkapan & kepatuhan.

✅ *Output:* laporan ketercapaian, analisis AI, dan bukti audit.

---

## Daftar periksa keberhasilan simulasi

- [ ] Hirarki Universitas→Fakultas→Jurusan→Prodi terbentuk
- [ ] Semester aktif tersedia
- [ ] Akun tiap role dibuat dan ditugaskan ke unit yang benar
- [ ] Kurikulum: Profil Lulusan, CPL, BoK, MK, MK Unit lengkap & terpetakan
- [ ] Kelas terbentuk dengan dosen pengampu
- [ ] CPMK, Sub-CPMK, Komponen Penilaian (bobot total 100%) tersedia
- [ ] Nilai mahasiswa terinput penuh
- [ ] Dashboard menampilkan ketercapaian CPL/CPMK
- [ ] Analisis AI berhasil dijalankan
- [ ] Log audit mencatat aktivitas

---

## Catatan urutan ketergantungan

- **CPL harus ada sebelum** BoK, MK, dan CPMK (semuanya merujuk ke CPL).
- **Komponen Penilaian harus ada sebelum** Dosen dapat input nilai.
- **Nilai harus lengkap sebelum** analisis AI/laporan ketercapaian bermakna.
- **Semester aktif harus benar** sebelum membuat kelas dan input nilai.
