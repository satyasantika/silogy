# Validasi Nilai Kelas — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Laporan Lengkap kelas hanya sah dicetak setelah divalidasi peran baru Validator Nilai; validasi gugur otomatis bila isi laporan berubah.

**Architecture:** Satu baris `validasi_nilai_kelas` per kelas menyimpan validator, waktu, dan sidik (sha256) isi laporan. Setiap halaman laporan menghitung ulang sidik saat memuat kelas dan menandai `gugur_at` bila berbeda. Cakupan validator ditentukan penanda `status_validator_nilai` pada penugasan unit, beserta unit turunannya, dicocokkan ke unit penawar kelas.

**Tech Stack:** Laravel, Filament (halaman Livewire), Spatie Permission, Pest, SQLite in-memory untuk tes.

**Spec:** `docs/superpowers/specs/2026-11-13-validasi-nilai-kelas-design.md`

## Global Constraints

- Nama peran persis `Validator Nilai`; izin persis `validasi_nilai`.
- Gerbang berlaku untuk **semua kelas di semua semester**; tidak ada migrasi yang menandai kelas lama tervalidasi.
- Nilai, bobot, dan peserta **tidak dikunci** oleh validasi.
- Semua perubahan baris `validasi_nilai_kelas` lewat model Eloquent (bukan query builder) supaya tercatat activity log.
- Tidak boleh ada penulisan basis data saat `App\Modules\Simulasi\Support\HanyaBaca::aktif()`.
- Aturan cakupan validator hanya boleh hidup di `ValidatorNilaiScope` (Task 2); tempat lain memanggilnya.
- Teks UI berbahasa Indonesia. Status tampil sebagai: `Belum divalidasi`, `Tervalidasi`, `Perlu validasi ulang`. Tanda air cetak: `BELUM DIVALIDASI`.
- Sebelum commit: `vendor/bin/pint --test` dan `vendor/bin/phpstan analyse --memory-limit=512M` lulus.
- Menjalankan tes: `docker exec silogy-php php artisan test --filter=<NamaTes>` (stack dev lokal; container bernama `silogy-php`). Seluruh tes: `docker exec silogy-php php -d memory_limit=1G artisan test --parallel --passthru-php="'-d' 'memory_limit=1G'"` (batas bawaan 128M kehabisan memori).
- Commit mengikuti Conventional Commits berbahasa Indonesia, scope `penilaian` (lihat `CONTRIBUTING.md`).

## Review Focus

1. Sidik tidak stabil: memuat laporan dua kali tanpa perubahan data harus menghasilkan sidik sama (float `80.0` vs string `"80.00"` vs int `80` tidak boleh mengubah hash) → tes di Task 3.
2. Penugasan kelas tervalidasi rusak (total bobot ≠ 100%) → laporan tidak dirender, tetapi status harus menjadi Perlu validasi ulang → tes di Task 4.
3. Pengguna multi-peran: dosen di prodi A (tanpa penanda validator di A) yang validator di prodi B tidak boleh melihat/memvalidasi kelas prodi A → tes di Task 2.
4. Data berubah antara validator membuka laporan dan menekan Validasi → ditolak, tidak ada baris tersimpan → tes di Task 6.
5. Penanda validator dicabut dari penugasan terakhir → peran `Validator Nilai` ikut tercabut dan halaman tak bisa diakses → tes di Task 1.

## File Structure

| Berkas | Tanggung jawab |
|---|---|
| `database/migrations/2026_11_13_000001_add_status_validator_nilai_to_academic_unit_users_table.php` | kolom penanda |
| `database/migrations/2026_11_13_000002_tambah_peran_validator_nilai.php` | peran & izin untuk basis data yang sudah berjalan |
| `database/migrations/2026_11_13_000003_create_validasi_nilai_kelas_table.php` | tabel validasi |
| `app/Modules/Penilaian/Models/ValidasiNilaiKelas.php` | model baris validasi |
| `app/Modules/Penilaian/Support/StatusValidasiNilai.php` | enum status + label |
| `app/Modules/Penilaian/Support/ValidatorNilaiScope.php` | satu-satunya aturan cakupan |
| `app/Modules/Penilaian/Policies/ValidasiNilaiPolicy.php` | siapa boleh lihat/validasi/batalkan |
| `app/Modules/Penilaian/Services/SidikLaporanKelas.php` | hash kanonik isi laporan |
| `app/Modules/Penilaian/Services/ValidasiNilaiService.php` | validasi, batalkan, gugurkan, periksa |
| `app/Modules/Penilaian/Filament/Pages/Concerns/HasLaporanKelasMk.php` | (ubah) sidik + status validasi saat memuat |
| `app/Modules/Penilaian/Filament/Pages/ValidasiNilai.php` + view `resources/views/filament/modules/penilaian/pages/validasi-nilai.blade.php` | halaman validator |
| `resources/views/filament/modules/penilaian/partials/laporan-kelas-tabs.blade.php` + `partials/status-validasi-nilai.blade.php` | (ubah/baru) gerbang cetak, lencana, blok validasi, tanda air |
| `tests/Support/KelasValidasiFixture.php` | fixture kelas bernilai lengkap |

---

### Task 1: Peran, izin, dan penanda penugasan

