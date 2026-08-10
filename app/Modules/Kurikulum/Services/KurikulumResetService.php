<?php

namespace App\Modules\Kurikulum\Services;

use App\Modules\Kurikulum\Models\Kurikulum;
use Illuminate\Support\Facades\DB;

class KurikulumResetService
{
    /**
     * Kosongkan kurikulum kembali ke kondisi baru dibuat: hapus seluruh
     * Profil Lulusan, CPL, BoK, MK, dan Penawaran (MkUnit) beserta
     * relasinya. Baris kurikulum sendiri TIDAK dihapus.
     */
    public function reset(Kurikulum $kurikulum): void
    {
        DB::transaction(function () use ($kurikulum): void {
            // Dihapus TERPISAH dari mks(): Adaptasi MK massal bisa membuat
            // MkUnit.mk_id menunjuk Mk milik kurikulum ANCESTOR lain, jadi
            // cascade dari mks() di bawah tidak selalu menyapu semua
            // mkUnits() milik kurikulum ini.
            $kurikulum->mkUnits()->get()->each->delete();

            // Satu per satu (BUKAN bulk query delete) — MkObserver::deleted()
            // yang mencabut role Koordinator Mata Kuliah hanya terpicu delete
            // per-model. Cascade DB di bawahnya (cpmk, mk_cpmk, subcpmk,
            // komponen_penilaian, subcpmk_komponenpenilaian, nilai_mahasiswas,
            // cpl_mk sisi mk) berjalan otomatis.
            $kurikulum->mks()->get()->each->delete();

            // Tanpa Observer terdaftar pada model-model ini — aman lewat
            // bulk delete (cascade cpl_bok, cpl_mk sisi bok, cpl_profil_lulusan,
            // profil_indikators via FK).
            $kurikulum->cpls()->delete();
            $kurikulum->boks()->delete();
            $kurikulum->profilLulusan()->delete();
        });
    }
}
