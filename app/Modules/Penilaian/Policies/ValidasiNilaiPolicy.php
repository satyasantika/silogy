<?php

namespace App\Modules\Penilaian\Policies;

use App\Models\User;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Penilaian\Services\PenilaianMatrixService;
use App\Modules\Penilaian\Support\ValidatorNilaiScope;
use App\Modules\Simulasi\Support\HanyaBaca;

class ValidasiNilaiPolicy
{
    public function access(User $user): bool
    {
        return $user->hasRole('Validator Nilai')
            && $user->can('validasi_nilai');
    }

    public function lihat(User $user, KelasMk $kelasMk): bool
    {
        return $this->access($user) && $this->dalamCakupan($user, $kelasMk);
    }

    /**
     * Alasan validasi ditolak, atau null bila boleh. Urutan pemeriksaan tetap.
     * $kelengkapan boleh diberikan pemanggil yang sudah menghitungnya pada
     * request yang sama (hasil PenilaianMatrixService::kelengkapanKelas()).
     *
     * @param  array{status_kelengkapan: string}|null  $kelengkapan
     */
    public function alasanTidakBisaValidasi(User $user, KelasMk $kelasMk, ?array $kelengkapan = null): ?string
    {
        if (! $this->lihat($user, $kelasMk)) {
            return 'Kelas di luar cakupan Anda.';
        }

        if ($kelasMk->dosen_pengampu_id === $user->id) {
            return 'Anda dosen pengampu kelas ini.';
        }

        if ($kelasMk->koordinator_mk_id === $user->id) {
            return 'Anda koordinator mata kuliah ini.';
        }

        if (! $kelasMk->penugasanSelesai()) {
            return 'Penugasan asesmen belum selesai.';
        }

        $kelengkapan ??= app(PenilaianMatrixService::class)->kelengkapanKelas($kelasMk);

        if ($kelengkapan['status_kelengkapan'] !== 'lengkap') {
            return 'Nilai belum lengkap.';
        }

        return null;
    }

    /**
     * @param  array{status_kelengkapan: string}|null  $kelengkapan  lihat alasanTidakBisaValidasi()
     */
    public function validasi(User $user, KelasMk $kelasMk, ?array $kelengkapan = null): bool
    {
        if (HanyaBaca::aktif($user)) {
            return false;
        }

        return $this->alasanTidakBisaValidasi($user, $kelasMk, $kelengkapan) === null;
    }

    public function batalkanValidasi(User $user, KelasMk $kelasMk): bool
    {
        if (HanyaBaca::aktif($user)) {
            return false;
        }

        return $this->lihat($user, $kelasMk)
            && $kelasMk->dosen_pengampu_id !== $user->id
            && $kelasMk->koordinator_mk_id !== $user->id;
    }

    private function dalamCakupan(User $user, KelasMk $kelasMk): bool
    {
        return ValidatorNilaiScope::kelasMkQuery($user)->whereKey($kelasMk->getKey())->exists();
    }
}
