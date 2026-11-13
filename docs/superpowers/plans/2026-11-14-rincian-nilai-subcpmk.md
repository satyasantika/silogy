# Rincian Nilai per Sub-CPMK Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dosen bisa merinci nilai satu asesmen per Sub-CPMK (opsional, per kelas), dan Laporan Lengkap menandai Sub-CPMK yang lemah tanpa memberi peringkat palsu.

**Architecture:** Penyimpanan nilai sudah per pivot `subcpmk_komponenpenilaian`; yang ditambah hanya penanda "asesmen ini dirinci di kelas ini" (tabel `rincian_nilai_kelas`) dan jalur baca/tulis per pivot. `nilai[kmm][komponen]` tetap berarti nilai asesmen (rata-rata berbobot pivot bila dirinci), sehingga semua pemakai lama tidak berubah. Data rincian dibawa lewat dua kunci baru (`rincian`, `nilaiRinci`) dari `DataLaporanKelasService::muat` ke halaman.

**Tech Stack:** Laravel, Filament 4, Livewire 3, Pest. Tes dijalankan di container `silogy-php`.

**Spec:** `docs/superpowers/specs/2026-11-14-rincian-nilai-subcpmk-design.md`

## Global Constraints

- Hanya asesmen yang dibebankan ke **dua Sub-CPMK atau lebih** yang bisa dirinci.
- Nilai asesmen dirinci = Σ(skor × `bobot` pivot) / Σ(`bobot` pivot) atas sub-kolom yang **terisi**, dibulatkan 2 desimal; `null` bila tak satu pun terisi.
- Menyalakan atau mematikan rincian tanpa mengubah angka **tidak boleh** menggeser nilai asesmen maupun nilai akhir mahasiswa.
- **Sidik laporan kelas tanpa rincian tidak boleh berubah** (validasi yang ada tidak boleh gugur saat rilis). Kunci baru masuk sidik hanya bila kelas punya asesmen dirinci.
- Status `Lemah` tampil di semua baris; tanda `Terlemah` hanya di antara baris `Terukur terpisah`, hanya bila ada ≥ 2 baris demikian; seri → semua ditandai.
- Ambang: `EvaluasiCplService::targetCapaianLulusan($kelasMk)` untuk rata-rata kelas dan untuk ambang per mahasiswa. Penyebut "mencapai target" = mahasiswa yang punya `hasil_subcpmk.nilai_akhir` non-null.
- Yang boleh menyalakan/mematikan rincian = yang lolos gate `inputNilai` pada kelas itu. Mode `HanyaBaca` menolak.
- Teks antarmuka berbahasa Indonesia: `Rinci per Sub-CPMK`, `Gabungkan kembali`, `Ketercapaian Sub-CPMK`, `Tercapai`, `Lemah`, `Terlemah`, `Terukur terpisah`, `Tidak dapat dibedakan`.
- Perintah tes: `docker exec silogy-php php -d memory_limit=1G vendor/bin/pest --filter=<Nama>`; seluruhnya: `docker exec silogy-php php -d memory_limit=1G artisan test --parallel --passthru-php="'-d' 'memory_limit=1G'"`. Analisis: `docker exec silogy-php php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress`.
- Jangan `git add -A` dan jangan `git stash` (ada berkas untracked milik pengguna: `PROPOSAL_BILLING_RATE.md`). Tambahkan berkas satu per satu.
- Akhiri pesan commit dengan `Co-Authored-By: Claude Fable 5.2 <noreply@anthropic.com>`.

## Review Focus

