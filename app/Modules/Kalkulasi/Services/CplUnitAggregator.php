<?php

namespace App\Modules\Kalkulasi\Services;

use App\Modules\CPL\Models\Cpl;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalkulasi\Models\HasilCplMk;
use App\Modules\Kalkulasi\Models\HasilCplMkUnit;
use App\Modules\Kalkulasi\Models\HasilCplUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Models\MkUnit;
use Illuminate\Support\Str;

class CplUnitAggregator
{
    public function aggregate(string $academicUnitId, string $semesterId): void
    {
        $unit = AcademicUnit::query()->findOrFail($academicUnitId);

        $cpls = Cpl::query()
            ->where('academic_unit_id', $academicUnitId)
            ->get();

        if ($cpls->isEmpty()) {
            return;
        }

        $threshold = $this->targetCapaianUntukUnit($academicUnitId);

        $existing = HasilCplUnit::query()
            ->where('academic_unit_id', $academicUnitId)
            ->where('semester_id', $semesterId)
            ->get()
            ->keyBy('cpl_id');

        $now = now();
        $rows = [];

        foreach ($cpls as $cpl) {
            $stats = $unit->type === 'study_program'
                ? $this->agregatDariMkUnits($cpl->id, $academicUnitId, $semesterId, $threshold)
                : $this->agregatDariMkUnit($cpl->id, $academicUnitId, $semesterId);

            if ($stats === null) {
                continue;
            }

            $hasil = $existing->get($cpl->id);

            $rows[] = [
                'id' => $hasil?->id ?? (string) Str::uuid(),
                'cpl_id' => $cpl->id,
                'academic_unit_id' => $academicUnitId,
                'semester_id' => $semesterId,
                'rata_rata' => $stats['rata_rata'],
                'persentase_tercapai' => $stats['persentase_tercapai'],
                'jumlah_mahasiswa' => $stats['jumlah_mahasiswa'],
                'created_at' => $hasil?->created_at ?? $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return;
        }

        HasilCplUnit::query()->upsert(
            $rows,
            ['cpl_id', 'academic_unit_id', 'semester_id'],
            ['rata_rata', 'persentase_tercapai', 'jumlah_mahasiswa', 'updated_at'],
        );
    }

    /**
     * @return array{rata_rata: float, persentase_tercapai: float, jumlah_mahasiswa: int}|null
     */
    protected function agregatDariMkUnits(
        string $cplId,
        string $academicUnitId,
        string $semesterId,
        int $threshold,
    ): ?array {
        $mkUnitIds = MkUnit::query()
            ->where('academic_unit_id', $academicUnitId)
            ->pluck('id');

        if ($mkUnitIds->isEmpty()) {
            return null;
        }

        $hasilRows = HasilCplMk::query()
            ->where('cpl_id', $cplId)
            ->where('semester_id', $semesterId)
            ->whereIn('mk_unit_id', $mkUnitIds)
            ->whereNotNull('nilai_berbobot')
            ->with('kelasMkMahasiswa')
            ->get();

        if ($hasilRows->isEmpty()) {
            return null;
        }

        $rataRata = round((float) $hasilRows->avg('nilai_berbobot'), 2);

        $perMahasiswa = $hasilRows->groupBy(
            fn (HasilCplMk $hasil): string => (string) $hasil->kelasMkMahasiswa?->mahasiswa_id,
        );

        $jumlahMahasiswa = $perMahasiswa->count();
        $tercapai = $perMahasiswa->filter(
            fn ($baris) => (float) $baris->avg('nilai_berbobot') >= $threshold,
        )->count();

        $persentaseTercapai = $jumlahMahasiswa > 0
            ? round($tercapai / $jumlahMahasiswa * 100, 2)
            : 0.0;

        return [
            'rata_rata' => $rataRata,
            'persentase_tercapai' => $persentaseTercapai,
            'jumlah_mahasiswa' => $jumlahMahasiswa,
        ];
    }

    /**
     * @return array{rata_rata: float, persentase_tercapai: float|null, jumlah_mahasiswa: int}|null
     */
    protected function agregatDariMkUnit(
        string $cplId,
        string $academicUnitId,
        string $semesterId,
    ): ?array {
        $barisAgregat = HasilCplMkUnit::query()
            ->where('cpl_id', $cplId)
            ->where('academic_unit_id', $academicUnitId)
            ->where('semester_id', $semesterId)
            ->whereNotNull('rata_rata')
            ->get();

        if ($barisAgregat->isEmpty()) {
            return null;
        }

        $jumlahMahasiswa = (int) $barisAgregat->sum('jumlah_mahasiswa');

        $rataRata = round((float) $barisAgregat->avg('rata_rata'), 2);

        $persentaseTercapai = $jumlahMahasiswa > 0
            ? round(
                $barisAgregat->sum(
                    fn (HasilCplMkUnit $baris) => (float) ($baris->persentase_tercapai ?? 0) * (int) $baris->jumlah_mahasiswa,
                ) / $jumlahMahasiswa,
                2,
            )
            : null;

        return [
            'rata_rata' => $rataRata,
            'persentase_tercapai' => $persentaseTercapai,
            'jumlah_mahasiswa' => $jumlahMahasiswa,
        ];
    }

    protected function targetCapaianUntukUnit(string $academicUnitId): int
    {
        return (int) (Kurikulum::query()
            ->where('academic_unit_id', $academicUnitId)
            ->where('is_active', true)
            ->value('target_capaian_lulusan') ?? 75);
    }
}
