# SILOGY — Desain Sistem (System Design)

**Siliwangi Learning Outcomes & Quality Analytics**
*Universitas Siliwangi*

---

| Item | Nilai |
|---|---|
| Sistem Operasional | SILARIS – Siliwangi Learning & Quality Assurance System |
| Paradigma | Outcome-Based Education (OBE) |
| Stack Teknologi | Laravel 13 · Filament v4 · MySQL 8 · Redis · Supervisor |
| Versi Dokumen | 6.0 |
| Tagline | *"From learning data to academic quality"* |

---

## 1. Stack Teknologi

| Lapisan | Teknologi | Versi | Keterangan |
|---|---|---|---|
| Framework Backend | Laravel | 13.x | PHP 8.3+; Modular Monolith Vertical Slice |
| Panel Admin | Filament | 4.x | Livewire 3 + Alpine.js |
| Database | MySQL | 8.0 | InnoDB; utf8mb4; UUID PK |
| Cache & Queue | Redis | 7.x | 3 queue: `cpl-calculation`, `ai-analysis`, `default` |
| Process Manager | Supervisor | 4.x | 8 worker total |
| Autentikasi | Laravel session + Sanctum | 4.x | Username ATAU email |
| RBAC | Spatie Permission | 6.x | UUID untuk `roles` & `permissions` |
| AI Integration | Google Gemini (`google-gemini-php/laravel`) | ^2.0 | `config/gemini.php` |
| Workflow State | Spatie Model States | 2.x | 7-state kurikulum, 6-state MK |
| Audit Log | Spatie Activitylog | 4.x | Polymorphic log |
| PDF Export | DomPDF | 3.x | Laporan CPL |
| Excel Export | PhpSpreadsheet | 2.x | Export nilai & CPL |

---

## 2. Arsitektur Modul (Vertical Slice) v6

```text
app/Modules/
├── Institusi/        # AcademicUnit (UUID), AcademicUnitUser
├── Auth/             # User, Policies
├── Mahasiswa/        # Mahasiswas (terpisah dari Auth)
├── Kalender/         # Semester
├── Kurikulum/        # Kurikulum, ProfilLulusan, ProfilIndikator, States/
├── CPL/              # Cpl, CplBoK, CplMk, CplProfilLulusan
├── BoK/              # Bok
├── MK/               # Mk, MkUnit, Cpmk, MkCpmk, Subcpmk
├── Kelas/            # KelasMk, KelasMkMahasiswa
├── Penilaian/        # Evaluasi, KomponenPenilaian,
│                     # SubcpmkKomponenPenilaian, NilaiMahasiswas
├── Kalkulasi/        # HasilSubcpmk, HasilCpmk, HasilCplMk,
│                     # HasilCplMkUnit, HasilCplUnit, Jobs/
├── AI/               # GeminiClientService, GeminiCostGuard, AnalisisAi, Jobs/
└── Audit/            # ActivityLog, Pelaporan
```

### 2.1 Perubahan Modul di v6

| Modul | Model / Tabel Utama | Keterangan v6 |
|---|---|---|
| Institusi | `AcademicUnit`, `AcademicUnitUser` | UUID PK; profil lengkap dipindah ke `academic_units`; +`status_tim_kurikulum`, +`jabatan` |
| MK | `Mk`, `MkUnit`, `Cpmk`, `MkCpmk`, `Subcpmk` | +`MkUnit`; `Mk` tanpa `kode/semester_ke/status`; `Subcpmk` tanpa `cpmk_id` |
| Kelas | `KelasMk`, `KelasMkMahasiswa` | `KelasMk.mk_unit_id` (bukan `mk_id`); +`koordinator_mk_id` |
| Penilaian | `Evaluasi`, `KomponenPenilaian`, `SubcpmkKomponenPenilaian`, `NilaiMahasiswas` | `bobot` default 100; `NilaiMahasiswas` pakai `subcpmk_komponenpenilaian_id` |
| Kalkulasi | `HasilCplMk`, `HasilCplMkUnit`, `HasilCplUnit` | `HasilCplMk.mk_unit_id`; +`HasilCplMkUnit`; rename `HasilCplProdi` → `HasilCplUnit` |
| AI | `AnalisisAi` | `academic_unit_id` (ex-`prodi_id`) |

