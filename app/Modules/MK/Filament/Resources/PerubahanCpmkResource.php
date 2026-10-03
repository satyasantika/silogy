<?php

namespace App\Modules\MK\Filament\Resources;

use App\Models\User;
use App\Modules\Auth\Support\PeranUnitFormFields;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\MK\Enums\StatusPerubahanCpmk;
use App\Modules\MK\Filament\Resources\PerubahanCpmkResource\Pages\ListPerubahanCpmks;
use App\Modules\MK\Filament\Resources\PerubahanCpmkResource\Pages\ViewPerubahanCpmk;
use App\Modules\MK\Models\PerubahanCpmkRequest;
use App\Modules\MK\Policies\PerubahanCpmkRequestPolicy;
use App\Support\Filament\DelegasiMenu;
use App\Support\Filament\NavigationGroupPeran;
use App\Support\Filament\NavigationSortPeran;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Kotak masuk Tim Kurikulum untuk usulan perubahan CPMK dari Koordinator MK.
 *
 * Cakupannya mengikuti unit: Tim Kurikulum prodi menangani MK prodinya, tim
 * di fakultas/universitas menangani MK di lingkungannya. Koordinator MK
 * melihat halaman yang sama, tetapi hanya usulannya sendiri.
 */
class PerubahanCpmkResource extends Resource
{
    protected static ?string $model = PerubahanCpmkRequest::class;

    protected static ?string $policy = PerubahanCpmkRequestPolicy::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $modelLabel = 'Usulan Perubahan CPMK';

    protected static ?string $pluralModelLabel = 'Usulan Perubahan CPMK';

    protected static ?string $slug = 'usulan-perubahan-cpmk';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroupPeran::resolve('Kurikulum');
    }

    public static function getNavigationSort(): ?int
    {
        // Default 7: tepat di bawah Penawaran MK (6) untuk peran selain Tim Kurikulum.
        return NavigationSortPeran::resolve('usulan-perubahan-cpmk', 7);
    }

    /**
     * Menu hanya muncul bagi Tim Kurikulum (peran aktif) yang SEDANG punya
     * usulan menunggu keputusan dari Koordinator MK di unitnya. Tanpa usulan,
     * kotak masuk kosong hanya menambah menu yang tidak berguna. Halamannya
     * sendiri tetap bisa dibuka lewat URL (canAccess), jadi riwayat usulan
     * yang sudah diputuskan tidak hilang akses.
     */
    public static function shouldRegisterNavigation(): bool
    {
        if (DelegasiMenu::sembunyikanDariSuperAdmin()) {
            return false;
        }

        $user = Auth::user();

        if (! $user instanceof User || ! static::canAccess()) {
            return false;
        }

        if (PeranUnitFormFields::defaultRole($user) !== 'Tim Kurikulum') {
            return false;
        }

        return static::usulanMenungguPeninjauan($user)->exists();
    }

    /**
     * Usulan berstatus diajukan yang berwenang ditinjau $user: unit MK-nya
     * berada di bawah unit tempat ia Tim Kurikulum, dan bukan usulannya
     * sendiri (pengusul tidak boleh menyetujui usulannya).
     *
     * @return Builder<PerubahanCpmkRequest>
     */
    public static function usulanMenungguPeninjauan(User $user): Builder
    {
        $unitIds = static::unitIdsPeninjauan($user);

        if ($unitIds->isEmpty()) {
            return PerubahanCpmkRequest::query()->whereRaw('1 = 0');
        }

        return PerubahanCpmkRequest::query()
            ->where('status', StatusPerubahanCpmk::Diajukan)
            ->where('diajukan_oleh_id', '!=', $user->id)
            ->whereIn('academic_unit_id', $unitIds);
    }

    /**
     * Unit tempat user berwenang meninjau, DITAMBAH seluruh turunannya:
     * MK tersimpan di level prodi, sementara Tim Kurikulum bisa
     * ditugaskan di fakultas atau universitas.
     *
     * @return Collection<int, string>
     */
    protected static function unitIdsPeninjauan(User $user): Collection
    {
        return AcademicUnitScope::timKurikulumPivotUnitIdsFor($user)
            ->flatMap(fn (string $unitId): array => AcademicUnitScope::descendantIdsIncludingSelf($unitId)->all())
            ->unique()
            ->values();
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(PerubahanCpmkRequestPolicy::class)->viewAny($user);
    }

    public static function canCreate(): bool
    {
        // Usulan selalu lahir dari halaman CPMK (dengan konteks MK + semester
        // yang jelas), bukan dari form kosong di kotak masuk.
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        // Hitungan yang sama dengan syarat muncul menu: hanya usulan yang
        // memang menunggu keputusan user ini, bukan usulannya sendiri.
        $jumlah = static::usulanMenungguPeninjauan($user)->count();

        return $jumlah > 0 ? (string) $jumlah : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['mk', 'semester', 'academicUnit', 'diajukanOleh', 'ditinjauOleh']);

        $user = Auth::user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasAnyRole(['Super Admin', 'Auditor Mutu'])) {
            return $query;
        }

        $unitIds = static::unitIdsPeninjauan($user);

        return $query->where(function (Builder $scoped) use ($unitIds, $user): void {
            $scoped->where('diajukan_oleh_id', $user->id);

            if ($unitIds->isNotEmpty()) {
                $scoped->orWhereIn('academic_unit_id', $unitIds);
            }
        });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('mk.nama')
                    ->label('Mata Kuliah')
                    ->searchable()
                    ->wrap()
                    ->weight(FontWeight::Bold),

                TextColumn::make('semester.nama')
                    ->label('Semester')
                    ->size('sm'),

                TextColumn::make('academicUnit.nama')
                    ->label('Unit')
                    ->size('sm')
                    ->color('gray'),

                TextColumn::make('diajukanOleh.name')
                    ->label('Diajukan oleh')
                    ->size('sm')
                    ->color('gray'),

                TextColumn::make('alasan')
                    ->label('Alasan')
                    ->formatStateUsing(fn (?string $state): string => filled($state)
                        ? Str::limit(trim(strip_tags($state)), 90)
                        : '—')
                    ->wrap()
                    ->size('sm'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('created_at')
                    ->label('Diajukan')
                    ->dateTime('d M Y H:i')
                    ->size('sm')
                    ->color('gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(StatusPerubahanCpmk::cases())
                        ->mapWithKeys(fn (StatusPerubahanCpmk $status): array => [
                            $status->value => $status->getLabel(),
                        ])
                        ->all())
                    ->default(StatusPerubahanCpmk::Diajukan->value),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPerubahanCpmks::route('/'),
            'view' => ViewPerubahanCpmk::route('/{record}'),
        ];
    }
}
