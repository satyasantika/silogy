# Validasi Nilai per Kurikulum Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Validasi nilai kelas menjadi opsional per kurikulum (switch), dengan validator = pimpinan + tim kurikulum prodi (otomatis) + validator tambahan yang dipilih.

**Architecture:** Kolom `kurikulum.validasi_nilai_aktif` dan pivot `kurikulum_validator` menjadi satu-satunya sumber hak validasi. `ValidatorNilaiScope` menghitung hak langsung dari penugasan unit + pivot (tanpa salinan); `AcademicUnitUserRoleSync` memberi/mencabut peran Validator Nilai dari hasil hitung itu. Saat switch mati, gerbang cetak terbuka dan semua penulisan ke `validasi_nilai_kelas` dihentikan, baris lama dibiarkan.

**Tech Stack:** Laravel, Filament, Pest, Spatie Permission, PostgreSQL (test: sqlite/RefreshDatabase seperti test yang ada).

**Spec:** `docs/superpowers/specs/2026-11-24-validasi-nilai-per-kurikulum-design.md`

## Global Constraints

- Jalankan test: `docker exec silogy-php sh -c 'XDG_CONFIG_HOME=/tmp php -d memory_limit=1G artisan test <path>'` (batas memori bawaan 128 MB membuat suite Filament besar gagal).
- Berhak otomatis = penugasan `academic_unit_users` pada `kurikulum.academic_unit_id` itu sendiri dengan `status_pimpinan` ATAU `status_tim_kurikulum`; bukan unit induk, bukan Admin/Super Admin. Tidak ada konsep periode/nonaktif pada penugasan.
- Peran `Validator Nilai` dan permission `validasi_nilai` tidak berubah; `ValidasiNilaiPolicy::access()` tetap mensyaratkan `hasRole('Validator Nilai')`.
- Pemberian/pencabutan peran lewat pivot langsung (`roles()->syncWithoutDetaching` / `detach`), bukan `assignRole`/`hasRole` (lihat komentar kelas `AcademicUnitUserRoleSync`).
- Default `validasi_nilai_aktif = true` (kurikulum lama dan baru). Kelas tanpa kurikulum (`mk_units.kurikulum_id` null) = validasi tidak diwajibkan.
- Larangan pengampu/koordinator memvalidasi kelasnya sendiri tetap di `ValidasiNilaiPolicy::alasanTidakBisaValidasi()`, tidak dipindah.
- Saat switch mati: tidak ada `validasi()`, `batalkan()`, `gugurkan()` ataupun pengguguran lewat `periksa()`; baris lama tidak diubah.
- Kolom `academic_unit_users.status_validator_nilai` dibiarkan di basis data, tidak lagi dibaca untuk hak validasi dan tidak lagi ditulis.
- Commit hanya dilakukan setelah pengguna memintanya; langkah "Commit" di bawah dijalankan pada saat itu.

## Review Focus

Masing-masing baris ini punya test di task yang disebut.

1. Kelas dengan `mk_units.kurikulum_id` null: tidak crash, laporan bebas cetak, tidak masuk daftar validator (Task 2, Task 5).
2. Pimpinan/tim kurikulum fakultas (unit induk) dan Admin/Super Admin tidak otomatis berhak (Task 2).
3. Switch dimatikan, nilai diubah, lalu dinyalakan lagi: baris lama tidak digugurkan saat mati, dan digugurkan (status Perlu validasi ulang) pada pemuatan pertama setelah menyala karena sidik berbeda (Task 5).
4. Orang yang berhak di dua kurikulum: peran tetap ada selama satu kurikulum masih aktif, dicabut hanya bila semua mati atau haknya hilang (Task 3).
5. Koordinator MK yang juga tim kurikulum: otomatis berhak, tetapi tetap ditolak memvalidasi kelas MK yang ia koordinasi dan boleh memvalidasi MK lain (Task 2).

---

### Task 1: Skema, model, dan migrasi data

