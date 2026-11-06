# Input Manual Berdampingan Impor Massal + Domain CPL Permen 39 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** (1) Mengembalikan tombol **Tambah (manual satu-satu)** di samping **Impor Massal** pada 7 halaman daftar; (2) mengganti domain CPL kognitif/afektif/psikomotorik menjadi **sikap/pengetahuan/keterampilan** sesuai label Permendiktisaintek 39/2025.

**Architecture:** Bagian 1 murni lapisan UI: halaman `Create*` dan rutenya masih ada (tombolnya saja yang dicabut pada commit `fdf1ca6`, `42d719b`, `f80518f`, `e1ac442`, `73b479d`, `9300a1c`, `abe2c80`), jadi cukup menambah `CreateAction::make()` ke `getHeaderActions()` dan membalik tes `assertActionDoesNotExist('create')`. Bagian 2: satu sumber kebenaran opsi domain di `CplResource::domainOptions()`, migrasi data JSON yang memetakan nilai lama → baru, dan parser impor yang menerima nilai baru plus alias lama.

**Tech Stack:** Laravel + Filament v4 + Livewire, Pest, MySQL/MariaDB (kolom `cpl.domain` JSON), dijalankan via Docker (`make test`).

**Spec:** Permohonan pengguna (percakapan 2026-11-06):
1. "settingan input profil lulusan, CPL, dll defaultnya harus input massal… Bisa tidak opsinya disediakan 2, bisa input massal dan input manual satu2?"
2. "Domain CPL bisa diubah menjadi sikap, pengetahuan dan keterampilan saja? Agar sesuai dgn label dari permen 39."

## Global Constraints

- Halaman yang tercakup ("dll" = semua yang tombol Buat-nya dulu diganti impor massal): Profil Lulusan, CPL, BoK, MK, CPMK, Sub-CPMK, Asesmen (Komponen Penilaian).
- Impor Massal dan tombol Reset tetap ada; tombol Tambah ditambahkan, bukan menggantikan.
- Kunci domain baru (disimpan di DB): `sikap`, `pengetahuan`, `keterampilan`. Label: `Sikap`, `Pengetahuan`, `Keterampilan`, urutan tampil persis itu.
- Pemetaan data lama: `afektif → sikap`, `kognitif → pengetahuan`, `psikomotorik → keterampilan`.
- Domain tetap multiselect (keputusan migrasi `2026_07_16_000002` dipertahankan) dan tetap opsional.
- Taksonomi Bloom Sub-CPMK (C/A/P, `bloom_kognitif` dst.) **tidak** diubah — itu konsep berbeda dari domain CPL.
- Pesan commit tanpa trailer `Co-Authored-By`/"Generated with" (aturan pemilik repo).
- Working tree saat ini memuat perubahan belum di-commit di `app/Modules/Kalkulasi/**` dan `app/Modules/Kurikulum/Models/Kurikulum.php` milik pekerjaan lain — jangan ikut di-`git add`; stage per berkas.

## Review Focus

1. **Spreadsheet impor lama masih berisi `kognitif,afektif`** → harus tetap diterima dan disimpan sebagai `pengetahuan,sikap` (alias), bukan ditolak. Tes di Task 3.
2. **Nilai domain campur kapital/spasi/duplikat** (`" Sikap , sikap "`) → dinormalisasi jadi `['sikap']`. Tes di Task 3.
3. **Baris CPL lama berdomain `null` atau `[]`** → migrasi tidak boleh error dan tetap `null`/`[]`. Tes di Task 2.
4. **Pengguna tanpa izin kelola** (mis. dosen biasa, akun simulasi hanya-baca) → tombol Tambah tidak tampil, sama seperti Impor Massal. Tes di Task 1.
5. **Profil Lulusan dibuat manual saat kurikulum terpilih milik prodi** → `kurikulum_id` terisi kurikulum terpilih (default form sudah ada) sehingga baris muncul di daftar. Tes di Task 1.

---

## Bagian 1 — Tombol Tambah manual

### Task 1: Tambah `CreateAction` di 7 halaman daftar

