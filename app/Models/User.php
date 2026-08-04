<?php

namespace App\Models;

use App\Modules\AI\Models\AnalisisAi;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\StateTransition;
use App\Modules\MK\Models\Mk;
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
    use CanResetPassword, HasFactory, HasUuids, LogsSilogyActivity, Notifiable;

    use HasRoles {
        roles as protected spatieRoles;
    }

    /**
     * Relasi roles mengikuti role aktif yang dipilih user multi-role lewat
     * switcher navigasi. Filter diterapkan pada level baris (hanya pivot
     * milik user yang sedang login) supaya tetap benar saat eager loading —
     * Laravel membangun relasi eager dari instance model kosong — dan
     * pengecekan role user lain tidak terpengaruh.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        $relation = $this->spatieRoles();

        $authUser = auth()->user();

        if (! $authUser instanceof self) {
            return $relation;
        }

        $activeRole = ActiveRole::currentFor($authUser);

        if ($activeRole === null) {
            return $relation;
        }

        return $relation->where(fn (Builder $query) => $query
            ->where('model_has_roles.model_uuid', '!=', $authUser->getKey())
            ->orWhere('roles.name', $activeRole));
    }

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
     * Nama untuk tampilan formal (kop laporan, kartu kelas): gelar depan
     * (`prefix`) + nama + gelar belakang (`suffix`) bila terisi.
     * Contoh: "Dr. Satya Santika, M.Kom."
     */
    public function namaDenganGelar(): string
    {
        $nama = trim(implode(' ', array_filter([
            filled($this->prefix) ? trim((string) $this->prefix) : null,
            filled($this->full_name) ? trim((string) $this->full_name) : null,
        ], fn (?string $bagian): bool => $bagian !== null && $bagian !== '')));

        $suffix = filled($this->suffix) ? trim((string) $this->suffix) : '';

        if ($nama === '') {
            return $suffix !== '' ? $suffix : (string) ($this->username ?? '—');
        }

        return $suffix !== '' ? "{$nama}, {$suffix}" : $nama;
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
            || Mk::query()->where('koordinator_mk_id', $this->id)->exists()
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