1. Sub-kolom terisi sebagian (1 dari 3): nilai asesmen dihitung dari yang terisi, tetapi kelas **tidak** dihitung lengkap dan validasi ditolak. (Task 2)
2. Pemetaan asesmen berubah setelah rincian menyala (Sub-CPMK ditambah/dilepas hingga tinggal satu): halaman tidak galat; bila tinggal < 2 pivot, penanda diabaikan dan kolom kembali tunggal. (Task 1, Task 3)
3. Menyalakan rincian saat ada perubahan nilai yang belum disimpan di layar: perubahan itu tidak hilang diam-diam (tolak dengan pesan "Simpan nilai dulu"). (Task 3)
4. Tempel dari Excel yang hanya memuat sebagian sub-kolom, atau memuat kepala kolom asesmen yang sedang dirinci: sub-kolom lain tidak terhapus; kolom asesmen dirinci tidak bisa ditimpa sebagai satu nilai. (Task 3)
5. Semua Sub-CPMK "Tidak dapat dibedakan" atau hanya satu yang "Terukur terpisah": tidak ada tanda Terlemah sama sekali. (Task 4)

---

### Task 1: Penanda rincian dan layanannya

**Files:**
- Create: `database/migrations/2026_11_14_000001_create_rincian_nilai_kelas_table.php`
- Create: `app/Modules/Penilaian/Models/RincianNilaiKelas.php`
- Create: `app/Modules/Penilaian/Services/RincianNilaiService.php`
- Modify: `app/Modules/Simulasi/Support/PencatatArtefak.php` (`MODEL_TURUNAN` + komentar CASCADE)
- Modify: `tests/Support/KelasValidasiFixture.php` (pembantu kelas dengan asesmen ber-≥2 Sub-CPMK, bila belum ada)
- Test: `tests/Feature/Penilaian/RincianNilaiServiceTest.php`

**Interfaces:**
- Produces:
  - Tabel `rincian_nilai_kelas`: `id` uuid, `kelas_mk_id` (FK `kelas_mk`, cascade), `komponen_penilaian_id` (FK `komponen_penilaian`, cascade), `dinyalakan_oleh` (FK `users`, nullable, nullOnDelete), timestamps, unik (`kelas_mk_id`, `komponen_penilaian_id`).
  - `RincianNilaiService::rincianUntukKelas(KelasMk $kelasMk): array` → `array<string komponenId, list<array{pivot_id: string, subcpmk_kode: string, bobot: float}>>`, hanya komponen berpenanda yang **masih** punya ≥ 2 pivot pada semester kelas, pivot diurutkan menurut `subcpmk_kode` lalu `pivot_id`.
  - `RincianNilaiService::bisaDirinci(KomponenPenilaian $komponen): bool`
  - `RincianNilaiService::nyalakan(KelasMk $kelasMk, KomponenPenilaian $komponen, User $oleh): void` — menolak (`ValidationException`, kunci `rincian`) bila `! bisaDirinci`; membuat penanda; lalu untuk tiap peserta menulis **nilai asesmen yang tampil** (pivot pertama menurut urutan di atas) ke semua pivot.
  - `RincianNilaiService::matikan(KelasMk $kelasMk, KomponenPenilaian $komponen): void` — untuk tiap peserta menulis `nilaiAsesmen(...)` ke semua pivot, lalu menghapus penanda.
  - `RincianNilaiService::nilaiAsesmen(array $skorPerPivot, array $bobotPerPivot): ?float` — `array<string pivotId, string|float|null>` dan `array<string pivotId, float>`.
- Penulisan nilai memakai `NilaiMahasiswa::updateOrCreate` **dengan event** (observer penggugur validasi harus jalan).

- [ ] **Step 1: Tes gagal** di `RincianNilaiServiceTest.php`:
  - `nilaiAsesmen(['a' => '80', 'b' => '60'], ['a' => 10.0, 'b' => 30.0])` → `65.0`; dengan `'b' => null` → `80.0`; semua null → `null`.
  - `nyalakan` pada asesmen ber-1 pivot melempar `ValidationException`.
  - `nyalakan` lalu `matikan` tanpa mengubah angka: nilai tiap pivot sama seperti semula, dan `kelas_mk_mahasiswa.nilai_angka` tidak berubah.
  - `nyalakan` pada kelas yang pivotnya berbeda-beda (60 dan 90, tulis langsung ke DB): sesudahnya semua pivot = nilai pivot pertama menurut urutan.
  - `rincianUntukKelas` mengabaikan penanda yang asesmennya kini tinggal 1 pivot (hapus satu pivot setelah `nyalakan`).
  - Kelas tervalidasi: `nyalakan` yang mengubah nilai pivot menggugurkan validasi (`ValidasiNilaiService::status` ≠ Tervalidasi).
