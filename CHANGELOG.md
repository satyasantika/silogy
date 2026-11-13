# Changelog

Semua perubahan penting pada proyek SILOGY didokumentasikan di file ini.

Format mengacu [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), dan proyek ini mengikuti [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Tombol "Normalisasi semua"** di matriks Interaksi CPL ↔ MK — menormalisasi setiap baris MK ke 100% sekaligus, dengan aturan yang sama seperti tombol per baris (MK yang sudah 100%, tanpa CPL, atau milik unit lain dilewati).
- **Grafik tren ketercapaian CPL antarsemester** (beserta tabel data) di Grafik CPL, tab Grafik CPL Analisis MK, dan versi satu MK di Laporan Koordinator. Per semester, kontribusi MK dibagi ulang hanya di antara MK yang dinilai pada semester itu.
- **Peringatan "Data belum lengkap"** — kartu kelas menampilkan persentase nilai terisi, dan banner kuning muncul (ikut tercetak) bila sebagian nilai masih kosong. Nilai 0 dihitung terisi; hanya sel kosong yang dihitung belum.
- **Blok F "Analisis & Rencana Tindak Lanjut"** pada laporan kelas — dosen pengampu mengisi analisis penyebab dan RTL untuk tiap CPL di bawah target, ditambah catatan umum; koordinator MK membaca saja. Tabel baru `catatan_rtl_kelas`.
- **Tombol Tambah manual kembali** di daftar Profil Lulusan, CPL, BoK, MK, CPMK, Sub-CPMK, dan Asesmen — tombol bulat berikon **+** setinggi tombol **Impor massal**, di sampingnya. Data kini boleh masuk satu per satu atau sekaligus; visibilitas mengikuti izin membuat (ikut tersembunyi saat CPMK terkunci dan di contoh simulasi hanya-baca).

- **Peran Validator Nilai dan gerbang cetak Laporan Lengkap** — Laporan Lengkap sebuah kelas hanya bisa dicetak setelah kelas itu divalidasi. Validator ditandai lewat sakelar **Validator nilai** pada penugasan unit pengguna (kolom baru `academic_unit_users.status_validator_nilai`); cakupannya unit berpenanda beserta seluruh unit di bawahnya. Halaman baru **Penilaian → Validasi Nilai** (daftar kelas per semester, tombol Lihat, lalu Validasi / Batalkan validasi). Validasi gugur otomatis bila nilai, bobot, pemetaan, peserta, atau Analisis & RTL berubah; cetakan tervalidasi memuat blok "Divalidasi oleh", cetakan lain bertanda air "BELUM DIVALIDASI". Berlaku untuk semua kelas di semua semester. Tabel baru `validasi_nilai_kelas`. Panduan baru `/panduan/validator-nilai` dan akun demo `validator`.
- **Peran Validator Nilai di Pusat Simulasi** — tombol "Coba sebagai Validator Nilai" dan tile peran di ruang simulasi; kini tujuh akun per ruang (satu ruang untuk tujuh peserta).

### Changed

- **Jenis komponen penilaian disamakan dengan SI-OBE**: Aktivitas Partisipatif (Individu/Kelompok), Hasil PjBL/PBL/IBL/CBL (Individu/Kelompok), Tugas, Kuis, UTS, UAS — berurutan lewat kolom baru `evaluasi.urutan`. Kode internal tidak berubah dan nama lama tetap diterima saat impor. Migrasi `2026_11_08_000001` melebur **Studi Kasus** ke Hasil PjBL/PBL/IBL/CBL (Individu); peleburan ini **tidak dapat di-rollback**.
- **Radar Laporan Kelas** disusun 2×2 di layar lebar (sebelumnya bisa 4 sejajar), lebar kanvas dibatasi 560px.
- **Domain CPL menjadi Sikap, Pengetahuan, Keterampilan** sesuai label Permendiktisaintek 39/2025. Migrasi `2026_11_06_000001` memetakan data lama (afektif→sikap, kognitif→pengetahuan, psikomotorik→keterampilan) dan dapat di-rollback. Impor massal CPL tetap menerima istilah lama sebagai alias. Taksonomi Bloom Sub-CPMK (C/A/P) tidak berubah.

## [6.4.0] - 2026-09-24

Data latihan kini bisa dinyalakan dan dimatikan Super Admin kapan saja, dan panduan per peran terbit sebagai halaman publik bertangkapan layar asli — tertaut langsung dari beranda.

### Added

- **Pusat Simulasi** (`/simulasi`, khusus Super Admin) — tombol **Buat**, **Bangun Ulang**, dan **Hapus** data latihan. Simulasi membangun pohon unitnya sendiri (Universitas Simulasi → Fakultas Simulasi → Prodi Simulasi) beserta akun `sim-*`, seluruh rantai OBE, nilai, hasil kalkulasi, dan satu usulan perubahan CPMK yang menunggu keputusan.
- **Buku besar kepemilikan** — tabel `simulasi_jalan` dan `simulasi_artefak`. Sebuah baris dicatat hanya bila simulasi benar-benar membuatnya; baris yang diadopsi `firstOrCreate` tidak pernah tercatat, karena itu tidak mungkin ikut terhapus. Pembongkaran berjalan mundur menurut urutan pembuatan — urutan terbalik itulah yang memenuhi setiap batasan RESTRICT di skema.
- **`php artisan simulasi:status` / `simulasi:buat` / `simulasi:hapus`** — tanpa `--terapkan`, perintah hapus hanya melaporkan apa yang akan dibuang.
- **Panduan publik `/panduan`** — satu halaman per peran, dirender langsung dari `docs/user-manual/*.md` sehingga isinya tidak pernah menyimpang dari sumbernya. Tertaut dari navbar, hero, seksi baru di beranda, dan footer.
- **Tombol "Coba sebagai ‹peran›"** — masuk otomatis ke akun simulasi peran tersebut, lengkap dengan peran dan unit yang sudah terpilih. **Tertutup secara bawaan**, POST ber-CSRF, dibatasi per IP, dan hanya menerima akun yang tercatat di buku besar simulasi.
- **Sakelar mode latihan di basis data** (`simulasi_jalan.coba_peran`) — dibuka dan ditutup Super Admin dari menu Simulasi, tanpa menyunting `.env` maupun menunggu deploy. Sakelar menempel pada jalan simulasi, jadi menghapus data simulasi otomatis menutup jalur masuk tanpa kata sandi. `SIMULASI_IZINKAN_COBA_PERAN` di `.env` tinggal berfungsi sebagai pemutus keras yang hanya bisa melarang.
- **Pipeline tangkapan layar nyata** — `scripts/tangkap-layar/` menjalankan Playwright terhadap instans yang berjalan dan menulis `docs/user-manual/aset/manifes.json`. Koordinat angka penunjuk dihitung dari selector elemen saat memotret, lalu digambar sebagai overlay CSS — sehingga penunjuk ikut berpindah sendiri ketika antarmuka berubah. `--periksa` menjaga manifes dan Markdown tetap sinkron.
- **`php artisan panduan:tautkan-aset`** — menautkan `public/manual` ke `docs/user-manual/aset`.

### Changed

- **Layout publik diekstrak** — `resources/views/layouts/publik.blade.php` beserta partial token, navbar, dan footer. Beranda kini memakainya; palet Unsil yang dulu terduplikasi di tiga tempat berkurang satu. Terbukti tidak mengubah tampilan beranda satu piksel pun lewat perbandingan tangkapan layar terang dan gelap.
- **Empat tautan mati `href="#"` di footer** diganti tautan panduan yang sebenarnya.
- **`06-pimpinan-auditor.md` dipecah** menjadi `06-pimpinan.md` dan `07-auditor-mutu.md`; `07-simulasi-penggunaan.md` menjadi `08-`. Seluruh manual kini bergambar — sebelumnya hanya dua berkas yang punya gambar.
- **`RolePermissionSeeder` dipisah** menjadi `seedPeranDanIzin()` (peran + izin saja) dan bagian akun, supaya pemicu dari antarmuka tidak pernah menyentuh akun.

### Fixed

- **Seeder tidak lagi mereset kata sandi dan peran pengguna nyata** yang kebetulan memakai username akun demo (mis. seorang dosen ber-username `dosen`). Sebelumnya `RolePermissionSeeder` menimpanya tanpa peringatan.
- **Migrasi `2026_09_15_000002` kini bisa dilanjutkan dan berjalan di MySQL.** Urutan lamanya menjatuhkan indeks unik yang justru sedang menopang foreign key (`errno 1553`), dan karena DDL MySQL tidak transaksional, kegagalan di tengah membuat pengulangan mati pada "Can't DROP FOREIGN KEY".
- **`SimulasiAkademikBuilder::seedMkUnitRingkas()`** tidak lagi memungut prodi sembarangan sebagai sumber peserta kelas — pada basis data berisi data nyata, itu berarti mahasiswa sungguhan ikut terdaftar ke kelas simulasi.

### Removed

- **`docs/user-manual/html/`** beserta `buat_gambar.py` dan `buat_halaman.py` — 67 SVG tiruan antarmuka digantikan tangkapan layar aplikasi yang sebenarnya.

## [6.3.0] - 2026-09-15

CPMK, Sub-CPMK, dan Asesmen kini **berlaku per semester** dan dapat **dipakai ulang dengan ID yang sama** lintas semester — bukan lagi disalin menjadi baris baru berisi kode lama. Perubahan CPMK berada di bawah persetujuan Tim Kurikulum.

### Added

- **Pivot semester** — `cpmk_semester`, `subcpmk_semester`, `komponen_penilaian_semester`. Satu baris dapat berlaku di banyak semester, sehingga capaian satu Sub-CPMK dapat ditelusuri lintas semester lewat satu identitas.
- **Gerbang keputusan per semester** — aksi "Gunakan data semester lain" pada langkah CPMK, Sub-CPMK, dan Asesmen, dengan pilihan **sepaket** atau **sebagian**.
- **Kaskade per-CPMK** (`KaskadeReuseSemester`) — turunan hanya boleh dipakai ulang bila induknya juga dipakai ulang. CPMK yang baru disusun untuk semester berjalan mewajibkan Sub-CPMK dan Asesmen baru.
- **Persetujuan perubahan CPMK** — tabel `perubahan_cpmk_requests`, kotak masuk Tim Kurikulum, permission `setujui_perubahan_cpmk`, dan gerbang `GerbangPerubahanCpmk`. Tim Kurikulum unit **induk** kini juga berwenang (`AcademicUnitScope::userIsTimKurikulumOnUnitOrAncestor()`).
- **`php artisan mk:konsolidasi-lintas-semester`** — melaporkan (dry-run) atau melebur baris kembar lintas semester menjadi satu baris kanonik.

### Changed

- **Pakai ulang menggantikan salin.** `SubcpmkSalinSemesterService` dan `KomponenPenilaianSalinSemesterService` diganti `…PakaiUlangSemesterService`: baris lama dilampirkan ke semester tujuan, ID-nya tetap.
- **Bobot pindah ke pivot.** Kolom `subcpmk.bobot` dan `komponen_penilaian.bobot` dihapus; keduanya milik pasangan (entitas, semester). Pengakses lamanya sengaja melempar `LogicException` agar pemanggil yang terlewat gagal nyaring, bukan diam-diam membaca 0.
- **Reset melepas, tidak menghapus.** Reset Sub-CPMK/Asesmen/CPMK per semester kini melepas lampiran semester; barisnya hanya dihapus bila tidak lagi dipakai semester mana pun. Sebelumnya bulk delete — yang, dengan ID dipakai bersama, akan ikut menghapus data semester lain beserta nilainya.
- **`subcpmk_komponenpenilaian.semester_id` menjadi NOT NULL** dan masuk UNIQUE `(subcpmk_id, komponen_penilaian_id, semester_id)`; kolom inilah penentu semester yang dipakai `SubcpmkCalculator`.
- **`komponen_penilaian.kode` menjadi NOT NULL** dengan UNIQUE `(mk_id, kode)`; baris tanpa kode diberi kode turunan dari namanya saat konsolidasi.
- **Daftar CPMK disaring per semester** untuk Koordinator MK. Admin/Tim Kurikulum/Auditor tetap melihat lintas semester, dengan kolom "Berlaku pada semester".

### Fixed

- `SubcpmkKomponenPenilaianRelationManager` membuat baris pemetaan tanpa `semester_id` (NULL) karena relasi HasMany Filament tidak mengetahui konteks semester.

### Migration notes

1. Jalankan `php artisan mk:konsolidasi-lintas-semester` lebih dulu pada salinan data produksi dan **baca laporannya** — terutama bila ia menyebut nilai mahasiswa yang akan dibuang atau asesmen tanpa kode.
2. `php artisan migrate` menjalankan konsolidasi lalu memindahkan kolomnya. `hasil_subcpmk`/`hasil_cpmk` untuk kelas terdampak dihapus karena murni turunan — **jalankan ulang kalkulasi CPL** setelahnya agar terisi kembali.
3. Arah `down()` tidak setia: baris yang sudah dilebur tidak bisa dipecah kembali.

## [6.0.0] - 2026-05-18

Rilis MVP pertama — alur end-to-end dari kurikulum hingga dashboard CPL siap didemokan ke pimpinan prodi.

### Added

- **AcademicUnit UUID** — hierarki institusi tunggal (`university` → `faculty` → `department` → `study_program`) dengan pivot `academic_unit_users`.
- **Kurikulum workflow** — state machine 7 tahap: `draft` → `profil_lulusan` → `cpl` → `bok` → `mk` → `setdosenmk` → `aktif`.
- **CPL pipeline 5 tahap** — `hasil_subcpmk` → `hasil_cpmk` → `hasil_cpl_mk` → `hasil_cpl_mk_unit` → `hasil_cpl_unit` via `RecalkulasiCplJob`.
- **Dashboard CPL** — widget Filament capaian per unit, filter semester, drill-down per MK unit.
- **RBAC Spatie** — role & permission UUID; Filament Shield; akun demo seed.
- **Penilaian** — komponen penilaian, mapping sub-CPMK, input nilai matriks (dosen pengampu).
- **Kelas MK** — penawaran per `mk_unit`, penetapan dosen pengampu & koordinator MK.
- **Audit** — Spatie Activitylog + viewer read-only (Super Admin & Auditor Mutu).
- **Operasional** — skrip backup harian terenkripsi, endpoint `GET /health`, test E2E MVP (`MvpEndToEndTest`).
- **Dokumentasi** — README P0.1, onboarding 30 menit, skrip demo 15 menit, Docker Compose + Makefile.

### Changed

- **Migrated** dari skema v5: tabel terpisah `universitas` / `fakultas` / `jurusan` / `prodis` **dihapus**; diganti satu tabel `academic_units`.
- Stack admin: **Laravel 13**, **Filament v4**, **MySQL 8**, **Redis 7**.
- Kalkulasi AI direncanakan fase 2 (MVP memakai Google Gemini API di konfigurasi, belum wajib demo).

### Roles

- **Pimpinan** — Universitas, Fakultas, Jurusan, Program Studi (dashboard & laporan).
- **Koordinator Mata Kuliah** — CPMK, sub-CPMK, komponen penilaian.
- **Admin per level** — Admin Universitas / Fakultas / Jurusan / Program Studi.
- **Tim Kurikulum** — kurikulum, CPL, BoK, MK, `mk_units`.
- **Dosen Pengampu** — kelas & input nilai.
- **Super Admin** — institusi, pengguna, audit log.
- **Auditor Mutu** — baca laporan & audit log.

[6.3.0]: https://github.com/unsil/silogy/releases/tag/v6.3.0
[6.0.0]: https://github.com/unsil/silogy/releases/tag/v6.0.0
