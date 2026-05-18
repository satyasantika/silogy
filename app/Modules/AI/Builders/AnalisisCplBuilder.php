<?php

namespace App\Modules\AI\Builders;

use App\Modules\AI\Exceptions\AnalisisCplDataKosongException;
use App\Modules\CPL\Models\Cpl;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalkulasi\Models\HasilCplMkUnit;
use App\Modules\Kalkulasi\Models\HasilCplUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Mk;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;

class AnalisisCplBuilder
{
    private const JENIS_VALID = [
        'ringkasan_cpl',
        'rekomendasi_kurikulum',
        'tren_capaian',
    ];

    private AcademicUnit $unit;

    private Semester $semester;

    private string $jenis = 'ringkasan_cpl';

    public function forUnit(AcademicUnit $unit, Semester $semester): self
    {
        $this->unit = $unit;
        $this->semester = $semester;

        return $this;
    }

    public function withType(string $jenis): self
    {
        if (! in_array($jenis, self::JENIS_VALID, true)) {
            throw new InvalidArgumentException(
                "Jenis analisis «{$jenis}» tidak valid. Gunakan: ".implode(', ', self::JENIS_VALID),
            );
        }

        $this->jenis = $jenis;

        return $this;
    }

    /**
     * @return array{prompt: string, context: array<string, mixed>}
     */
    public function build(): array
    {
        $context = $this->buildContext();
        $target = (int) $context['target_capaian_lulusan'];

        $prompt = View::make("ai.prompts.{$this->jenis}", [
            'unit' => $this->unit,
            'semester' => $this->semester,
            'target' => $target,
            'contextJson' => json_encode(
                $context,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT,
            ),
        ])->render();

        return [
            'prompt' => trim($prompt),
            'context' => $context,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(): array
    {
        $target = $this->resolveTargetCapaian();
        $hasilCplUnit = $this->fetchHasilCplUnit($target);

        if ($hasilCplUnit->isEmpty()) {
            throw AnalisisCplDataKosongException::forUnitSemester(
                $this->unit->nama,
                $this->semester->nama,
            );
        }

        return [
            'unit' => [
                'id' => $this->unit->id,
                'nama' => $this->unit->nama,
                'kode' => $this->unit->code,
                'type' => $this->unit->type,
            ],
            'semester' => [
                'id' => $this->semester->id,
                'nama' => $this->semester->nama,
                'kode' => $this->semester->kode,
            ],
            'jenis' => $this->jenis,
            'target_capaian_lulusan' => $target,
            'statistik_unit' => $this->fetchStatistikUnit(),
            'hasil_cpl_unit' => $hasilCplUnit->values()->all(),
            'mk_terendah' => $this->fetchMkTerendah(),
        ];
    }

    private function resolveTargetCapaian(): int
    {
        return (int) (Kurikulum::query()
            ->where('academic_unit_id', $this->unit->id)
            ->where('is_active', true)
            ->value('target_capaian_lulusan') ?? 75);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fetchHasilCplUnit(int $target): Collection
    {
        return HasilCplUnit::query()
            ->where('hasil_cpl_unit.academic_unit_id', $this->unit->id)
            ->where('hasil_cpl_unit.semester_id', $this->semester->id)
            ->join('cpl', 'cpl.id', '=', 'hasil_cpl_unit.cpl_id')
            ->orderBy('cpl.kode')
            ->get([
                'hasil_cpl_unit.rata_rata',
                'hasil_cpl_unit.persentase_tercapai',
                'hasil_cpl_unit.jumlah_mahasiswa',
                'cpl.kode',
                'cpl.deskripsi',
            ])
            ->map(fn ($baris): array => [
                'kode' => (string) $baris->kode,
                'deskripsi' => (string) $baris->deskripsi,
                'rata_rata' => $baris->rata_rata !== null ? (float) $baris->rata_rata : null,
                'persentase_tercapai' => $baris->persentase_tercapai !== null
                    ? (float) $baris->persentase_tercapai
                    : null,
                'target' => $target,
            ]);
    }

    /**
     * @return array<string, int>
     */
    private function fetchStatistikUnit(): array
    {
        return [
            'jumlah_mahasiswa' => Mahasiswa::query()
                ->where('academic_unit_id', $this->unit->id)
                ->count(),
            'jumlah_kurikulum_aktif' => Kurikulum::query()
                ->where('academic_unit_id', $this->unit->id)
                ->where('is_active', true)
                ->count(),
            'jumlah_cpl' => Cpl::query()
                ->where('academic_unit_id', $this->unit->id)
                ->count(),
            'jumlah_mk' => Mk::query()
                ->where('academic_unit_id', $this->unit->id)
                ->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchMkTerendah(): array
    {
        return HasilCplMkUnit::query()
            ->where('hasil_cpl_mk_unit.academic_unit_id', $this->unit->id)
            ->where('hasil_cpl_mk_unit.semester_id', $this->semester->id)
            ->whereNotNull('hasil_cpl_mk_unit.persentase_tercapai')
            ->join('mk', 'mk.id', '=', 'hasil_cpl_mk_unit.mk_id')
            ->join('mk_units', function ($join): void {
                $join->on('mk_units.mk_id', '=', 'hasil_cpl_mk_unit.mk_id')
                    ->on('mk_units.academic_unit_id', '=', 'hasil_cpl_mk_unit.academic_unit_id');
            })
            ->join('cpl', 'cpl.id', '=', 'hasil_cpl_mk_unit.cpl_id')
            ->get([
                'hasil_cpl_mk_unit.mk_id',
                'mk_units.kode as mk_kode',
                'mk.nama as mk_nama',
                'hasil_cpl_mk_unit.rata_rata',
                'hasil_cpl_mk_unit.persentase_tercapai',
                'cpl.kode as cpl_kode',
            ])
            ->groupBy('mk_id')
            ->map(function (Collection $baris): array {
                $pertama = $baris->first();

                return [
                    'mk_kode' => (string) $pertama->mk_kode,
                    'mk_nama' => (string) $pertama->mk_nama,
                    'persentase_tercapai_terendah' => (float) $baris->min('persentase_tercapai'),
                    'rata_rata' => round((float) $baris->avg('rata_rata'), 2),
                    'cpl_terkait' => $baris->pluck('cpl_kode')->unique()->values()->all(),
                ];
            })
            ->sortBy('persentase_tercapai_terendah')
            ->take(5)
            ->values()
            ->all();
    }
}
