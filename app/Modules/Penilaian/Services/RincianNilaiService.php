<?php

namespace App\Modules\Penilaian\Services;

use App\Models\User;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Models\RincianNilaiKelas;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rincian nilai per Sub-CPMK untuk asesmen yang dibebankan ke dua Sub-CPMK
 * atau lebih. Tanpa rincian, satu angka disebar ke semua pivot; dengan
 * rincian, tiap pivot punya angkanya sendiri dan nilai asesmen adalah
 * rata-rata tertimbang bobot pivot.
 */
class RincianNilaiService
{
    public function __construct(private readonly PenilaianMatrixService $matrix) {}

    /**
     * Asesmen berpenanda yang masih punya >= 2 pivot pada semester kelas.
     *
     * @return array<string, list<array{pivot_id: string, subcpmk_kode: string, bobot: float}>>
     */
    public function rincianUntukKelas(KelasMk $kelasMk): array
    {
        $ditandai = RincianNilaiKelas::query()
            ->where('kelas_mk_id', $kelasMk->id)
            ->pluck('komponen_penilaian_id')
            ->all();

        if ($ditandai === []) {
            return [];
        }

        $hasil = [];

        foreach ($this->matrix->komponenUntukKelas($kelasMk) as $komponen) {
            if (! in_array($komponen->id, $ditandai, true)) {
                continue;
            }

            $pivots = $this->pivotTerurut($komponen);

            if (count($pivots) >= 2) {
                $hasil[$komponen->id] = $pivots;
            }
        }

        return $hasil;
    }

    /** Hanya asesmen dengan >= 2 pivot Sub-CPMK yang bisa dirinci. */
    public function bisaDirinci(KomponenPenilaian $komponen): bool
    {
        return $komponen->subcpmkKomponens()->count() >= 2;
    }

    public function nyalakan(KelasMk $kelasMk, KomponenPenilaian $komponen, User $oleh): void
    {
        if (! $this->bisaDirinci($komponen)) {
            throw ValidationException::withMessages([
                'rincian' => 'Hanya asesmen yang dibebankan ke dua Sub-CPMK atau lebih yang bisa dirinci.',
            ]);
        }

        DB::transaction(function () use ($kelasMk, $komponen, $oleh): void {
            RincianNilaiKelas::query()->firstOrCreate(
                ['kelas_mk_id' => $kelasMk->id, 'komponen_penilaian_id' => $komponen->id],
                ['dinyalakan_oleh' => $oleh->id],
            );

            $pivots = $this->pivotKelas($kelasMk, $komponen->id);

            if ($pivots === []) {
                return;
            }

            $pivotIds = array_column($pivots, 'pivot_id');

            // Nilai yang tampil sebelum rincian = nilai pivot pertama (urutan
            // sama dengan matriks), jadi angka itulah yang disebar.
            $this->tulisKeSemuaPivot($kelasMk, $pivotIds, function (array $skor) use ($pivotIds): ?float {
                $tampil = $skor[$pivotIds[0]] ?? null;

                return $tampil === null ? null : (float) $tampil;
            });
        });
    }

    public function matikan(KelasMk $kelasMk, KomponenPenilaian $komponen): void
    {
        DB::transaction(function () use ($kelasMk, $komponen): void {
            $pivots = $this->pivotKelas($kelasMk, $komponen->id);

            if ($pivots !== []) {
                $pivotIds = array_column($pivots, 'pivot_id');
                $bobot = array_column($pivots, 'bobot', 'pivot_id');

                $this->tulisKeSemuaPivot(
                    $kelasMk,
                    $pivotIds,
                    fn (array $skor): ?float => $this->nilaiAsesmen($skor, $bobot),
                );
            }

            RincianNilaiKelas::query()
                ->where('kelas_mk_id', $kelasMk->id)
                ->where('komponen_penilaian_id', $komponen->id)
                ->delete();
        });
    }

    /**
     * Σ(skor × bobot) / Σ(bobot) atas pivot yang terisi, 2 desimal; null
     * bila tak satu pun terisi.
     *
     * @param  array<string, string|float|null>  $skorPerPivot
     * @param  array<string, float>  $bobotPerPivot
     */
    public function nilaiAsesmen(array $skorPerPivot, array $bobotPerPivot): ?float
    {
        $jumlah = 0.0;
        $totalBobot = 0.0;

        foreach ($skorPerPivot as $pivotId => $skor) {
            if ($skor === null || $skor === '') {
                continue;
            }

            $bobot = (float) ($bobotPerPivot[$pivotId] ?? 0);
            $jumlah += (float) $skor * $bobot;
            $totalBobot += $bobot;
        }

        if ($totalBobot <= 0.0) {
            return null;
        }

        return round($jumlah / $totalBobot, 2);
    }

    /**
     * @param  list<string>  $pivotIds
     * @param  callable(array<string, string|null>): ?float  $tentukanNilai  menerima skor per pivot milik satu peserta
     */
    private function tulisKeSemuaPivot(KelasMk $kelasMk, array $pivotIds, callable $tentukanNilai): void
    {
        $kmmIds = KelasMkMahasiswa::query()->where('kelas_mk_id', $kelasMk->id)->pluck('id');

        foreach ($kmmIds as $kmmId) {
            $ada = NilaiMahasiswa::query()
                ->where('kelas_mk_mahasiswa_id', $kmmId)
                ->whereIn('subcpmk_komponenpenilaian_id', $pivotIds)
                ->get()
                ->keyBy('subcpmk_komponenpenilaian_id');

            $skor = [];
            foreach ($pivotIds as $pivotId) {
                $nilai = $ada->get($pivotId)?->nilai;
                $skor[$pivotId] = $nilai === null ? null : (string) $nilai;
            }

            $nilai = $tentukanNilai($skor);

            if ($nilai === null) {
                // Tak ada angka yang bisa ditulis; baris yang sudah ada dibiarkan.
                continue;
            }

            foreach ($pivotIds as $pivotId) {
                NilaiMahasiswa::query()->updateOrCreate(
                    [
                        'subcpmk_komponenpenilaian_id' => $pivotId,
                        'kelas_mk_mahasiswa_id' => $kmmId,
                    ],
                    ['nilai' => $nilai],
                );
            }
        }
    }

    /**
     * @return list<array{pivot_id: string, subcpmk_kode: string, bobot: float}>
     */
    private function pivotKelas(KelasMk $kelasMk, string $komponenId): array
    {
        $komponen = $this->matrix->komponenUntukKelas($kelasMk)->firstWhere('id', $komponenId);

        return $komponen === null ? [] : $this->pivotTerurut($komponen);
    }

    /**
     * @return list<array{pivot_id: string, subcpmk_kode: string, bobot: float}>
     */
    private function pivotTerurut(KomponenPenilaian $komponen): array
    {
        $pivots = $komponen->subcpmkKomponens
            ->map(fn ($pivot): array => [
                'pivot_id' => (string) $pivot->id,
                'subcpmk_kode' => (string) ($pivot->subcpmk->kode ?? ''),
                'bobot' => (float) $pivot->bobot,
            ])
            ->all();

        usort($pivots, fn (array $a, array $b): int => [$a['subcpmk_kode'], $a['pivot_id']] <=> [$b['subcpmk_kode'], $b['pivot_id']]);

        return $pivots;
    }
}
