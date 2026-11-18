<?php

namespace App\Modules\Kalkulasi\Services;

use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalkulasi\Models\HasilCplMk;
use App\Modules\Kalkulasi\Models\HasilCplMkUnit;
use App\Modules\Kalkulasi\Models\HasilCplUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Models\MkUnit;
use Illuminate\Support\Collection;
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

        $existing = HasilCplUnit::query()
            ->where('academic_unit_id', $academicUnitId)
            ->where('semester_id', $semesterId)
            ->get()
            ->keyBy('cpl_id');

        $now = now();
        $rows = [];

        $adalahProdi = $unit->type === 'study_program';
        $bobotPerCpl = $adalahProdi ? $this->bobotCplPerMk($cpls->pluck('id')->all()) : [];

        foreach ($cpls as $cpl) {
            $stats = $adalahProdi
                ? $this->agregatDariMkUnits($cpl->id, $cpl->kurikulum_id, $academicUnitId, $semesterId, Kurikulum::targetCapaianUntukCpl($cpl->kurikulum_id, $academicUnitId), $bobotPerCpl)
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
     * @param  array<string, array<string, float>>  $bobotPerCpl  cpl_id => (mk_id => bobot)
     * @return array{rata_rata: float, persentase_tercapai: float, jumlah_mahasiswa: int}|null
     */
    protected function agregatDariMkUnits(
        string $cplId,
        ?string $kurikulumId,
        string $academicUnitId,
        string $semesterId,
        int $threshold,
        array $bobotPerCpl,
    ): ?array {
        // Hanya penawaran pada kurikulum yang sama dengan CPL, supaya capaian
        // kurikulum lama dan baru tidak tercampur.
        $mkUnitIds = MkUnit::query()
            ->where('academic_unit_id', $academicUnitId)
            ->when($kurikulumId !== null, fn ($query) => $query->where('kurikulum_id', $kurikulumId))
            ->pluck('mk_id', 'id');

        if ($mkUnitIds->isEmpty()) {
            return null;
        }

        $hasilRows = HasilCplMk::query()
            ->where('cpl_id', $cplId)
            ->where('semester_id', $semesterId)
            ->whereIn('mk_unit_id', $mkUnitIds->keys())
            ->whereNotNull('nilai_akhir')
            ->whereHas('kelasMkMahasiswa.mahasiswa', fn ($query) => $query->where('academic_unit_id', $academicUnitId))
            ->with('kelasMkMahasiswa')
            ->get();

        if ($hasilRows->isEmpty()) {
            return null;
        }

        $bobotPerMk = $bobotPerCpl[$cplId] ?? [];

        $nilaiPerMahasiswa = $hasilRows
            ->groupBy(fn (HasilCplMk $hasil): string => (string) $hasil->kelasMkMahasiswa?->mahasiswa_id)
            ->map(fn (Collection $baris): float => $this->nilaiCplMahasiswa($baris, $bobotPerMk, $mkUnitIds->all()));

        $jumlahMahasiswa = $nilaiPerMahasiswa->count();
        $rataRata = round((float) $nilaiPerMahasiswa->avg(), 2);
        $tercapai = $nilaiPerMahasiswa->filter(fn (float $nilai): bool => $nilai >= $threshold)->count();

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
     * Bobot tiap MK terhadap tiap CPL: jumlah cpl_mk.bobot lewat semua cpl_bok
     * milik CPL itu (satu MK bisa terhubung lewat lebih dari satu BoK).
     *
     * @param  list<string>  $cplIds
     * @return array<string, array<string, float>> cpl_id => (mk_id => bobot)
     */
    protected function bobotCplPerMk(array $cplIds): array
    {
        $bobotPerCpl = [];

        $rows = CplMk::query()
            ->join('cpl_bok', 'cpl_bok.id', '=', 'cpl_mk.cpl_bok_id')
            ->whereIn('cpl_bok.cpl_id', $cplIds)
            ->toBase()
            ->get(['cpl_bok.cpl_id', 'cpl_mk.mk_id', 'cpl_mk.bobot']);

        foreach ($rows as $row) {
            $bobotPerCpl[$row->cpl_id][$row->mk_id] = ($bobotPerCpl[$row->cpl_id][$row->mk_id] ?? 0.0) + (float) $row->bobot;
        }

        return $bobotPerCpl;
    }

    /**
     * Nilai CPL satu mahasiswa: rata-rata nilai_akhir per MK (beberapa kelas
     * pada MK sama dirata-ratakan dulu), lalu ditimbang bobot cpl_mk dan
     * dibagi jumlah bobot MK yang punya nilai. MK berbobot <= 0 dilewati;
     * bila tidak ada MK berbobot, jatuh ke rata-rata sederhana.
     *
     * @param  Collection<int, HasilCplMk>  $baris
     * @param  array<string, float>  $bobotPerMk
     * @param  array<string, string>  $mkIdPerMkUnit  mk_unit_id => mk_id
     */
    protected function nilaiCplMahasiswa(Collection $baris, array $bobotPerMk, array $mkIdPerMkUnit): float
    {
        $rerataPerMk = $baris
            ->groupBy(fn (HasilCplMk $hasil): string => (string) ($mkIdPerMkUnit[$hasil->mk_unit_id] ?? ''))
            ->map(fn (Collection $samaMk): float => (float) $samaMk->avg('nilai_akhir'));

        $jumlahBerbobot = 0.0;
        $jumlahBobot = 0.0;

        foreach ($rerataPerMk as $mkId => $rerata) {
            $bobot = (float) ($bobotPerMk[$mkId] ?? 0);

            if ($bobot <= 0) {
                continue;
            }

            $jumlahBerbobot += $rerata * $bobot;
            $jumlahBobot += $bobot;
        }

        if ($jumlahBobot <= 0) {
            return (float) $baris->avg('nilai_akhir');
        }

        return $jumlahBerbobot / $jumlahBobot;
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
}
