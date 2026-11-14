<?php

namespace App\Modules\Penilaian\Services;

use App\Models\User;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Models\RincianNilaiKelas;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
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

            $pivots = $this->matrix->pivotTerurut($komponen);

            if (count($pivots) >= 2) {
                $hasil[$komponen->id] = $pivots;
            }
        }

        return $hasil;
    }

    /** Hanya asesmen dengan >= 2 pivot Sub-CPMK pada semester kelas yang bisa dirinci. */
    public function bisaDirinci(KelasMk $kelasMk, KomponenPenilaian $komponen): bool
    {
        return count($this->pivotKelas($kelasMk, $komponen->id)) >= 2;
    }

    public function nyalakan(KelasMk $kelasMk, KomponenPenilaian $komponen, User $oleh): void
    {
        if (! $this->bisaDirinci($kelasMk, $komponen)) {
            throw ValidationException::withMessages([
                'rincian' => 'Hanya asesmen yang dibebankan ke dua Sub-CPMK atau lebih yang bisa dirinci.',
            ]);
        }

        DB::transaction(function () use ($kelasMk, $komponen, $oleh): void {
            $penanda = RincianNilaiKelas::query()->firstOrCreate(
                ['kelas_mk_id' => $kelasMk->id, 'komponen_penilaian_id' => $komponen->id],
                ['dinyalakan_oleh' => $oleh->id],
            );

            // Idempoten: penanda sudah ada berarti nilai sudah dirinci; menyebar
            // ulang akan menimpa angka per Sub-CPMK yang sudah diisi.
            if (! $penanda->wasRecentlyCreated) {
                return;
            }

            $pivots = $this->pivotKelas($kelasMk, $komponen->id);

            if ($pivots === []) {
                return;
            }

            $pivotIds = array_column($pivots, 'pivot_id');

            // Angka yang disebar = angka yang tampil di matriks sebelum
            // rincian, dibaca dengan aturan yang sama persis (nilaiUntukMatrix),
            // supaya menyalakan rincian tidak menggeser nilai asesmen.
            $kmmIds = KelasMkMahasiswa::query()->where('kelas_mk_id', $kelasMk->id)->pluck('id');
            $tampil = $this->matrix->nilaiUntukMatrix(
                $kmmIds->map(fn ($id): array => ['id' => (string) $id])->all(),
                [$komponen->id => $pivotIds],
            );

            $this->tulisKeSemuaPivot($kelasMk, $pivotIds, function (array $skor, string $kmmId) use ($tampil, $komponen): ?float {
                $nilai = $tampil[$kmmId][$komponen->id] ?? null;

                return $nilai === null ? null : (float) $nilai;
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
     * Dipanggil setiap pivot Sub-CPMK suatu asesmen pada satu semester
     * dibuat, diubah, atau dihapus (SubcpmkKomponenPenilaianObserver). Nilai
     * asesmen yang dirinci bergantung pada bobot pivot, jadi nilai akhir
     * tersimpan kelas-kelas yang merinci asesmen itu dihitung ulang. Penanda
     * asesmen yang tinggal kurang dari dua pivot dihapus, supaya kolomnya
     * tidak diam-diam kembali dirinci saat pemetaan bertambah lagi. Kelas
     * tanpa penanda tidak disentuh: nilainya tidak bergantung pada bobot pivot.
     */
    public function pemetaanAsesmenBerubah(string $komponenId, string $semesterId): void
    {
        $kelasIds = RincianNilaiKelas::query()
            ->where('komponen_penilaian_id', $komponenId)
            ->whereHas('kelasMk', fn ($query) => $query->where('semester_id', $semesterId))
            ->pluck('kelas_mk_id')
            ->unique()
            ->values();

        if ($kelasIds->isEmpty()) {
            return;
        }

        $jumlahPivot = SubcpmkKomponenPenilaian::query()
            ->where('komponen_penilaian_id', $komponenId)
            ->where('semester_id', $semesterId)
            ->count();

        if ($jumlahPivot < 2) {
            RincianNilaiKelas::query()
                ->where('komponen_penilaian_id', $komponenId)
                ->whereIn('kelas_mk_id', $kelasIds)
                ->delete();
        }

        foreach (KelasMk::query()->with('mkUnit')->whereIn('id', $kelasIds)->get() as $kelasMk) {
            $this->matrix->simpanNilaiAkhirKelas($kelasMk, hanyaYangSudahAda: true);
        }
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
     * @param  callable(array<string, string|null>, string): ?float  $tentukanNilai  menerima skor per pivot dan id peserta
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

            $nilai = $tentukanNilai($skor, (string) $kmmId);

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

        return $komponen === null ? [] : $this->matrix->pivotTerurut($komponen);
    }
}
