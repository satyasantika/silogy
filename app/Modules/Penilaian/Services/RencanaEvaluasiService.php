<?php

namespace App\Modules\Penilaian\Services;

use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RencanaEvaluasiService
{
    /**
     * @var array<string, list<string>>
     */
    private const GRUP_KATEGORI = [
        'Pengetahuan/Kognitif' => ['ujian', 'tugas'],
        'Hasil Proyek/Studi Kasus' => ['proyek', 'studi_kasus'],
        'Aktivitas Partisipatif' => ['partisipasi'],
    ];

    /**
     * @return array{
     *     groups: list<array{
     *         label: string,
     *         bobot_persen: float,
     *         rows: list<array{
     *             evaluasi_nama: string,
     *             asesmen: list<array{kode: string, nama: string, bobot: float}>,
     *             bobot_total: float,
     *             cpl_kodes: list<string>,
     *             cpmk_kodes: list<string>
     *         }>
     *     }>,
     *     total_bobot: float
     * }|null
     */
    public function build(?string $mkId, ?string $semesterId): ?array
    {
        if (blank($mkId) || blank($semesterId)) {
            return null;
        }

        $kelasIds = KelasMk::query()
            ->where('semester_id', $semesterId)
            ->whereHas(
                'mkUnit',
                fn (Builder $query): Builder => $query->where('mk_id', $mkId),
            )
            ->pluck('id');

        if ($kelasIds->isEmpty()) {
            return $this->buildKosong();
        }

        $komponensByEvaluasi = KomponenPenilaian::query()
            ->whereIn('kelas_mk_id', $kelasIds)
            ->with([
                'evaluasi',
                'subcpmkKomponens.subcpmk.mkCpmk.cpmk',
                'subcpmkKomponens.subcpmk.mkCpmk.cplMk.cplBok.cpl',
            ])
            ->get()
            ->unique(fn (KomponenPenilaian $komponen): string => ($komponen->evaluasi_id ?? '').'|'.($komponen->kode ?? $komponen->id))
            ->groupBy('evaluasi_id');

        $evaluasis = Evaluasi::query()->orderBy('kode')->get();
        $groups = [];
        $totalBobot = 0.0;

        foreach (self::GRUP_KATEGORI as $grupLabel => $kategoris) {
            $rows = [];
            $grupBobot = 0.0;

            foreach ($evaluasis as $evaluasi) {
                if (! in_array($evaluasi->kategori, $kategoris, true)) {
                    continue;
                }

                $komponens = $komponensByEvaluasi->get($evaluasi->id, collect());

                $asesmen = $komponens
                    ->sortBy(fn (KomponenPenilaian $komponen): string => $komponen->kode ?? $komponen->nama)
                    ->map(fn (KomponenPenilaian $komponen): array => [
                        'kode' => $komponen->kode ?: '—',
                        'nama' => $komponen->nama,
                        'bobot' => (float) $komponen->bobot,
                    ])
                    ->values()
                    ->all();

                $bobotTotal = $komponens->sum(fn (KomponenPenilaian $komponen): float => (float) $komponen->bobot);
                $grupBobot += $bobotTotal;
                $totalBobot += $bobotTotal;

                $rows[] = [
                    'evaluasi_nama' => $evaluasi->nama,
                    'asesmen' => $asesmen,
                    'bobot_total' => $bobotTotal,
                    'cpl_kodes' => $this->kumpulkanCplKodes($komponens),
                    'cpmk_kodes' => $this->kumpulkanCpmkKodes($komponens),
                ];
            }

            $groups[] = [
                'label' => $grupLabel,
                'bobot_persen' => $grupBobot,
                'rows' => $rows,
            ];
        }

        return [
            'groups' => $groups,
            'total_bobot' => $totalBobot,
        ];
    }

    public function resolveSemesterId(?string $mkId): ?string
    {
        if (blank($mkId)) {
            return null;
        }

        $semesterId = SemesterTerpilih::currentId($mkId);

        if (filled($semesterId)) {
            return $semesterId;
        }

        return Semester::query()->where('status_aktif', true)->value('id');
    }

    /**
     * @return array{
     *     groups: list<array{
     *         label: string,
     *         bobot_persen: float,
     *         rows: list<array{
     *             evaluasi_nama: string,
     *             asesmen: list<array{kode: string, nama: string, bobot: float}>,
     *             bobot_total: float,
     *             cpl_kodes: list<string>,
     *             cpmk_kodes: list<string>
     *         }>
     *     }>,
     *     total_bobot: float
     * }
     */
    private function buildKosong(): array
    {
        $groups = [];

        foreach (self::GRUP_KATEGORI as $grupLabel => $kategoris) {
            $rows = Evaluasi::query()
                ->whereIn('kategori', $kategoris)
                ->orderBy('kode')
                ->get()
                ->map(fn (Evaluasi $evaluasi): array => [
                    'evaluasi_nama' => $evaluasi->nama,
                    'asesmen' => [],
                    'bobot_total' => 0.0,
                    'cpl_kodes' => [],
                    'cpmk_kodes' => [],
                ])
                ->values()
                ->all();

            $groups[] = [
                'label' => $grupLabel,
                'bobot_persen' => 0.0,
                'rows' => $rows,
            ];
        }

        return [
            'groups' => $groups,
            'total_bobot' => 0.0,
        ];
    }

    /**
     * @param  Collection<int, KomponenPenilaian>  $komponens
     * @return list<string>
     */
    private function kumpulkanCplKodes(Collection $komponens): array
    {
        return $komponens
            ->flatMap(fn (KomponenPenilaian $komponen) => $komponen->subcpmkKomponens)
            ->map(fn ($pivot) => $pivot->subcpmk?->mkCpmk?->cplMk?->cplBok?->cpl?->kode)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, KomponenPenilaian>  $komponens
     * @return list<string>
     */
    private function kumpulkanCpmkKodes(Collection $komponens): array
    {
        return $komponens
            ->flatMap(fn (KomponenPenilaian $komponen) => $komponen->subcpmkKomponens)
            ->map(fn ($pivot) => $pivot->subcpmk?->mkCpmk?->cpmk?->kode)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function formatBobot(float $bobot): string
    {
        if ($bobot === 0.0) {
            return '0%';
        }

        $formatted = rtrim(rtrim(number_format($bobot, 2, ',', '.'), '0'), ',');

        return $formatted.'%';
    }
}
