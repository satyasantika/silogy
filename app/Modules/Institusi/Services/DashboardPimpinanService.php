<?php

namespace App\Modules\Institusi\Services;

use App\Models\User;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kalkulasi\Models\HasilCplMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Data dashboard Pimpinan: KPI kurikulum serta capaian tertinggi
 * LINTAS KURIKULUM pada unit kepemimpinannya beserta seluruh keturunannya
 * (Pimpinan bisa ditugaskan di level Universitas/Fakultas/Jurusan, sementara
 * data Kurikulum/CPL/MK tersimpan di level Prodi — lihat
 * AcademicUnitScope::scopedPimpinanUnitIdsWithDescendantsFor()).
 *
 * Struktur mengikuti DashboardTimKurikulumService: agregasi all-time dari
 * hasil_cpl_mk, tanpa filter unit/semester manual.
 */
class DashboardPimpinanService
{
    /** Jumlah kurikulum pada unit kepemimpinan user beserta keturunannya. */
    public function jumlahKurikulum(User $user): int
    {
        $unitIds = $this->unitIds($user);

        if ($unitIds->isEmpty()) {
            return 0;
        }

        return Kurikulum::query()
            ->whereIn('academic_unit_id', $unitIds)
            ->count();
    }

    /** Jumlah unit (beserta keturunannya) dalam jangkauan kepemimpinan user. */
    public function jumlahUnitDikelola(User $user): int
    {
        return $this->unitIds($user)->count();
    }

    /**
     * CPL dengan rerata capaian tertinggi, boleh berasal dari kurikulum
     * manapun pada unit kepemimpinan user.
     *
     * @return list<array{cpl_kode: string, kurikulum_nama: string, unit_nama: string, rata_rata: float, jumlah_mahasiswa: int}>
     */
    public function cplTertinggi(User $user, int $limit = 5): array
    {
        $unitIds = $this->unitIds($user);

        if ($unitIds->isEmpty()) {
            return [];
        }

        return $this->baseQuery()
            ->join('cpl', 'cpl.id', '=', 'hasil_cpl_mk.cpl_id')
            ->leftJoin('kurikulum', 'kurikulum.id', '=', 'cpl.kurikulum_id')
            ->leftJoin('academic_units', 'academic_units.id', '=', 'cpl.academic_unit_id')
            ->whereIn('cpl.academic_unit_id', $unitIds)
            ->groupBy('cpl.id', 'cpl.kode', 'kurikulum.nama', 'academic_units.nama')
            ->selectRaw('cpl.kode as cpl_kode')
            ->selectRaw('kurikulum.nama as kurikulum_nama')
            ->selectRaw('academic_units.nama as unit_nama')
            ->selectRaw('AVG(hasil_cpl_mk.nilai_akhir) as rata_rata')
            ->selectRaw('COUNT(DISTINCT kelas_mk_mahasiswa.mahasiswa_id) as jumlah_mahasiswa')
            ->orderByDesc('rata_rata')
            ->orderBy('cpl.kode')
            ->limit($limit)
            ->get()
            ->map(fn (object $baris): array => [
                'cpl_kode' => (string) $baris->cpl_kode,
                'kurikulum_nama' => (string) ($baris->kurikulum_nama ?? '—'),
                'unit_nama' => (string) ($baris->unit_nama ?? '—'),
                'rata_rata' => round((float) $baris->rata_rata, 2),
                'jumlah_mahasiswa' => (int) $baris->jumlah_mahasiswa,
            ])
            ->all();
    }

    /**
     * Penawaran mata kuliah (mk_units) dengan rerata capaian tertinggi.
     *
     * @return list<array{mk_unit_id: string, mk_nama: string, mk_unit_kode: string, kurikulum_nama: string, rata_rata: float, jumlah_mahasiswa: int}>
     */
    public function mkTertinggi(User $user, int $limit = 10): array
    {
        $unitIds = $this->unitIds($user);

        if ($unitIds->isEmpty()) {
            return [];
        }

        return $this->baseQuery()
            ->join('mk_units', 'mk_units.id', '=', 'hasil_cpl_mk.mk_unit_id')
            ->join('mk', 'mk.id', '=', 'mk_units.mk_id')
            ->leftJoin('kurikulum', 'kurikulum.id', '=', 'mk_units.kurikulum_id')
            ->whereIn('mk_units.academic_unit_id', $unitIds)
            ->groupBy('mk_units.id', 'mk_units.kode', 'mk.nama', 'kurikulum.nama')
            ->selectRaw('mk_units.id as mk_unit_id')
            ->selectRaw('mk.nama as mk_nama')
            ->selectRaw('mk_units.kode as mk_unit_kode')
            ->selectRaw('kurikulum.nama as kurikulum_nama')
            ->selectRaw('AVG(hasil_cpl_mk.nilai_akhir) as rata_rata')
            ->selectRaw('COUNT(DISTINCT kelas_mk_mahasiswa.mahasiswa_id) as jumlah_mahasiswa')
            ->orderByDesc('rata_rata')
            ->orderBy('mk_units.kode')
            ->limit($limit)
            ->get()
            ->map(fn (object $baris): array => [
                'mk_unit_id' => (string) $baris->mk_unit_id,
                'mk_nama' => (string) ($baris->mk_nama ?? '—'),
                'mk_unit_kode' => (string) $baris->mk_unit_kode,
                'kurikulum_nama' => (string) ($baris->kurikulum_nama ?? '—'),
                'rata_rata' => round((float) $baris->rata_rata, 2),
                'jumlah_mahasiswa' => (int) $baris->jumlah_mahasiswa,
            ])
            ->all();
    }

    /**
     * Basis agregasi: hanya hasil kalkulasi yang sudah punya nilai akhir,
     * di-join ke peserta kelas agar jumlah mahasiswa dihitung unik per
     * mahasiswa (bukan per baris peserta kelas).
     */
    protected function baseQuery(): QueryBuilder
    {
        return HasilCplMk::query()
            ->toBase()
            ->join(
                'kelas_mk_mahasiswa',
                'kelas_mk_mahasiswa.id',
                '=',
                'hasil_cpl_mk.kelas_mk_mahasiswa_id',
            )
            ->whereNotNull('hasil_cpl_mk.nilai_akhir');
    }

    /**
     * @return Collection<int, string>
     */
    protected function unitIds(User $user): Collection
    {
        return AcademicUnitScope::scopedPimpinanUnitIdsWithDescendantsFor($user);
    }
}
