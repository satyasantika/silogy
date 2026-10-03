<?php

namespace App\Modules\Audit\Models;

use App\Modules\Simulasi\Models\Concerns\BerRanahSimulasi;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    use BerRanahSimulasi;

    public const RANAH_MODE = 'pelaku';

    public const RANAH_KOLOM = 'causer_id';

    //
}