---

## 3. Rantai Pivot CPL → SubCPMK → Nilai

```text
academic_units (universitas/fakultas/jurusan/study_program)
        │
        ├─ cpl  (academic_unit_id)
        │     └─ cpl_bok  (cpl_id + bok_id)
        │           └─ cpl_mk  (cpl_bok_id + mk_id)
        │                 └─ mk_cpmk  (cpl_mk_id + cpmk_id)
        │                       └─ subcpmk  (mk_cpmk_id + semester_id)
        │                             └─ subcpmk_komponenpenilaian
        │                                   └─ nilai_mahasiswas
        │
        └─ mk_units (academic_unit_id + mk_id + kode + semester_ke)
              └─ kelas_mk (mk_unit_id + semester_id + dosen_pengampu_id + koordinator_mk_id)
                    └─ kelas_mk_mahasiswa (kelas_mk_id + mahasiswa_id)
```

Rantai ini memastikan setiap nilai mahasiswa dapat ditelusuri balik hingga CPL asal, melewati BoK, MK, CPMK, dan SubCPMK secara eksplisit.

```php
// Contoh query — nilai mahasiswa → CPL
NilaiMahasiswas::with([
    'subcpmkKomponen.subcpmk.mkCpmk.cplMk.cplBoK.cpl',
    'kelasMkMahasiswa.kelasMk.mkUnit.mk',
])->where('kelas_mk_mahasiswa_id', $kmMhsId)->get();

// Sebaliknya — CPL → semua nilai mahasiswa
Cpl::find($cplId)
    ->cplBoKs                  // hasMany CplBoK
    ->flatMap->cplMks          // hasMany CplMk
    ->flatMap->mkCpmks         // hasMany MkCpmk
    ->flatMap->subcpmks        // hasMany Subcpmk
    ->flatMap->subcpmkKomponen
    ->flatMap->nilaiMahasiswas;
```

---

## 4. Workflow State (Spatie Model States)

### 4.1 Alur Kurikulum

| State | Kondisi Masuk | Transisi Berikutnya |
|---|---|---|
| draft | Kurikulum baru dibuat | profil_lulusan *(prodi)* / cpl *(non-prodi)* |
| profil_lulusan | ≥1 profil lulusan + indikator (khusus `study_program`) | cpl |
| cpl | ≥1 CPL ditetapkan (dipetakan ke profil bila prodi) | bok |
| bok | ≥1 BoK dipetakan ke CPL (`cpl_bok`) | mk |
| mk | ≥1 MK dipetakan via `cpl_mk` & `mk_units` | setdosenmk |
| setdosenmk | Semua kelas MK punya dosen pengampu | aktif |
| aktif | Kurikulum diaktifkan Admin Unit | — (terminal) |

> Untuk unit non-prodi, state `profil_lulusan` dilewati otomatis (`canTransition()` dari `draft` ke `cpl`).

### 4.2 Alur MK

| State | Kondisi | Transisi |
|---|---|---|
| draft | MK baru dibuat | cpmk |
| cpmk | ≥1 CPMK via `mk_cpmk` | sub_cpmk |
| sub_cpmk | ≥1 SubCPMK per CPMK | penugasan |
| penugasan | Komponen penilaian + pivot `subcpmk_komponenpenilaian` siap | penilaian |
| penilaian | Nilai mahasiswa diinput | selesai |
| selesai | Kalkulasi CPL selesai | — |

### 4.3 Contoh State `SetdosenmkState`

```php
<?php

namespace App\Modules\Kurikulum\States;

class SetdosenmkState extends KurikulumState
{
    public static string $name = 'setdosenmk';

    public function transitionTo(): array
    {
        return [AktifState::class];
    }

    public function canTransition(): bool
    {
        // Untuk kurikulum prodi: ambil semua mk_units di unit-nya
        $mkUnitIds = MkUnit::where('academic_unit_id', $this->model->academic_unit_id)
                           ->pluck('id');

        return KelasMk::whereIn('mk_unit_id', $mkUnitIds)
                      ->whereNull('dosen_pengampu_id')
                      ->doesntExist();
    }
}
```

