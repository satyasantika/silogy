<?php

namespace App\Modules\AI\Filament\Resources;

use App\Models\User;
use App\Modules\AI\Filament\Resources\AnalisisAiResource\Pages\ListAnalisisAis;
use App\Modules\AI\Models\AnalisisAi;
use App\Modules\AI\Policies\AnalisisAiPolicy;
use App\Modules\AI\Support\AnalisisAiStatus;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Support\Filament\DelegasiMenu;
use App\Support\Filament\NavigationGroupPeran;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class AnalisisAiResource extends Resource
{
    protected static ?string $model = AnalisisAi::class;

    protected static ?string $policy = AnalisisAiPolicy::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroupPeran::resolve('AI Analisis');
    }

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'analisis AI';

    protected static ?string $pluralModelLabel = 'analisis AI';

    protected static ?string $navigationLabel = 'Riwayat Analisis';

    protected static ?string $slug = 'ai-analisis';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && app(AnalisisAiPolicy::class)->viewAny($user);
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Pimpinan fokus ke laporan analisis CPL di sidebar.
        if (DelegasiMenu::peranAktifPimpinan()) {
            return false;
        }

        return static::canViewAny();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['academicUnit', 'semester', 'dibuatOleh']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('academicUnit.nama')
                    ->label('Unit akademik')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('semester.kode')
                    ->label('Semester')
                    ->sortable(),

                TextColumn::make('jenis')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => static::labelJenis($state))
                    ->color(fn (string $state): string => match ($state) {
                        'ringkasan_cpl' => 'info',
                        'rekomendasi_kurikulum' => 'warning',
                        'tren_capaian' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('model_ai')
                    ->label('Model')
                    ->placeholder('—'),

                TextColumn::make('token_digunakan')
                    ->label('Token')
                    ->numeric()
                    ->placeholder('—'),

                TextColumn::make('durasi_ms')
                    ->label('Durasi (ms)')
                    ->numeric()
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (AnalisisAi $record): string => AnalisisAiStatus::labelFor($record))
                    ->badge()
                    ->color(fn (AnalisisAi $record): string => AnalisisAiStatus::colorFor($record)),
            ])
            ->filters([
                SelectFilter::make('academic_unit_id')
                    ->label('Unit akademik')
                    ->options(fn (): array => AcademicUnit::query()->orderBy('nama')->pluck('nama', 'id')->all())
                    ->searchable(),

                SelectFilter::make('semester_id')
                    ->label('Semester')
                    ->options(fn (): array => Semester::query()->orderByDesc('tahun_mulai')->pluck('nama', 'id')->all()),

                SelectFilter::make('jenis')
                    ->label('Jenis')
                    ->options(static::jenisOptions()),

                SelectFilter::make('model_ai')
                    ->label('Model AI')
                    ->options(fn (): array => AnalisisAi::query()
                        ->whereNotNull('model_ai')
                        ->distinct()
                        ->orderBy('model_ai')
                        ->pluck('model_ai', 'model_ai')
                        ->all()),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Lihat hasil')
                    ->modalHeading('Hasil analisis AI')
                    ->modalWidth('5xl')
                    ->visible(fn (AnalisisAi $record): bool => filled($record->hasil))
                    ->schema([
                        Section::make('Hasil')
                            ->schema([
                                Placeholder::make('hasil_markdown')
                                    ->label('')
                                    ->content(function (AnalisisAi $record): HtmlString {
                                        return new HtmlString(
                                            Str::markdown($record->hasil ?? '')
                                        );
                                    })
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnalisisAis::route('/'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function jenisOptions(): array
    {
        return [
            'ringkasan_cpl' => 'Ringkasan CPL',
            'rekomendasi_kurikulum' => 'Rekomendasi kurikulum',
            'tren_capaian' => 'Tren capaian',
            'lainnya' => 'Lainnya',
        ];
    }

    protected static function labelJenis(string $jenis): string
    {
        return static::jenisOptions()[$jenis] ?? $jenis;
    }
}
