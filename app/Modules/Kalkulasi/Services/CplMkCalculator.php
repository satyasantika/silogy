<?php

namespace App\Modules\Kalkulasi\Services;

use App\Modules\CPL\Models\CplMk;
use App\Modules\Kalkulasi\Models\HasilCplMk;
use App\Modules\Kalkulasi\Models\HasilCpmk;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use Illuminate\Support\Str;

class CplMkCalculator
{
    public function calculate(string $kelasMkId, string $semesterId): void
    {
        $kelasMk = KelasMk::query()
            ->with('mkUnit')
            ->findOrFail($kelasMkId);

        $mkUnitId = $kelasMk->mk_unit_id;
        $mkId = $kelasMk->mkUnit?->mk_id;

        if ($mkUnitId === null || $mkId === null) {
            return;
        }

        $kmmIds = KelasMkMahasiswa::query()
            ->where('kelas_mk_id', $kelasMkId)
            ->pluck('id');

        if ($kmmIds->isEmpty()) {
            return;
        }

        $cplMks = CplMk::query()
            ->with(['cplBok', 'mkCpmks'])
            ->where('mk_id', $mkId)
            ->get();

        if ($cplMks->isEmpty()) {
            return;
        }

        $existing = HasilCplMk::query()
            ->where('mk_unit_id', $mkUnitId)
            ->where('semester_id', $semesterId)
            ->whereIn('kelas_mk_mahasiswa_id', $kmmIds)
            ->get()
            ->keyBy(fn (HasilCplMk $hasil): string => $hasil->cpl_id.'|'.$hasil->kelas_mk_mahasiswa_id);

        $now = now();
        $rows = [];

        foreach ($cplMks as $cplMk) {
            $cplId = $cplMk->cplBok?->cpl_id;

            if ($cplId === null) {
                continue;
            }

            $cpmkIds = $cplMk->mkCpmks->pluck('cpmk_id')->filter()->all();

            if ($cpmkIds === []) {
                continue;
            }

            $bobotCplMk = (float) $cplMk->bobot / 100;
            $bobotCplBok = (float) ($cplMk->cplBok?->bobot ?? 0) / 100;

            foreach ($kmmIds as $kmmId) {
                $nilaiAkhir = HasilCpmk::query()
                    ->whereIn('cpmk_id', $cpmkIds)
                    ->where('kelas_mk_mahasiswa_id', $kmmId)
                    ->whereNotNull('nilai_akhir')
                    ->avg('nilai_akhir');

                if ($nilaiAkhir === null) {
                    continue;
                }

                $nilaiAkhir = round((float) $nilaiAkhir, 2);
                $nilaiBerbobot = round($nilaiAkhir * $bobotCplMk * $bobotCplBok, 2);

                $key = $cplId.'|'.$kmmId;
                $hasil = $existing->get($key);

                $rows[] = [
                    'id' => $hasil?->id ?? (string) Str::uuid(),
                    'cpl_id' => $cplId,
                    'mk_unit_id' => $mkUnitId,
                    'kelas_mk_mahasiswa_id' => $kmmId,
                    'semester_id' => $semesterId,
                    'nilai_akhir' => $nilaiAkhir,
                    'nilai_berbobot' => $nilaiBerbobot,
                    'created_at' => $hasil?->created_at ?? $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows === []) {
            return;
        }

        HasilCplMk::query()->upsert(
            $rows,
            ['cpl_id', 'mk_unit_id', 'kelas_mk_mahasiswa_id', 'semester_id'],
            ['nilai_akhir', 'nilai_berbobot', 'updated_at'],
        );
    }
}
