<?php

namespace App\Modules\MK\Services;

use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AdaptasiMkMassalService
{
    /**
     * @param  array{
     *     adaptasi_unit_id?: string|null,
     *     kurikulum_univ_id?: string|null,
     *     kurikulum_fakultas_id?: string|null,
     *     kurikulum_prodi_id?: string|null,
     * }  $context
     * @return list<array{
     *     line: int,
     *     mk_id: string,
     *     nama: string,
     *     sumber: string,
     *     status: string,
     *     keterangan: string,
     *     existing_id: ?string,
     * }>
     */
    public function resolveBaris(array $context): array
    {
        $unitId = $context['adaptasi_unit_id'] ?? null;

        if (blank($unitId)) {
            return [[
                'line' => 1,
                'mk_id' => '',
                'nama' => '',
                'sumber' => '',
                'status' => 'invalid',
                'keterangan' => 'Pilih unit penawaran prodi terlebih dahulu.',
                'existing_id' => null,
            ]];
        }

        $prodi = AcademicUnit::query()->with('parent.parent')->find($unitId);

        if (! $prodi instanceof AcademicUnit || ! $prodi->isProdi()) {
            return [[
                'line' => 1,
                'mk_id' => '',
                'nama' => '',
                'sumber' => '',
                'status' => 'invalid',
                'keterangan' => 'Unit penawaran harus prodi.',
                'existing_id' => null,
            ]];
        }

        $sumber = $this->konteksSumber($context, $prodi);

        if (is_string($sumber)) {
            return [[
                'line' => 1,
                'mk_id' => '',
                'nama' => '',
                'sumber' => '',
                'status' => 'invalid',
                'keterangan' => $sumber,
                'existing_id' => null,
            ]];
        }

        if ($sumber->isEmpty()) {
            return [[
                'line' => 1,
                'mk_id' => '',
                'nama' => '',
                'sumber' => '',
                'status' => 'invalid',
                'keterangan' => 'Tidak ada mata kuliah aktif pada kurikulum sumber yang dipilih.',
                'existing_id' => null,
            ]];
        }

        $existingByMk = MkUnit::query()
            ->where('academic_unit_id', $prodi->id)
            ->pluck('id', 'mk_id');

        $rows = [];
        $line = 1;
        $seenMk = [];

        foreach ($sumber as $item) {
            /** @var Mk $mk */
            $mk = $item['mk'];
            $labelSumber = $item['sumber'];

            if (isset($seenMk[$mk->id])) {
                continue;
            }

            $seenMk[$mk->id] = true;
            $nama = trim($mk->nama);

            if ($nama === '') {
                $rows[] = [
                    'line' => $line++,
                    'mk_id' => $mk->id,
                    'nama' => '—',
                    'sumber' => $labelSumber,
                    'status' => 'invalid',
                    'keterangan' => 'Nama mata kuliah kosong.',
                    'existing_id' => null,
                ];

                continue;
            }

            $existingId = $existingByMk->get($mk->id);

            if ($existingId) {
                $rows[] = [
                    'line' => $line++,
                    'mk_id' => $mk->id,
                    'nama' => $nama,
                    'sumber' => $labelSumber,
                    'status' => 'duplikat',
                    'keterangan' => 'MK ini sudah ditawarkan pada prodi.',
                    'existing_id' => (string) $existingId,
                ];

                continue;
            }

            $rows[] = [
                'line' => $line++,
                'mk_id' => $mk->id,
                'nama' => $nama,
                'sumber' => $labelSumber,
                'status' => 'baru',
                'keterangan' => 'Siap diadaptasi.',
                'existing_id' => null,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $context
     * @return array{dibuat: int, diperbarui: int, dilewati: int, gagal: list<string>}
     */
    public function jalankan(array $rows, string $modeDuplikat, array $context): array
    {
        $unitId = (string) ($context['adaptasi_unit_id'] ?? '');

        $dibuat = 0;
        $diperbarui = 0;
        $dilewati = 0;
        $gagal = [];

        foreach ($rows as $row) {
            if ($row['status'] === 'invalid') {
                $gagal[] = "Baris {$row['line']}: {$row['keterangan']}";

                continue;
            }

            if ($row['status'] === 'duplikat') {
                if ($modeDuplikat !== 'timpa') {
                    $dilewati++;

                    continue;
                }

                $existingId = $row['existing_id'] ?? null;

                if (blank($existingId)) {
                    $gagal[] = "Baris {$row['line']}: data penawaran lama tidak ditemukan.";

                    continue;
                }

                MkUnit::query()->whereKey($existingId)->update(['is_active' => true]);
                $diperbarui++;

                continue;
            }

            MkUnit::query()->create([
                'academic_unit_id' => $unitId,
                'mk_id' => $row['mk_id'],
                'kode' => $this->generateKodeUnik($unitId, (string) $row['nama']),
                'semester_ke' => null,
                'is_active' => true,
            ]);

            $dibuat++;
        }

        return compact('dibuat', 'diperbarui', 'dilewati', 'gagal');
    }

    public function generateKodeUnik(string $unitId, string $nama): string
    {
        $kata = collect(preg_split('/\s+/u', trim($nama)) ?: [])
            ->filter()
            ->take(4)
            ->map(fn (string $kata): string => mb_strtoupper(mb_substr($kata, 0, 1)))
            ->join('');

        $base = Str::upper(Str::slug($kata !== '' ? $kata : Str::limit($nama, 12, ''), ''));

        if ($base === '') {
            $base = 'MK';
        }

        $base = Str::limit($base, 15, '');
        $kode = $base;
        $suffix = 1;

        while (MkUnit::query()->where('academic_unit_id', $unitId)->where('kode', $kode)->exists()) {
            $kode = Str::limit($base, 12, '').'-'.$suffix;
            $suffix++;
        }

        return $kode;
    }

    /**
     * @return array<string, string>
     */
    public function kurikulumOptionsUntukAncestor(AcademicUnit $prodi, string $unitType): array
    {
        $ancestor = $this->ancestorUnit($prodi, $unitType);

        if (! $ancestor) {
            return [];
        }

        return Kurikulum::query()
            ->where('academic_unit_id', $ancestor->id)
            ->orderByDesc('is_active')
            ->orderByDesc('tahun')
            ->orderBy('nama')
            ->get()
            ->mapWithKeys(fn (Kurikulum $kurikulum): array => [
                $kurikulum->id => KurikulumTerpilih::label($kurikulum),
            ])
            ->all();
    }

    public function defaultKurikulumIdUntukAncestor(AcademicUnit $prodi, string $unitType): ?string
    {
        $options = $this->kurikulumOptionsUntukAncestor($prodi, $unitType);

        if ($options === []) {
            return null;
        }

        $ancestor = $this->ancestorUnit($prodi, $unitType);

        if (! $ancestor) {
            return array_key_first($options);
        }

        $aktif = Kurikulum::query()
            ->where('academic_unit_id', $ancestor->id)
            ->where('is_active', true)
            ->orderByDesc('tahun')
            ->value('id');

        return $aktif ?? array_key_first($options);
    }

    public function ancestorUnit(AcademicUnit $from, string $type): ?AcademicUnit
    {
        $current = $from->loadMissing('parent');

        while ($current) {
            if ($current->type === $type) {
                return $current;
            }

            $current = $current->parent;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return Collection<int, array{mk: Mk, sumber: string}>|string
     */
    protected function konteksSumber(array $context, AcademicUnit $prodi): Collection|string
    {
        $pilihan = [
            'kurikulum_univ_id' => 'university',
            'kurikulum_fakultas_id' => 'faculty',
            'kurikulum_prodi_id' => 'study_program',
        ];

        $labelLevel = [
            'university' => 'Universitas',
            'faculty' => 'Fakultas',
            'study_program' => 'Prodi',
        ];

        $adaPilihan = false;
        $items = collect();

        foreach ($pilihan as $key => $type) {
            $kurikulumId = $context[$key] ?? null;

            if (blank($kurikulumId)) {
                continue;
            }

            $adaPilihan = true;

            $kurikulum = Kurikulum::query()->with('academicUnit')->find($kurikulumId);

            if (! $kurikulum) {
                return 'Kurikulum sumber tidak ditemukan.';
            }

            $ancestor = $this->ancestorUnit($prodi, $type);

            if (! $ancestor || $kurikulum->academic_unit_id !== $ancestor->id) {
                return "Kurikulum {$labelLevel[$type]} tidak sesuai dengan unit penawaran.";
            }

            $unitNama = $kurikulum->academicUnit?->nama ?? '—';
            $labelSumber = "{$labelLevel[$type]} — {$unitNama}";

            $mks = Mk::query()
                ->where('academic_unit_id', $kurikulum->academic_unit_id)
                ->where('is_active', true)
                ->orderBy('nama')
                ->get();

            foreach ($mks as $mk) {
                $items->push([
                    'mk' => $mk,
                    'sumber' => $labelSumber,
                ]);
            }
        }

        if (! $adaPilihan) {
            return 'Pilih minimal satu kurikulum sumber (universitas, fakultas, atau prodi).';
        }

        return $items;
    }
}
