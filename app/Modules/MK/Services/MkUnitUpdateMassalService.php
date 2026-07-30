<?php

namespace App\Modules\MK\Services;

use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Models\MkUnit;

class MkUnitUpdateMassalService
{
    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     * @return array{status: string, keterangan: string, existing_id?: ?string, dedup?: ?string}
     */
    public function resolveBaris(array $data, array $context): array
    {
        $unitId = $this->unitIdDariKonteks($context);
        $kurikulumId = $context['import_kurikulum_id'] ?? null;

        if (blank($unitId) || blank($kurikulumId)) {
            return [
                'status' => 'invalid',
                'keterangan' => 'Kurikulum prodi yang dikerjakan belum ditetapkan.',
            ];
        }

        if (! ctype_digit($data['semester_ke'])) {
            return [
                'status' => 'invalid',
                'keterangan' => 'Semester ke- harus angka bulat.',
            ];
        }

        $semester = (int) $data['semester_ke'];

        if ($semester < 1 || $semester > 14) {
            return [
                'status' => 'invalid',
                'keterangan' => 'Semester ke- harus antara 1 dan 14.',
            ];
        }

        $namaNormal = mb_strtolower(trim($data['nama']));

        $mkUnit = MkUnit::query()
            ->where('academic_unit_id', $unitId)
            ->where('kurikulum_id', $kurikulumId)
            ->whereHas('mk', fn ($query) => $query->whereRaw('LOWER(TRIM(nama)) = ?', [$namaNormal]))
            ->first();

        if (! $mkUnit) {
            return [
                'status' => 'invalid',
                'keterangan' => 'Penawaran MK tidak ditemukan untuk nama mata kuliah ini.',
                'dedup' => $namaNormal,
            ];
        }

        $kode = trim($data['kode']);

        if ($kode === '') {
            return [
                'status' => 'invalid',
                'keterangan' => 'Kode wajib diisi.',
                'dedup' => $namaNormal,
            ];
        }

        if (mb_strlen($kode) > 20) {
            return [
                'status' => 'invalid',
                'keterangan' => 'Kode maksimal 20 karakter.',
                'dedup' => $namaNormal,
            ];
        }

        $kodeBentrok = MkUnit::query()
            ->where('academic_unit_id', $unitId)
            ->where('kurikulum_id', $kurikulumId)
            ->where('kode', $kode)
            ->whereKeyNot($mkUnit->id)
            ->exists();

        if ($kodeBentrok) {
            return [
                'status' => 'invalid',
                'keterangan' => 'Kode sudah dipakai penawaran MK lain pada prodi ini.',
                'dedup' => $namaNormal,
            ];
        }

        $kodeSama = $mkUnit->kode === $kode;
        $semesterSama = (int) $mkUnit->semester_ke === $semester;

        if ($kodeSama && $semesterSama) {
            return [
                'status' => 'duplikat',
                'keterangan' => 'Kode dan semester sudah sama; tidak ada perubahan.',
                'existing_id' => $mkUnit->id,
                'dedup' => $namaNormal,
            ];
        }

        return [
            'status' => 'baru',
            'keterangan' => 'Siap diperbarui.',
            'existing_id' => $mkUnit->id,
            'dedup' => $namaNormal,
        ];
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    public function perbaruiPenawaran(MkUnit $mkUnit, array $data): void
    {
        $mkUnit->update([
            'kode' => trim($data['kode']),
            'semester_ke' => (int) $data['semester_ke'],
        ]);
    }

    public function cariPenawaran(string $unitId, string $kurikulumId, string $nama): ?MkUnit
    {
        $namaNormal = mb_strtolower(trim($nama));

        return MkUnit::query()
            ->where('academic_unit_id', $unitId)
            ->where('kurikulum_id', $kurikulumId)
            ->whereHas('mk', fn ($query) => $query->whereRaw('LOWER(TRIM(nama)) = ?', [$namaNormal]))
            ->first();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function unitIdDariKonteks(array $context): ?string
    {
        $kurikulumId = $context['import_kurikulum_id'] ?? null;

        if (blank($kurikulumId)) {
            return null;
        }

        $kurikulum = Kurikulum::query()->with('academicUnit')->find($kurikulumId);

        if (! $kurikulum?->academicUnit?->isProdi()) {
            return null;
        }

        return $kurikulum->academic_unit_id;
    }
}
