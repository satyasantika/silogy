<?php

namespace App\Modules\Penilaian\Services;

use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\KomponenPenilaianSemester;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;

class AsesmenImporService
{
    /**
     * Buat atau perbarui asesmen (komponen penilaian) dari baris impor.
     * Satu baris = satu definisi untuk seluruh kelas MK pada mata kuliah +
     * semester ini (tidak diduplikasi per kelas).
     */
    public static function buatAtauPerbaruiKomponen(array $data, string $mkId, string $semesterId): KomponenPenilaian
    {
        $evaluasi = EvaluasiResolverService::cariDariKodeAtauNama($data['komponen_penilaian']);

        // Identitas Asesmen kini (mk_id, kode) — tanpa semester. Yang
        // bersemester adalah berlakunya dan bobotnya, di pivot.
        $komponen = KomponenPenilaian::query()->updateOrCreate(
            ['mk_id' => $mkId, 'kode' => $data['kode_asesmen']],
            [
                'evaluasi_id' => $evaluasi?->id,
                'nama' => $data['nama_tugas'],
            ],
        );

        KomponenPenilaianSemester::query()->updateOrCreate(
            ['komponen_penilaian_id' => $komponen->id, 'semester_id' => $semesterId],
            ['bobot' => (float) $data['bobot_tugas']],
        );

        return $komponen;
    }

    /**
     * Terapkan pemetaan Sub-CPMK opsional dari baris impor.
     */
    public static function terapkanPemetaanSubcpmk(KomponenPenilaian $komponen, array $data, string $mkId, string $semesterId): void
    {
        $kodeSubcpmk = trim($data['kode_subcpmk'] ?? '');

        if ($kodeSubcpmk === '') {
            return;
        }

        $subcpmk = SubcpmkAsesmenPemetaanService::cariSubcpmkUntukMk($kodeSubcpmk, $mkId, $semesterId);

        if ($subcpmk === null) {
            return;
        }

        SubcpmkAsesmenPemetaanService::petakanSubcpmk($komponen, $subcpmk, $semesterId);
    }

    /**
     * Deteksi duplikat baris impor asesmen (konteks MK + semester).
     *
     * @return array{status: string, keterangan: string, existing_id?: ?string, dedup?: ?string}
     */
    public static function resolveBaris(array $data, string $mkId, string $semesterId): array
    {
        $kodeAsesmen = trim($data['kode_asesmen']);
        $kodeSubcpmk = trim($data['kode_subcpmk'] ?? '');
        $dedup = $kodeSubcpmk !== ''
            ? mb_strtolower($kodeAsesmen.'/'.$kodeSubcpmk)
            : mb_strtolower($kodeAsesmen);

        $komponen = KomponenPenilaian::query()
            ->where('mk_id', $mkId)
            ->untukSemester($semesterId)
            ->where('kode', $kodeAsesmen)
            ->first();

        if ($kodeSubcpmk === '') {
            if ($komponen) {
                return [
                    'status' => 'duplikat',
                    'keterangan' => 'Kode asesmen sudah ada pada mata kuliah dan semester ini.',
                    'existing_id' => $komponen->id,
                    'dedup' => $dedup,
                ];
            }

            return ['status' => 'baru', 'keterangan' => '', 'dedup' => $dedup];
        }

        if ($komponen) {
            $subcpmk = SubcpmkAsesmenPemetaanService::cariSubcpmkUntukMk($kodeSubcpmk, $mkId, $semesterId);

            if ($subcpmk && SubcpmkKomponenPenilaian::query()
                ->where('komponen_penilaian_id', $komponen->id)
                ->where('subcpmk_id', $subcpmk->id)
                ->where('semester_id', $semesterId)
                ->exists()) {
                return [
                    'status' => 'duplikat',
                    'keterangan' => 'Sub-CPMK sudah dipetakan ke asesmen ini.',
                    'existing_id' => $komponen->id,
                    'dedup' => $dedup,
                ];
            }
        }

        return ['status' => 'baru', 'keterangan' => '', 'dedup' => $dedup];
    }

    /**
     * Perbarui asesmen berkode sama pada MK + semester impor (mode timpa).
     */
    public static function perbarui(string $kodeAsesmen, array $data, string $mkId, string $semesterId): void
    {
        $evaluasi = EvaluasiResolverService::cariDariKodeAtauNama($data['komponen_penilaian']);

        $komponen = KomponenPenilaian::query()
            ->where('mk_id', $mkId)
            ->untukSemester($semesterId)
            ->where('kode', $kodeAsesmen)
            ->first();

        if (! $komponen instanceof KomponenPenilaian) {
            return;
        }

        $komponen->update([
            'evaluasi_id' => $evaluasi?->id,
            'nama' => $data['nama_tugas'],
        ]);

        KomponenPenilaianSemester::query()
            ->where('komponen_penilaian_id', $komponen->id)
            ->where('semester_id', $semesterId)
            ->update(['bobot' => (float) $data['bobot_tugas']]);

        self::terapkanPemetaanSubcpmk($komponen->fresh(), $data, $mkId, $semesterId);
    }
}