**Files:**
- Create: `database/migrations/2026_11_24_000001_tambah_validasi_nilai_ke_kurikulum.php`
- Modify: `app/Modules/Kurikulum/Models/Kurikulum.php`, `app/Modules/Kelas/Models/KelasMk.php`
- Modify (test fixture): `tests/Support/KelasValidasiFixture.php` — `siapkanKelasValidasi()` saat ini membuat `Mk`/`MkUnit` TANPA `kurikulum_id` (tidak ada `Kurikulum::factory()` dan factory MK tidak mengisinya). Ubah agar membuat/mengambil satu `Kurikulum` untuk `$prodi` (`Kurikulum::query()->firstOrCreate(['academic_unit_id' => $prodi->id, 'nama' => 'Kurikulum Uji Validasi'], ['tahun' => 2026, 'is_active' => true])`) dan mengisi `kurikulum_id` pada `Mk` dan `MkUnit` yang dibuat. Tanpa ini semua test validasi lama akan memperlakukan kelasnya sebagai "tanpa kurikulum".
- Test: `tests/Feature/Penilaian/ValidasiNilaiKurikulumSkemaTest.php`

**Interfaces:**
- Produces:
  - Kolom `kurikulum.validasi_nilai_aktif` boolean default true; cast `bool` di `Kurikulum`.
  - Tabel `kurikulum_validator` (`kurikulum_id` uuid FK cascade, `user_id` uuid FK cascade, timestamps, primary key gabungan keduanya).
  - `Kurikulum::validatorTambahan(): BelongsToMany` (ke `User`, tabel `kurikulum_validator`, `withTimestamps()`).
  - `KelasMk::validasiDiwajibkan(): bool` — true bila `mkUnit->kurikulum?->validasi_nilai_aktif === true`; false bila kurikulum null.

- [ ] **Step 1: Tulis test gagal** di `ValidasiNilaiKurikulumSkemaTest.php` (RefreshDatabase + seeder seperti `ValidatorNilaiPeranTest`):
  - `it('kurikulum baru default mewajibkan validasi')`: kurikulum dari fixture (`$kelas->mkUnit->kurikulum->fresh()->validasi_nilai_aktif`) `toBeTrue()`.
  - `it('validatorTambahan menyimpan pengguna lewat pivot')`: attach satu user, `->validatorTambahan()->count()` `toBe(1)`.
  - `it('KelasMk::validasiDiwajibkan mengikuti switch kurikulum dan false tanpa kurikulum')`: kelas dari `siapkanKelasValidasi()`; true; update switch false → false; set `mk_units.kurikulum_id` null → false.
  - `it('migrasi menyalin validator lama menjadi validator tambahan pada kurikulum di unit dan turunannya')`: buat penugasan `status_validator_nilai=true` di prodi untuk user biasa dan kosongkan pivot; jalankan `(require database_path('migrations/2026_11_24_000001_tambah_validasi_nilai_ke_kurikulum.php'))->up()` kembali pada skema yang sudah ada (migrasi harus idempoten: `Schema::hasColumn`/`hasTable` guard); pivot berisi user itu untuk tiap kurikulum prodi tersebut, dan TIDAK berisi user yang sudah pimpinan/tim kurikulum di unit kurikulum itu.

- [ ] **Step 2: Jalankan test, pastikan gagal** (kolom/relasi belum ada). Setelah fixture diubah, jalankan juga `tests/Feature/Penilaian/ValidasiNilai*Test.php` untuk memastikan fixture baru tidak merusak apa pun sebelum switch dipakai.

- [ ] **Step 3: Implementasi** migrasi (skema + penyalinan satu kali dengan guard idempoten: `insertOrIgnore`), relasi dan cast di `Kurikulum`, `validasiDiwajibkan()` di `KelasMk` (pakai `loadMissing('mkUnit.kurikulum')`). Turunan unit dihitung dengan `AcademicUnitScope::descendantIdsIncludingSelf`.

- [ ] **Step 4: Jalankan test, pastikan lolos.**

- [ ] **Step 5: Commit** — `feat(validasi): kolom switch dan pivot validator pada kurikulum`.

---

### Task 2: Cakupan dan policy dihitung dari kurikulum

**Files:**
- Modify: `app/Modules/Penilaian/Support/ValidatorNilaiScope.php`, `app/Modules/Penilaian/Policies/ValidasiNilaiPolicy.php`
- Modify (test fixture): `tests/Support/KelasValidasiFixture.php`
- Test: `tests/Feature/Penilaian/ValidatorNilaiCakupanTest.php` (tulis ulang), `tests/Unit/Modules/Penilaian/ValidasiNilaiPolicyKurikulumTest.php` (baru)

