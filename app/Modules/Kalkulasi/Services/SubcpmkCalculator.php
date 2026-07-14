<?php

namespace App\Modules\Kalkulasi\Services;

use App\Modules\Kalkulasi\Models\HasilSubcpmk;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SubcpmkCalculator
{
    public function calculate(string $kelasMkId): void
    {
        $kelasMk = KelasMk::query()->with('mkUnit')->find($kelasMkId);

        if (! $kelasMk instanceof KelasMk) {
            return;
        }

        $mkId = $kelasMk->mkUnit?->mk_id;

        if ($mkId === null) {
            return;
        }

        $kmmIds = KelasMkMahasiswa::query()
            ->where('kelas_mk_id', $kelasMkId)
            ->pluck('id');

        if ($kmmIds->isEmpty()) {
            return;
        }

        $komponenPerSubcpmk = SubcpmkKomponenPenilaian::query()
            ->whereHas(
                'komponenPenilaian',
                fn ($query) => $query->where('mk_id', $mkId)->where('semester_id', $kelasMk->semester_id),
            )
            ->with([
                'komponenPenilaian',
                'nilaiMahasiswas' => fn ($query) => $query->whereIn('kelas_mk_mahasiswa_id', $kmmIds),
            ])
            ->get()
            ->groupBy('subcpmk_id');

        if ($komponenPerSubcpmk->isEmpty()) {
            return;
        }

        $existing = HasilSubcpmk::query()
            ->where('kelas_mk_id', $kelasMkId)
            ->get()
            ->keyBy(fn (HasilSubcpmk $hasil): string => $hasil->subcpmk_id.'|'.$hasil->kelas_mk_mahasiswa_id);

        $now = now();
        $rows = [];

        foreach ($komponenPerSubcpmk as $subcpmkId => $komponens) {
            /** @var Collection<int, SubcpmkKomponenPenilaian> $komponens */
            foreach ($kmmIds as $kmmId) {
                $nilaiAkhir = $this->hitungNilaiAkhir($komponens, (string) $kmmId);

                if ($nilaiAkhir === null) {
                    continue;
                }

                $key = $subcpmkId.'|'.$kmmId;
                $hasil = $existing->get($key);

                $rows[] = [
                    'id' => $hasil?->id ?? (string) Str::uuid(),
                    'subcpmk_id' => $subcpmkId,
                    'kelas_mk_mahasiswa_id' => $kmmId,
                    'kelas_mk_id' => $kelasMkId,
                    'nilai_akhir' => $nilaiAkhir,
                    'created_at' => $hasil?->created_at ?? $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows === []) {
            return;
        }

        HasilSubcpmk::query()->upsert(
            $rows,
            ['subcpmk_id', 'kelas_mk_mahasiswa_id'],
            ['nilai_akhir', 'kelas_mk_id', 'updated_at'],
        );
    }

    /**
     * @param  Collection<int, SubcpmkKomponenPenilaian>  $komponens
     */
    public function hitungNilaiAkhir(Collection $komponens, string $kelasMkMahasiswaId): ?float
    {
        $pembilang = 0.0;
        $penyebut = 0.0;

        foreach ($komponens as $skp) {
            $nilai = $skp->nilaiMahasiswas
                ->firstWhere('kelas_mk_mahasiswa_id', $kelasMkMahasiswaId)
                ?->nilai;

            if ($nilai === null || $nilai === '') {
                continue;
            }

            // $skp->bobot sudah berupa kontribusi nyata (skala sama dengan
            // KomponenPenilaian.bobot), bukan lagi persentase bagian dari
            // 100 — jadi TIDAK dikalikan lagi dengan bobot Asesmen di sini,
            // supaya proporsi antar-Asesmen yang bobotnya berbeda tidak
            // terhitung ganda.
            $bobotGabungan = (float) $skp->bobot;

            $pembilang += (float) $nilai * $bobotGabungan;
            $penyebut += $bobotGabungan;
        }

        if ($penyebut <= 0) {
            return null;
        }

        return round($pembilang / $penyebut, 2);
    }
}
