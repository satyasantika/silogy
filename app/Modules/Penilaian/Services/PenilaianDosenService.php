<?php

namespace App\Modules\Penilaian\Services;

use App\Models\User;
use App\Modules\Institusi\Filament\Resources\AcademicUnitResource;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Models\Mk;
use App\Modules\Penilaian\Filament\Pages\InputNilai;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;

class PenilaianDosenService
{
    /**
     * Cache kesiapan asesmen per (mk_id|semester_id) dalam satu request.
     *
     * @var array<string, bool>
     */
    protected static array $cacheAsesmenSiap = [];

    /**
     * Rekap jumlah mata kuliah (distinct) yang diampu dosen ini per semester,
     * hanya semester yang benar-benar punya kelas — dipakai widget dashboard.
     *
     * @return Collection<int, array{semester: Semester, jumlah_mk: int<0, max>}>
     */
    public static function rekapMkPerSemester(User $dosen): Collection
    {
        $kelasPerSemester = KelasMk::query()
            ->where('dosen_pengampu_id', $dosen->id)
            ->with('mkUnit')
            ->get()
            ->groupBy('semester_id');

        if ($kelasPerSemester->isEmpty()) {
            return collect();
        }

        return Semester::query()
            ->whereIn('id', $kelasPerSemester->keys())
            ->orderByDesc('kode')
            ->get()
            ->map(fn (Semester $semester): array => [
                'semester' => $semester,
                'jumlah_mk' => $kelasPerSemester->get($semester->id, collect())
                    ->pluck('mkUnit.mk_id')
                    ->filter()
                    ->unique()
                    ->count(),
            ])
            ->values();
    }

    /**
     * Kelas MK milik dosen untuk sebuah MK, pada semester tertentu (bila diisi).
     *
     * @return Collection<int, KelasMk>
     */
    public static function kelasUntukMk(Mk $mk, User $dosen, ?string $semesterId): Collection
    {
        return KelasMk::query()
            ->where('dosen_pengampu_id', $dosen->id)
            ->whereHas('mkUnit', fn ($query) => $query->where('mk_id', $mk->id))
            ->when(
                filled($semesterId),
                fn ($query) => $query->where('semester_id', $semesterId),
            )
            ->with('mkUnit.academicUnit')
            ->withCount('kelasMkMahasiswas')
            ->orderBy('kode_kelas')
            ->get();
    }

    /**
     * Kode penawaran (mk_units.kode) yang relevan untuk kelas diampu dosen
     * pada MK + semester — bisa lebih dari satu bila penawaran berbeda.
     */
    public static function kodePenawaranUntukMk(Mk $mk, User $dosen, ?string $semesterId): string
    {
        $kodes = static::kelasUntukMk($mk, $dosen, $semesterId)
            ->pluck('mkUnit.kode')
            ->filter(fn (mixed $kode): bool => filled($kode))
            ->unique()
            ->values();

        return $kodes->isEmpty() ? '—' : $kodes->implode(', ');
    }

    /**
     * Label unit penawaran (prodi/jurusan/fakultas/univ) dari kelas diampu.
     * Pola sama dengan card koordinator: prodi/jurusan pakai "Jenis · Nama",
     * fakultas/universitas hanya nama unit.
     *
     * @return list<string>
     */
    public static function labelUnitPenawaranUntukMk(Mk $mk, User $dosen, ?string $semesterId): array
    {
        return static::kelasUntukMk($mk, $dosen, $semesterId)
            ->map(fn (KelasMk $kelas): ?AcademicUnit => $kelas->mkUnit?->academicUnit)
            ->filter(fn (mixed $unit): bool => $unit instanceof AcademicUnit)
            ->unique('id')
            ->values()
            ->map(function (AcademicUnit $unit): string {
                if (in_array($unit->type, ['study_program', 'department'], true)) {
                    [$typeLabel, $nama] = AcademicUnitResource::jenisDanNamaUntukCard($unit);

                    return $typeLabel.' · '.$nama;
                }

                $nama = trim((string) ($unit->nama_lengkap ?: $unit->nama));

                return $nama !== '' ? $nama : '—';
            })
            ->all();
    }

