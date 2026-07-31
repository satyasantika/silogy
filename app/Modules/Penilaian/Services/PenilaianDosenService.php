<?php

namespace App\Modules\Penilaian\Services;

use App\Models\User;
use App\Modules\Institusi\Filament\Resources\AcademicUnitResource;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Models\Mk;
use App\Modules\Penilaian\Filament\Pages\InputNilai;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class PenilaianDosenService
{
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

    public static function ringkasanKelasHtml(Mk $mk, User $dosen, ?string $semesterId): HtmlString
    {
        $kelasList = static::kelasUntukMk($mk, $dosen, $semesterId);

        if ($kelasList->isEmpty()) {
            return new HtmlString(
                '<div class="silogy-penilaian-card__kelas-empty">Tidak ada kelas pada semester ini.</div>'
            );
        }

        $badges = $kelasList
            ->map(function (KelasMk $kelas): string {
                $ringkasan = static::ringkasanKelas($kelas);
                $url = InputNilai::getUrl(['kelas_mk_id' => $kelas->id]);
                $sudahDinilai = $ringkasan['sudah_dinilai'];
                $statusClass = $sudahDinilai
                    ? 'silogy-penilaian-card__kelas--ok'
                    : 'silogy-penilaian-card__kelas--pending';

                $keterangan = $sudahDinilai
                    ? sprintf('%d mhs · rata-rata %s', $ringkasan['jumlah_mahasiswa'], $ringkasan['rata_rata'])
                    : sprintf('%d mhs · Belum dinilai', $ringkasan['jumlah_mahasiswa']);

                return '<a href="'.e($url).'" class="silogy-penilaian-card__kelas '.$statusClass.'">'
                    .'<span class="silogy-penilaian-card__kelas-kode">'.e($ringkasan['kode_kelas']).'</span>'
                    .'<span class="silogy-penilaian-card__kelas-meta">'.e($keterangan).'</span>'
                    .'</a>';
            })
            ->implode('');

        return new HtmlString(
            '<div class="silogy-penilaian-card__kelas-list">'.$badges.'</div>'
        );
    }
}