**Files:**
- Create: `database/migrations/2026_11_13_000001_add_status_validator_nilai_to_academic_unit_users_table.php`
- Create: `database/migrations/2026_11_13_000002_tambah_peran_validator_nilai.php`
- Modify: `database/seeders/RolePermissionSeeder.php` (daftar izin, peran, akun demo)
- Modify: `app/Modules/Institusi/Models/AcademicUnitUser.php` (cast)
- Modify: `app/Models/User.php:162` (`withPivot`)
- Modify: `app/Modules/Institusi/Support/AcademicUnitUserRoleSync.php` (`ROLE_COLUMNS`)
- Modify: `app/Modules/Auth/Filament/Resources/UserResource.php` (toggle ~l.198, lencana ~l.328)
- Modify: `app/Modules/Institusi/Filament/Resources/AcademicUnitResource/RelationManagers/AcademicUnitUsersRelationManager.php` (toggle l.45, kolom l.72)
- Modify: `app/Modules/Auth/Support/PeranUnitFormFields.php` (`iconForRole`, `colorForRole`)
- Test: `tests/Feature/Penilaian/ValidatorNilaiPeranTest.php`

**Interfaces:**
- Produces: kolom `academic_unit_users.status_validator_nilai` (bool, default false); peran `Validator Nilai` dengan izin `validasi_nilai`, `lihat_laporan`, `lihat_dashboard`; `ROLE_COLUMNS['Validator Nilai'] = 'status_validator_nilai'`; akun demo username `validator` pada prodi nyata pertama dengan penanda aktif, jabatan `Validator Nilai`.

- [ ] **Step 1: Tulis tes gagal**

```php
uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed([AcademicUnitSeeder::class, RolePermissionSeeder::class]));

it('seeder membuat peran Validator Nilai dengan izinnya', function () {
    $peran = Role::findByName('Validator Nilai', 'web');
    expect($peran->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(['validasi_nilai', 'lihat_laporan', 'lihat_dashboard']);
});

it('penanda penugasan memberi dan mencabut peran', function () {
    $user = User::factory()->create();
    $prodi = AcademicUnit::where('type', 'study_program')->firstOrFail();
    $penugasan = AcademicUnitUser::create(['academic_unit_id' => $prodi->id, 'user_id' => $user->id, 'status_validator_nilai' => true]);
    expect(DB::table('model_has_roles')->where('model_id', $user->id)->count())->toBe(1);

    $penugasan->update(['status_validator_nilai' => false]);
    expect(DB::table('model_has_roles')->where('model_id', $user->id)->count())->toBe(0);
});

it('migrasi peran idempoten di atas basis data yang sudah diseed', function () {
    (require database_path('migrations/2026_11_13_000002_tambah_peran_validator_nilai.php'))->up();
    expect(Role::where('name', 'Validator Nilai')->count())->toBe(1);
});
```

- [ ] **Step 2:** Jalankan `--filter=ValidatorNilaiPeranTest`; harus GAGAL (kolom/peran belum ada).
- [ ] **Step 3:** Implementasi. Migrasi 000002 memakai `firstOrCreate` untuk izin dan peran lalu `givePermissionTo` (tanpa `syncPermissions`, supaya tidak menimpa penyesuaian produksi) dan memanggil `app(PermissionRegistrar::class)->forgetCachedPermissions()`; `down()` kosong. Toggle berlabel `Validator nilai`; lencana di `labelPenugasanUnit` bertuliskan `Validator nilai`. Ikon `Heroicon::OutlinedCheckBadge`, warna `success`.
- [ ] **Step 4:** Jalankan `--filter=ValidatorNilaiPeranTest` dan `--filter=RolePermissionSeeder` dan `--filter=UserResourceTest`; semua LULUS.
- [ ] **Step 5:** Commit `feat(penilaian): peran validator nilai dan penanda penugasan unit`.

---

### Task 2: Cakupan dan otorisasi

**Files:**
- Create: `app/Modules/Penilaian/Support/ValidatorNilaiScope.php`
- Create: `app/Modules/Penilaian/Policies/ValidasiNilaiPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php:145` (tambah `Gate::define` di samping `inputNilai`)
- Create: `tests/Support/KelasValidasiFixture.php`
- Test: `tests/Feature/Penilaian/ValidatorNilaiCakupanTest.php`