- [ ] **Step 2: Jalankan**, harus gagal karena kelas belum ada.
- [ ] **Step 3: Implementasi** migrasi, model (`HasUuids`, relasi `kelasMk`, `komponenPenilaian`), layanan, dan entri `MODEL_TURUNAN`.
- [ ] **Step 4: Jalankan** tes berkas ini + `tests/Feature/Simulasi` → lulus.
- [ ] **Step 5: Commit** `feat(penilaian): penanda dan layanan rincian nilai per Sub-CPMK`.

---

### Task 2: Jalur baca, kelengkapan, dan sidik

**Files:**
- Modify: `app/Modules/Penilaian/Services/PenilaianMatrixService.php` (`pivotIdsByKomponen`, `nilaiUntukMatrix`, `kelengkapanKelas`, metode baru)
- Modify: `app/Modules/Penilaian/Services/DataLaporanKelasService.php` (`muat`, `untukSidik`)
- Modify: `app/Modules/Penilaian/Filament/Pages/Concerns/HasLaporanKelasMk.php` (properti, `resetDataLaporan`, `muatDataLaporan`, `dataUntukSidik`)
- Test: `tests/Feature/Penilaian/RincianNilaiBacaTest.php`

**Interfaces:**
- Consumes: `RincianNilaiService::rincianUntukKelas`, `::nilaiAsesmen` (Task 1).
- Produces:
  - `PenilaianMatrixService::pivotIdsByKomponen` mengembalikan pivot terurut (`subcpmk.kode`, lalu id) — pembacaan asesmen tak-dirinci jadi pasti.
  - `PenilaianMatrixService::nilaiRinciUntukMatrix(array $rows, array $rincian): array` → `array<string kmmId, array<string pivotId, string|null>>`.
  - `DataLaporanKelasService::muat` menambah kunci `rincian` (bentuk dari Task 1) dan `nilaiRinci`; untuk komponen dirinci, `nilai[kmm][komponen]` = `nilaiAsesmen` (string 2 desimal atau null).
  - `untukSidik($data)`: menambah `'rincian' => [...]` dan `'nilaiRinci' => [...]` **hanya bila** `($data['rincian'] ?? []) !== []`.
  - Properti publik trait: `public array $rincian = []`, `public array $nilaiRinci = []`.
  - `kelengkapanKelas`: untuk komponen dirinci, sel asesmen dihitung terisi hanya bila **semua** pivotnya terisi. Bentuk kembalian tidak berubah.

- [ ] **Step 1: Tes gagal**:
  - Kelas tanpa rincian: `sidik(muat($kelas))` sama dengan nilai yang dihitung sebelum perubahan — kunci dengan menghitung `SidikLaporanKelas::dariData` atas `untukSidik` yang kunci `rincian`/`nilaiRinci`-nya dibuang, dan pastikan `untukSidik` tidak memuat kedua kunci itu.
  - Kelas dengan rincian menyala: `muat()['rincian']` memuat komponen itu; setelah skor pivot diubah menjadi 80/60 dengan bobot 10/30, `muat()['nilai'][kmm][komponen]` = `'65.00'`; sidik berbeda dari sebelum rincian menyala.
  - `kelengkapanKelas`: satu sub-kolom dikosongkan → `status_kelengkapan` = `sebagian` dan `Gate::denies('validasiNilai', ...)`.
  - Pivot berbeda nilai tanpa rincian (tulis langsung): `nilaiUntukMatrix` mengembalikan nilai pivot berkode Sub-CPMK terkecil, stabil pada dua pemanggilan.
