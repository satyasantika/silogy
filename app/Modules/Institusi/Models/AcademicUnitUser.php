<?php

namespace App\Modules\Institusi\Models;

use App\Models\User;
use App\Modules\Simulasi\Models\Concerns\BerRanahSimulasi;
use App\Support\Concerns\LogsSilogyActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicUnitUser extends Model
{
    use BerRanahSimulasi;

    public const RANAH_MODE = 'unit';

    public const RANAH_KOLOM = 'academic_unit_id';

    use HasUuids, LogsSilogyActivity;

    protected $table = 'academic_unit_users';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status_pimpinan' => 'bool',
            'status_tim_kurikulum' => 'bool',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<AcademicUnit, $this>
     */
    public function academicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class);
    }
}