**Interfaces:**
- Consumes: kolom `status_validator_nilai` (Task 1); `AcademicUnitScope::descendantIdsIncludingSelf(string): Collection`; `PenilaianMatrixService::kelengkapanKelas(KelasMk): array`; `KelasMk::penugasanSelesai(): bool`.
- Produces:
  - `ValidatorNilaiScope::unitIdsFor(User $user): Collection<int,string>` — unit berpenanda + turunannya.
  - `ValidatorNilaiScope::kelasMkQuery(User $user): Builder<KelasMk>` — kelas yang `mkUnit.academic_unit_id` ∈ `unitIdsFor`.
  - `ValidasiNilaiPolicy::access(User): bool` — `hasRole('Validator Nilai') && can('validasi_nilai')`.
  - `ValidasiNilaiPolicy::lihat(User, KelasMk): bool` — `access` dan kelas dalam cakupan.
  - `ValidasiNilaiPolicy::alasanTidakBisaValidasi(User, KelasMk): ?string` — `null` bila boleh; selain itu salah satu teks: `Kelas di luar cakupan Anda.`, `Anda dosen pengampu kelas ini.`, `Anda koordinator mata kuliah ini.`, `Penugasan asesmen belum selesai.`, `Nilai belum lengkap.` (diperiksa dalam urutan itu).
  - `ValidasiNilaiPolicy::validasi(User, KelasMk): bool` — `alasanTidakBisaValidasi(...) === null`.
  - `ValidasiNilaiPolicy::batalkanValidasi(User, KelasMk): bool` — `lihat` dan bukan dosen pengampu/koordinator kelas itu.
  - Gate: `lihatValidasiNilai`, `validasiNilai`, `batalkanValidasiNilai` (argumen `KelasMk`).
  - `siapkanKelasValidasi(User $dosen, ?AcademicUnit $prodi = null, array $atribut = []): array{kelas: KelasMk, kmm: list<KelasMkMahasiswa>, komponen: KomponenPenilaian, pivot: SubcpmkKomponenPenilaian}` — kelas dengan penugasan selesai (1 komponen bobot 100 terpetakan ke 1 Sub-CPMK ber-CPL), 2 mahasiswa, nilai terisi semua (80 dan 60). Tiru `siapkanFixtureTabLaporan()` di `tests/Feature/Penilaian/InputNilaiTabLaporanTest.php`; setiap berkas tes memuatnya dengan `require_once`.
  - `jadikanValidator(User $user, AcademicUnit $unit): void` di berkas fixture yang sama — membuat `AcademicUnitUser` berpenanda.

- [ ] **Step 1: Tulis tes gagal** (seed sama seperti Task 1 + `SemesterSeeder`, `EvaluasiSeeder`)

```php
it('validator prodi hanya mencakup kelas yang ditawarkan prodinya', function () {
    [$prodiA, $prodiB] = AcademicUnit::where('type', 'study_program')->take(2)->get()->all();
    $kelasA = siapkanKelasValidasi(User::factory()->create(), $prodiA)['kelas'];
    $kelasB = siapkanKelasValidasi(User::factory()->create(), $prodiB)['kelas'];
    $validator = User::factory()->create();
    jadikanValidator($validator, $prodiA);

    expect(ValidatorNilaiScope::kelasMkQuery($validator)->pluck('id')->all())->toBe([$kelasA->id]);
    expect(Gate::forUser($validator)->allows('lihatValidasiNilai', $kelasB))->toBeFalse();
});

it('validator fakultas mencakup prodi di bawahnya, termasuk MK milik fakultas', function () {
    $prodi = AcademicUnit::where('type', 'study_program')->firstOrFail();
    $fakultas = /* naik lewat induk sampai type = faculty */;
    $kelas = siapkanKelasValidasi(User::factory()->create(), $prodi, ['mk_pemilik' => $fakultas])['kelas'];
    $validator = User::factory()->create();
    jadikanValidator($validator, $fakultas);
    expect(Gate::forUser($validator)->allows('validasiNilai', $kelas))->toBeTrue();
});

it('penugasan tanpa penanda tidak memberi cakupan (multi-peran)', function () {
    [$prodiA, $prodiB] = AcademicUnit::where('type', 'study_program')->take(2)->get()->all();
    $kelasA = siapkanKelasValidasi(User::factory()->create(), $prodiA)['kelas'];
    $user = User::factory()->create();
    AcademicUnitUser::create(['academic_unit_id' => $prodiA->id, 'user_id' => $user->id]);
    jadikanValidator($user, $prodiB);
    expect(Gate::forUser($user)->allows('lihatValidasiNilai', $kelasA))->toBeFalse();
});

it('menolak kelas sendiri, kelas yang dikoordinatori, dan nilai belum lengkap', function () {
    $prodi = AcademicUnit::where('type', 'study_program')->firstOrFail();
    $validator = User::factory()->create();
    jadikanValidator($validator, $prodi);
    $policy = app(ValidasiNilaiPolicy::class);

    $sendiri = siapkanKelasValidasi($validator, $prodi)['kelas'];
    expect($policy->alasanTidakBisaValidasi($validator, $sendiri))->toBe('Anda dosen pengampu kelas ini.');

    $koordinasi = siapkanKelasValidasi(User::factory()->create(), $prodi, ['koordinator_mk_id' => $validator->id])['kelas'];
    expect($policy->alasanTidakBisaValidasi($validator, $koordinasi))->toBe('Anda koordinator mata kuliah ini.');

    $f = siapkanKelasValidasi(User::factory()->create(), $prodi);
    NilaiMahasiswa::where('kelas_mk_mahasiswa_id', $f['kmm'][0]->id)->first()->update(['nilai' => null]);
    expect($policy->alasanTidakBisaValidasi($validator, $f['kelas']))->toBe('Nilai belum lengkap.');
});

it('Super Admin tanpa penanda tidak boleh memvalidasi', function () { /* allows('validasiNilai') false */ });
```

Parameter fixture `mk_pemilik` mengisi `mk.academic_unit_id`; `koordinator_mk_id` mengisi kolom itu pada `kelas_mk`.

