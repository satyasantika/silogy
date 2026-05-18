<?php

namespace App\Support\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait LogsSilogyActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logExcept(array_merge(
                ['created_at', 'updated_at'],
                $this->activityLogHiddenAttributes(),
            ));
    }

    /**
     * @return list<string>
     */
    protected function activityLogHiddenAttributes(): array
    {
        return [];
    }
}