**Interfaces:**
- Consumes: Task 1 (`validatorTambahan()`, `validasi_nilai_aktif`).
- Produces (di `ValidatorNilaiScope`, hapus `unitIdsFor`):
  - `otomatisBerhakUserIds(Kurikulum $k): Collection<int,string>` — user id dengan `status_pimpinan` atau `status_tim_kurikulum` pada `$k->academic_unit_id`.
  - `berhakPadaKurikulum(User $u, Kurikulum $k): bool` — `$k->validasi_nilai_aktif` dan (otomatis berhak atau ada di `validatorTambahan`).
  - `kurikulumQuery(User $u): Builder<Kurikulum>` — kurikulum dengan switch aktif tempat `$u` berhak.
  - `kelasMkQuery(User $u): Builder<KelasMk>` — kelas yang `mkUnit.kurikulum_id` ada di `kurikulumQuery`.
  - `punyaHakValidasi(User $u): bool` — `kurikulumQuery($u)->exists()`.
  - `jabatanUntuk(User $validator, AcademicUnit $unit): ?string` — sama seperti sekarang tetapi tanpa filter `status_validator_nilai`.
  - Fixture: `jadikanValidatorKurikulum(User $user, KelasMk $kelas): void` — `syncWithoutDetaching` ke `validatorTambahan()` kurikulum kelas itu lalu `app(AcademicUnitUserRoleSync::class)->syncForUser($user->id)`.
- Policy: `lihat()`/`dalamCakupan()` memakai `kelasMkQuery` yang baru; urutan `alasanTidakBisaValidasi()` dan semua pesannya tidak berubah.

- [ ] **Step 1: Tulis test gagal**:
  - `it('pimpinan dan tim kurikulum prodi berhak otomatis, pengguna lain tidak')`: `berhakPadaKurikulum` true untuk penugasan `status_pimpinan` dan `status_tim_kurikulum` di prodi kurikulum, false untuk user tanpa penugasan.
  - `it('validator tambahan berhak, switch mati menghapus hak semua')`: user di pivot → true; set switch false → `berhakPadaKurikulum` false untuk ketiganya dan `kelasMkQuery` kosong.
  - `it('pimpinan/tim kurikulum unit induk dan admin tidak otomatis berhak')` (Review Focus 2): penugasan `status_pimpinan` di fakultas, dan user berperan Admin/Super Admin → false.
  - `it('kelas tanpa kurikulum tidak masuk cakupan dan tidak crash')` (Review Focus 1): `mk_units.kurikulum_id` null → `kelasMkQuery($validator)->count()` `toBe(0)`.
  - `it('koordinator yang juga tim kurikulum ditolak di kelas MK-nya tetapi boleh di MK lain')` (Review Focus 5): policy `validasi()` false dengan pesan `Anda koordinator mata kuliah ini.` pada kelas MK koordinasinya, true pada kelas MK lain yang lengkap.
  - Ubah test lama di `ValidatorNilaiCakupanTest.php` yang memakai `status_validator_nilai` agar memakai `jadikanValidatorKurikulum`.

- [ ] **Step 2: Jalankan test baru, pastikan gagal.**

- [ ] **Step 3: Implementasi** scope dan policy sesuai Interfaces; `HanyaBaca` tetap menolak. Semua pemanggil `unitIdsFor` (cari dengan grep) dialihkan.

- [ ] **Step 4: Jalankan** `tests/Feature/Penilaian/ValidatorNilaiCakupanTest.php`, `tests/Unit/Modules/Penilaian/ValidasiNilaiPolicyKurikulumTest.php` dan `tests/Feature/Penilaian/ValidasiNilaiHalamanTest.php`; perbaiki fixture yang masih memakai toggle lama.

- [ ] **Step 5: Commit** — `feat(validasi): hitung hak validator dari kurikulum`.

---

### Task 3: Sinkronisasi peran Validator Nilai