- [ ] **Step 2:** Jalankan `--filter=ValidatorNilaiCakupanTest`; GAGAL (kelas belum ada).
- [ ] **Step 3:** Implementasi sesuai Interfaces. `unitIdsFor` membaca `AcademicUnitUser` berpenanda milik user lalu menggabungkan `descendantIdsIncludingSelf` tiap unit. Metode tulis di policy (`validasi`, `batalkanValidasi`) mengembalikan `false` bila `HanyaBaca::aktif($user)`.
- [ ] **Step 4:** Jalankan lagi; LULUS.
- [ ] **Step 5:** Commit `feat(penilaian): cakupan dan otorisasi validator nilai`.

---

### Task 3: Tabel, sidik, dan layanan validasi

**Files:**
- Create: `database/migrations/2026_11_13_000003_create_validasi_nilai_kelas_table.php`
- Create: `app/Modules/Penilaian/Models/ValidasiNilaiKelas.php`
- Create: `app/Modules/Penilaian/Support/StatusValidasiNilai.php`
- Create: `app/Modules/Penilaian/Services/SidikLaporanKelas.php`
- Create: `app/Modules/Penilaian/Services/ValidasiNilaiService.php`
- Modify: `app/Modules/Kelas/Models/KelasMk.php` (relasi)
- Test: `tests/Feature/Penilaian/ValidasiNilaiServiceTest.php`, `tests/Unit/Penilaian/SidikLaporanKelasTest.php`

**Interfaces:**
- Produces:
  - Tabel `validasi_nilai_kelas`: `id` uuid PK; `kelas_mk_id` uuid unik, FK `kelas_mk` cascadeOnDelete; `validator_id` uuid FK `users` restrictOnDelete; `divalidasi_at` timestamp; `sidik` char(64); `gugur_at` timestamp nullable; timestamps.
  - `ValidasiNilaiKelas` — pola `CatatanRtlKelas` + trait `LogsSilogyActivity`; cast `divalidasi_at`, `gugur_at` → `datetime`; relasi `kelasMk()`, `validator()`.
  - `KelasMk::validasiNilai(): HasOne<ValidasiNilaiKelas>`.
  - `enum StatusValidasiNilai: string { Belum = 'belum'; Tervalidasi = 'tervalidasi'; PerluUlang = 'perlu_ulang'; }` dengan `label(): string` (teks di Global Constraints) dan `static dari(?ValidasiNilaiKelas $baris): self`.
  - `SidikLaporanKelas::dariData(array $data): string` — sha256 dari bentuk kanonik.
  - `ValidasiNilaiService::status(KelasMk): StatusValidasiNilai`
  - `ValidasiNilaiService::validasi(KelasMk, User $validator, string $sidik): ValidasiNilaiKelas` — `updateOrCreate` per `kelas_mk_id`, `divalidasi_at = now()`, `gugur_at = null`.
  - `ValidasiNilaiService::batalkan(KelasMk): void` — hapus lewat model.
  - `ValidasiNilaiService::gugurkan(KelasMk): void` — isi `gugur_at = now()` bila baris ada dan `gugur_at` kosong; tidak berbuat apa-apa bila `HanyaBaca::aktif()`.
  - `ValidasiNilaiService::periksa(KelasMk, ?string $sidikTerkini): StatusValidasiNilai` — bila baris aktif dan (`$sidikTerkini === null` atau berbeda dari `sidik`) → `gugurkan()` dan kembalikan `PerluUlang` (juga saat hanya-baca, tanpa menulis); selain itu kembalikan status tersimpan.

- [ ] **Step 1: Tulis tes gagal**

```php
// tests/Unit/Penilaian/SidikLaporanKelasTest.php
it('sidik tidak bergantung pada urutan kunci maupun tipe angka', function () {
    $s = new SidikLaporanKelas;
    $a = ['rows' => [['nim' => '1', 'nilai_angka' => 80.0]], 'targetCapaianLulusan' => 75];
    $b = ['targetCapaianLulusan' => '75', 'rows' => [['nilai_angka' => '80.00', 'nim' => '1']]];
    expect($s->dariData($a))->toBe($s->dariData($b))->toHaveLength(64);
});

it('sidik berubah bila nilai atau urutan baris berubah', function () {
    $s = new SidikLaporanKelas;
    $dasar = ['rows' => [['nim' => '1', 'nilai_angka' => 80.0], ['nim' => '2', 'nilai_angka' => 60.0]]];
    expect($s->dariData($dasar))->not->toBe($s->dariData(['rows' => [['nim' => '1', 'nilai_angka' => 81.0], ['nim' => '2', 'nilai_angka' => 60.0]]]));
    expect($s->dariData($dasar))->not->toBe($s->dariData(['rows' => array_reverse($dasar['rows'])]));
});

it('membedakan null dari nol', function () {
    $s = new SidikLaporanKelas;
    expect($s->dariData(['x' => null]))->not->toBe($s->dariData(['x' => 0]));
});
```

