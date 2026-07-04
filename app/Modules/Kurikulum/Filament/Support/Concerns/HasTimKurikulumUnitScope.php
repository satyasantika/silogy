<?php

namespace App\Modules\Kurikulum\Filament\Support\Concerns;

use App\Models\User;
use App\Modules\Institusi\Filament\Resources\AcademicUnitResource;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Unique;

trait HasTimKurikulumUnitScope
{
    /**
     * @return Collection<int, string>
     */
    public static function scopedTimKurikulumUnitIds(): Collection
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return collect();
        }

        return AcademicUnitScope::scopedTimKurikulumUnitIdsFor($user);
    }

    /**
     * @return array<string, string>
     */
    public static function timKurikulumUnitOptions(): array
    {
        return AcademicUnit::query()
            ->whereIn('id', static::scopedTimKurikulumUnitIds())
            ->orderBy('nama')
            ->get()
            ->mapWithKeys(fn (AcademicUnit $unit): array => [
                $unit->id => AcademicUnitResource::formatUnitOptionLabel($unit),
            ])
            ->all();
    }

    public static function uniqueKodePerUnitRule(
        string $table,
        ?string $ignoreRecordId = null,
        ?string $academicUnitId = null,
    ): Unique {
        $rule = Unique::make($table, 'kode');

        if ($academicUnitId) {
            $rule->where('academic_unit_id', $academicUnitId);
        }

        if ($ignoreRecordId) {
            $rule->ignore($ignoreRecordId);
        }

        return $rule;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function scopeEloquentByTimKurikulumUnits(Builder $query, string $column = 'academic_unit_id'): Builder
    {
        $unitIds = static::scopedTimKurikulumUnitIds();

        if ($unitIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        if (Auth::user()?->hasRole('Super Admin')) {
            return $query;
        }

        return $query->whereIn($column, $unitIds);
    }
}