**Files:**
- Modify: `app/Modules/Institusi/Support/AcademicUnitUserRoleSync.php`, `app/Providers/AppServiceProvider.php`
- Create: `app/Modules/Kurikulum/Support/SinkronValidatorKurikulum.php`, `app/Modules/Kurikulum/Observers/KurikulumObserver.php`
- Test: `tests/Feature/Penilaian/ValidatorNilaiPeranTest.php` (ubah), `tests/Feature/Penilaian/ValidatorNilaiSinkronPeranTest.php` (baru)

**Interfaces:**
- Consumes: Task 2 `ValidatorNilaiScope::punyaHakValidasi`, `otomatisBerhakUserIds`.
- Produces:
  - `AcademicUnitUserRoleSync`: baris `'Validator Nilai' => 'status_validator_nilai'` dihapus dari `ROLE_COLUMNS`; `syncForUser()` memanggil metode privat baru yang memberi peran bila `ValidatorNilaiScope::punyaHakValidasi($user)`, mencabut bila tidak (mengembalikan `'granted'|'revoked'|'unchanged'` dan ikut dihitung di tally). Penugasan unit yang berubah sudah memicu sinkronisasi lewat `AcademicUnitUserObserver`.
  - `SinkronValidatorKurikulum::sinkronkan(Kurikulum $k, array $userIdsSebelumnya = []): void` — jalankan `syncForUser` untuk gabungan: `$userIdsSebelumnya`, `validatorTambahan` saat ini, dan `otomatisBerhakUserIds($k)`.
  - `KurikulumObserver::saved(Kurikulum $k)` — bila `wasChanged('validasi_nilai_aktif')` atau `wasChanged('academic_unit_id')`, panggil `sinkronkan`. Daftarkan dengan `Kurikulum::observe(...)` di `AppServiceProvider` di samping `AcademicUnitUser::observe`.

- [ ] **Step 1: Tulis test gagal** di `ValidatorNilaiSinkronPeranTest.php`:
  - `it('pimpinan prodi menerima peran saat ada kurikulum aktif dan kehilangannya saat switch mati')`.
  - `it('validator tambahan menerima peran setelah sinkronkan dan kehilangannya setelah dilepas dari pivot')`: panggil `sinkronkan($k, [$idLama])` untuk kasus pelepasan.
  - `it('orang yang berhak di dua kurikulum tetap berperan sampai keduanya mati')` (Review Focus 4).
  - `it('peran yang diberikan manual tanpa hak tetap dicabut oleh sinkronisasi')`.
  - Ubah `ValidatorNilaiPeranTest`: `'penanda penugasan memberi dan mencabut peran'` kini memakai pivot kurikulum, dan `'akun demo validator menempel pada prodi nyata dengan penanda aktif'` memeriksa bahwa akun `validator` ada di `validatorTambahan` kurikulum demo (lihat Task 6).

- [ ] **Step 2: Jalankan test, pastikan gagal.**

- [ ] **Step 3: Implementasi** sesuai Interfaces.

- [ ] **Step 4: Jalankan** kedua file test dan `tests/Feature/Institusi` (apa pun yang menguji `AcademicUnitUserRoleSync`/command `SyncAcademicUnitUserRoles`); pastikan hijau.

- [ ] **Step 5: Commit** — `feat(validasi): sinkronkan peran Validator Nilai dari hak per kurikulum`.

---

### Task 4: Form kurikulum (switch + multi-select)

**Files:**
- Modify: `app/Modules/Kurikulum/Filament/Resources/KurikulumResource.php:252` (di bawah `is_active`), `.../Pages/EditKurikulum.php`, `.../Pages/CreateKurikulum.php`
- Test: `tests/Feature/Kurikulum/KurikulumValidasiNilaiFormTest.php`

**Interfaces:**
- Consumes: Task 1 relasi, Task 3 `SinkronValidatorKurikulum::sinkronkan`.
- Form: `Toggle::make('validasi_nilai_aktif')` (label "Kurikulum ini divalidasi", `->live()`, default true, teks bantuan: pimpinan dan tim kurikulum prodi otomatis berhak memvalidasi); `Select::make('validatorTambahan')->relationship('validatorTambahan', 'full_name')->multiple()->preload()->searchable()` terlihat hanya bila toggle aktif; query opsi mengecualikan `otomatisBerhakUserIds` kurikulum itu (pada halaman create: tanpa pengecualian). Label: "Validator tambahan (mis. UPM)".
- Pages: `beforeSave()` menyimpan id `validatorTambahan` sebelumnya; `afterSave()` memanggil `sinkronkan($record, $idSebelumnya)`. `CreateKurikulum` memakai `afterCreate()`.