```php
// tests/Feature/Penilaian/ValidasiNilaiServiceTest.php
it('validasi → tervalidasi; periksa sidik sama tetap; sidik beda → perlu ulang', function () {
    $kelas = siapkanKelasValidasi(User::factory()->create())['kelas'];
    $validator = User::factory()->create();
    $layanan = app(ValidasiNilaiService::class);

    expect($layanan->status($kelas))->toBe(StatusValidasiNilai::Belum);
    $layanan->validasi($kelas, $validator, str_repeat('a', 64));
    expect($layanan->periksa($kelas, str_repeat('a', 64)))->toBe(StatusValidasiNilai::Tervalidasi);
    expect($layanan->periksa($kelas, str_repeat('b', 64)))->toBe(StatusValidasiNilai::PerluUlang);
    expect($kelas->validasiNilai()->first()->gugur_at)->not->toBeNull();
});

it('periksa dengan sidik null menggugurkan', fn () => /* PerluUlang */);
it('validasi ulang mengosongkan gugur_at dan tetap satu baris', fn () => /* count 1, gugur_at null */);
it('batalkan menghapus baris dan mencatat activity log', fn () => /* Belum; Activity subject ValidasiNilaiKelas event deleted ada */);
it('gugurkan tercatat di activity log', fn () => /* Activity event updated dengan properti gugur_at */);
```

- [ ] **Step 2:** Jalankan `--filter="SidikLaporanKelasTest|ValidasiNilaiServiceTest"`; GAGAL.
- [ ] **Step 3:** Implementasi. Bentuk kanonik `dariData`:

```php
// rekursif: array asosiatif → ksort; list → urutan dipertahankan;
// skalar numerik (is_numeric) → number_format((float) $v, 4, '.', '');
// bool → 'true'/'false'; null tetap null; string lain apa adanya.
// Lalu hash('sha256', json_encode($kanonik, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)).
```

- [ ] **Step 4:** Jalankan lagi; LULUS.
- [ ] **Step 5:** Commit `feat(penilaian): tabel, sidik, dan layanan validasi nilai kelas`.

---

### Task 4: Deteksi gugur saat laporan dimuat

**Files:**
- Modify: `app/Modules/Penilaian/Filament/Pages/Concerns/HasLaporanKelasMk.php`
- Modify: `app/Modules/Penilaian/Filament/Pages/InputNilai.php:140-151`
- Modify: `app/Modules/Penilaian/Filament/Pages/LaporanKoordinator.php` (`loadLaporan`, cabang `penugasanBelumSelesai`)
- Modify: `app/Modules/Penilaian/Observers/NilaiMahasiswaObserver.php`
- Test: `tests/Feature/Penilaian/ValidasiNilaiGugurTest.php`

**Interfaces:**
- Consumes: `SidikLaporanKelas::dariData`, `ValidasiNilaiService::periksa/gugurkan` (Task 3).
- Produces (pada trait `HasLaporanKelasMk`):
  - `public ?string $sidikLaporan = null;`
  - `public string $statusValidasi = 'belum';` (nilai enum)
  - `public ?array $infoValidasi = null;` — `array{nama: string, jabatan: ?string, waktu: string}` bila Tervalidasi; `nama` = `namaDenganGelar()` validator, `jabatan` = `jabatan` penugasan berpenanda validator pada unit kelas/induknya (boleh null), `waktu` = `divalidasi_at` format `d F Y H:i` (locale id).
  - `protected function dataUntukSidik(): array` — kunci persis: `rows`, `columns`, `kolomEvaluasi`, `nilai`, `ketercapaianCpl`, `distribusiNilaiHuruf`, `detailCplCpmkSubcpmk`, `rencanaEvaluasi`, `targetCapaianLulusan`, `rtl` (= `['baris' => $this->blokRtl['baris'], 'umum' => $this->blokRtl['umum']]`).
  - `protected function muatStatusValidasi(KelasMk $kelasMk, bool $laporanDimuat): void` — menghitung `sidikLaporan` (null bila `$laporanDimuat` false), memanggil `periksa`, mengisi `statusValidasi` dan `infoValidasi`.
  - `resetDataLaporan()` ikut mereset tiga properti di atas.
  - `muatDataLaporan()` memanggil `muatStatusValidasi($kelasMk, true)` di akhir; `simpanCatatanRtl()` memanggilnya lagi setelah `muatBlokRtl`.
  - Kedua halaman memanggil `muatStatusValidasi($kelasMk, false)` pada cabang `penugasanBelumSelesai` sebelum `return`.
  - `NilaiMahasiswaObserver::saved` dan `deleted` memanggil `ValidasiNilaiService::gugurkan($kelasMk)`.

- [ ] **Step 1: Tulis tes gagal** (login sebagai dosen pengampu fixture, peran `Dosen Pengampu`; pola login/panel tiru `InputNilaiTabLaporanTest.php`)

