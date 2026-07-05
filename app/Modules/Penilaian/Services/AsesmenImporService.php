<?php

namespace App\Modules\Penilaian\Services;

use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Support\PenawaranMkScope;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use Illuminate\Support\Collection;

class AsesmenImporService
{
    /**
     * Buat atau perbarui asesmen (komponen penilaian) dari baris impor.
     */
    public static function buatAtauPerbaruiKomponen(array $data, string $kelasMkId): KomponenPenilaian
    {
        $evaluasi = EvaluasiResolverService::cariDariKodeAtauNama($data['komponen_penilaian']);

        $payload = [
            'evaluasi_id' => $evaluasi?->id,
            'nama' => $data['nama_tugas'],
            'bobot' => (float) $data['bobot_tugas'],
        ];

        $existing = KomponenPenilaian::query()
            ->where('kelas_mk_id', $kelasMkId)
            ->where('kode', $data['kode_asesmen'])
            ->first();

        if ($existing) {
            $existing->update($payload);

            return $existing->fresh();
        }

        return KomponenPenilaian::query()->create([
            'kelas_mk_id' => $kelasMkId,
            'kode' => $data['kode_asesmen'],
            ...$payload,
        ]);
    }

    /**
     * Buat atau perbarui asesmen pada semua kelas MK untuk MK + semester impor.
     *
     * @return Collection<int, KomponenPenilaian>
     */
    public static function buatAtauPerbaruiSemuaKelas(array $data, string $mkId, string $semesterId): Collection
    {
        $kelasMks = PenawaranMkScope::kelasMkUntukMkSemester($mkId, $semesterId);

        return $kelasMks->map(
            fn (KelasMk $kelasMk): KomponenPenilaian => self::buatAtauPerbaruiKomponen($data, $kelasMk->id),
        );
    }

    /**
     * Terapkan pemetaan Sub-CPMK opsional dari baris impor.
     */
    public static function terapkanPemetaanSubcpmk(KomponenPenilaian $komponen, array $data, KelasMk $kelasMk): void
    {
        $kodeSubcpmk = trim($data['kode_subcpmk'] ?? '');

        if ($kodeSubcpmk === '') {
            return;
        }

        $subcpmk = SubcpmkAsesmenPemetaanService::cariSubcpmkUntukKelas($kodeSubcpmk, $kelasMk);

        if ($subcpmk === null) {
            return;
        }

        SubcpmkAsesmenPemetaanService::petakanSubcpmk($komponen, $subcpmk);
    }

    /**
     * Terapkan pemetaan Sub-CPMK ke semua komponen (per kelas MK) dari baris impor.
     *
     * @param  Collection<int, KomponenPenilaian>  $komponens
     */
    public static function terapkanPemetaanSubcpmkSemuaKelas(Collection $komponens, array $data, KelasMk $kelasMk): void
    {
        foreach ($komponens as $komponen) {
            self::terapkanPemetaanSubcpmk($komponen, $data, $kelasMk);
        }
    }

    /**
     * Deteksi duplikat baris impor asesmen (konteks MK + semester).
     *
     * @return array{status: string, keterangan: string, existing_id?: ?string, dedup?: ?string}
     */
    public static function resolveBaris(array $data, string $mkId, string $semesterId): array
    {
        $kelasMks = PenawaranMkScope::kelasMkUntukMkSemester($mkId, $semesterId);

        if ($kelasMks->isEmpty()) {
            return [
                'status' => 'invalid',
                'keterangan' => 'Belum ada kelas MK untuk mata kuliah dan semester ini.',
            ];
        }

        $kelasMk = $kelasMks->first();
        $kodeAsesmen = trim($data['kode_asesmen']);
        $kodeSubcpmk = trim($data['kode_subcpmk'] ?? '');
        $dedup = $kodeSubcpmk !== ''
            ? mb_strtolower($kodeAsesmen.'/'.$kodeSubcpmk)
            : mb_strtolower($kodeAsesmen);

        $komponen = KomponenPenilaian::query()
            ->whereIn('kelas_mk_id', $kelasMks->pluck('id'))
            ->where('kode', $kodeAsesmen)
            ->first();

        if ($kodeSubcpmk === '') {
            if ($komponen) {
                return [
                    'status' => 'duplikat',
                    'keterangan' => 'Kode asesmen sudah ada pada kelas MK semester ini.',
                    'existing_id' => $komponen->id,
                    'dedup' => $dedup,
                ];
            }

            return ['status' => 'baru', 'keterangan' => '', 'dedup' => $dedup];
        }

        if ($komponen) {
            $subcpmk = SubcpmkAsesmenPemetaanService::cariSubcpmkUntukKelas($kodeSubcpmk, $kelasMk);

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
     * Perbarui semua asesmen dengan kode sama pada MK + semester impor (mode timpa).
     */
    public static function perbaruiSemuaKelas(string $kodeAsesmen, array $data, string $mkId, string $semesterId): void
    {
        $evaluasi = EvaluasiResolverService::cariDariKodeAtauNama($data['komponen_penilaian']);
        $kelasMks = PenawaranMkScope::kelasMkUntukMkSemester($mkId, $semesterId);
        $kelasMk = $kelasMks->first();

        if (! $kelasMk instanceof KelasMk) {
            return;
        }

        $komponens = KomponenPenilaian::query()
            ->whereIn('kelas_mk_id', $kelasMks->pluck('id'))
            ->where('kode', $kodeAsesmen)
            ->get();

        foreach ($komponens as $komponen) {
            $komponen->update([
                'evaluasi_id' => $evaluasi?->id,
                'nama' => $data['nama_tugas'],
                'bobot' => (float) $data['bobot_tugas'],
            ]);

            self::terapkanPemetaanSubcpmk($komponen->fresh(), $data, $kelasMk);
        }
    }
}
