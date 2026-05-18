<?php

namespace App\Modules\Audit\Policies;

use App\Models\User;
use App\Modules\Audit\Models\Activity;

class ActivityLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->bolehAksesAudit($user);
    }

    public function view(User $user, Activity $activity): bool
    {
        return $this->bolehAksesAudit($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Activity $activity): bool
    {
        return false;
    }

    public function delete(User $user, Activity $activity): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Activity $activity): bool
    {
        return false;
    }

    public function forceDelete(User $user, Activity $activity): bool
    {
        return false;
    }

    protected function bolehAksesAudit(User $user): bool
    {
        return $user->hasRole(['Super Admin', 'Auditor Mutu']);
    }
}