```php
function validasiSekarang(KelasMk $kelas, User $dosen): void {
    // muat InputNilai sebagai dosen, ambil $sidikLaporan, simpan lewat ValidasiNilaiService::validasi
}

it('memuat ulang tanpa perubahan tidak menggugurkan', function () {
    // validasiSekarang; muat InputNilai dua kali → statusValidasi 'tervalidasi', sidikLaporan sama
});

it('mengubah nilai langsung menggugurkan lewat observer', function () {
    // update NilaiMahasiswa → kelas->validasiNilai->gugur_at terisi tanpa membuka laporan
});

it('mengubah bobot pivot lewat query builder gugur saat laporan dimuat', function () {
    // DB::table('subcpmk_komponenpenilaian')->update(['bobot' => 50]) + komponen bobot tetap 100 → muat → 'perlu_ulang'
});

it('menambah peserta gugur saat laporan dimuat', fn () => /* KelasMkMahasiswa baru → 'perlu_ulang' */);
it('mengubah catatan RTL menggugurkan', fn () => /* set isianRtl.umum, call simpanCatatanRtl → 'perlu_ulang' */);

it('penugasan yang rusak menggugurkan walau laporan tidak dirender', function () {
    // ubah bobot komponen semester menjadi 90 → muat InputNilai → penugasanBelumSelesai true dan gugur_at terisi
});

it('kelas tanpa validasi berstatus belum dan infoValidasi null', fn () => /* assertSet */);
it('kelas tervalidasi mengisi infoValidasi dengan nama validator', fn () => /* assertSet('infoValidasi.nama', ...) */);
```

- [ ] **Step 2:** Jalankan `--filter=ValidasiNilaiGugurTest`; GAGAL.
- [ ] **Step 3:** Implementasi sesuai Interfaces.
- [ ] **Step 4:** Jalankan `--filter="ValidasiNilaiGugurTest|InputNilaiTabLaporanTest|LaporanKoordinatorTest|CatatanRtlServiceTest|HanyaBacaTest"`; LULUS.
- [ ] **Step 5:** Commit `feat(penilaian): validasi gugur otomatis saat isi laporan berubah`.

---

### Task 5: Gerbang cetak

**Files:**
- Create: `resources/views/filament/modules/penilaian/partials/status-validasi-nilai.blade.php`
- Modify: `resources/views/filament/modules/penilaian/partials/laporan-kelas-tabs.blade.php:146-150` (tombol) dan badan cetak `data-silogy="laporan-cetak-badan"`
- Modify: `resources/views/filament/hooks/kurikulum-cards-styles.blade.php` (CSS cetak)
- Test: `tests/Feature/Penilaian/ValidasiNilaiGerbangCetakTest.php`

**Interfaces:**
- Consumes: `$this->statusValidasi`, `$this->infoValidasi` (Task 4).
- Produces (penanda DOM yang dipakai tes dan Task 6):
  - Lencana status `data-silogy="status-validasi-nilai"` di atas sub-tab Laporan Lengkap, berisi label status.
  - Tombol Cetak: bila bukan `tervalidasi` → atribut `disabled`, tanpa `onclick`, dan teks bantu `data-silogy="alasan-cetak-terkunci"`: `Belum divalidasi Validator Nilai.` atau `Data berubah sejak divalidasi — perlu validasi ulang.`
  - Di dalam badan cetak, paling bawah: bila `tervalidasi` → `data-silogy="blok-validasi-nilai"` berisi `Divalidasi oleh {nama}`, baris jabatan bila ada, dan `{waktu}`; selain itu → `data-silogy="tanda-air-belum-divalidasi"` berteks `BELUM DIVALIDASI`.
  - CSS: tanda air `display:none` di layar; pada `@media print` tampil `position:fixed`, diagonal, besar, semi-transparan, pada **setiap** cetak halaman (bukan hanya saat `body[data-silogy-print]`), supaya Ctrl+P juga membawanya. Blok validasi tampil di layar dan cetak.

- [ ] **Step 1: Tulis tes gagal**

```php
it('kelas belum divalidasi: tombol cetak terkunci dan tanda air ada', function () {
    // Livewire InputNilai → assertSeeHtml('data-silogy="tanda-air-belum-divalidasi"')
    //   ->assertSee('Belum divalidasi Validator Nilai.')
    //   ->assertDontSeeHtml('onclick="silogyCetakLaporanLengkap()"')
    //   ->assertDontSeeHtml('data-silogy="blok-validasi-nilai"')
});
it('kelas tervalidasi: tombol aktif, blok validasi memuat nama, tanpa tanda air', fn () => /* kebalikan + assertSee('Divalidasi oleh') */);
it('kelas gugur: alasan "Data berubah sejak divalidasi" dan tanda air', fn () => /* ... */);
it('Laporan Koordinator menampilkan gerbang yang sama', fn () => /* ulangi kasus pertama di LaporanKoordinator */);
```

- [ ] **Step 2:** Jalankan `--filter=ValidasiNilaiGerbangCetakTest`; GAGAL.
- [ ] **Step 3:** Implementasi. Warna lencana memakai kelas `silogy-tone-success|warning|neutral` yang sudah ada; pastikan terbaca di mode malam.
- [ ] **Step 4:** Jalankan lagi + `--filter=Ui`; LULUS.
- [ ] **Step 5:** Buka `http://localhost:8017` sebagai dosen demo, tab Laporan → Laporan Lengkap, pratinjau cetak: tanda air terlihat. Lampirkan tangkapan layar ke PR.
- [ ] **Step 6:** Commit `feat(penilaian): gerbang cetak laporan menurut status validasi`.

---

### Task 6: Halaman Validasi Nilai