- [ ] **Step 2: Jalankan**, gagal.
- [ ] **Step 3: Implementasi.**
- [ ] **Step 4: Jalankan** berkas ini + `tests/Feature/Penilaian` + `tests/Unit/Penilaian` → lulus (tes sidik/validasi lama tidak boleh diubah).
- [ ] **Step 5: Commit** `feat(penilaian): baca nilai per Sub-CPMK, kelengkapan, dan sidik untuk asesmen dirinci`.

---

### Task 3: Halaman Input — tombol, sub-kolom, simpan, tempel

**Files:**
- Modify: `app/Modules/Penilaian/Filament/Pages/InputNilai.php`
- Modify: `resources/views/filament/modules/penilaian/pages/input-nilai.blade.php`
- Modify: `app/Modules/Penilaian/Services/InputNilaiMatrixClipboardService.php` (hanya bila perlu; utamakan memberi kolom yang sudah diurai dari halaman)
- Test: `tests/Feature/Penilaian/InputNilaiRincianTest.php`

**Interfaces:**
- Consumes: properti `rincian`, `nilaiRinci` (Task 2); `RincianNilaiService::nyalakan/matikan/bisaDirinci/nilaiAsesmen` (Task 1).
- Produces:
  - `InputNilai::rinciAsesmen(string $komponenId): void` dan `InputNilai::gabungkanAsesmen(string $komponenId): void` — keduanya `Gate::authorize('inputNilai', $kelas)`, menolak dengan notifikasi `Simpan nilai dulu sebelum mengubah rincian.` bila `adaPerubahanNilai()`, lalu `loadMatrix()`. `gabungkanAsesmen` dipanggil lewat aksi Filament `gabungkanAsesmenAction` ber-`requiresConfirmation()` dengan deskripsi bahwa skor terpisah akan hilang.
  - Sel sub-kolom terikat ke `nilaiRinci.{kmmId}.{pivotId}` (`wire:model.blur`, gaya sama dengan sel yang ada); sel nilai asesmen pada kolom dirinci hanya-baca.
  - `InputNilai::updatedNilaiRinci()`: menghitung ulang `nilai[kmm][komponen]` dari `nilaiRinci` supaya nilai asesmen dan nilai akhir di layar ikut berubah.
  - `save()`: untuk komponen dirinci menulis tiap pivot dari `nilaiRinci` (validasi 0–100 dengan kunci galat `nilaiRinci.{kmm}.{pivot}`; kosong = reset pivot itu saja), tidak fan-out; komponen lain persis seperti sekarang. `adaPerubahanNilai()` juga membandingkan `nilaiRinci` dengan snapshot-nya.
  - Salin/Tempel: kolom dirinci diurai menjadi satu kolom per pivot berlabel `<kode asesmen>/<kode Sub-CPMK>`; tempel menulis ke `nilaiRinci`. Kepala kolom yang hanya menyebut kode asesmen dirinci dilaporkan sebagai galat tempel, tidak diterapkan.
  - Tombol `Rinci per Sub-CPMK` / `Gabungkan kembali` hanya dirender bila `bolehUbahNilai` dan asesmen `bisaDirinci`; atribut `data-silogy="rinci-asesmen"`.

- [ ] **Step 1: Tes gagal** (Livewire):
  - `rinciAsesmen` memunculkan komponen di `rincian`; HTML memuat kode tiap Sub-CPMK sebagai kepala sub-kolom.
  - `set("nilaiRinci.$kmm.$pivotA", '80')`, `set(... $pivotB, '60')`, `call('save')`: DB berisi 80 dan 60; `kelas_mk_mahasiswa.nilai_angka` sesuai bobot; `hasil_subcpmk` kedua Sub-CPMK berbeda.
  - Skor 101 → galat validasi, DB tidak berubah.
  - Asesmen lain yang tak dirinci tetap fan-out seperti semula.
  - `rinciAsesmen` saat ada perubahan belum disimpan → penanda tidak dibuat, perubahan di layar utuh.
  - Pengguna tanpa hak `inputNilai` (dosen lain) → 403; mode hanya-baca → tombol tidak ada di HTML.
  - Tempel dua baris ber-kepala `NIM`, `UTS/SUB-1A` saja → hanya pivot itu berubah, pivot lain utuh; kepala `UTS` saat UTS dirinci → masuk `errors`.
  - Kelas tervalidasi: `save` dengan skor Sub-CPMK berubah → status validasi gugur.