**Files:**
- Modify: `app/Modules/Kurikulum/Filament/Resources/ProfilLulusanResource/Pages/ListProfilLulusans.php` (`getHeaderActions()`)
- Modify: `app/Modules/CPL/Filament/Resources/CplResource/Pages/ListCpls.php:35-42`
- Modify: `app/Modules/BoK/Filament/Resources/BokResource/Pages/ListBoks.php:35`
- Modify: `app/Modules/MK/Filament/Resources/MkResource/Pages/ListMks.php:35`
- Modify: `app/Modules/MK/Filament/Resources/CpmkResource/Pages/ListCpmks.php:44`
- Modify: `app/Modules/MK/Filament/Resources/SubcpmkResource/Pages/ListSubcpmks.php:39`
- Modify: `app/Modules/Penilaian/Filament/Resources/KomponenPenilaianResource/Pages/ListKomponenPenilaians.php:55`
- Test (ubah): `tests/Feature/Kurikulum/ProfilLulusanResetTest.php:40`, `tests/Feature/CPL/CplResetTest.php:40`, `tests/Feature/BoK/BokResetTest.php:42`, `tests/Feature/MK/MkResetTest.php:41`, `tests/Feature/MK/CpmkResetTest.php:63`, `tests/Feature/MK/SubcpmkResetTest.php:64`, `tests/Feature/Penilaian/KomponenPenilaianResetTest.php:70`
- Test (baru): `tests/Feature/InputManualBerdampinganTest.php`

**Interfaces:**
- Consumes: halaman `Create*` yang sudah ada; `ProfilLulusanResource::bisaKelola()`; `*Resource::canCreate()`.
- Produces: aksi header bernama `create` di ketujuh halaman.

- [ ] **Step 1: Balik ketujuh tes lama.** Di tiap berkas di atas, ganti `->assertActionDoesNotExist('create')` menjadi `->assertActionExists('create')` dan ubah judul tes menjadi `'tombol tambah manual dan impor massal tampil berdampingan'`.

- [ ] **Step 2: Tulis tes baru** `tests/Feature/InputManualBerdampinganTest.php` (pola `beforeEach` salin dari `tests/Feature/Kurikulum/ProfilLulusanResetTest.php`):
  - `it('tim kurikulum dapat membuat profil lulusan manual ke kurikulum terpilih')` — `Livewire::test(CreateProfilLulusan::class)->fillForm(['kode' => 'PL-MAN-01', 'nama' => 'Profil Manual', ...field wajib lain])->call('create')->assertHasNoFormErrors()`; lalu `expect(ProfilLulusan::where('kode','PL-MAN-01')->first()->kurikulum_id)->toBe($this->kurikulum->id)`.
  - `it('tombol tambah tersembunyi bagi pengguna tanpa izin kelola')` — login sebagai pengguna berperan dosen tanpa izin `kelola_cpl`, `Livewire::test(ListCpls::class)->assertActionHidden('create')` dan `->assertActionHidden('bulkImport')` (atau `assertForbidden` bila halaman memang tak boleh diakses — pakai mana yang sesuai perilaku sekarang untuk `bulkImport`).

- [ ] **Step 3: Jalankan, pastikan gagal**
  Run: `docker compose exec app php artisan test tests/Feature/InputManualBerdampinganTest.php tests/Feature/CPL/CplResetTest.php tests/Feature/Kurikulum/ProfilLulusanResetTest.php tests/Feature/BoK/BokResetTest.php tests/Feature/MK/MkResetTest.php tests/Feature/MK/CpmkResetTest.php tests/Feature/MK/SubcpmkResetTest.php tests/Feature/Penilaian/KomponenPenilaianResetTest.php`
  Expected: FAIL pada `assertActionExists('create')`.

- [ ] **Step 4: Implementasi.** Di tiap `getHeaderActions()` sisipkan `CreateAction::make()->label('Tambah')` **sebelum** `makeImporMassalAction()` (impor `Filament\Actions\CreateAction`). Visibilitas: biarkan bawaan Filament (`canCreate()`), kecuali Profil Lulusan pakai `->visible(fn (): bool => ProfilLulusanResource::bisaKelola())` agar sama dengan impor massalnya. Empty state (`getTableEmptyStateActions()`) juga diberi `CreateAction` yang sama di samping impor massal.