**Files:**
- Create: `app/Modules/Penilaian/Filament/Pages/ValidasiNilai.php`
- Create: `resources/views/filament/modules/penilaian/pages/validasi-nilai.blade.php`
- Modify: `app/Support/Filament/NavigationSortPeran.php` hanya bila diperlukan agar menu tampil di grup Penilaian
- Test: `tests/Feature/Penilaian/ValidasiNilaiHalamanTest.php`

**Interfaces:**
- Consumes: `ValidatorNilaiScope::kelasMkQuery`, `ValidasiNilaiPolicy`, gate Task 2; `ValidasiNilaiService`; trait `HasLaporanKelasMk` (Task 4); partial laporan (Task 5); `PenilaianSemesterTerpilih::options()/currentId()/set()`.
- Produces: halaman `ValidasiNilai` (slug `penilaian/validasi-nilai`, label navigasi `Validasi Nilai`, grup `NavigationGroupPeran::resolve('Penilaian')`), ditemukan otomatis oleh `discoverPages` modul Penilaian.
  - `canAccess()`/`shouldRegisterNavigation()`: `ValidasiNilaiPolicy::access` **dan** peran aktif bukan dosen-delegasi (`DelegasiMenu::peranAktifDosenBukanAdmin`), meniru `LaporanKoordinator`.
  - Properti: `?string $semesterTerpilihId`, `?string $kelasMkId`.
  - `getDaftarKelasProperty(): list<array{id, mk_nama, mk_kode, kode_kelas, dosen, nilai_terisi, nilai_total, status_kelengkapan, status_validasi: string, divalidasi_at: ?string}>` — dari `kelasMkQuery` pada semester terpilih, urut nama MK lalu kode kelas; status validasi dari baris tersimpan (tanpa menghitung sidik).
  - `bukaKelas(string $kelasMkId): void` — hanya bila gate `lihatValidasiNilai`; memuat laporan seperti `LaporanKoordinator::loadLaporan`.
  - `tutupKelas(): void`.
  - `validasiAction(): Action` (label `Validasi`, `requiresConfirmation`, terlihat bila `alasanTidakBisaValidasi === null` dan status ≠ tervalidasi) — menyimpan `$sidikLama = $this->sidikLaporan`, memuat ulang laporan, dan bila `$this->sidikLaporan !== $sidikLama` mengirim notifikasi bahaya `Data berubah sejak laporan dibuka. Periksa kembali sebelum memvalidasi.` tanpa menyimpan; bila sama → `ValidasiNilaiService::validasi(...)`, notifikasi sukses `Nilai kelas divalidasi`.
  - `batalkanValidasiAction(): Action` (label `Batalkan validasi`, warna danger, konfirmasi, gate `batalkanValidasiNilai`).
  - Bila validasi tidak tersedia, tampilkan alasannya (`alasanTidakBisaValidasi`) di samping judul laporan.
  - Daftar kelas hanya punya tombol `Lihat` (tanpa Validasi), sesuai spec §7.4.
  - Pemilih semester mencakup semua semester (`PenilaianSemesterTerpilih::options()`).

- [ ] **Step 1: Tulis tes gagal**

```php
it('bukan validator ditolak', fn () => /* dosen → get halaman → 403 */);
it('daftar hanya memuat kelas dalam cakupan pada semester terpilih', fn () => /* assertSee kelas A, assertDontSee kelas prodi lain */);
it('semester lampau bisa dipilih dan kelasnya bisa divalidasi', fn () => /* kelas di semester non-aktif */);
it('validasi dari tampilan laporan menyimpan baris dengan sidik laporan', function () {
    // Livewire::test(ValidasiNilai::class)->call('bukaKelas', $id)->callAction('validasi')
    // expect baris: validator_id = user, sidik = $komponen->get('sidikLaporan'), gugur_at null
});
it('menolak validasi bila data berubah sejak laporan dibuka', function () {
    // bukaKelas → ubah nilai via DB::table (tanpa event) → callAction('validasi')
    // → ValidasiNilaiKelas::count() === 0 dan assertNotified('Data berubah sejak laporan dibuka. Periksa kembali sebelum memvalidasi.')
});
it('aksi validasi tersembunyi untuk kelas sendiri dan menampilkan alasannya', fn () => /* assertActionHidden + assertSee('Anda dosen pengampu kelas ini.') */);
it('bukaKelas di luar cakupan diabaikan', fn () => /* kelasMkId tetap null */);
it('batalkan validasi menghapus baris', fn () => /* callAction('batalkanValidasi') → count 0 */);
it('validator bisa mencetak kelas tervalidasi dari halaman ini', fn () => /* assertSeeHtml onclick cetak */);
```

- [ ] **Step 2:** Jalankan `--filter=ValidasiNilaiHalamanTest`; GAGAL.
- [ ] **Step 3:** Implementasi. Tampilan daftar memakai tabel HTML sederhana bergaya sama dengan panel `laporan-koordinator.blade.php`; kolom: MK / Kelas, Dosen, Nilai, Status validasi, aksi.
- [ ] **Step 4:** Jalankan lagi + `--filter="SmokeTest|RoleSwitcherTest|PilihPeranUnitTest"`; LULUS.
- [ ] **Step 5:** Uji manual di `http://localhost:8017` dengan akun demo `validator`: validasi satu kelas, ubah nilai sebagai dosen, pastikan status menjadi Perlu validasi ulang. Lampirkan tangkapan layar.
- [ ] **Step 6:** Commit `feat(penilaian): halaman validasi nilai untuk validator`.

