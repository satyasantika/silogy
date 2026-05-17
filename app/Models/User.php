<?php

namespace App\Models;

use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuids, Notifiable;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'username',
        'email',
        'nidn',
        'prefix',
        'full_name',
        'suffix',
        'jenis_kelamin',
        'nomor_wa',
        'password',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * @return HasMany<AcademicUnitUser, $this>
     */
    public function academicUnitUsers(): HasMany
    {
        return $this->hasMany(AcademicUnitUser::class);
    }

    /**
     * @return BelongsToMany<AcademicUnit, $this>
     */
    public function academicUnits(): BelongsToMany
    {
        return $this->belongsToMany(AcademicUnit::class, 'academic_unit_users')
            ->withPivot(['status_pimpinan', 'status_tim_kurikulum', 'jabatan'])
            ->withTimestamps();
    }
}
