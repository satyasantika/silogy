<?php

namespace App\Modules\Kalkulasi\Services;

use App\Modules\CPL\Models\Cpl;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kalkulasi\Models\HasilCplMk;
use App\Modules\Kalkulasi\Models\HasilCplUnit;
use App\Modules\Kalkulasi\Support\DashboardCplCache;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Models\MkUnit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DashboardCplDataService
{
    /**
     * @return array{
     *     rows: list<array{kode: string, rata_rata: float|null, persentase_tercapai: float|null}>,
     *     target: int
     * }
     */
    public function chartData(string $academicUnitId, string $semesterId): array
    {
        return Cache::remember(
            DashboardCplCache::chartKey($academicUnitId, $semesterId),
            DashboardCplCache::TTL_SECONDS,
            fn (): array => $this->fetchChartData($academicUnitId, $semesterId),
        );
    }

    /**
     * @return list<array{
     *     mk_nama: string,
     *     mk_unit_kode: string,
     *     rata_rata: float|null,
     *     jumlah_mahasiswa: int,
     *     persentase_tercapai: float|null
     * }>
     */
    public function mkUnitTableRows(string $academicUnitId, string $semesterId, string $cplId): array
    {
        return Cache::remember(
            DashboardCplCache::mkUnitTableKey($academicUnitId, $semesterId, $cplId),
            DashboardCplCache::TTL_SECONDS,
            fn (): array => $this->fetchMkUnitTableRows($academicUnitId, $semesterId, $cplId),
        );
    }

    /**
     * @return array{
     *     rows: list<array{kode: string, rata_rata: float|null, persentase_tercapai: float|null}>,
     *     target: int
     * }
     */
    protected function fetchChartData(string $academicUnitId, string $semesterId): array
    {
        $rows = HasilCplUnit::query()
            ->join('cpl', 'cpl.id', '=', 'hasil_cpl_unit.cpl_id')
            ->where('hasil_cpl_unit.academic_unit_id', $academicUnitId)
            ->where('hasil_cpl_unit.semester_id', $semesterId)
            ->orderBy('cpl.kode')
            ->get([
                'cpl.kode',
                'hasil_cpl_unit.rata_rata',
                'hasil_cpl_unit.persentase_tercapai',
            ])
            ->map(fn ($baris): array => [
                'kode' => (string) $baris->kode,
                'rata_rata' => $baris->rata_rata !== null ? (float) $baris->rata_rata : null,
                'persentase_tercapai' => $baris->persentase_tercapai !== null
                    ? (float) $baris->persentase_tercapai
                    : null,
            ])
            ->values()
            ->all();

        $target = (int) (Kurikulum::query()
            ->where('academic_unit_id', $academicUnitId)
            ->where('is_active', true)
            ->value('target_capaian_lulusan') ?? 75);

        return [
            'rows' => $rows,
            'target' => $target,
        ];
    }

    /**
     * @return list<array{
     *     mk_nama: string,
     *     mk_unit_kode: string,
     *     rata_rata: float|null,
     *     jumlah_mahasiswa: int,
     *     persentase_tercapai: float|null
     * }>
     */
    protected function fetchMkUnitTableRows(string $academicUnitId, string $semesterId, string $cplId): array
    {
        $unitIds = AcademicUnitScope::descendantIdsIncludingSelf($academicUnitId);
        $threshold = (int) (Kurikulum::query()
            ->where('academic_unit_id', $academicUnitId)
            ->where('is_active', true)
            ->value('target_capaian_lulusan') ?? 75);

        $mkUnits = MkUnit::query()
            ->whereIn('academic_unit_id', $unitIds)
            ->with('mk')
            ->orderBy('kode')
            ->get();

        $rows = [];

        foreach ($mkUnits as $mkUnit) {
            $hasilRows = HasilCplMk::query()
                ->where('cpl_id', $cplId)
                ->where('mk_unit_id', $mkUnit->id)
                ->where('semester_id', $semesterId)
                ->whereNotNull('nilai_akhir')
                ->with('kelasMkMahasiswa')
                ->get();

            if ($hasilRows->isEmpty()) {
                continue;
            }

            $rataRata = round((float) $hasilRows->avg('nilai_akhir'), 2);

            $perMahasiswa = $hasilRows->groupBy(
                fn (HasilCplMk $hasil): string => (string) $hasil->kelasMkMahasiswa?->mahasiswa_id,
            );

            $jumlahMahasiswa = $perMahasiswa->count();
            $tercapai = $perMahasiswa->filter(
                fn (Collection $baris) => (float) $baris->avg('nilai_akhir') >= $threshold,
            )->count();

            $persentaseTercapai = $jumlahMahasiswa > 0
                ? round($tercapai / $jumlahMahasiswa * 100, 2)
                : null;

            $rows[] = [
                'mk_nama' => $mkUnit->mk?->nama ?? '—',
                'mk_unit_kode' => $mkUnit->kode,
                'rata_rata' => $rataRata,
                'jumlah_mahasiswa' => $jumlahMahasiswa,
                'persentase_tercapai' => $persentaseTercapai,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    public function cplOptionsForUnit(string $academicUnitId, string $semesterId): array
    {
        return Cpl::query()
            ->whereIn(
                'id',
                HasilCplUnit::query()
                    ->where('academic_unit_id', $academicUnitId)
                    ->where('semester_id', $semesterId)
                    ->pluck('cpl_id'),
            )
            ->orderBy('kode')
            ->pluck('kode', 'id')
            ->all();
    }
}