---

### Task 7: Pusat Simulasi

**Files:**
- Modify: `app/Modules/Simulasi/Support/AkunSimulasi.php` (`akun()`: tambah `sim-validator`, kunci baru `validator_nilai: bool` di `$buat`)
- Modify: `app/Modules/Simulasi/Services/PembangunSimulasi.php` (~l.249 penanda penugasan; langkah contoh terisi; naikkan versi bentuk contoh terisi)
- Modify: `app/Modules/Simulasi/Support/HanyaBaca.php` (`KEMAMPUAN_TULIS` tambah `validasiNilai`, `batalkanValidasiNilai`)
- Modify: `app/Modules/Panduan/Support/PeranPanduan.php` bila tombol "Coba sebagai" diturunkan dari daftar itu (lihat Task 8)
- Test: `tests/Feature/Simulasi/ValidatorNilaiSimulasiTest.php`

**Interfaces:**
- Consumes: semua Task 1–6.
- Produces: akun `sim-validator` = `$buat('Validator Nilai Program Studi (Simulasi)', 'Validator Nilai', 'Validator Nilai', validatorNilai: true)`; pada contoh terisi, setiap kelas bernilai lengkap memiliki baris `validasi_nilai_kelas` dengan `validator_id` = akun `sim-validator` sandbox itu dan `sidik` yang dihitung dari data laporan yang sama dengan yang dimuat halaman.

Catatan: sidik contoh terisi harus identik dengan yang dihitung halaman saat hanya-baca. Hitung dengan memuat trait lewat kelas pembantu kecil atau dengan memanggil layanan yang sama yang dipakai `muatDataLaporan()`; uji di bawah menjaganya.

- [ ] **Step 1: Tulis tes gagal** (tiru penyiapan di `tests/Feature/Simulasi/HanyaBacaTest.php`)

```php
it('contoh terisi: laporan kelas tampil tervalidasi tanpa penulisan', function () {
    // bangun contoh terisi, masuk sebagai sim-dosen hanya-baca, muat InputNilai
    // → assertSet('statusValidasi', 'tervalidasi'), tidak ada pengecualian HanyaBaca
});
it('hanya-baca: aksi validasi dan batalkan ditolak', fn () => /* Gate denies keduanya untuk sim-validator hanya-baca */);
it('sandbox pribadi: sim-validator bisa memvalidasi kelas prodi simulasi', fn () => /* mode tulis */);
```

- [ ] **Step 2:** Jalankan `--filter=ValidatorNilaiSimulasiTest`; GAGAL.
- [ ] **Step 3:** Implementasi.
- [ ] **Step 4:** Jalankan `--filter=Simulasi` dan `--filter=SimulasiSistemSeederTest`; LULUS (perbarui hitungan akun di tes yang mengandaikan enam peran).
- [ ] **Step 5:** Commit `feat(simulasi): peran validator nilai dan contoh terisi tervalidasi`.

---

### Task 8: Panduan dan catatan rilis

**Files:**
- Create: `docs/user-manual/09-validator-nilai.md`
- Modify: `app/Modules/Panduan/Support/PeranPanduan.php` (entri `validator-nilai` sebelum `alur-end-to-end`: berkas di atas, label `Validator Nilai`, ringkas `Memeriksa dan memvalidasi nilai kelas sebelum laporan dicetak.`, ikon `bi-patch-check`)
- Modify: `docs/user-manual/05-dosen-pengampu.md`, `04-koordinator-mk.md`, `08-simulasi-penggunaan.md`, `00-README.md`
- Modify: `CHANGELOG.md` (bagian Unreleased), `README.md` (daftar akun seed: `validator`)
- Test: `tests/Feature/Panduan/PanduanRouteTest.php` (tambah kasus)

**Interfaces:**
- Consumes: slug rute `/panduan/{peran}` dari `PeranPanduan::slug()`.

- [ ] **Step 1:** Tambah tes: `get('/panduan/validator-nilai')->assertOk()->assertSee('Validator Nilai')`. Jalankan; GAGAL.
- [ ] **Step 2:** Tulis panduan. Isi wajib `09-validator-nilai.md`: siapa yang menjadi validator dan bagaimana ditugaskan (penanda pada penugasan unit); cakupan (unit + turunan); langkah Lihat → Validasi; arti tiga status; validasi gugur otomatis bila nilai/bobot/peserta/catatan RTL berubah; tidak bisa memvalidasi kelas sendiri/yang dikoordinatori; cetakan sah memuat "Divalidasi oleh", cetakan lain bertanda air. Panduan dosen & koordinator: satu bagian pendek "Mencetak laporan memerlukan validasi". Tanpa tangkapan layar baru (manifes aset tidak diubah).
- [ ] **Step 3:** Jalankan `--filter=Panduan`; LULUS.
- [ ] **Step 4:** Jalankan seluruh tes (perintah di Global Constraints), `vendor/bin/pint --test`, `vendor/bin/phpstan analyse --memory-limit=512M`; semua lulus.
- [ ] **Step 5:** Commit `docs: panduan validator nilai dan gerbang cetak laporan`.