    /**
     * Apakah koordinator MK sudah menyelesaikan asesmen (komponen 100% +
     * terpetakan Sub-CPMK) untuk MK pada semester kelas ini.
     */
    public static function asesmenSiapUntukKelas(KelasMk $kelas): bool
    {
        $kelas->loadMissing('mkUnit');

        $mkId = $kelas->mkUnit?->mk_id;
        $semesterId = $kelas->semester_id;

        if ($mkId === null || blank($semesterId)) {
            return false;
        }

        $cacheKey = $mkId.'|'.$semesterId;

        if (array_key_exists($cacheKey, static::$cacheAsesmenSiap)) {
            return static::$cacheAsesmenSiap[$cacheKey];
        }

        return static::$cacheAsesmenSiap[$cacheKey] = $kelas->penugasanSelesai();
    }

    /**
     * Dosen punya minimal satu kelas siap dinilai (asesmen MK sudah
     * disusun koordinator) pada semester tsb — dipakai untuk menyembunyikan
     * menu/akses "Input Nilai" selama BELUM ada satu pun kelas yang siap.
     */
    public static function adaKelasSiap(User $dosen, ?string $semesterId): bool
    {
        if (blank($semesterId)) {
            return false;
        }

        $kelasList = KelasMk::query()
            ->where('dosen_pengampu_id', $dosen->id)
            ->where('semester_id', $semesterId)
            ->with('mkUnit')
            ->get();

        if ($kelasList->isEmpty()) {
            return false;
        }

        static::praMuatAsesmenSiap($kelasList);

        return $kelasList->contains(fn (KelasMk $kelas): bool => static::asesmenSiapUntukKelas($kelas));
    }

    /**
     * Pra-muat cache kesiapan asesmen untuk sekumpulan kelas (hindari N+1).
     *
     * @param  Collection<int, KelasMk>|iterable<KelasMk>  $kelasList
     */
    public static function praMuatAsesmenSiap(iterable $kelasList): void
    {
        $pairs = collect($kelasList)
            ->map(function (KelasMk $kelas): ?array {
                $kelas->loadMissing('mkUnit');
                $mkId = $kelas->mkUnit?->mk_id;

                if ($mkId === null || blank($kelas->semester_id)) {
                    return null;
                }

                return ['mk_id' => $mkId, 'semester_id' => $kelas->semester_id];
            })
            ->filter()
            ->unique(fn (array $pair): string => $pair['mk_id'].'|'.$pair['semester_id'])
            ->values();

        if ($pairs->isEmpty()) {
            return;
        }

        $mkIds = $pairs->pluck('mk_id')->unique()->values();
        $semesterIds = $pairs->pluck('semester_id')->unique()->values();

        $komponens = KomponenPenilaian::query()
            ->whereIn('mk_id', $mkIds)
            ->whereIn('semester_id', $semesterIds)
            ->withCount('subcpmkKomponens')
            ->get()
            ->groupBy(fn (KomponenPenilaian $k): string => $k->mk_id.'|'.$k->semester_id);

        foreach ($pairs as $pair) {
            $key = $pair['mk_id'].'|'.$pair['semester_id'];
            $grup = $komponens->get($key, collect());

            if ($grup->isEmpty()) {
                static::$cacheAsesmenSiap[$key] = false;

                continue;
            }

            static::$cacheAsesmenSiap[$key] = (float) $grup->sum('bobot') === 100.0
                && $grup->every(fn (KomponenPenilaian $k): bool => $k->subcpmk_komponens_count > 0);
        }
    }