- [ ] **Step 2: Jalankan**, gagal.
- [ ] **Step 3: Implementasi.** Ikuti gaya kepala kolom dan sel yang ada di `input-nilai.blade.php`; sub-kolom berada di bawah kepala asesmennya (colspan).
- [ ] **Step 4: Jalankan** berkas ini + `tests/Feature/Penilaian` → lulus.
- [ ] **Step 5: Commit** `feat(penilaian): input nilai per Sub-CPMK pada asesmen yang dirinci`.

---

### Task 4: Bagian laporan "Ketercapaian Sub-CPMK"

**Files:**
- Create: `app/Modules/Penilaian/Services/KetercapaianSubcpmkService.php`
- Create: `resources/views/filament/modules/penilaian/partials/tabel-ketercapaian-subcpmk.blade.php`
- Modify: `app/Modules/Penilaian/Services/DataLaporanKelasService.php` (`muat` menambah `ketercapaianSubcpmk`; **tidak** masuk `untukSidik`)
- Modify: `app/Modules/Penilaian/Filament/Pages/Concerns/HasLaporanKelasMk.php` (properti `public array $ketercapaianSubcpmk = []`)
- Modify: `resources/views/filament/modules/penilaian/partials/laporan-kelas-tabs.blade.php` (sisipkan setelah C2, judul `C3. Ketercapaian Sub-CPMK`)
- Test: `tests/Feature/Penilaian/KetercapaianSubcpmkTest.php`

**Interfaces:**
- Consumes: `rincian` (Task 2), tabel `hasil_subcpmk` (sudah dihitung `jalankanKalkulasiSinkron` di `muat`).
- Produces: `KetercapaianSubcpmkService::untukKelas(KelasMk $kelasMk, array $rincian, int $target): array` → `list<array{kode: string, nama: string|null, rata_rata: float|null, mencapai: int, dinilai: int, status: 'tercapai'|'lemah'|null, dasar: 'terpisah'|'tak_dibedakan', terlemah: bool}>`, urut kode.
  - `dasar = 'terpisah'` bila ada asesmen penyumbang yang dirinci **atau** yang hanya dipetakan ke Sub-CPMK itu (pada semester kelas); selain itu `'tak_dibedakan'`.
  - `rata_rata` = rata-rata `hasil_subcpmk.nilai_akhir` non-null peserta kelas (2 desimal); `status` null bila `rata_rata` null.
  - `terlemah`: sesuai Global Constraints.
- Partial menampilkan kolom: Sub-CPMK, Rata-rata kelas, Mahasiswa mencapai target (`7/10 (70%)`, `—` bila `dinilai` 0), Status (lencana + `Terlemah`), Dasar. Di bawah tabel, bila ada baris `tak_dibedakan`: `Sub-CPMK bertanda "Tidak dapat dibedakan" hanya diukur lewat asesmen bersama yang tidak dirinci, sehingga angkanya sama dengan Sub-CPMK lain pada asesmen itu.`

