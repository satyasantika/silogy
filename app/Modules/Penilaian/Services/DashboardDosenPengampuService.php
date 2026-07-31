<?php

namespace App\Modules\Penilaian\Services;

use App\Models\User;
use App\Modules\Kalkulasi\Models\HasilCplMk;
use App\Modules\MK\Filament\Support\Concerns\HasDosenPengampuMkScope;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Data dashboard Dosen Pengampu: KPI serta capaian tertinggi discope ke
 * kelas yang benar-benar diajar user (kelas_mk.dosen_pengampu_id) — bukan
 * seluruh kelas dari MK yang sama (bisa diajar dosen lain), meniru pola
 * App\Modules\Kurikulum\Services\DashboardTimKurikulumService.
 */
class DashboardDosenPengampuService
{
    use HasDosenPengampuMkScope;

    /** Jumlah MK (distinct) yang diampu user lewat kelas_mk.dosen_pengampu_id. */
    public function jumlahMkDiampu(User $user): int
    {
        return static::scopedDiampuMkIds($user)->count();
    }

    /**
     * CPL dengan rerata capaian tertinggi, dari kelas yang diampu user.
     *
     * @return list<array{cpl_kode: string, mk_nama: string, kurikulum_nama: string, rata_rata: float, jumlah_mahasiswa: int}>
     */
    public function cplTertinggi(User $user, int $limit = 5): array
    {
        $kelasIds = static::scopedDiampuKelasMkIds($user);

        if ($kelasIds->isEmpty()) {
            return [];
        }

        return $this->baseQuery()
            ->whereIn('kelas_mk_mahasiswa.kelas_mk_id', $kelasIds)
            ->join('cpl', 'cpl.id', '=', 'hasil_cpl_mk.cpl_id')
            ->join('mk_units', 'mk_units.id', '=', 'hasil_cpl_mk.mk_unit_id')
            ->join('mk', 'mk.id', '=', 'mk_units.mk_id')
            ->leftJoin('kurikulum', 'kurikulum.id', '=', 'mk_units.kurikulum_id')
            ->groupBy('cpl.id', 'cpl.kode', 'mk.nama', 'kurikulum.nama')
            ->selectRaw('cpl.kode as cpl_kode')
            ->selectRaw('mk.nama as mk_nama')
            ->selectRaw('kurikulum.nama as kurikulum_nama')
            ->selectRaw('AVG(hasil_cpl_mk.nilai_akhir) as rata_rata')
            ->selectRaw('COUNT(DISTINCT kelas_mk_mahasiswa.mahasiswa_id) as jumlah_mahasiswa')
            ->orderByDesc('rata_rata')
            ->orderBy('cpl.kode')
            ->limit($limit)
            ->get()
            ->map(fn (object $baris): array => [
                'cpl_kode' => (string) $baris->cpl_kode,
                'mk_nama' => (string) ($baris->mk_nama ?? '—'),
                'kurikulum_nama' => (string) ($baris->kurikulum_nama ?? '—'),
                'rata_rata' => round((float) $baris->rata_rata, 2),
                'jumlah_mahasiswa' => (int) $baris->jumlah_mahasiswa,
            ])
            ->all();
    }

    /**
     * Penawaran mata kuliah (mk_units) dengan rerata capaian tertinggi, dari
     * kelas yang diampu user.
     *
     * @return list<array{mk_unit_id: string, mk_nama: string, mk_unit_kode: string, kurikulum_nama: string, rata_rata: float, jumlah_mahasiswa: int}>
     */
    public function mkTertinggi(User $user, int $limit = 10): array
    {
        $kelasIds = static::scopedDiampuKelasMkIds($user);

        if ($kelasIds->isEmpty()) {
            return [];
        }

        return $this->baseQuery()
            ->whereIn('kelas_mk_mahasiswa.kelas_mk_id', $kelasIds)
            ->join('mk_units', 'mk_units.id', '=', 'hasil_cpl_mk.mk_unit_id')
            ->join('mk', 'mk.id', '=', 'mk_units.mk_id')
            ->leftJoin('kurikulum', 'kurikulum.id', '=', 'mk_units.kurikulum_id')
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
}