    /**
     * KPI bento pengampu MK untuk semester terpilih.
     *
     * @return array{
     *     semester_label: string,
     *     jumlah_prodi: int,
     *     jumlah_mk: int,
     *     jumlah_kelas: int,
     *     kelas_siap: int,
     *     kelas_dinilai: int,
     *     kelas_menunggu_asesmen: int,
     *     mk_menunggu: int,
     *     progress_persen: int
     * }
     */
    public static function rekapKpiPengampu(User $dosen, ?string $semesterId): array
    {
        $semesterLabel = filled($semesterId)
            ? (string) (Semester::query()->find($semesterId)?->kode ?? '—')
            : '—';

        if (blank($semesterId)) {
            return [
                'semester_label' => $semesterLabel,
                'jumlah_prodi' => 0,
                'jumlah_mk' => 0,
                'jumlah_kelas' => 0,
                'kelas_siap' => 0,
                'kelas_dinilai' => 0,
                'kelas_menunggu_asesmen' => 0,
                'mk_menunggu' => 0,
                'progress_persen' => 0,
            ];
        }

        $kelasList = KelasMk::query()
            ->where('dosen_pengampu_id', $dosen->id)
            ->where('semester_id', $semesterId)
            ->with('mkUnit')
            ->withCount('kelasMkMahasiswas')
            ->get();

        static::praMuatAsesmenSiap($kelasList);

        $jumlahProdi = $kelasList->pluck('mkUnit.academic_unit_id')->filter()->unique()->count();
        $jumlahMk = $kelasList->pluck('mkUnit.mk_id')->filter()->unique()->count();
        $jumlahKelas = $kelasList->count();

        $kelasSiap = 0;
        $kelasDinilai = 0;

        foreach ($kelasList as $kelas) {
            if (! static::asesmenSiapUntukKelas($kelas)) {
                continue;
            }

            $kelasSiap++;

            if (static::ringkasanKelas($kelas)['sudah_dinilai']) {
                $kelasDinilai++;
            }
        }

        $kelasMenunggu = max(0, $jumlahKelas - $kelasSiap);

        $mkMenunggu = $kelasList
            ->groupBy(fn (KelasMk $kelas): string => (string) ($kelas->mkUnit?->mk_id ?? ''))
            ->filter(fn ($grup, string $mkId): bool => $mkId !== '')
            ->filter(fn ($grup): bool => $grup->contains(
                fn (KelasMk $kelas): bool => ! static::asesmenSiapUntukKelas($kelas),
            ))
            ->count();

        $progress = $kelasSiap > 0
            ? (int) round(($kelasDinilai / $kelasSiap) * 100)
            : 0;

        return [
            'semester_label' => $semesterLabel,
            'jumlah_prodi' => $jumlahProdi,
            'jumlah_mk' => $jumlahMk,
            'jumlah_kelas' => $jumlahKelas,
            'kelas_siap' => $kelasSiap,
            'kelas_dinilai' => $kelasDinilai,
            'kelas_menunggu_asesmen' => $kelasMenunggu,
            'mk_menunggu' => $mkMenunggu,
            'progress_persen' => $progress,
        ];
    }

    /**
     * Judul card prodi/unit penawaran (Jenis · Nama).
     */
    public static function judulKartuUnitHtml(AcademicUnit $unit): HtmlString
    {
        if (in_array($unit->type, ['study_program', 'department'], true)) {
            [$typeLabel, $nama] = AcademicUnitResource::jenisDanNamaUntukCard($unit);
            $judul = $typeLabel.' · '.$nama;
        } else {
            $nama = trim((string) ($unit->nama_lengkap ?: $unit->nama));
            $judul = $nama !== '' ? $nama : '—';
        }

        return new HtmlString(
            '<div class="silogy-penilaian-prodi__judul">'
            .'<span class="silogy-penilaian-prodi__nama">'.e($judul).'</span>'
            .'</div>'
        );
    }

