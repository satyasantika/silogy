<?php

namespace App\Modules\MK\Filament\Resources;

use App\Models\User;
use App\Modules\MK\Filament\Resources\MataKuliahKoordinatorResource\Pages\ListMataKuliahKoordinators;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Policies\MataKuliahKoordinatorPolicy;
use App\Modules\MK\Services\MataKuliahKoordinatorService;
use App\Modules\MK\Support\MkTerpilih;
use App\Support\Filament\DelegasiMenu;
use App\Support\Filament\NavigationGroupPeran;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MataKuliahKoordinatorResource extends Resource
{
    use HasKoordinatorMkScope;

    protected static ?string $model = Mk::class;

    protected static ?string $policy = MataKuliahKoordinatorPolicy::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroupPeran::resolve('Mata Kuliah');
    }

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
            ->with(['academicUnit', 'kurikulum.academicUnit', 'mkUnits.kurikulum.academicUnit'])
            ->whereIn('id', $mkIds);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->selectable(false)
            ->recordUrl(null)
            ->recordAction('kerjakan')
            ->recordClasses(fn (Mk $record): array => MataKuliahKoordinatorService::isMkSedangDikerjakan($record)
                ? ['silogy-mk-card-sedang-dikerjakan']
                : ['silogy-mk-card-belum-dikerjakan'])
            ->recordActions([
                Action::make('kerjakan')
                    ->label(fn (Mk $record): string => MataKuliahKoordinatorService::isMkSedangDikerjakan($record)
                        ? 'Sedang dikerjakan'
                        : 'Kerjakan')
                    ->icon(fn (Mk $record): Heroicon => MataKuliahKoordinatorService::isMkSedangDikerjakan($record)
                        ? Heroicon::OutlinedCheckBadge
                        : Heroicon::OutlinedPlayCircle)
                    ->color(fn (Mk $record): string => MataKuliahKoordinatorService::isMkSedangDikerjakan($record)
                        ? 'gray'
                        : 'success')
                    ->disabled(fn (Mk $record): bool => MataKuliahKoordinatorService::isMkSedangDikerjakan($record))
                    ->extraAttributes(fn (Mk $record): array => [
                        'class' => MataKuliahKoordinatorService::isMkSedangDikerjakan($record)
                            ? 'silogy-mk-aksi-sedang-dikerjakan'
                            : 'silogy-mk-aksi-kerjakan',
                    ])
                    ->action(function (Mk $record): void {
                        MkTerpilih::set($record->id);

                        Notification::make()
                            ->title('Mata kuliah sedang dikerjakan diganti')
                            ->body($record->nama)
                            ->success()
                            ->send();
                    }),
            ])
            ->extraAttributes([
                'class' => 'silogy-mk-koordinator-cards',
            ])
            ->columns([
                Stack::make([
                    TextColumn::make('judul_card')
                        ->label('Mata kuliah')
                        ->state(fn (Mk $record): string => MataKuliahKoordinatorService::judulCardHtml($record)->toHtml())
                        ->html()
                        ->searchable(query: function (Builder $query, string $search): Builder {
                            return $query->where('nama', 'like', "%{$search}%");
                        }),

                    TextColumn::make('meta_penawaran')
                        ->label('')
                        ->state(function (Mk $record): string {
                            $user = Auth::user();

                            if (! $user instanceof User) {
                                return '';
                            }

                            return MataKuliahKoordinatorService::metaPenawaranHtml($record, $user)->toHtml();
                        })
                        ->html(),
                ])->space(1),
            ])
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->paginated([6, 12, 24])
            ->defaultPaginationPageOption(12);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMataKuliahKoordinators::route('/'),
        ];
    }
}
