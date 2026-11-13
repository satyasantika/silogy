# Validasi Nilai Kelas sebelum Cetak Laporan — Desain

Tanggal: 2026-11-13

Catatan penanda: **[diputuskan]** = diputuskan pemilik produk dalam diskusi;
**[usulan]** = keputusan perancang, belum dibahas, boleh dikoreksi saat
meninjau dokumen ini.

## 1. Maksud

Laporan Lengkap sebuah kelas ("Laporan Mata Kuliah ke Prodi", tab Laporan)
saat ini bisa dicetak siapa saja yang bisa membukanya, kapan saja. Pemilik
produk ingin cetakan itu menjadi **bukti bahwa hasil penilaian dosen pengampu
sudah diperiksa pihak penjamin mutu** (mis. UPM), bukan sekadar keluaran
sepihak dosen.

## 2. Sasaran

- Ada peran baru **Validator Nilai** yang memvalidasi nilai per kelas.
  **[diputuskan]**
- Validator ditugaskan ke unit akademik. Awalnya dipakai di tingkat prodi,
  tetapi penugasan ke fakultas/universitas harus langsung berfungsi (untuk MK
  milik unit non-prodi) tanpa perubahan kode. **[diputuskan]**
- Tombol Cetak Laporan Lengkap hanya aktif untuk kelas yang tervalidasi.
  **[diputuskan]**
- Bila data laporan berubah setelah divalidasi, validasi **gugur otomatis**;
  nilai tidak dikunci. **[diputuskan]**
- Cetakan memuat keterangan siapa yang memvalidasi dan kapan. **[usulan]**

## 3. Bukan sasaran

- Mengunci input nilai, bobot, atau peserta setelah validasi.
- Alur "ajukan → tolak dengan catatan → perbaiki". Validator cukup tidak
  memvalidasi; komunikasi perbaikan terjadi di luar sistem. **[usulan]**
- Notifikasi ke dosen saat divalidasi/gugur.
- Tanda tangan digital, QR verifikasi, atau PDF buatan server.
- Fitur "Sub-CPMK mana yang lemah" (proyek terpisah).

## 4. Anti-sasaran

- Cetakan yang tampak sah padahal isinya berbeda dari yang divalidasi.
- Validator memvalidasi kelas di luar unitnya, atau kelas yang ia ampu
  sendiri.
- Perubahan data lewat jalur mana pun (input nilai, impor, ubah bobot,
  ubah pemetaan, ubah peserta) yang lolos tanpa menggugurkan validasi.

## 5. Kondisi sekarang

- Laporan kelas dimuat lewat trait
  `App\Modules\Penilaian\Filament\Pages\Concerns\HasLaporanKelasMk`
  (`muatDataLaporan()`), dipakai `InputNilai` (dosen) dan
  `LaporanKoordinator` (koordinator MK). Keduanya merender partial
  `resources/views/filament/modules/penilaian/partials/laporan-kelas-tabs.blade.php`.
- Tombol Cetak ada di partial itu (sub-tab `laporan-lengkap`) dan memanggil
  `window.silogyCetakLaporanLengkap()` → `window.print()`. CSS cetaknya di
  `resources/views/filament/hooks/kurikulum-cards-styles.blade.php`
  (`body[data-silogy-print="laporan-lengkap"]`, kelas `.silogy-hanya-cetak`).
- Kelas (`kelas_mk`) → `mk_units.academic_unit_id` = unit **penawar** MK,
  selalu prodi (penawaran MK hanya level prodi). Unit **pemilik** MK ada di
  `mk.academic_unit_id` dan bisa fakultas/universitas ("MK non-prodi").
- Pengguna ↔ unit lewat `academic_unit_users`, dengan penanda per penugasan
  `status_pimpinan` dan `status_tim_kurikulum`. Pembantu cakupan ada di
  `App\Modules\Institusi\Support\AcademicUnitScope`
  (mis. `descendantIdsIncludingSelf()`).
