<?php

namespace App\Modules\Audit\Filament\Resources;

use App\Models\User;
use App\Modules\Audit\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs;
use App\Modules\Audit\Models\Activity;
use App\Modules\Audit\Policies\ActivityLogPolicy;
use App\Modules\CPL\Models\Cpl;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $policy = ActivityLogPolicy::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'log aktivitas';

    protected static ?string $pluralModelLabel = 'log aktivitas';

    protected static ?string $slug = 'activity-logs';

    protected static ?string $navigationLabel = 'Log Aktivitas';

    // Super Admin melihat menu ini datar (tanpa grup); role lain tetap
    // di bawah grup Audit seperti biasa.
    public static function getNavigationGroup(): ?string
    {
        return auth()->user()?->hasRole('Super Admin') ? null : 'Audit';
    }

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
            && app(ActivityLogPolicy::class)->viewAny($user);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),

                TextColumn::make('log_name')
                    ->label('Log')
                    ->badge()
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Deskripsi')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('subject_type')
                    ->label('Subjek')
                    ->formatStateUsing(fn (?string $state): string => static::labelSubjek($state))
                    ->sortable(),

                TextColumn::make('causer.full_name')
                    ->label('Pelaku')
                    ->placeholder('Sistem')
                    ->searchable(),

                TextColumn::make('properties')
                    ->label('Properties')
                    ->formatStateUsing(fn ($state): string => static::previewProperties($state))
                    ->limit(60)
                    ->tooltip(fn (Activity $record): ?string => static::previewProperties($record->properties, true)),
            ])
            ->filters([
                SelectFilter::make('subject_type')
                    ->label('Tipe subjek')
                    ->options(static::subjectTypeOptions()),

                SelectFilter::make('causer_id')
                    ->label('Pelaku')
                    ->options(fn (): array => User::query()
                        ->orderBy('full_name')
                        ->pluck('full_name', 'id')
                        ->all())
                    ->searchable(),

                Filter::make('created_at')
                    ->label('Rentang tanggal')
                    ->schema([
                        DatePicker::make('from')
                            ->label('Dari'),
                        DatePicker::make('until')
                            ->label('Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function subjectTypeOptions(): array
    {
        return [
            User::class => 'Pengguna',
            AcademicUnit::class => 'Unit akademik',
            AcademicUnitUser::class => 'Penugasan unit',
            Kurikulum::class => 'Kurikulum',
            ProfilLulusan::class => 'Profil lulusan',
            Cpl::class => 'CPL',
            Mk::class => 'Mata kuliah',
            MkUnit::class => 'Penawaran MK',
            KelasMk::class => 'Kelas MK',
            NilaiMahasiswa::class => 'Nilai mahasiswa',
        ];
    }

    protected static function labelSubjek(?string $subjectType): string
    {
        if ($subjectType === null) {
            return '—';
        }

        return static::subjectTypeOptions()[$subjectType]
            ?? class_basename($subjectType);
    }

    protected static function previewProperties(mixed $state, bool $pretty = false): string
    {
        if ($state === null) {
            return '—';
        }

        $flags = JSON_UNESCAPED_UNICODE | ($pretty ? JSON_PRETTY_PRINT : 0);

        if (is_array($state)) {
            return json_encode($state, $flags) ?: '—';
        }

        if (is_string($state)) {
            $decoded = json_decode($state, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return json_encode($decoded, $flags) ?: $state;
            }

            return $state;
        }

        return (string) $state;
    }
}
