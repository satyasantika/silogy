<?php

namespace App\Modules\Penilaian\Policies;

use App\Models\User;
use App\Modules\Kelas\Models\KelasMk;

class InputNilaiPolicy
{
    public function access(User $user): bool
    {
        return $user->hasRole('Dosen Pengampu')
            && $user->can('input_nilai');
    }

    public function inputNilai(User $user, KelasMk $kelasMk): bool
    {
        if (! $this->access($user)) {
            return false;
        }

        if ($kelasMk->dosen_pengampu_id !== $user->id) {
            return false;
        }

        // Dosen baru boleh menilai setelah koordinator MK menyelesaikan
        // penugasan (komponen 100% dan terpetakan ke Sub-CPMK).
        return $kelasMk->penugasanSelesai();
    }
}