- Peran & izin di `database/seeders/RolePermissionSeeder.php` (Spatie).
  Peran Auditor Mutu melihat semua unit, jadi tidak cocok sebagai validator
  per unit.
- Kelengkapan nilai: `PenilaianMatrixService::kelengkapanKelas()`.

## 6. Pendekatan

Persoalan intinya: bagaimana mengetahui bahwa data berubah sejak divalidasi.

| | Cara | Kelebihan | Kekurangan |
|---|---|---|---|
| A | **Sidik isi laporan.** Saat validasi, simpan hash dari data laporan; setiap laporan dimuat, hitung ulang dan bandingkan. | Menangkap semua jalur perubahan tanpa perlu tahu jalurnya; yang dijamin persis "yang dicetak = yang divalidasi". | Status di daftar bisa tertinggal sampai laporan kelas itu dibuka. |
| B | **Observer.** Setiap model terkait berubah → gugurkan validasi. | Status selalu segar. | Harus menutup setiap jalur; `upsert`/impor massal melewati event model, mudah bocor. |
| C | **Banding waktu.** `max(updated_at)` data terkait > waktu validasi. | Murah. | Penghapusan baris dan pembaruan massal tidak terdeteksi. |

**Dipilih: A**, ditambah satu observer ringan dari B untuk jalur paling umum
(ubah nilai) supaya daftar cepat segar. **[usulan — pemilik produk menyerahkan
cara teknis ke perancang]** Alasannya: anti-sasaran utama adalah perubahan yang
lolos, dan hanya A yang tidak bergantung pada kelengkapan daftar jalur.

## 7. Desain

### 7.1 Peran, izin, cakupan

- Peran baru `Validator Nilai`; izin baru `validasi_nilai`; peran ini juga
  mendapat `lihat_laporan` dan `lihat_dashboard`.
- Kolom baru `academic_unit_users.status_validator_nilai` (boolean, default
  false), mengikuti pola `status_pimpinan`/`status_tim_kurikulum`, supaya
  pengguna multi-peran (mis. dosen di prodi A sekaligus validator) tidak
  otomatis menjadi validator di semua unit tempat ia ditugaskan. **[usulan]**
- Cakupan validator = unit berpenanda `status_validator_nilai` **beserta
  seluruh unit turunannya**. Kelas masuk cakupan bila unit **penawar**-nya
  (`mk_units.academic_unit_id`, selalu prodi) termasuk di dalamnya. Jadi:
  validator prodi memvalidasi semua kelas yang ditawarkan prodinya, termasuk
  kelas MK milik fakultas/universitas; validator yang ditugaskan di
  fakultas/universitas mencakup semua prodi di bawahnya. Tumpang-tindih
  disengaja. **[usulan, menafsirkan "levelnya di prodi langsung"]**
