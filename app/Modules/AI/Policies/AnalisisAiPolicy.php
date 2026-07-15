<?php

namespace App\Modules\AI\Policies;

use App\Models\User;
use App\Modules\AI\Models\AnalisisAi;

class AnalisisAiPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->bolehAksesModulAi($user);
    }

    public function view(User $user, AnalisisAi $analisisAi): bool
    {
        return $this->bolehAksesModulAi($user);
    }

    public function create(User $user): bool
    {
        return $user->can('minta_analisis_ai');
    }

    public function update(User $user, AnalisisAi $analisisAi): bool
    {
        return false;
    }

    public function delete(User $user, AnalisisAi $analisisAi): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    protected function bolehAksesModulAi(User $user): bool
    {
        return $user->hasRole('Auditor Mutu')
            || $user->can('minta_analisis_ai');
    }
}
