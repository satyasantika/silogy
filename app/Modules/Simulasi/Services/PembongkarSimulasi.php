<?php

namespace App\Modules\Simulasi\Services;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\MK\Models\PerubahanCpmkRequest;
use App\Modules\Simulasi\DataObjects\HasilPembongkaran;
use App\Modules\Simulasi\Models\SimulasiArtefak;
use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Support\Ranah;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Membongkar data simulasi, dan HANYA data simulasi.
 *
 * Aturan mainnya cuma satu: yang dihapus adalah baris yang tercatat di buku
 * besar, dalam urutan terbalik dari saat dibuat. Urutan terbalik itu sendiri
 * yang memenuhi setiap batasan RESTRICT di skema — mahasiswa dihapus sebelum
 * unitnya, unit anak sebelum induknya, pengguna sebelum unitnya — tanpa perlu
 * satu pun daftar urutan yang ditulis tangan dan bisa basi.
 *
 * Baris yang sudah lenyap lebih dulu lewat CASCADE dilewati diam-diam; baris
 * yang masih dirujuk data di luar buku besar TIDAK dipaksa hapus, melainkan
 * dilaporkan. Di situlah jaminan "tidak menyentuh data nyata" terlihat bekerja.
 */
class PembongkarSimulasi
{
    public function bongkar(SimulasiJalan $jalan, bool $terapkan = true): HasilPembongkaran
    {
        if (! $terapkan) {
            return $this->rencana($jalan);
        }

        $dihapus = [];
        $dilewati = [];

        // Ranah sandbox: model-model akar ber-global-scope tidak akan "ditemukan"
        // dari ranah inti (Super Admin), sehingga find() di hapusSatu() pasti
        // mengira barisnya sudah lenyap dan melewatinya.
        Ranah::sebagai((string) $jalan->getKey(), fn () => DB::transaction(function () use ($jalan, &$dihapus, &$dilewati): void {
            $this->sapuUsulanPerubahanCpmk($jalan);

            // chunkByIdDesc, bukan chunkById: urutan terbalik itulah yang
            // memenuhi seluruh batasan RESTRICT di skema.
            SimulasiArtefak::query()
                ->where('simulasi_jalan_id', $jalan->getKey())
                ->chunkByIdDesc(500, function ($kumpulan) use (&$dihapus, &$dilewati): void {
                    foreach ($kumpulan as $artefak) {
                        $this->hapusSatu($artefak, $dihapus, $dilewati);
                    }
                });

            SimulasiArtefak::query()->where('simulasi_jalan_id', $jalan->getKey())->delete();

            $jalan->forceFill([
                'status' => SimulasiJalan::STATUS_DIBONGKAR,
                'dibongkar_pada' => now(),
                'ringkasan' => array_merge($jalan->ringkasan ?? [], [
                    'dihapus' => $dihapus,
                    'dilewati' => $dilewati,
                ]),
            ])->save();
        }));

        return new HasilPembongkaran($dihapus, $dilewati, true);
    }

    /**
     * Laporan kering: apa yang AKAN dihapus, tanpa menyentuh apa pun.
     * Dipakai `simulasi:hapus` tanpa --terapkan dan oleh modal konfirmasi.
     */
    protected function rencana(SimulasiJalan $jalan): HasilPembongkaran
    {
        $rencana = [];

        // Query builder, bukan Eloquent: hasil agregat tidak punya properti
        // model sehingga analisis statis mempersoalkannya.
        $cacah = DB::table('simulasi_artefak')
            ->where('simulasi_jalan_id', $jalan->getKey())
            ->groupBy('model_type')
            ->pluck(DB::raw('count(*)'), 'model_type');

        foreach ($cacah as $kelas => $jumlah) {
            $rencana[$this->labelEntitas((string) $kelas)] = (int) $jumlah;
        }

        return new HasilPembongkaran($rencana, [], false);
    }

    /**
     * perubahan_cpmk_requests menahan users dan academic_units dengan RESTRICT,
     * dan barisnya tidak pernah dibuat pembangun — ia lahir dari pengunjung yang
     * mencoba peran Koordinator MK. Tanpa sapuan ini, sebuah usulan yang diajukan
     * seorang pencoba akan mengunci seluruh pembongkaran.
     */
    protected function sapuUsulanPerubahanCpmk(SimulasiJalan $jalan): void
    {
        $idUnit = $this->idArtefak($jalan, AcademicUnit::class);
        $idUser = $this->idArtefak($jalan, User::class);

        if ($idUnit === [] && $idUser === []) {
            return;
        }

        PerubahanCpmkRequest::query()
            ->where(function ($query) use ($idUnit, $idUser): void {
                if ($idUnit !== []) {
                    $query->orWhereIn('academic_unit_id', $idUnit);
                }

                if ($idUser !== []) {
                    $query->orWhereIn('diajukan_oleh_id', $idUser)
                        ->orWhereIn('ditinjau_oleh_id', $idUser);
                }
            })
            ->get()
            ->each(fn (PerubahanCpmkRequest $usulan) => $usulan->delete());
    }

    /**
     * @return list<string>
     */
    protected function idArtefak(SimulasiJalan $jalan, string $kelas): array
    {
        return SimulasiArtefak::query()
            ->where('simulasi_jalan_id', $jalan->getKey())
            ->where('model_type', $kelas)
            ->pluck('model_uuid')
            ->all();
    }

    /**
     * @param  array<string, int>  $dihapus
     * @param  list<array{model: string, id: string, alasan: string}>  $dilewati
     */
    protected function hapusSatu(SimulasiArtefak $artefak, array &$dihapus, array &$dilewati): void
    {
        $kelas = $artefak->model_type;

        if (! class_exists($kelas) || ! is_subclass_of($kelas, Model::class)) {
            $dilewati[] = [
                'model' => $kelas,
                'id' => $artefak->model_uuid,
                'alasan' => 'kelas model tidak dikenali lagi',
            ];

            return;
        }

        /** @var Model|null $model */
        $model = $kelas::query()->find($artefak->model_uuid);

        // Sudah lenyap lewat CASCADE dari induknya — itu jalur normal, bukan galat.
        if ($model === null) {
            return;
        }

        if ($model instanceof User && $model->hasDependentRecords()) {
            $dilewati[] = [
                'model' => $this->labelEntitas($kelas),
                'id' => $artefak->model_uuid,
                'alasan' => 'akun masih dirujuk data di luar simulasi',
            ];

            return;
        }

        // Dihapus satu per satu, BUKAN lewat query builder: observer seperti
        // MkObserver dan AcademicUnitUserObserver mencabut peran koordinator
        // dan hanya berjalan pada penghapusan per model.
        $model->delete();

        $label = $this->labelEntitas($kelas);
        $dihapus[$label] = ($dihapus[$label] ?? 0) + 1;
    }

    protected function labelEntitas(string $kelas): string
    {
        return class_basename($kelas);
    }
}
