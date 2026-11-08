<?php

namespace App\Modules\Kurikulum\Services;

use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalkulasi\Models\HasilCplMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use Illuminate\Support\Collection;

/**
 * Tren ketercapaian CPL antarsemester untuk grafik garis (rapat evaluasi
 * kurikulum & bukti akreditasi).
 *
 * Per semester, nilai CPL seorang mahasiswa = rerata nilai MK penyumbang
 * yang ia tempuh pada semester itu, ditimbang kontribusi MK ke CPL yang
 * DIBAGI ULANG hanya di antara MK yang dinilai. Ini sengaja berbeda dari
 * ketercapaian kumulatif di AnalisisMkProdiService (basis 100% tanpa
 * pembagian ulang): kalau basis itu dipakai per semester, CPL tampak
 * anjlok pada semester yang hanya menawarkan sebagian MK penyumbangnya.
 * Nilai CPL semester = rerata seluruh mahasiswa.
 */
class TrenCplSemesterService
{
    public function __construct(private AnalisisMkProdiService $analisis) {}

    /**
     * @param  Collection<int, string>  $mkUnitIds
     * @param  ?string  $mkId  bila diisi: versi satu MK (Laporan Koordinator) — hanya CPL & nilai dari MK ini
     * @return array{
     *     target: int,
     *     semesters: list<array{id: string, label: string}>,
     *     cpl: list<array{cpl_id: string, kode: string, data: list<float|null>}>,
     * }
     */
    public function tren(Kurikulum $kurikulum, Collection $mkUnitIds, ?string $mkId = null): array
    {
        $target = (int) ($kurikulum->target_capaian_lulusan ?? 75);
        $kosong = ['target' => $target, 'semesters' => [], 'cpl' => []];

        if ($mkUnitIds->isEmpty()) {
            return $kosong;
        }

        /** @var array<string, array{kode: string, kontribusi: array<string, float>}> $cplMap */
        $cplMap = [];

        foreach ($this->analisis->pemetaanCplMk($kurikulum, $mkUnitIds) as $cplGroup) {
            $kontribusi = collect($cplGroup['mk_rows'])
                ->when($mkId !== null, fn (Collection $rows) => $rows->where('mk_id', $mkId))
                ->mapWithKeys(fn (array $mkRow): array => [$mkRow['mk_id'] => (float) $mkRow['kontribusi']])
                ->all();

            if ($kontribusi !== []) {
                $cplMap[$cplGroup['cpl_id']] = ['kode' => $cplGroup['cpl_kode'], 'kontribusi' => $kontribusi];
            }
        }

        if ($cplMap === []) {
            return $kosong;
        }

        $hasilRows = HasilCplMk::query()
            ->whereNotNull('nilai_akhir')
            ->whereIn('mk_unit_id', $mkUnitIds)
            ->whereIn('cpl_id', array_keys($cplMap))
            ->when($mkId !== null, fn ($query) => $query->whereHas('mkUnit', fn ($q) => $q->where('mk_id', $mkId)))
            ->with(['mkUnit:id,mk_id', 'kelasMkMahasiswa:id,mahasiswa_id'])
            ->get()
            ->filter(fn (HasilCplMk $hasil): bool => $hasil->mkUnit !== null && $hasil->kelasMkMahasiswa !== null);

        if ($hasilRows->isEmpty()) {
            return $kosong;
        }

        $semesters = Semester::query()
            ->whereIn('id', $hasilRows->pluck('semester_id')->unique())
            ->orderBy('kode')
            ->get(['id', 'nama']);

        $perCplSemester = $hasilRows->groupBy(fn (HasilCplMk $hasil): string => $hasil->cpl_id.'|'.$hasil->semester_id);

        $cpl = collect($cplMap)
            ->map(fn (array $info, string $cplId): array => [
                'cpl_id' => $cplId,
                'kode' => $info['kode'],
                'data' => $semesters
                    ->map(fn (Semester $semester): ?float => $this->nilaiCplSemester(
                        $perCplSemester->get($cplId.'|'.$semester->id, collect()),
                        $info['kontribusi'],
                    ))
                    ->values()
                    ->all(),
            ])
            ->sortBy('kode', SORT_NATURAL)
            ->values()
            ->all();

        return [
            'target' => $target,
            'semesters' => $semesters
                ->map(fn (Semester $semester): array => ['id' => $semester->id, 'label' => $semester->nama])
                ->values()
                ->all(),
            'cpl' => $cpl,
        ];
    }

    /**
     * @param  Collection<int, HasilCplMk>  $rows  hasil satu CPL pada satu semester
     * @param  array<string, float>  $kontribusiPerMk
     */
    protected function nilaiCplSemester(Collection $rows, array $kontribusiPerMk): ?float
    {
        if ($rows->isEmpty()) {
            return null;
        }

        $perMahasiswa = $rows
            ->groupBy(fn (HasilCplMk $hasil): string => (string) $hasil->kelasMkMahasiswa->mahasiswa_id)
            ->map(function (Collection $baris) use ($kontribusiPerMk): float {
                $rerataPerMk = $baris
                    ->groupBy(fn (HasilCplMk $hasil): string => (string) $hasil->mkUnit->mk_id)
                    ->map(fn (Collection $samaMk): float => (float) $samaMk->avg('nilai_akhir'));

                $jumlah = 0.0;
                $totalBobot = 0.0;

                foreach ($rerataPerMk as $mkId => $rerata) {
                    $bobot = $kontribusiPerMk[$mkId] ?? 0.0;

                    if ($bobot > 0) {
                        $jumlah += $rerata * $bobot;
                        $totalBobot += $bobot;
                    }
                }

                return $totalBobot > 0 ? $jumlah / $totalBobot : (float) $rerataPerMk->avg();
            });

        return round((float) $perMahasiswa->avg(), 2);
    }
}