    /**
     * Baris kelas diampu dosen pada satu unit penawaran + semester.
     *
     * @return Collection<int, array{
     *     kelas_mk_id: string,
     *     kode: string,
     *     nama_mk: string,
     *     kode_kelas: string,
     *     jumlah_mahasiswa: int,
     *     rata_rata: float|null,
     *     sudah_dinilai: bool,
     *     asesmen_siap: bool,
     *     url_input: string,
     *     url_laporan: string
     * }>
     */
    public static function barisKelasUntukUnit(AcademicUnit $unit, User $dosen, ?string $semesterId): Collection
    {
        $kelasList = KelasMk::query()
            ->where('dosen_pengampu_id', $dosen->id)
            ->whereHas(
                'mkUnit',
                fn ($query) => $query->where('academic_unit_id', $unit->id),
            )
            ->when(
                filled($semesterId),
                fn ($query) => $query->where('semester_id', $semesterId),
            )
            ->with(['mkUnit.mk'])
            ->withCount('kelasMkMahasiswas')
            ->get()
            ->sortBy([
                fn (KelasMk $kelas): string => (string) ($kelas->mkUnit?->kode ?? ''),
                fn (KelasMk $kelas): string => (string) ($kelas->mkUnit?->mk?->nama ?? ''),
                fn (KelasMk $kelas): string => (string) $kelas->kode_kelas,
            ])
            ->values();

        static::praMuatAsesmenSiap($kelasList);

        return $kelasList->map(function (KelasMk $kelas): array {
            $ringkasan = static::ringkasanKelas($kelas);
            $urlInput = InputNilai::getUrl(['kelas_mk_id' => $kelas->id]);
            $urlLaporan = $urlInput.(str_contains($urlInput, '?') ? '&' : '?').'tab=laporan';

            return [
                'kelas_mk_id' => $kelas->id,
                'kode' => (string) ($kelas->mkUnit?->kode ?: '—'),
                'nama_mk' => (string) ($kelas->mkUnit?->mk?->nama ?: '—'),
                'kode_kelas' => $ringkasan['kode_kelas'],
                'jumlah_mahasiswa' => $ringkasan['jumlah_mahasiswa'],
                'rata_rata' => $ringkasan['rata_rata'],
                'sudah_dinilai' => $ringkasan['sudah_dinilai'],
                'asesmen_siap' => static::asesmenSiapUntukKelas($kelas),
                'url_input' => $urlInput,
                'url_laporan' => $urlLaporan,
            ];
        });
    }

