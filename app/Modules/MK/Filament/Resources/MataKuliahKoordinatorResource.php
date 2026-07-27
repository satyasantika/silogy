<?php

namespace App\Modules\MK\Filament\Resources;

use App\Models\User;
use App\Modules\Kurikulum\Filament\Support\Concerns\HasKurikulumTerpilihFilter;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\MataKuliahKoordinatorResource\Pages\ListMataKuliahKoordinators;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Policies\MataKuliahKoordinatorPolicy;
use App\Modules\MK\Services\MataKuliahKoordinatorService;
use App\Modules\MK\Support\PenawaranMkScope;
use App\Support\Filament\DelegasiMenu;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class MataKuliahKoordinatorResource extends Resource
{
    use HasKoordinatorMkScope;
    use HasKurikulumTerpilihFilter;

    protected static ?string $model = Mk::class;

    protected static ?string $policy = MataKuliahKoordinatorPolicy::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|\UnitEnum|null $navigationGroup = 'Mata Kuliah';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Mata Kuliah';

    protected static ?string $modelLabel = 'mata kuliah';

    protected static ?string $pluralModelLabel = 'mata kuliah';

    protected static ?string $slug = 'mata-kuliah-koordinator';

    protected static ?string $recordTitleAttribute = 'nama';

    public static function shouldRegisterNavigation(): bool
    {
        if (DelegasiMenu::sembunyikanDariSuperAdmin()) {
            return false;
        }

        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(MataKuliahKoordinatorPolicy::class)->viewAny($user);
    }

    /**
     * @return Collection<int, string>
     */
    protected static function kurikulumTerpilihFilterUnitIds(): ?Collection
    {
        $user = Auth::user();

        if ($user instanceof User) {
            return PenawaranMkScope::unitIdsDariPenawaran($user);
        }

        return collect();
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        $mkIds = static::scopedKoordinatorMkIds($user);

        if ($mkIds->isEmpty()) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->with(['academicUnit', 'mkUnits'])
            ->whereIn('id', $mkIds);
    }

    public static function table(Table $table): Table
    {
        return static::applyKurikulumTerpilihCardTable(
            $table
                ->selectable(false)
                ->recordUrl(fn (Mk $record): string => route('silogy.mk-navigasi', [
                    'mk' => $record->id,
                    'menu' => 'cpmk',
                ])),
            [
                Split::make([
                    TextColumn::make('nama')
                        ->label('Mata kuliah')
                        ->searchable()
                        ->sortable()
                        ->weight(FontWeight::Bold),

                    TextColumn::make('total_sks')
                        ->label('SKS')
                        ->sortable()
                        ->size('sm'),
                ]),

                TextColumn::make('kode_penawaran')
                    ->label('Kode penawaran')
                    ->state(function (Mk $record): string {
                        $kurikulum = KurikulumTerpilih::current();

                        if (! $kurikulum instanceof Kurikulum) {
                            return '—';
                        }

                        return MataKuliahKoordinatorService::labelPenawaranPadaKurikulum($record, $kurikulum);
                    })
                    ->size('sm')
                    ->color('gray'),

                TextColumn::make('academicUnit.nama')
                    ->label('Unit pemilik MK')
                    ->size('sm')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('ketersediaan_penilaian')
                    ->label('')
                    ->state(function (Mk $record): string {
                        $user = Auth::user();

                        if (! $user instanceof User) {
                            return '';
                        }

                        return MataKuliahKoordinatorService::ketersediaanPenilaianHtml($record, $user)->toHtml();
                    })
                    ->html(),
            ],
            fn (Builder $query, Kurikulum $kurikulum): Builder => $query->whereHas(
                'mkUnits',
                fn (Builder $mkUnitQuery): Builder => $mkUnitQuery
                    ->where('kurikulum_id', $kurikulum->id)
                    ->where('is_active', true),
            ),
            withKurikulumFilter: true,
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMataKuliahKoordinators::route('/'),
        ];
    }
}
