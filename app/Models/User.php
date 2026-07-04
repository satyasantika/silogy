<?php

namespace App\Models;

use App\Modules\AI\Models\AnalisisAi;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\StateTransition;
use App\Support\Concerns\LogsSilogyActivity;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements CanResetPasswordContract, FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use CanResetPassword, HasFactory, HasRoles, HasUuids, LogsSilogyActivity, Notifiable;

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

    public function canImpersonate(): bool
    {
        return $this->hasRole('Super Admin');
    }

    public function canBeImpersonated(): bool
    {
        return ! $this->hasRole('Super Admin');
    }

    public function getFilamentName(): string
    {
        return $this->full_name ?? $this->username ?? 'Pengguna';
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

    /**
     * User dianggap terpakai jika direferensikan sebagai pembuat kurikulum,
     * aktor transisi state, dosen/koordinator kelas MK, atau pembuat analisis AI.
     */
    public function hasDependentRecords(): bool
    {
        return Kurikulum::query()->where('dibuat_oleh', $this->id)->exists()
            || StateTransition::query()->where('actor_id', $this->id)->exists()
            || KelasMk::query()
                ->where(fn (Builder $query) => $query
                    ->where('dosen_pengampu_id', $this->id)
                    ->orWhere('koordinator_mk_id', $this->id))
                ->exists()
            || AnalisisAi::query()->where('dibuat_oleh', $this->id)->exists();
    }

    /**
     * @return list<string>
     */
    protected function activityLogHiddenAttributes(): array
    {
        return ['password', 'remember_token'];
    }
}
