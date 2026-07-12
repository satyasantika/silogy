<?php

namespace App\Modules\Penilaian\Services;

use App\Modules\MK\Support\PenawaranMkScope;
use App\Modules\Penilaian\Models\KomponenPenilaian;
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

        $payload = [
            'evaluasi_id' => $evaluasi?->id,
            'nama' => $data['nama_tugas'],
            'bobot' => (float) $data['bobot_tugas'],
        ];

        return KomponenPenilaian::query()->updateOrCreate(
            ['mk_id' => $mkId, 'semester_id' => $semesterId, 'kode' => $data['kode_asesmen']],
            $payload,
        );
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

        SubcpmkAsesmenPemetaanService::petakanSubcpmk($komponen, $subcpmk);
    }

    /**
     * Deteksi duplikat baris impor asesmen (konteks MK + semester).
     *
     * @return array{status: string, keterangan: string, existing_id?: ?string, dedup?: ?string}
     */
    public static function resolveBaris(array $data, string $mkId, string $semesterId): array
    {
        if (PenawaranMkScope::kelasMkUntukMkSemester($mkId, $semesterId)->isEmpty()) {
            return [
                'status' => 'invalid',
                'keterangan' => 'Belum ada kelas MK untuk mata kuliah dan semester ini.',
            ];
        }

        $kodeAsesmen = trim($data['kode_asesmen']);
        $kodeSubcpmk = trim($data['kode_subcpmk'] ?? '');
        $dedup = $kodeSubcpmk !== ''
            ? mb_strtolower($kodeAsesmen.'/'.$kodeSubcpmk)
            : mb_strtolower($kodeAsesmen);

        $komponen = KomponenPenilaian::query()
            ->where('mk_id', $mkId)
            ->where('semester_id', $semesterId)
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
            ->where('semester_id', $semesterId)
            ->where('kode', $kodeAsesmen)
            ->first();

        if (! $komponen instanceof KomponenPenilaian) {
            return;
        }

        $komponen->update([
            'evaluasi_id' => $evaluasi?->id,
            'nama' => $data['nama_tugas'],
            'bobot' => (float) $data['bobot_tugas'],
        ]);

        self::terapkanPemetaanSubcpmk($komponen->fresh(), $data, $mkId, $semesterId);
    }
}