- [ ] **Step 5: Jalankan ulang perintah Step 3** — Expected: PASS. Lalu `docker compose exec app php artisan test tests/Feature/ImporMassalTest.php tests/Feature/Simulasi/HanyaBacaTest.php` — Expected: PASS (akun simulasi hanya-baca tidak boleh melihat tombol Tambah; bila tes ini gagal, perbaiki visibilitas, bukan tesnya).

- [ ] **Step 6: Perbarui manual** `docs/user-manual/03-tim-kurikulum.md` (dan berkas peran koordinator MK/dosen yang menyebut "Impor Massal" untuk MK/CPMK/Sub-CPMK/Asesmen): sebutkan dua cara — **Tambah** (satu per satu) atau **Impor Massal** (banyak sekaligus, dapat diedit satu per satu sesudahnya).

- [ ] **Step 7: Commit**
```bash
git add app/Modules/Kurikulum/Filament/Resources/ProfilLulusanResource/Pages/ListProfilLulusans.php app/Modules/CPL/Filament/Resources/CplResource/Pages/ListCpls.php app/Modules/BoK/Filament/Resources/BokResource/Pages/ListBoks.php app/Modules/MK/Filament/Resources/MkResource/Pages/ListMks.php app/Modules/MK/Filament/Resources/CpmkResource/Pages/ListCpmks.php app/Modules/MK/Filament/Resources/SubcpmkResource/Pages/ListSubcpmks.php app/Modules/Penilaian/Filament/Resources/KomponenPenilaianResource/Pages/ListKomponenPenilaians.php tests/Feature/InputManualBerdampinganTest.php tests/Feature/*/*ResetTest.php docs/user-manual/
git commit -m "feat(kurikulum,mk,penilaian): tombol tambah manual kembali berdampingan dengan impor massal"
```

---

## Bagian 2 — Domain CPL Sikap/Pengetahuan/Keterampilan

### Task 2: Opsi domain baru + migrasi data

**Files:**
- Modify: `app/Modules/CPL/Filament/Resources/CplResource.php:75-82` (`domainOptions()`)
- Create: `database/migrations/2026_11_06_000001_ubah_domain_cpl_ke_sikap_pengetahuan_keterampilan.php`
- Modify: `database/factories/CplFactory.php:23-24`
- Modify: `database/seeders/Support/SimulasiAkademikBuilder.php:344,642-643`
- Test (ubah): `tests/Feature/CPL/CplDomainMultiselectTest.php` (ganti semua nilai lama ke nilai baru)
- Test (baru): `tests/Feature/CPL/MigrasiDomainCplPermen39Test.php`

**Interfaces:**
- Produces: `CplResource::domainOptions(): array<string,string>` = `['sikap' => 'Sikap', 'pengetahuan' => 'Pengetahuan', 'keterampilan' => 'Keterampilan']`; konstanta `CplResource::DOMAIN_ALIAS_LAMA = ['afektif' => 'sikap', 'kognitif' => 'pengetahuan', 'psikomotorik' => 'keterampilan']` (dipakai Task 3; migrasi menyalin petanya sendiri agar tidak bergantung pada kode aplikasi).

- [ ] **Step 1: Tulis tes migrasi** `MigrasiDomainCplPermen39Test.php`: sisipkan baris `cpl` via `DB::table` dengan domain `['kognitif','afektif']`, `['psikomotorik']`, `null`, `[]`; panggil `(require database_path('migrations/2026_11_06_000001_…php'))->up()`; assert hasilnya `['pengetahuan','sikap']`, `['keterampilan']`, `null`, `[]`. Lalu `->down()` dan assert kembali ke nilai semula.

- [ ] **Step 2: Ubah `CplDomainMultiselectTest.php`** ke nilai baru (`['pengetahuan','sikap']`, `['sikap','keterampilan']`, dst.) dan tambah `it('opsi domain hanya sikap, pengetahuan, keterampilan')` → `expect(CplResource::domainOptions())->toBe([...persis seperti Interfaces])`.

- [ ] **Step 3: Jalankan** `docker compose exec app php artisan test tests/Feature/CPL/` — Expected: FAIL.

- [ ] **Step 4: Implementasi.** Ganti `domainOptions()`; tambah konstanta `DOMAIN_ALIAS_LAMA`. Migrasi: `chunkById(500)` atas `cpl` `whereNotNull('domain')`, `json_decode` → map dengan peta lama→baru (nilai tak dikenal dibiarkan apa adanya), `array_values(array_unique(...))`, tulis balik `json_encode`; `down()` memakai peta terbalik. Factory & seeder simulasi pakai kunci baru. `CplRelationManager` tidak perlu diubah (sudah memakai `domainOptions()`).