---

## 5. Manajemen Akses via `academic_unit_users`

Super Admin/Admin unit menetapkan user ke `academic_units` melalui `academic_unit_users`. Satu user dapat ditetapkan ke banyak unit sekaligus (mis. dosen yang mengajar di beberapa prodi). Pengecekan akses dilakukan via Policy berbasis tipe unit dan `status_*`.

```php
<?php

namespace App\Modules\MK\Policies;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\MK\Models\Mk;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Models\MkUnit;

class MkPolicy
{
    public function view(User $user, Mk $mk): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Dosen Pengampu / Koordinator MK: cek via kelas_mk
        if ($user->hasAnyRole(['Dosen Pengampu', 'Koordinator Mata Kuliah'])) {
            return KelasMk::whereHas('mkUnit', fn ($q) =>
                       $q->where('mk_id', $mk->id))
                   ->where(function ($q) use ($user) {
                       $q->where('dosen_pengampu_id', $user->id)
                         ->orWhere('koordinator_mk_id', $user->id);
                   })->exists();
        }

        // Tim Kurikulum / Admin Unit: cek via academic_unit_users
        // MK terlihat jika unit penawaran-nya merupakan unit user
        // ATAU unit user adalah ancestor unit penawaran (cascade ke bawah)
        $userUnitIds = AcademicUnitUser::where('user_id', $user->id)
                        ->pluck('academic_unit_id');

        $mkUnitIds = MkUnit::where('mk_id', $mk->id)
                           ->pluck('academic_unit_id');

        return $userUnitIds->intersect($mkUnitIds)->isNotEmpty()
            || $userUnitIds->contains($mk->academic_unit_id);
    }
}
```

### 5.1 Tim Kurikulum & `status_tim_kurikulum`

```php
public function update(User $user, Kurikulum $kurikulum): bool
{
    $pivot = AcademicUnitUser::where('user_id', $user->id)
        ->where('academic_unit_id', $kurikulum->academic_unit_id)
        ->first();

    if (!$pivot) return false;
    return (bool) $pivot->status_tim_kurikulum;
}
```

---

## 6. Mesin Kalkulasi CPL v6

```php
<?php

namespace App\Modules\Kalkulasi\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalkulasiCplJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'cpl-calculation';

    public function __construct(
        public string $kelasMkId,
        public string $academicUnitId,
        public string $semesterId,
    ) {}

    public function handle(
        SubcpmkCalculator    $s1,
        CpmkCalculator       $s2,
        CplMkCalculator      $s3,
        CplMkUnitCalculator  $s4,
        CplUnitAggregator    $s5,
    ): void {
        // Sumber: nilai_mahasiswas.nilai via subcpmk_komponenpenilaian_id
        $s1->calculate($this->kelasMkId);                  // Tahap 1: hasil_subcpmk
        $s2->calculate($this->kelasMkId);                  // Tahap 2: hasil_cpmk
        $s3->calculate($this->kelasMkId, $this->semesterId); // Tahap 3-4: hasil_cpl_mk (per mk_unit)
        $s4->calculate($this->academicUnitId, $this->semesterId); // Tahap 4b: hasil_cpl_mk_unit (per mk_id × unit)
        $s5->aggregate($this->academicUnitId, $this->semesterId); // Tahap 5: hasil_cpl_unit
    }
}
```

### 6.1 `CplUnitAggregator`

