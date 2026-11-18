<?php

namespace App\Modules\MK\Services;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kalkulasi\Models\HasilCplMkUnit;
use App\Modules\Kalkulasi\Models\HasilCplUnit;
use App\Modules\Kalkulasi\Services\CplMkUnitCalculator;
use App\Modules\Kalkulasi\Services\CplUnitAggregator;
use App\Modules\Kalkulasi\Support\DashboardCplCache;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Reset data satu penawaran MK (MkUnit): seluruh kontrak kelas beserta
 * penilaiannya dihapus. MK, CPMK/Sub-CPMK, komponen asesmen, dan baris
 * penawaran itu sendiri TIDAK ikut terhapus.
 *
 * Semua tabel turunan (peserta, nilai, rincian nilai, hasil kalkulasi,
 * validasi, catatan RTL) mengalir lewat cascade DB dari kelas_mk.
 */
class MkUnitResetService
{
    /**
     * @return array{kelas: int, mahasiswa: int, nilai: int}
     */
    public function ringkasan(MkUnit $mkUnit): array
    {
        $kelasIds = KelasMk::query()->where('mk_unit_id', $mkUnit->id)->pluck('id');
        $pesertaIds = KelasMkMahasiswa::query()->whereIn('kelas_mk_id', $kelasIds)->pluck('id');

        return [
            'kelas' => $kelasIds->count(),
            'mahasiswa' => $pesertaIds->count(),
            'nilai' => NilaiMahasiswa::query()->whereIn('kelas_mk_mahasiswa_id', $pesertaIds)->count(),
        ];
    }

    public function adaData(MkUnit $mkUnit): bool
    {
        return KelasMk::query()->where('mk_unit_id', $mkUnit->id)->exists();
    }

    /**
     * @return array{kelas: int, mahasiswa: int, nilai: int}
     */
    public function reset(MkUnit $mkUnit): array
    {
        $ringkasan = $this->ringkasan($mkUnit);

        $semesterIds = KelasMk::query()
            ->where('mk_unit_id', $mkUnit->id)
            ->distinct()
            ->pluck('semester_id');

        DB::transaction(function () use ($mkUnit): void {
            KelasMk::query()->where('mk_unit_id', $mkUnit->id)->delete();
        });

        $this->hitungUlangAgregat($mkUnit, $semesterIds->map(fn ($id): string => (string) $id)->all());

        activity()
            ->performedOn($mkUnit)
            ->causedBy(auth()->user())
            ->withProperties($ringkasan)
            ->log('reset data penawaran MK');

        return $ringkasan;
    }

    /**
     * Reset tetap menghapus baris HasilCplMkUnit unit ini secara eksplisit
     * sebelum dihitung ulang (reset eksplisit). Kalkulator CPL kini juga
     * memangkas baris lama dengan kunci eksplisit, jadi agregat unit ini dan
     * unit induknya dibangun ulang dari data yang tersisa. Dijalankan sinkron
     * (stack dev tanpa queue worker).
     *
     * @param  list<string>  $semesterIds
     */
    public function hitungUlangAgregat(MkUnit|string $unitAtauId, array $semesterIds): void
    {
        $academicUnitId = $unitAtauId instanceof MkUnit ? $unitAtauId->academic_unit_id : $unitAtauId;
        $unit = AcademicUnit::query()->find($academicUnitId);

        if (! $unit instanceof AcademicUnit || $semesterIds === []) {
            return;
        }

        $unitIds = AcademicUnitScope::ancestorIdsIncludingSelf($unit);

        HasilCplMkUnit::query()->whereIn('academic_unit_id', $unitIds)->whereIn('semester_id', $semesterIds)->delete();
        HasilCplUnit::query()->whereIn('academic_unit_id', $unitIds)->whereIn('semester_id', $semesterIds)->delete();

        foreach ($unitIds as $unitId) {
            foreach ($semesterIds as $semesterId) {
                app(CplMkUnitCalculator::class)->calculate((string) $unitId, (string) $semesterId);
                app(CplUnitAggregator::class)->aggregate((string) $unitId, (string) $semesterId);
                DashboardCplCache::invalidate((string) $unitId, (string) $semesterId);
            }
        }
    }

    /**
     * Catatan sementara (unit penawaran + semester ber-kelas) yang diambil
     * SEBELUM penghapusan, karena setelahnya kelasnya sudah lenyap lewat
     * cascade DB sehingga semester terdampak tak bisa lagi dicari.
     *
     * @var array<string, array{0: string, 1: list<string>}>
     */
    protected static array $tertunda = [];

    public function catatSebelumHapus(MkUnit $mkUnit): void
    {
        static::$tertunda[$mkUnit->id] = [
            (string) $mkUnit->academic_unit_id,
            KelasMk::query()->where('mk_unit_id', $mkUnit->id)->distinct()->pluck('semester_id')->map(fn ($id): string => (string) $id)->all(),
        ];
    }

    public function selesaikanSetelahHapus(MkUnit $mkUnit): void
    {
        [$unitId, $semesterIds] = static::$tertunda[$mkUnit->id] ?? [null, []];
        unset(static::$tertunda[$mkUnit->id]);

        if ($unitId !== null) {
            $this->hitungUlangAgregat($unitId, $semesterIds);
        }
    }

    /** @var array<string, list<MkUnit>> */
    protected static array $penawaranMkTertunda = [];

    /** Hapus MK memusnahkan seluruh penawarannya lewat cascade DB. */
    public function catatSebelumHapusMk(Mk $mk): void
    {
        $penawaran = MkUnit::query()->where('mk_id', $mk->id)->get();
        static::$penawaranMkTertunda[$mk->id] = $penawaran->all();

        foreach ($penawaran as $mkUnit) {
            $this->catatSebelumHapus($mkUnit);
        }
    }

    public function selesaikanSetelahHapusMk(Mk $mk): void
    {
        $penawaran = static::$penawaranMkTertunda[$mk->id] ?? [];
        unset(static::$penawaranMkTertunda[$mk->id]);

        foreach ($penawaran as $mkUnit) {
            $this->selesaikanSetelahHapus($mkUnit);
        }
    }

    /** Apakah ada kontrak kelas pada penawaran MANA PUN dari MK ini. */
    public function adaDataMk(Mk $mk): bool
    {
        return KelasMk::query()
            ->whereIn('mk_unit_id', MkUnit::query()->where('mk_id', $mk->id)->select('id'))
            ->exists();
    }

    /**
     * Reset seluruh penawaran MK ini yang boleh dikelola $user. Penawaran
     * prodi lain di luar lingkupnya dilewati dan dihitung terpisah.
     *
     * @return array{kelas: int, mahasiswa: int, nilai: int, penawaran_direset: int, penawaran_dilewati: int}
     */
    public function resetPenawaranMk(Mk $mk, User $user): array
    {
        $total = ['kelas' => 0, 'mahasiswa' => 0, 'nilai' => 0, 'penawaran_direset' => 0, 'penawaran_dilewati' => 0];

        foreach (MkUnit::query()->where('mk_id', $mk->id)->get() as $mkUnit) {
            if (! $this->adaData($mkUnit)) {
                continue;
            }

            if (! Gate::forUser($user)->allows('update', $mkUnit)) {
                $total['penawaran_dilewati']++;

                continue;
            }

            $hasil = $this->reset($mkUnit);
            $total['kelas'] += $hasil['kelas'];
            $total['mahasiswa'] += $hasil['mahasiswa'];
            $total['nilai'] += $hasil['nilai'];
            $total['penawaran_direset']++;
        }

        return $total;
    }
}