    /**
     * Tabel MK/kelas di dalam card unit penawaran.
     */
    public static function tabelKelasUnitHtml(AcademicUnit $unit, User $dosen, ?string $semesterId): HtmlString
    {
        $baris = static::barisKelasUntukUnit($unit, $dosen, $semesterId);

        if ($baris->isEmpty()) {
            return new HtmlString(
                '<div class="silogy-penilaian-prodi__empty">Tidak ada kelas pada semester ini.</div>'
            );
        }

        $rows = $baris->map(function (array $row): string {
            // Hapus hanya untuk kelas yang belum punya nilai — jangan tampilkan
            // bila sudah dinilai (rata-rata nilai sudah ada).
            $hapus = $row['sudah_dinilai']
                ? ''
                : static::tombolHapusKelasHtml($row['kelas_mk_id'], $row['kode_kelas']);

            if (! $row['asesmen_siap']) {
                $status = '<span class="silogy-penilaian-prodi__status silogy-penilaian-prodi__status--wait">'
                    .'Menunggu persiapan asesmen MK oleh koordinator'
                    .'</span>';
                $aksiUtama = '<span class="silogy-penilaian-prodi__aksi-disabled">—</span>';
                $kodeCell = e($row['kode']);
                $namaCell = e($row['nama_mk']);
            } elseif ($row['sudah_dinilai']) {
                $status = '<span class="silogy-penilaian-prodi__status silogy-penilaian-prodi__status--ok">'
                    .'<span class="silogy-penilaian-prodi__rata">'.e((string) $row['rata_rata']).'</span>'
                    .'<a href="'.e($row['url_laporan']).'" class="silogy-penilaian-prodi__laporan">Laporan</a>'
                    .'</span>';
                $aksiUtama = '<a href="'.e($row['url_input']).'" class="silogy-penilaian-prodi__aksi">Edit nilai</a>';
                $kodeCell = '<a href="'.e($row['url_input']).'" class="silogy-penilaian-prodi__link-kode">'.e($row['kode']).'</a>';
                $namaCell = '<a href="'.e($row['url_input']).'" class="silogy-penilaian-prodi__link-nama">'.e($row['nama_mk']).'</a>';
            } else {
                $status = '<a href="'.e($row['url_input']).'" class="silogy-penilaian-prodi__status silogy-penilaian-prodi__status--pending">Belum dinilai</a>';
                $aksiUtama = '<a href="'.e($row['url_input']).'" class="silogy-penilaian-prodi__aksi">Nilai</a>';
                $kodeCell = '<a href="'.e($row['url_input']).'" class="silogy-penilaian-prodi__link-kode">'.e($row['kode']).'</a>';
                $namaCell = '<a href="'.e($row['url_input']).'" class="silogy-penilaian-prodi__link-nama">'.e($row['nama_mk']).'</a>';
            }

            $aksi = '<div class="silogy-penilaian-prodi__aksi-group">'
                .$aksiUtama
                .$hapus
                .'</div>';

            return '<tr class="silogy-penilaian-prodi__row">'
                .'<td class="silogy-penilaian-prodi__td silogy-penilaian-prodi__td--kode">'.$kodeCell.'</td>'
                .'<td class="silogy-penilaian-prodi__td silogy-penilaian-prodi__td--nama">'.$namaCell.'</td>'
                .'<td class="silogy-penilaian-prodi__td silogy-penilaian-prodi__td--kelas">'.e($row['kode_kelas']).'</td>'
                .'<td class="silogy-penilaian-prodi__td silogy-penilaian-prodi__td--mhs">'.e((string) $row['jumlah_mahasiswa']).'</td>'
                .'<td class="silogy-penilaian-prodi__td silogy-penilaian-prodi__td--status">'.$status.'</td>'
                .'<td class="silogy-penilaian-prodi__td silogy-penilaian-prodi__td--aksi">'.$aksi.'</td>'
                .'</tr>';
        })->implode('');

        return new HtmlString(
            '<div class="silogy-penilaian-prodi__table-wrap">'
            .'<table class="silogy-penilaian-prodi__table">'
            .'<thead><tr>'
            .'<th scope="col">Kode</th>'
            .'<th scope="col">Nama MK</th>'
            .'<th scope="col">Kelas</th>'
            .'<th scope="col">Mhs</th>'
            .'<th scope="col">Status</th>'
            .'<th scope="col">Aksi</th>'
            .'</tr></thead>'
            .'<tbody>'.$rows.'</tbody>'
            .'</table>'
            .'</div>'
        );
    }

    /**
     * Ikon trash di kolom aksi /penilaian (hanya kelas belum dinilai).
     * Default tersembunyi; muncul saat hover baris — ikon saja, tanpa border.
     * Format wire:click mengikuti Filament getJsClickHandler (Js::from / JSON.parse).
     */
    protected static function tombolHapusKelasHtml(string $kelasMkId, string $kodeKelas): string
    {
        $label = 'Hapus kelas '.$kodeKelas;
        $args = Js::from(['kelasMkId' => $kelasMkId])->toHtml();

        return '<button type="button"'
            .' class="silogy-penilaian-prodi__hapus"'
            .' wire:click="mountAction(\'hapusKelas\', '.$args.')"'
            .' wire:key="hapus-kelas-'.e($kelasMkId).'"'
            .' title="'.e($label).'"'
            .' aria-label="'.e($label).'">'
            .'<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true" width="18" height="18">'
            .'<path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.042-2.201a51.964 51.964 0 0 0-3.32 0c-1.132.037-2.042 1.022-2.042 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>'
            .'</svg>'
            .'</button>';
    }

