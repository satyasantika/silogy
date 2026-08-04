<?php

namespace App\Modules\Penilaian\Filament\Resources;

use App\Models\User;
use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;
use App\Modules\MK\Filament\Support\Concerns\HasSemesterTerpilihFilter;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\MK\Support\PenawaranMkScope;
use App\Modules\Penilaian\Filament\Resources\PesertaKelasResource\Pages\ListPesertaKelas;
use App\Modules\Penilaian\Policies\PesertaKelasPolicy;
use App\Support\Filament\DelegasiMenu;
use App\Support\Filament\NavigationGroupPeran;
use App\Support\Filament\NavigationSortPeran;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PesertaKelasResource extends Resource
{
    use HasKoordinatorMkScope;
    use HasSemesterTerpilihFilter;

    protected static ?string $model = KelasMk::class;

    protected static ?string $policy = PesertaKelasPolicy::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroupPeran::resolve('Penilaian');
    }

    public static function getNavigationSort(): ?int
    {
        return NavigationSortPeran::resolve('peserta-kelas', 1);
    }

    protected static ?string $navigationLabel = 'Mahasiswa';

    protected static ?string $modelLabel = 'kelas';

    protected static ?string $pluralModelLabel = 'kelas';

    protected static ?string $slug = 'peserta-kelas';

    public static function shouldRegisterNavigation(): bool
    {
        if (DelegasiMenu::sembunyikanDariSuperAdmin() || DelegasiMenu::peranAktifDosenBukanAdmin()) {
            return false;
        }

        $user = Auth::user();

        return $user instanceof User && app(PesertaKelasPolicy::class)->viewAny($user)
            && (! PenawaranMkScope::isKoordinatorMkOnly($user) || MkTerpilih::current() !== null);
    }

    public static function canAccess(): bool
    {
        if (DelegasiMenu::peranAktifDosenBukanAdmin()) {
            return false;
        }

        $user = Auth::user();

        return $user instanceof User && app(PesertaKelasPolicy::class)->viewAny($user);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['mkUnit.mk', 'semester', 'dosenPengampu'])
            ->withCount('mahasiswas');

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

        return $query->whereHas(
            'mkUnit',
            fn (Builder $mkUnitQuery): Builder => $mkUnitQuery->whereIn('mk_id', $mkIds),
        );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->extraAttributes([
                'class' => 'silogy-mk-semester-toolbar silogy-peserta-kelas',
            ])
            ->columns([
                TextColumn::make('kode_kelas')
                    ->label('Kelas')
                    ->sortable()
                    ->searchable()
                    ->weight(FontWeight::Bold)
                    ->badge()
                    ->color('gray'),

                TextColumn::make('dosenPengampu.full_name')
                    ->label('Dosen pengampu')
                    ->placeholder('—')
                    ->wrap()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas(
                            'dosenPengampu',
                            fn (Builder $dosenQuery): Builder => $dosenQuery
                                ->where('full_name', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%"),
                        );
                    }),

                TextColumn::make('mahasiswas_count')
                    ->label('Mahasiswa')
                    ->sortable()
                    ->alignEnd()
                    ->numeric(),
            ])
            ->defaultSort('kode_kelas')
            ->paginated(false)
            ->filters([
                static::semesterTerpilihFilter(
                    fn (Builder $query, string $semesterId): Builder => $query->where('semester_id', $semesterId),
                    ['indikator' => false, 'labelTersembunyi' => true],
                ),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(1)
            ->deferFilters(false)
            ->hiddenFilterIndicators()
            ->modifyQueryUsing(function (Builder $query): Builder {
                $mkId = MkTerpilih::currentId();

                if (blank($mkId)) {
                    return $query->whereRaw('1 = 0');
                }

                $query = $query->whereHas(
                    'mkUnit',
                    fn (Builder $mkUnitQuery): Builder => $mkUnitQuery->where('mk_id', $mkId),
                );

                if (! SemesterTerpilih::berlakuUntukUser()) {
                    return $query;
                }

                $semesterId = SemesterTerpilih::currentId($mkId);

                if (blank($semesterId)) {
                    return $query->whereRaw('1 = 0');
                }

                return $query->where('semester_id', $semesterId);
            })
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPesertaKelas::route('/'),
        ];
    }
}
