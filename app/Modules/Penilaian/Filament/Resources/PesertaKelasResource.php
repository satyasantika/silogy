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
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class PesertaKelasResource extends Resource
{
    use HasKoordinatorMkScope;
    use HasSemesterTerpilihFilter;

    protected static ?string $model = KelasMk::class;

    protected static ?string $policy = PesertaKelasPolicy::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Penilaian';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Mahasiswa';

    protected static ?string $modelLabel = 'kelas';

    protected static ?string $pluralModelLabel = 'kelas';

    protected static ?string $slug = 'peserta-kelas';

    public static function shouldRegisterNavigation(): bool
    {
        if (DelegasiMenu::sembunyikanDariSuperAdmin()) {
            return false;
        }

        $user = Auth::user();

        return $user instanceof User && app(PesertaKelasPolicy::class)->viewAny($user)
            && (! PenawaranMkScope::isKoordinatorMkOnly($user) || MkTerpilih::current() !== null);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['mkUnit.mk', 'semester']);

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
            ->description(fn (): HtmlString => MkTerpilih::bannerHtml())
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('mkUnit.kode')
                            ->label('Kode MK')
                            ->weight(FontWeight::Bold),

                        TextColumn::make('kode_kelas')
                            ->label('Kelas')
                            ->badge()
                            ->color('gray'),
                    ]),

                    TextColumn::make('mkUnit.mk.nama')
                        ->label('Mata kuliah')
                        ->wrap()
                        ->weight(FontWeight::Bold),

                    TextColumn::make('mahasiswas_count')
                        ->label('Jumlah peserta')
                        ->counts('mahasiswas')
                        ->suffix(' mahasiswa')
                        ->size('sm')
                        ->color('gray'),

                    TextColumn::make('dosenPengampu.full_name')
                        ->label('Dosen pengampu')
                        ->placeholder('—')
                        ->size('sm')
                        ->color('gray'),
                ])->space(2),
            ])
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->paginated(false)
            ->filters([
                static::semesterTerpilihFilter(
                    fn (Builder $query, string $semesterId): Builder => $query->where('semester_id', $semesterId),
                ),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(1)
            ->deferFilters(false)
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
