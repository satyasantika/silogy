<?php

namespace App\Modules\Kelas\Services;

use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Models\MkUnit;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class SetBanyakKelasMkService
{
    public const SEMESTER_KE_MIN = 1;

    public const SEMESTER_KE_MAX = 8;

    /**
     * @param  Collection<int, string>|null  $scopedUnitIds
     * @return list<array{mk_unit_id: string, kode_penawaran: string, nama_mk: string, label_mk: string, semester_ke: int, dipilih: bool, jumlah_kelas: int}>
     */
    public function penawaranDefaultState(?string $kurikulumId, int $jumlahDefault = 1, ?Collection $scopedUnitIds = null): array
    {
        return $this->flattenPenawaranBySemester(
            $this->penawaranDefaultStateBySemester($kurikulumId, $jumlahDefault, $scopedUnitIds),
        );
    }

    /**
     * @param  Collection<int, string>|null  $scopedUnitIds
     * @return array<string, list<array{mk_unit_id: string, kode_penawaran: string, nama_mk: string, label_mk: string, semester_ke: int, dipilih: bool, jumlah_kelas: int}>>
     */
    public function penawaranDefaultStateBySemester(?string $kurikulumId, int $jumlahDefault = 1, ?Collection $scopedUnitIds = null): array
    {
        $scopedUnitIds ??= collect();
        $unitIds = $this->unitIdsPenawaranUntukKurikulum($kurikulumId, $scopedUnitIds);
        $jumlahDefault = max(1, min(26, $jumlahDefault));

        $grouped = collect(range(self::SEMESTER_KE_MIN, self::SEMESTER_KE_MAX))
            ->mapWithKeys(fn (int $ke): array => [(string) $ke => []])
            ->all();

        if ($unitIds->isEmpty()) {
            return $grouped;
        }

        MkUnit::query()
            ->with('mk')
            ->whereIn('academic_unit_id', $unitIds)
            ->where('is_active', true)
            ->get()
            ->each(function (MkUnit $mkUnit) use (&$grouped, $jumlahDefault): void {
                $semesterKe = $this->normalisasiSemesterKe($mkUnit->semester_ke);
                $namaMk = $mkUnit->mk?->nama ?? '—';

                $grouped[(string) $semesterKe][] = [
                    'mk_unit_id' => $mkUnit->id,
                    'kode_penawaran' => $mkUnit->kode,
                    'nama_mk' => $namaMk,
                    'label_mk' => sprintf('%s (%s)', $namaMk, $mkUnit->kode),
                    'semester_ke' => $semesterKe,
                    'dipilih' => false,
                    'jumlah_kelas' => $jumlahDefault,
                ];
            });

        foreach ($grouped as $semesterKe => $baris) {
            $grouped[$semesterKe] = collect($baris)
                ->sortBy('label_mk', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();
        }

        return $grouped;
    }

    /**
     * @return array<string, array{dipilih_semua: bool, jumlah_kelas: int}>
     */
    public function semesterKontrolDefaultState(int $jumlahDefault = 1): array
    {
        $jumlahDefault = max(1, min(26, $jumlahDefault));
        $kontrol = [];

        foreach (range(self::SEMESTER_KE_MIN, self::SEMESTER_KE_MAX) as $semesterKe) {
            $kontrol[(string) $semesterKe] = [
                'dipilih_semua' => false,
                'jumlah_kelas' => $jumlahDefault,
            ];
        }

        return $kontrol;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $bySemester
     * @return list<array<string, mixed>>
     */
    public function flattenPenawaranBySemester(array $bySemester): array
    {
        return collect(range(self::SEMESTER_KE_MIN, self::SEMESTER_KE_MAX))
            ->flatMap(fn (int $ke): array => $bySemester[(string) $ke] ?? [])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, list<array<string, mixed>>>|list<array<string, mixed>>  $penawaranState
     * @return list<array<string, mixed>>
     */
    public function normalisasiPenawaranState(array $penawaranState): array
    {
        if ($penawaranState === []) {
            return [];
        }

        if (array_is_list($penawaranState)) {
            return $penawaranState;
        }

        return $this->flattenPenawaranBySemester($penawaranState);
    }

    /**
     * Unit penawaran MK yang relevan dengan kurikulum terpilih dan scope user.
     *
     * @param  Collection<int, string>  $scopedUnitIds
     * @return Collection<int, string>
     */
    public function unitIdsPenawaranUntukKurikulum(?string $kurikulumId, Collection $scopedUnitIds): Collection
    {
        if (blank($kurikulumId) || $scopedUnitIds->isEmpty()) {
            return collect();
        }

        $kurikulumUnitId = $this->unitIdDariKurikulum($kurikulumId);

        if (blank($kurikulumUnitId)) {
            return collect();
        }

        return $scopedUnitIds->filter(function (string $unitId) use ($kurikulumUnitId): bool {
            if ($unitId === $kurikulumUnitId) {
                return true;
            }

            $unit = AcademicUnit::query()->find($unitId);

            if (! $unit instanceof AcademicUnit) {
                return false;
            }

            return in_array(
                $kurikulumUnitId,
                AcademicUnitScope::ancestorIdsIncludingSelf($unit),
                true,
            );
        })->values();
    }

    /**
     * @param  array<string, list<array<string, mixed>>>|list<array<string, mixed>>  $penawaranState
     * @return list<array{mk_unit_id: string, kode_penawaran: string, nama_mk: string, jumlah_kelas: int}>
     */
    public function penawaranTerpilih(array $penawaranState): array
    {
        return collect($this->normalisasiPenawaranState($penawaranState))
            ->filter(fn (array $baris): bool => (bool) ($baris['dipilih'] ?? false))
            ->map(function (array $baris): array {
                $jumlah = (int) ($baris['jumlah_kelas'] ?? 1);

                return [
                    'mk_unit_id' => (string) $baris['mk_unit_id'],
                    'kode_penawaran' => (string) ($baris['kode_penawaran'] ?? ''),
                    'nama_mk' => (string) ($baris['nama_mk'] ?? ''),
                    'jumlah_kelas' => max(1, min(26, $jumlah)),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array{mk_unit_id: string, kode_penawaran: string, nama_mk: string, jumlah_kelas: int}>  $terpilih
     * @return array{
     *     max_kolom: int,
     *     kolom_kelas: list<string>,
     *     baris: list<array<string, mixed>>,
     *     ringkasan: array{baru: int, duplikat: int, kosong: int}
     * }
     */
    public function buildPreview(string $semesterId, array $terpilih): array
    {
        if ($terpilih === [] || blank($semesterId)) {
            return [
                'max_kolom' => 0,
                'kolom_kelas' => [],
                'baris' => [],
                'ringkasan' => ['baru' => 0, 'duplikat' => 0, 'kosong' => 0],
            ];
        }

        $maxKolom = collect($terpilih)->max('jumlah_kelas') ?? 0;
        $kolomKelas = $this->kodeKelasHingga($maxKolom);
        $existing = $this->existingKelasMap($semesterId, collect($terpilih)->pluck('mk_unit_id')->all());

        $ringkasan = ['baru' => 0, 'duplikat' => 0, 'kosong' => 0];
        $baris = [];

        foreach ($terpilih as $penawaran) {
            $sel = [];

            foreach ($kolomKelas as $indeks => $kodeKelas) {
                if ($indeks >= $penawaran['jumlah_kelas']) {
                    $sel[] = ['kode' => $kodeKelas, 'status' => 'kosong', 'keterangan' => ''];
                    $ringkasan['kosong']++;

                    continue;
                }

                $key = $penawaran['mk_unit_id'].'|'.$kodeKelas;

                if (isset($existing[$key])) {
                    $sel[] = ['kode' => $kodeKelas, 'status' => 'duplikat', 'keterangan' => 'Sudah ada'];
                    $ringkasan['duplikat']++;

                    continue;
                }

                $sel[] = ['kode' => $kodeKelas, 'status' => 'baru', 'keterangan' => 'Akan dibuat'];
                $ringkasan['baru']++;
            }

            $baris[] = [
                'mk_unit_id' => $penawaran['mk_unit_id'],
                'nama_mk' => $penawaran['nama_mk'],
                'kode_penawaran' => $penawaran['kode_penawaran'],
                'jumlah_kelas' => $penawaran['jumlah_kelas'],
                'sel' => $sel,
            ];
        }

        return [
            'max_kolom' => $maxKolom,
            'kolom_kelas' => $kolomKelas,
            'baris' => $baris,
            'ringkasan' => $ringkasan,
        ];
    }

    /**
     * @param  array<string, list<array<string, mixed>>>|list<array<string, mixed>>  $penawaranState
     */
    public function renderPreviewHtml(string $semesterId, array $penawaranState): HtmlString
    {
        $terpilih = $this->penawaranTerpilih($penawaranState);
        $preview = $this->buildPreview($semesterId, $terpilih);

        if ($terpilih === []) {
            return new HtmlString('<p class="text-sm">Pilih minimal satu mata kuliah pada langkah sebelumnya.</p>');
        }

        if ($preview['baris'] === []) {
            return new HtmlString('<p class="text-sm">Tidak ada kelas yang dapat dibuat.</p>');
        }

        $ringkasan = sprintf(
            '<p class="text-sm" style="margin-bottom:8px;"><strong>%d mata kuliah dipilih</strong> · '
            .'<span style="color:#16a34a;font-weight:600;">%d kelas baru</span> · '
            .'<span style="color:#d97706;font-weight:600;">%d duplikat</span></p>',
            count($terpilih),
            $preview['ringkasan']['baru'],
            $preview['ringkasan']['duplikat'],
        );

        $header = '<th style="padding:6px 10px;text-align:left;">Mata kuliah</th>'
            .'<th style="padding:6px 10px;text-align:left;">Kode</th>';

        foreach ($preview['kolom_kelas'] as $kode) {
            $header .= '<th style="padding:6px 10px;text-align:center;">'.e($kode).'</th>';
        }

        $body = '';

        foreach ($preview['baris'] as $baris) {
            $cells = '<td style="padding:6px 10px;">'.e($baris['nama_mk']).'</td>'
                .'<td style="padding:6px 10px;white-space:nowrap;">'.e($baris['kode_penawaran']).'</td>';

            foreach ($baris['sel'] as $sel) {
                [$label, $warna] = match ($sel['status']) {
                    'baru' => ['Baru', '#16a34a'],
                    'duplikat' => ['Ada', '#d97706'],
                    default => ['—', 'rgba(128,128,128,.45)'],
                };

                $cells .= '<td style="padding:6px 10px;text-align:center;">'
                    .'<span style="font-weight:600;color:'.$warna.';">'.$label.'</span>'
                    .'</td>';
            }

            $body .= '<tr style="border-top:1px solid rgba(128,128,128,.25);">'.$cells.'</tr>';
        }

        $tabel = '<div style="overflow-x:auto;max-height:360px;overflow-y:auto;">'
            .'<table style="width:100%;font-size:12px;border-collapse:collapse;">'
            .'<thead><tr style="text-align:left;background:rgba(128,128,128,.08);">'.$header.'</tr></thead>'
            .'<tbody>'.$body.'</tbody></table></div>';

        return new HtmlString($ringkasan.$tabel);
    }

    /**
     * @param  array<string, list<array<string, mixed>>>|list<array<string, mixed>>  $penawaranState
     * @return array{dibuat: int, dilewati: int, gagal: list<string>}
     */
    public function jalankan(string $semesterId, array $penawaranState, string $modeDuplikat = 'lewati'): array
    {
        $terpilih = $this->penawaranTerpilih($penawaranState);
        $preview = $this->buildPreview($semesterId, $terpilih);

        $dibuat = 0;
        $dilewati = 0;
        $gagal = [];

        $mkUnits = MkUnit::query()
            ->with('mk')
            ->whereIn('id', collect($terpilih)->pluck('mk_unit_id'))
            ->get()
            ->keyBy('id');

        foreach ($preview['baris'] as $baris) {
            foreach ($baris['sel'] as $sel) {
                if ($sel['status'] === 'kosong') {
                    continue;
                }

                if ($sel['status'] === 'duplikat') {
                    if ($modeDuplikat !== 'timpa') {
                        $dilewati++;

                        continue;
                    }

                    $gagal[] = "{$baris['kode_penawaran']}/{$sel['kode']}: kelas sudah ada (timpa belum didukung).";

                    continue;
                }

                $mkUnit = $mkUnits->get($baris['mk_unit_id']);

                if (! $mkUnit instanceof MkUnit) {
                    $gagal[] = "{$baris['kode_penawaran']}/{$sel['kode']}: penawaran MK tidak ditemukan.";

                    continue;
                }

                KelasMk::query()->create([
                    'mk_unit_id' => $mkUnit->id,
                    'semester_id' => $semesterId,
                    'kode_kelas' => $sel['kode'],
                    'koordinator_mk_id' => $mkUnit->mk?->koordinator_mk_id,
                ]);

                $dibuat++;
            }
        }

        return compact('dibuat', 'dilewati', 'gagal');
    }

    /**
     * @return list<string>
     */
    public function kodeKelasHingga(int $jumlah): array
    {
        $jumlah = max(0, min(26, $jumlah));

        return collect(range(0, $jumlah - 1))
            ->map(fn (int $indeks): string => chr(65 + $indeks))
            ->values()
            ->all();
    }

    public function labelSemesterKe(int $semesterKe): string
    {
        return 'Semester '.$semesterKe;
    }

    protected function normalisasiSemesterKe(?int $semesterKe): int
    {
        if ($semesterKe === null || $semesterKe < self::SEMESTER_KE_MIN) {
            return self::SEMESTER_KE_MIN;
        }

        if ($semesterKe > self::SEMESTER_KE_MAX) {
            return self::SEMESTER_KE_MAX;
        }

        return $semesterKe;
    }

    protected function unitIdDariKurikulum(?string $kurikulumId): ?string
    {
        if (blank($kurikulumId)) {
            return null;
        }

        return Kurikulum::query()->whereKey($kurikulumId)->value('academic_unit_id');
    }

    /**
     * @param  list<string>  $mkUnitIds
     * @return array<string, true>
     */
    protected function existingKelasMap(string $semesterId, array $mkUnitIds): array
    {
        if ($mkUnitIds === []) {
            return [];
        }

        return KelasMk::query()
            ->where('semester_id', $semesterId)
            ->whereIn('mk_unit_id', $mkUnitIds)
            ->get(['mk_unit_id', 'kode_kelas'])
            ->mapWithKeys(fn (KelasMk $kelas): array => [
                $kelas->mk_unit_id.'|'.$kelas->kode_kelas => true,
            ])
            ->all();
    }
}