- Pembedaan kelak ("MK non-prodi hanya boleh divalidasi validator unit
  pemiliknya") **tidak dibangun sekarang**, tetapi aturan cakupan harus
  berada di satu tempat (satu metode/scope) sehingga nanti cukup diganti
  menjadi berbasis `mk.academic_unit_id` tanpa perubahan skema.
- Validator **tidak boleh** memvalidasi kelas yang ia ampu
  (`dosen_pengampu_id`) maupun kelas yang MK-nya ia koordinatori
  (`koordinator_mk_id`), karena koordinator menentukan bobot dan pemetaan
  yang menjadi dasar laporan. **[usulan]**
- Super Admin tidak otomatis boleh memvalidasi (konsisten dengan batas Super
  Admin di seeder). **[usulan]**

### 7.2 Data

Tabel baru `validasi_nilai_kelas`:

| Kolom | Keterangan |
|---|---|
| `id` | uuid |
| `kelas_mk_id` | unik, FK → `kelas_mk`, cascade delete |
| `validator_id` | FK → `users` |
| `divalidasi_at` | waktu validasi |
| `sidik` | sha256 isi laporan saat divalidasi |
| `gugur_at` | nullable; terisi saat terdeteksi berubah |
| timestamps | |

Satu baris per kelas (keadaan terkini). Riwayat validasi/pembatalan/gugur
dicatat lewat activity log yang sudah ada (`LogsSilogyActivity`); karena
trait itu hanya mencatat event model, semua perubahan baris ini (termasuk
mengisi `gugur_at`) harus lewat model, bukan query builder.

Status turunan sebuah kelas:

- **Belum divalidasi** — tidak ada baris.
- **Tervalidasi** — ada baris, `gugur_at` kosong.
- **Perlu validasi ulang** — ada baris, `gugur_at` terisi.

### 7.3 Sidik

Hash atas data yang tampil di Laporan Lengkap, dalam bentuk kanonik (urutan
kunci tetap): baris mahasiswa (nim, nama, nilai angka, nilai huruf), kolom
asesmen & bobot, nilai per sel, ketercapaian CPL, distribusi nilai huruf,
detail CPL–CPMK–Sub-CPMK, rencana evaluasi, target capaian, serta isi blok F
Analisis & RTL (analisis, rencana, umum — **tanpa** metadata "terakhir diubah
oleh/kapan"). Blok F disertakan karena ikut tercetak. **[usulan]**

Sidik dihitung:

- saat validator menekan Validasi (disimpan);
- setiap kali halaman laporan memuat kelas yang punya baris validasi tanpa
  `gugur_at` — bila berbeda, isi `gugur_at`. Pemeriksaan ini juga harus
  berjalan pada cabang "penugasan belum selesai" (kedua halaman saat ini
  keluar sebelum `muatDataLaporan()` bila `penugasanSelesai()` false): kelas
  tervalidasi yang penugasannya tidak lagi selesai langsung dianggap gugur.

Selain itu `NilaiMahasiswaObserver` yang sudah ada (saat ini hanya `saved()`)
ditambah agar mengisi `gugur_at` kelas terkait saat nilai disimpan atau
dihapus. Ini hanya percepatan; jalur yang melewati event model (upsert,
`withoutEvents()`) tetap tertangkap oleh pemeriksaan sidik saat laporan
dimuat.

Akibat yang diterima: status di daftar validator bisa menampilkan
"Tervalidasi" untuk kelas yang berubah lewat jalur selain input nilai sampai
laporan kelas itu dibuka. Cetak tidak mungkin terjadi tanpa membuka laporan,
jadi bukti cetak tetap terjamin.

### 7.4 Halaman Validator

Halaman Filament baru "Validasi Nilai" di modul Penilaian, grup navigasi yang
sama dengan Laporan Koordinator, hanya untuk peran aktif Validator Nilai.

- Pemilih semester (pola `PenilaianSemesterTerpilih`/`LaporanKoordinator`).
- Daftar kelas dalam cakupan pada semester itu:

```
MK / Kelas            Dosen        Nilai         Status validasi
Aljabar Linear – A    Dr. X        Lengkap       Belum divalidasi     [Lihat]
Aljabar Linear – B    Dr. Y        Lengkap       Tervalidasi 12 Nov   [Lihat]
Kalkulus I – A        Dr. Z        31/40 terisi  —                    [Lihat]
Kalkulus I – B        Dr. W        Lengkap       Perlu validasi ulang [Lihat]
```

- **Lihat** menampilkan laporan kelas baca-saja (pakai ulang
  `HasLaporanKelasMk` + partial `laporan-kelas-tabs`).
- Tombol **Validasi** dan **Batalkan validasi** hanya ada di tampilan Lihat,
  bukan di daftar, supaya validator tidak bisa memvalidasi tanpa membuka
  laporan. Validasi membawa sidik dari laporan yang sedang ditampilkan; bila
  sidik terkini berbeda (data berubah sejak halaman dimuat), validasi ditolak
  dan laporan dimuat ulang. **[usulan]**
- **Validasi** hanya tersedia bila kelengkapan nilai `lengkap` dan
  `penugasanSelesai()`; memakai konfirmasi. **[usulan]**
- **Batalkan** menghapus baris validasi (dengan konfirmasi).
- Validator boleh mencetak kelas tervalidasi dari halaman ini.

### 7.5 Gerbang cetak

Berlaku di semua halaman yang merender partial laporan (Input Nilai, Laporan
Koordinator, Validasi Nilai):

- Tombol Cetak nonaktif bila status bukan Tervalidasi, dengan keterangan
  alasannya ("Belum divalidasi Validator Nilai" / "Data berubah sejak
  divalidasi — perlu validasi ulang").
- Di atas laporan tampil lencana status validasi.
- Browser tidak bisa dicegah mencetak lewat Ctrl+P. Karena itu bukti sah
  ditentukan oleh **isi cetakan**, bukan oleh tombol:
  - Tervalidasi → cetakan memuat blok "Divalidasi oleh [nama dengan gelar],
    [jabatan penugasan bila ada], [tanggal & jam]".
  - Selain itu → cetakan apa pun dari halaman laporan memuat tanda air
    mencolok "BELUM DIVALIDASI" (elemen khusus cetak, `@media print`), dan
    tidak memuat blok validasi.

### 7.6 Kelas lama dan Pusat Simulasi

- **Kelas yang sudah ada saat fitur diluncurkan:** gerbang berlaku untuk
  **semua kelas di semua semester**, tanpa batas semester dan tanpa
  pengecualian. Kelas lama berstatus Belum divalidasi sampai divalidasi;
  tidak ada migrasi data yang menandainya tervalidasi. Pemilih semester di
  halaman Validasi Nilai harus mencakup semester lampau. **[diputuskan]**
- **Sandbox simulasi hanya-baca:** penulisan `gugur_at` saat memuat laporan
  akan ditolak pagar `HanyaBaca`. Di sana status dihitung di memori saja
  (tanpa menulis). Kelas contoh terisi dibangun dalam keadaan tervalidasi
  supaya alur cetak bisa didemokan; peran Validator Nilai ikut tersedia di
  ruang simulasi. **[usulan]**

### 7.7 Tempat lain yang ikut disentuh

- `RolePermissionSeeder` (peran, izin, akun demo `validator`), dan cara
  menambahkannya di basis data produksi yang sudah berjalan.
- Form penugasan unit pengguna (penanda `status_validator_nilai`), ikon/warna
  peran di `PeranUnitFormFields`, pemilihan unit untuk peran ini.
- Panduan: `PeranPanduan` + halaman baru di `docs/user-manual/`, tambahan
  pada panduan Dosen Pengampu/Koordinator MK tentang gerbang cetak.
- Akun simulasi (`AkunSimulasi`) bila tes mengharuskan setiap peran punya.
- `CHANGELOG.md`.

## 8. Pengujian

Minimal (Pest/PHPUnit sesuai yang dipakai repo):

- Cakupan: validator prodi melihat kelas prodinya saja; validator fakultas
  melihat MK fakultas dan prodi di bawahnya; tanpa penanda → tidak ada akses.
- Tidak bisa memvalidasi kelas sendiri, kelas belum lengkap, kelas luar
  cakupan.
- Validasi → status Tervalidasi; ubah nilai → gugur (observer); ubah bobot
  asesmen/pemetaan/peserta/blok F → gugur saat laporan dimuat.
- Memuat laporan tanpa perubahan tidak menggugurkan (sidik stabil/kanonik).
- Tombol cetak & blok validasi/tanda air sesuai status di ketiga halaman.

## 9. Diserahkan ke pembangun

- Nama kelas/berkas, letak service sidik, bentuk kanonik persisnya.
- Cara meluncurkan peran & izin ke produksi (seeder ulang vs migrasi data).
- Tata letak lencana, blok validasi, dan tanda air.
- Apakah daftar kelas memakai tabel Filament atau kartu seperti
  `LaporanKoordinator`.
- Pemecahan menjadi beberapa PR (CONTRIBUTING: ≤ 400 baris per PR).