    /**
     * Judul card: nama MK, kode + SKS, lalu unit penawaran (prodi, dll.).
     */
    public static function judulCardHtml(Mk $mk, User $dosen, ?string $semesterId): HtmlString
    {
        $kode = static::kodePenawaranUntukMk($mk, $dosen, $semesterId);
        $sks = (int) $mk->total_sks;
        $unitLabels = static::labelUnitPenawaranUntukMk($mk, $dosen, $semesterId);

        $unitHtml = $unitLabels === []
            ? '<span class="silogy-penilaian-card__unit">—</span>'
            : collect($unitLabels)
                ->map(fn (string $label): string => '<span class="silogy-penilaian-card__unit">'.e($label).'</span>')
                ->implode('');

        return new HtmlString(
            '<div class="silogy-penilaian-card__judul">'
            .'<span class="silogy-penilaian-card__nama">'.e($mk->nama).'</span>'
            .'<span class="silogy-penilaian-card__meta">'
            .'<span class="silogy-penilaian-card__kode">'.e($kode).'</span>'
            .'<span class="silogy-penilaian-card__sks">'.e((string) $sks).' SKS</span>'
            .'</span>'
            .'<span class="silogy-penilaian-card__units">'.$unitHtml.'</span>'
            .'</div>'
        );
    }

    /**
     * @return array{kode_kelas: string, jumlah_mahasiswa: int, rata_rata: float|null, sudah_dinilai: bool}
     */
    public static function ringkasanKelas(KelasMk $kelas): array
    {
        $jumlahMahasiswa = $kelas->kelas_mk_mahasiswas_count
            ?? $kelas->kelasMkMahasiswas()->count();

        $rataRata = NilaiMahasiswa::query()
            ->whereHas(
                'kelasMkMahasiswa',
                fn ($query) => $query->where('kelas_mk_id', $kelas->id),
            )
            ->avg('nilai');

        return [
            'kode_kelas' => $kelas->kode_kelas,
            'jumlah_mahasiswa' => $jumlahMahasiswa,
            'rata_rata' => $rataRata !== null ? round((float) $rataRata, 2) : null,
            'sudah_dinilai' => $rataRata !== null,
        ];
    }

    /**
     * Ringkasan gabungan (total mahasiswa & rata-rata nilai) seluruh kelas
     * milik dosen untuk sebuah MK, pada semester tertentu (bila diisi).
     *
     * @return array{jumlah_mahasiswa: int, rata_rata: float|null, sudah_dinilai: bool}
     */
    public static function ringkasanSeluruhKelas(Mk $mk, User $dosen, ?string $semesterId): array
    {
        $kelasList = static::kelasUntukMk($mk, $dosen, $semesterId);

        if ($kelasList->isEmpty()) {
            return ['jumlah_mahasiswa' => 0, 'rata_rata' => null, 'sudah_dinilai' => false];
        }

        $jumlahMahasiswa = (int) $kelasList->sum(
            fn (KelasMk $kelas): int => $kelas->kelas_mk_mahasiswas_count ?? $kelas->kelasMkMahasiswas()->count(),
        );

        $rataRata = NilaiMahasiswa::query()
            ->whereHas(
                'kelasMkMahasiswa',
                fn ($query) => $query->whereIn('kelas_mk_id', $kelasList->pluck('id')),
            )
            ->avg('nilai');

        return [
            'jumlah_mahasiswa' => $jumlahMahasiswa,
            'rata_rata' => $rataRata !== null ? round((float) $rataRata, 2) : null,
            'sudah_dinilai' => $rataRata !== null,
        ];
    }
}
