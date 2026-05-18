<?php

namespace App\Modules\Kalkulasi\Services;

use App\Modules\CPL\Models\Cpl;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kalkulasi\Models\HasilCplMk;
use App\Modules\Kalkulasi\Models\HasilCplMkUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Illuminate\Support\Str;

class CplMkUnitCalculator
{
    public function calculate(string $academicUnitId, string $semesterId): void
    {
        $unitIds = AcademicUnitScope::descendantIdsIncludingSelf($academicUnitId);

        foreach ($unitIds as $unitId) {
            $this->calculateForUnit((string) $unitId, $semesterId);
        }
    }

    protected function calculateForUnit(string $academicUnitId, string $semesterId): void
    {
        $descendantIds = AcademicUnitScope::descendantIdsIncludingSelf($academicUnitId);
        $mkIds = Mk::query()
            ->whereIn('academic_unit_id', $descendantIds)
            ->pluck('id');

        if ($mkIds->isEmpty()) {
            return;
        }

        $mkUnitIds = MkUnit::query()
            ->whereIn('mk_id', $mkIds)
            ->pluck('id');

        if ($mkUnitIds->isEmpty()) {
            return;
        }

        $cpls = Cpl::query()
            ->where('academic_unit_id', $academicUnitId)
            ->get();

        if ($cpls->isEmpty()) {
            return;
        }

        $threshold = $this->targetCapaianUntukUnit($academicUnitId);

        $existing = HasilCplMkUnit::query()
            ->where('academic_unit_id', $academicUnitId)
            ->where('semester_id', $semesterId)
            ->get()
            ->keyBy(fn (HasilCplMkUnit $hasil): string => $hasil->cpl_id.'|'.$hasil->mk_id);

        $now = now();
        $rows = [];

        foreach ($cpls as $cpl) {
            foreach ($mkIds as $mkId) {
                $hasilRows = HasilCplMk::query()
                    ->where('cpl_id', $cpl->id)
                    ->where('semester_id', $semesterId)
                    ->whereIn('mk_unit_id', $mkUnitIds)
                    ->whereHas('mkUnit', fn ($query) => $query->where('mk_id', $mkId))
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
                    fn ($baris) => (float) $baris->avg('nilai_akhir') >= $threshold,
                )->count();

                $persentaseTercapai = $jumlahMahasiswa > 0
                    ? round($tercapai / $jumlahMahasiswa * 100, 2)
                    : null;

                $key = $cpl->id.'|'.$mkId;
                $hasil = $existing->get($key);

                $rows[] = [
                    'id' => $hasil?->id ?? (string) Str::uuid(),
                    'cpl_id' => $cpl->id,
                    'mk_id' => $mkId,
                    'academic_unit_id' => $academicUnitId,
                    'semester_id' => $semesterId,
                    'rata_rata' => $rataRata,
                    'persentase_tercapai' => $persentaseTercapai,
                    'jumlah_mahasiswa' => $jumlahMahasiswa,
                    'created_at' => $hasil?->created_at ?? $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows === []) {
            return;
        }

        HasilCplMkUnit::query()->upsert(
            $rows,
            ['cpl_id', 'mk_id', 'academic_unit_id', 'semester_id'],
            ['rata_rata', 'persentase_tercapai', 'jumlah_mahasiswa', 'updated_at'],
        );
    }

    protected function targetCapaianUntukUnit(string $academicUnitId): int
    {
        return (int) (Kurikulum::query()
            ->where('academic_unit_id', $academicUnitId)
            ->where('is_active', true)
            ->value('target_capaian_lulusan') ?? 75);
    }
}
