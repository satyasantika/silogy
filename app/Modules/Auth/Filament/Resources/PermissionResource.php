<?php

namespace App\Modules\Auth\Filament\Resources;

use App\Models\Permission;
use App\Modules\Auth\Filament\Resources\PermissionResource\Pages\ListPermissions;
use App\Modules\Auth\Support\DomainPermissionLabels;
use App\Support\Filament\DelegasiMenu;
use App\Support\Filament\NavigationGroupPeran;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroupPeran::resolve('Autentikasi');
    }

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'permission';

    protected static ?string $pluralModelLabel = 'permission';

    protected static ?string $slug = 'permissions';

    /**
     * Menu Permission disembunyikan dari Super Admin — menu Autentikasi
     * yang tersisa untuknya hanya Peran & Pengguna (lihat DelegasiMenu).
     * kelola_permission tetap melekat pada rolenya untuk keperluan lain,
     * jadi override di sini, bukan pada PermissionPolicy.
     */
    public static function shouldRegisterNavigation(): bool
    {
        if (DelegasiMenu::sembunyikanDariSuperAdmin()) {
            return false;
        }

        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        if (DelegasiMenu::sembunyikanDariSuperAdmin()) {
            return false;
        }

        return static::canViewAny();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->withCount(['roles', 'users'])
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Kode permission')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('label')
                    ->label('Deskripsi')
                    ->state(fn (Permission $record): string => DomainPermissionLabels::label($record->name))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $matching = collect(DomainPermissionLabels::all())
                            ->filter(fn (string $label, string $name): bool => str_contains(strtolower($label), strtolower($search))
                                || str_contains(strtolower($name), strtolower($search)))
                            ->keys()
                            ->all();

                        return $query->where(function (Builder $inner) use ($search, $matching): void {
                            $inner->where('name', 'like', "%{$search}%");

                            if ($matching !== []) {
                                $inner->orWhereIn('name', $matching);
                            }
                        });
                    }),

                TextColumn::make('roles_count')
                    ->label('Jumlah role')
                    ->counts('roles')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('users_count')
                    ->label('Pengguna langsung')
                    ->counts('users')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('guard_name')
                    ->label('Guard')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPermissions::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