- [ ] **Step 1: Tes gagal**:
  - Tiga Sub-CPMK pada satu asesmen tak-dirinci: semua `tak_dibedakan`, tidak ada `terlemah`, status mengikuti target.
  - Asesmen yang sama dirinci dengan rata-rata 80/60/70 (target 75): semua `terpisah`; status `tercapai`/`lemah`/`lemah`; hanya yang 60 `terlemah`.
  - Seri terendah (60/60/80) → dua `terlemah`.
  - Hanya satu `terpisah` (asesmen tunggal untuk Sub-CPMK C, A dan B berbagi asesmen tak-dirinci) → tidak ada `terlemah`.
  - `mencapai`/`dinilai`: 3 mahasiswa bernilai 80, 70, null → `1/2`.
  - Render: `Livewire::test(InputNilai...)` pada tab laporan memuat `Ketercapaian Sub-CPMK` dan `Tidak dapat dibedakan`; halaman `ValidasiNilai` setelah `bukaKelas` juga memuatnya.
  - Sidik kelas tanpa rincian tetap sama dengan sebelum Task 4 (ulang asersi Task 2).
- [ ] **Step 2: Jalankan**, gagal.
- [ ] **Step 3: Implementasi.** Gaya tabel dan lencana mengikuti `tabel-ketercapaian-cpl.blade.php` (termasuk tampilan cetak dan mode malam).
- [ ] **Step 4: Jalankan** berkas ini + `tests/Feature/Penilaian` → lulus.
- [ ] **Step 5: Commit** `feat(laporan): ketercapaian Sub-CPMK dengan penanda lemah dan dasar pengukuran`.

---

### Task 5: Simulasi, panduan, catatan rilis

**Files:**
- Modify: `database/seeders/Support/SimulasiAkademikBuilder.php` (pengisian nilai acak: satu angka per (mahasiswa, asesmen) untuk semua pivotnya)
- Modify: `app/Modules/Simulasi/Services/PembangunSimulasi.php` (tandai satu asesmen contoh terisi sebagai dirinci dengan skor berbeda; `VERSI_CONTOH` 5 → 6; lakukan **sebelum** `validasiKelasContoh` supaya sidik validasi contoh cocok)
- Modify: `docs/user-manual/05-dosen-pengampu.md`, `docs/user-manual/04-koordinator-mk.md` (bagian singkat), `CHANGELOG.md`
- Modify: `scripts/tangkap-layar/adegan.mjs` (adegan `dosen-input-rincian`, akun `sim-dosen`) dan gambar hasilnya bila aplikasi dev bisa dijangkau (`APP_URL=http://localhost:8017 npm run tangkap-layar -- --adegan=dosen-input-rincian` setelah `docker exec silogy-php php artisan simulasi:kolam`); bila tidak, lewati gambar dan laporkan
- Test: `tests/Feature/Simulasi/RincianNilaiSimulasiTest.php`

**Interfaces:**
- Consumes: `RincianNilaiService`, `KetercapaianSubcpmkService`, `DataLaporanKelasService`.

- [ ] **Step 1: Tes gagal**:
  - Contoh terisi: `rincianUntukKelas` kelas contoh berisi tepat satu asesmen; `KetercapaianSubcpmkService::untukKelas` memuat sedikitnya satu baris `terpisah`.
  - Contoh terisi tetap `tervalidasi` (sidik tersimpan = sidik `muat`), dan `takDikenal` kosong.
  - Asesmen contoh yang tak dirinci: semua pivotnya bernilai sama untuk tiap mahasiswa.
  - Pembongkaran sandbox menghapus baris `rincian_nilai_kelas`-nya.
- [ ] **Step 2: Jalankan**, gagal.
- [ ] **Step 3: Implementasi** pembangun + dokumen. Panduan: cara menyalakan rincian, arti "Terlemah" dan "Tidak dapat dibedakan", dan bahwa mengubah skor menggugurkan validasi.
- [ ] **Step 4: Jalankan seluruh tes, phpstan, dan `vendor/bin/pint --test` pada berkas yang disentuh** → lulus/bersih. `npm run tangkap-layar -- --periksa` → sinkron.
- [ ] **Step 5: Commit** `feat(simulasi): contoh rincian nilai per Sub-CPMK; docs panduan dan catatan rilis`.