```php
class CplUnitAggregator
{
    /**
     * Agregat CPL per academic_unit_id (semua level).
     * - Unit prodi: pakai kelas_mk lewat mk_unit_id.
     * - Unit non-prodi: berpatokan pada mk_id yang disebar ke mk_units prodi-prodi anak.
     */
    public function aggregate(string $academicUnitId, string $semesterId): void
    {
        $unit = AcademicUnit::findOrFail($academicUnitId);
        $threshold = Kurikulum::where('academic_unit_id', $academicUnitId)
                              ->where('is_active', 1)
                              ->value('target_capaian_lulusan') ?? 75;

        $cpls = Cpl::where('academic_unit_id', $academicUnitId)->get();

        foreach ($cpls as $cpl) {
            $stats = $unit->type === 'study_program'
                ? $this->fromMkUnits($cpl, $unit, $semesterId)
                : $this->fromMkAggregate($cpl, $unit, $semesterId);

            HasilCplUnit::updateOrCreate(
                [
                    'cpl_id'           => $cpl->id,
                    'academic_unit_id' => $unit->id,
                    'semester_id'      => $semesterId,
                ],
                [
                    'rata_rata'           => $stats['rata_rata'],
                    'persentase_tercapai' => $this->persen($stats, $threshold),
                    'jumlah_mahasiswa'    => $stats['jumlah'],
                ]
            );
        }
    }
}
```

---

## 7. Matriks RBAC

7 role generik (Super Admin, Admin, Tim Kurikulum, Koordinator Mata Kuliah, Dosen Pengampu, Pimpinan, Auditor Mutu); level/unit kerja ditentukan penugasan `academic_unit_users`, bukan role terpisah per tipe unit (lihat §5).

| Permission | SAdm | Adm | TimK | Korma | Dos | Pim | Audit |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| kelola_unit | ✓ | ✓ | — | — | — | — | — |
| kelola_semester | ✓ | — | — | — | — | — | — |
| kelola_evaluasi | ✓ | — | — | — | — | — | — |
| kelola_user / role / permission / impersonate_user | ✓ | — | — | — | — | — | — |
| kelola_kurikulum | — | ✓ | ✓ | — | — | — | — |
| kelola_profil_lulusan* | — | ✓ | ✓ | — | — | — | — |
| kelola_cpl / bok / mk / mk_unit | — | ✓ | ✓ | — | — | — | — |
| kelola_cpmk / subcpmk / komponen_penilaian | — | ✓ | — | ✓ | — | — | — |
| kelola_kelas | — | ✓ | ✓ | — | ✓ | — | — |
| kelola_peserta_kelas | — | ✓ | — | ✓ | — | — | — |
| setdosen_mk | — | ✓ | ✓ | — | — | — | — |
| input_nilai / import_nilai | — | — | — | — | ✓ | — | — |
| lihat_laporan | — | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| ekspor_data | — | ✓ | — | — | — | ✓ | ✓ |
| minta_analisis_ai | — | — | — | — | — | ✓ | — |
| lihat_dashboard | — | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| lihat_audit_log | ✓ | — | — | — | — | — | ✓ |
| konfigurasi_sistem | ✓ | — | — | — | — | — | — |

*Profil Lulusan hanya jika unit penugasan Tim Kurikulum bertipe `study_program`.
Sumber kebenaran: `database/seeders/RolePermissionSeeder.php`.

---

## 8. Catatan Integrasi Laravel 13

- **Bootstrap minimal.** Konfigurasi rute, middleware, dan exception handler diset di `bootstrap/app.php` (streamlined dari Laravel 11/12).
- **Model UUID.** Gunakan trait `Illuminate\Database\Eloquent\Concerns\HasUuids` dan set `$keyType='string'; public $incrementing=false;` pada model.
- **Anonymous class migrations.** Setiap file migration di v6 menggunakan format `return new class extends Migration {...};` yang sudah default sejak Laravel 9 dan tetap pada 13.
- **`casts()` method.** Atribut JSON pada `analisis_ai.konteks` dideklarasikan via method `casts(): array` (bukan property `$casts`).
- **Spatie Permission UUID.** Pastikan publish ulang migration setelah update config `permission.column_names.model_morph_key = 'model_uuid'` agar konsisten dengan PK `users`.
- **Filament Shield 4.x** untuk auto-generate policy & gate FilamentResource sesuai permission baru.

---

*Selesai — System Design SILARIS v6 selaras dengan PRD & ERD v6.*
