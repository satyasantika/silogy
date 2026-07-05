<?php

namespace App\Modules\MK\Filament\Resources;

use App\Models\User;
use App\Modules\MK\Filament\Resources\CpmkResource\Pages\CreateCpmk;
use App\Modules\MK\Filament\Resources\CpmkResource\Pages\EditCpmk;
use App\Modules\MK\Filament\Resources\CpmkResource\Pages\ListCpmks;
use App\Modules\MK\Filament\Resources\CpmkResource\RelationManagers\MkCpmkRelationManager;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Policies\CpmkPolicy;
use App\Support\Filament\DelegasiMenu;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CpmkResource extends Resource
{
    use HasKoordinatorMkScope;

    protected static ?string $model = Cpmk::class;

    protected static ?string $policy = CpmkPolicy::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Mata Kuliah';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'CPMK';

    protected static ?string $pluralModelLabel = 'CPMK';

    protected static ?string $slug = 'cpmk';

    public static function shouldRegisterNavigation(): bool
    {
        if (DelegasiMenu::sembunyikanDariSuperAdmin()) {
            return false;
        }

        $user = Auth::user();

        return $user instanceof User && app(CpmkPolicy::class)->viewAny($user);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('mk');

        $user = Auth::user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole(['Super Admin', 'Auditor Mutu'])) {
            return $query;
        }

        $mkIds = static::scopedKoordinatorMkIds($user);

        if ($mkIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('mk_id', $mkIds);
    }

    public static function form(Schema $schema): Schema
    {
        $mkOptions = static::scopedKoordinatorMkOptions();

        return $schema
            ->components([
                Section::make('CPMK')
                    ->schema([
                        Select::make('mk_id')
                            ->label('Mata kuliah')
                            ->options($mkOptions)
                            ->searchable()
                            ->required()
                            ->default(count($mkOptions) === 1 ? array_key_first($mkOptions) : null)
                            ->disabled(count($mkOptions) === 1)
                            ->dehydrated(),

                        TextInput::make('kode')
                            ->label('Kode')
                            ->required()
                            ->maxLength(15),

                        Textarea::make('deskripsi')
                            ->label('Deskripsi')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('mk.nama')
                    ->label('Mata kuliah')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('kode')
                    ->label('Kode')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('deskripsi')
                    ->label('Deskripsi')
                    ->limit(60),
            ])
            ->filters([
                SelectFilter::make('mk_id')
                    ->label('Mata kuliah')
                    ->options(static::scopedKoordinatorMkOptions())
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            MkCpmkRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCpmks::route('/'),
            'create' => CreateCpmk::route('/create'),
            'edit' => EditCpmk::route('/{record}/edit'),
        ];
    }
}