- [ ] **Step 5: Jalankan** `docker compose exec app php artisan test tests/Feature/CPL/ tests/Feature/Seeders/SimulasiSistemSeederTest.php tests/Feature/Simulasi/SimulasiServiceTest.php` — Expected: PASS.

- [ ] **Step 6: Commit**
```bash
git add app/Modules/CPL/Filament/Resources/CplResource.php database/migrations/2026_11_06_000001_ubah_domain_cpl_ke_sikap_pengetahuan_keterampilan.php database/factories/CplFactory.php database/seeders/Support/SimulasiAkademikBuilder.php tests/Feature/CPL/
git commit -m "feat(cpl): domain cpl menjadi sikap, pengetahuan, keterampilan sesuai permen 39"
```

### Task 3: Impor massal CPL menerima domain baru + alias lama

**Files:**
- Modify: `app/Modules/CPL/Filament/Resources/CplResource/Pages/ListCpls.php:214,278-279,436-451` (`importInstructionsExtra`, validasi, `parseDomainImport`)
- Test (ubah): `tests/Feature/ImporMassalTest.php` (kasus CPL yang memakai `kognitif`/`afektif`)

**Interfaces:**
- Consumes: `CplResource::domainOptions()`, `CplResource::DOMAIN_ALIAS_LAMA` (Task 2).
- Produces: `parseDomainImport(string $raw): ?array` — mengembalikan list kunci baru unik sesuai urutan masukan, atau `null` bila ada nilai tak dikenal.

- [ ] **Step 1: Tulis/ubah tes di `ImporMassalTest.php`:**
  - `'pengetahuan,sikap'` → tersimpan `['pengetahuan','sikap']`.
  - `'kognitif,afektif'` (alias lama) → tersimpan `['pengetahuan','sikap']`.
  - `' Sikap , sikap '` → `['sikap']`.
  - `'spiritual'` → baris berstatus `invalid` dengan keterangan `'Domain harus sikap, pengetahuan, dan/atau keterampilan, dipisah koma.'`

- [ ] **Step 2: Jalankan** `docker compose exec app php artisan test tests/Feature/ImporMassalTest.php` — Expected: FAIL.

- [ ] **Step 3: Implementasi.** `parseDomainImport`: lower+trim, petakan lewat `DOMAIN_ALIAS_LAMA`, tolak bila tidak ada di `array_keys(domainOptions())`, unik. Petunjuk impor (baris 214) menjadi: `'Domain opsional: sikap, pengetahuan, dan/atau keterampilan; lebih dari satu dipisah koma (,), mis. pengetahuan,keterampilan.'` Contoh baris impor (`importExampleRows`) ikut memakai nilai baru.

- [ ] **Step 4: Jalankan** perintah Step 2 — Expected: PASS.

- [ ] **Step 5: Perbarui manual** `docs/user-manual/03-tim-kurikulum.md:101` → "…dan **Domain** (boleh lebih dari satu: sikap, pengetahuan, keterampilan — sesuai Permendiktisaintek 39/2025)." Tambah entri di `CHANGELOG.md` untuk kedua bagian.

- [ ] **Step 6: Suite penuh + statis**
  Run: `make test` lalu `docker compose exec app ./vendor/bin/phpstan analyse` dan `docker compose exec app ./vendor/bin/pint --test`
  Expected: semua hijau (jalankan suite paralel saat tidak ada suite lain berjalan — lihat catatan deadlock).

- [ ] **Step 7: Commit**
```bash
git add app/Modules/CPL/Filament/Resources/CplResource/Pages/ListCpls.php tests/Feature/ImporMassalTest.php docs/user-manual/03-tim-kurikulum.md CHANGELOG.md
git commit -m "feat(cpl): impor massal menerima domain baru dan alias kognitif/afektif/psikomotorik"
```

## Catatan deploy

Task 2 memuat migrasi data → jalankan `php artisan migrate` di produksi setelah deploy; migrasi dapat di-rollback (`down()` memetakan balik).