- [ ] **Step 1: Tulis test gagal** (Livewire/Filament, pola test form yang sudah ada di repo):
  - `it('menyimpan switch dan validator tambahan lalu memberi peran Validator Nilai')`: isi form edit dengan user tanpa peran; setelah simpan user itu di pivot dan `hasRole('Validator Nilai')` (lewat `ActiveRole::ownedRoleNames`).
  - `it('opsi multi-select tidak memuat pimpinan dan tim kurikulum prodi')`.
  - `it('mematikan switch mencabut peran dan tidak menghapus baris validasi_nilai_kelas')`: buat validasi lewat `ValidasiNilaiService::validasi`, matikan switch lewat form, baris tetap ada dengan `gugur_at` null.
  - `it('multi-select tersembunyi saat switch mati')`.

- [ ] **Step 2: Jalankan test, pastikan gagal.**

- [ ] **Step 3: Implementasi** sesuai Interfaces; otorisasi edit tetap mengikuti `KurikulumPolicy::update`.

- [ ] **Step 4: Jalankan test form dan `tests/Feature/Kurikulum`** pada file yang menyentuh `KurikulumResource`; hijau.

- [ ] **Step 5: Commit** — `feat(kurikulum): switch validasi nilai dan validator tambahan pada form kurikulum`.

---

### Task 5: Gerbang cetak, status, dan penulisan saat switch mati

**Files:**
- Modify: `app/Modules/Penilaian/Services/ValidasiNilaiService.php`, `app/Modules/Penilaian/Filament/Pages/Concerns/HasLaporanKelasMk.php:285-326`, `app/Modules/Penilaian/Filament/Pages/InputNilai.php:500`, `resources/views/filament/modules/penilaian/partials/status-validasi-nilai.blade.php` dan `laporan-kelas-tabs.blade.php`, `app/Modules/Penilaian/Observers/NilaiMahasiswaObserver.php` (bila perlu)
- Test: `tests/Feature/Penilaian/ValidasiNilaiGerbangCetakTest.php`, `ValidasiNilaiGugurTest.php`, `ValidasiNilaiServiceTest.php` (tambah kasus)

**Interfaces:**
- Consumes: Task 1 `KelasMk::validasiDiwajibkan()`.
- `ValidasiNilaiService::gugurkan()` dan `periksa()`: bila `! $kelas->validasiDiwajibkan()` — `gugurkan` tidak melakukan apa pun; `periksa` mengembalikan `StatusValidasiNilai::dari($baris)` tanpa menulis.
- Trait `HasLaporanKelasMk`: properti publik `bool $validasiDiwajibkan = true` diisi di `muatStatusValidasi()`; `laporanSesuaiValidasi()` mengembalikan true bila `! $this->validasiDiwajibkan`. Blade `status-validasi-nilai` disembunyikan bila `! $validasiDiwajibkan` dan status `belum`; jika status `tervalidasi`/`perlu_ulang` tetap tampil sebagai riwayat.
- `InputNilai::kelasTervalidasi()` menjadi `$this->validasiDiwajibkan && status === Tervalidasi` (konfirmasi "validasi akan gugur" hanya bila validasi memang berlaku).

- [ ] **Step 1: Tulis test gagal**:
  - `it('switch mati: laporan boleh cetak dan lencana status tersembunyi untuk kelas belum divalidasi')`.
  - `it('switch mati: ubah nilai tidak menggugurkan validasi lama')`: validasi, matikan switch, ubah nilai lewat `NilaiMahasiswa` → baris `validasi_nilai_kelas` tidak berubah (`gugur_at` null, `sidik` sama).
  - `it('switch mati: baris lama tetap tampil sebagai Tervalidasi')`.
  - `it('switch dinyalakan lagi: sidik berbeda digugurkan pada pemuatan pertama')` (Review Focus 3): validasi → matikan → ubah nilai → nyalakan → muat laporan → status `perlu_ulang`.
  - `it('kelas tanpa kurikulum: gerbang cetak terbuka dan tidak crash')` (Review Focus 1).
  - `it('switch mati: halaman Validasi Nilai tidak memuat kelas kurikulum itu')` (lewat `ValidatorNilaiScope::kelasMkQuery`).

- [ ] **Step 2: Jalankan test, pastikan gagal.**

- [ ] **Step 3: Implementasi** sesuai Interfaces.

- [ ] **Step 4: Jalankan** ketiga file test di atas, `ValidasiNilaiHalamanTest`, dan `tests/Feature/Penilaian/InputNilai*`; hijau.

- [ ] **Step 5: Commit** — `feat(validasi): gerbang cetak dan status mengikuti switch kurikulum`.

---

### Task 6: Hentikan toggle lama, data demo dan simulasi

**Files:**
- Modify: `app/Modules/Institusi/Filament/Resources/AcademicUnitResource/RelationManagers/AcademicUnitUsersRelationManager.php:49-52,79` (sembunyikan toggle dan kolom), `app/Modules/Auth/Filament/Resources/UserResource/Pages/ListUsers.php:264`, `app/Modules/Simulasi/Support/AkunSimulasi.php:96`, `app/Modules/Simulasi/Services/PembangunSimulasi.php:258,315-330`, `database/seeders/RolePermissionSeeder.php:246-263`
- Test: `tests/Feature/Seeders/SimulasiSistemSeederTest.php` (ubah), `tests/Feature/ImporMassalTest.php` bila menyentuh impor pengguna, `tests/Feature/Penilaian/ValidatorNilaiPeranTest.php` (kasus akun demo)

**Interfaces:**
- Consumes: Task 1–3.
- Perubahan: tidak ada lagi penulisan `status_validator_nilai`. Akun demo `validator` dan `sim-validator` ditempel sebagai `validatorTambahan` pada kurikulum aktif prodi demo/sandbox masing-masing, lalu `syncForUser`. `PembangunSimulasi::validasiKelasContoh` tetap memanggil `ValidasiNilaiService::validasi` dengan akun itu. Isian "Jabatan" tetap ada di relation manager.

- [ ] **Step 1: Tulis/ubah test**: akun demo `validator` ada di `validatorTambahan` kurikulum demo dan berperan Validator Nilai; seeder simulasi menghasilkan kelas contoh berstatus Tervalidasi seperti sebelumnya; impor pengguna dengan peran Validator Nilai tidak lagi menulis `status_validator_nilai`.

- [ ] **Step 2: Jalankan test, pastikan gagal.**

- [ ] **Step 3: Implementasi** sesuai Interfaces.

- [ ] **Step 4: Jalankan** `tests/Feature/Seeders`, `ValidatorNilaiPeranTest`, `tests/Feature/ImporMassalTest.php`; hijau.

- [ ] **Step 5: Commit** — `refactor(validasi): lepas toggle validator per unit, pindahkan data demo dan simulasi`.

---

### Task 7: Panduan, manual pengguna, dan verifikasi akhir

**Files:**
- Modify: `app/Modules/Panduan/Support/PeranPanduan.php:66` (teks peran Validator Nilai), `docs/user-manual/*.md` yang menyebut validator/validasi nilai (cari dengan grep "Validator"), teks bantuan form dari Task 4 bila perlu disesuaikan

- [ ] **Step 1:** Perbarui teks: validasi dinyalakan per kurikulum oleh tim kurikulum/admin; pimpinan dan tim kurikulum prodi otomatis berhak; validator tambahan dipilih di edit kurikulum; switch mati berarti laporan bebas cetak dan riwayat validasi tetap.
- [ ] **Step 2:** Jalankan `tests/Feature/Penilaian`, `tests/Feature/Kurikulum`, `tests/Feature/Seeders`, `tests/Unit/Modules/Penilaian`; semuanya harus hijau. Jalankan `vendor/bin/pint --test` pada file yang berubah bila repo memakainya.
- [ ] **Step 3: Commit** — `docs(validasi): panduan validasi nilai per kurikulum`.
