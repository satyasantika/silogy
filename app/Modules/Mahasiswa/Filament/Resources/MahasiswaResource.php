<?php

namespace App\Modules\Mahasiswa\Filament\Resources;

use App\Models\User;
use App\Modules\Institusi\Filament\Resources\AcademicUnitResource;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Mahasiswa\Filament\Resources\MahasiswaResource\Pages\CreateMahasiswa;
use App\Modules\Mahasiswa\Filament\Resources\MahasiswaResource\Pages\EditMahasiswa;
use App\Modules\Mahasiswa\Filament\Resources\MahasiswaResource\Pages\ListMahasiswas;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class MahasiswaResource extends Resource
{
    protected static ?string $model = Mahasiswa::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'mahasiswa';

    protected static ?string $pluralModelLabel = 'mahasiswa';

    protected static ?string $recordTitleAttribute = 'nama';

    protected static ?string $slug = 'mahasiswas';

    // Super Admin melihat menu ini datar (tanpa grup); role lain tetap
    // di bawah grup Mahasiswa seperti biasa.
    public static function getNavigationGroup(): ?string
    {
        return auth()->user()?->hasRole('Super Admin') ? null : 'Mahasiswa';
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'aktif' => 'Aktif',
            'cuti' => 'Cuti',
            'lulus' => 'Lulus',
            'do' => 'Drop Out',
            'nonaktif' => 'Nonaktif',
        ];
    }

    /**
     * @return Collection<int, string>
     */
    public static function scopedStudyProgramIds(): Collection
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return collect();
        }

        return AcademicUnitScope::scopedStudyProgramIdsFor($user);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('academicUnit');

        $user = Auth::user();

        if (! $user instanceof User) {
            return $query;
        }

        if ($user->hasRole(['Super Admin', 'Auditor Mutu'])) {
            return $query;
        }

        $prodiIds = static::scopedStudyProgramIds();

        if ($prodiIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('academic_unit_id', $prodiIds);
    }

    public static function form(Schema $schema): Schema
    {
        $scopedProdiIds = static::scopedStudyProgramIds();

        return $schema
            ->components([
                Section::make('Data Mahasiswa')
                    ->schema([
                        TextInput::make('nim')
                            ->label('NIM')
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true),

                        TextInput::make('nama')
                            ->label('Nama')
                            ->required()
                            ->maxLength(150),

                        Select::make('jenis_kelamin')
                            ->label('Jenis kelamin')
                            ->options([
                                'L' => 'Laki-laki',
                                'P' => 'Perempuan',
                            ]),

                        TextInput::make('angkatan')
                            ->label('Angkatan')
                            ->maxLength(4)
                            ->length(4)
                            ->numeric(),

                        Select::make('academic_unit_id')
                            ->label('Program studi')
                            ->options(function () use ($scopedProdiIds): array {
                                return AcademicUnit::query()
                                    ->whereIn('id', $scopedProdiIds)
                                    ->orderBy('nama')
                                    ->get()
                                    ->mapWithKeys(fn (AcademicUnit $unit): array => [
                                        $unit->id => AcademicUnitResource::formatUnitOptionLabel($unit),
                                    ])
                                    ->all();
                            })
                            ->searchable()
                            ->required()
                            ->default($scopedProdiIds->count() === 1 ? $scopedProdiIds->first() : null)
                            ->disabled($scopedProdiIds->count() === 1)
                            ->dehydrated(),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(100),

                        TextInput::make('nomor_wa')
                            ->label('Nomor WhatsApp')
                            ->tel()
                            ->maxLength(20)
                            ->placeholder('628xxxxxxxxxx'),

                        Select::make('status')
                            ->label('Status')
                            ->options(static::statusOptions())
                            ->default('aktif')
                            ->required(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $scopedProdiIds = static::scopedStudyProgramIds();

        return $table
            ->columns([
                TextColumn::make('nim')
                    ->label('NIM')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('angkatan')
                    ->label('Angkatan')
                    ->sortable(),

                TextColumn::make('academicUnit.nama_lengkap')
                    ->label('Program studi')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => static::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'aktif' => 'success',
                        'cuti' => 'warning',
                        'lulus' => 'info',
                        'do' => 'danger',
                        'nonaktif' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('angkatan')
                    ->label('Angkatan')
                    ->options(fn (): array => Mahasiswa::query()
                        ->distinct()
                        ->orderBy('angkatan')
                        ->pluck('angkatan', 'angkatan')
                        ->filter()
                        ->all()),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(static::statusOptions()),

                SelectFilter::make('academic_unit_id')
                    ->label('Program studi')
                    ->options(fn (): array => AcademicUnit::query()
                        ->whereIn('id', $scopedProdiIds)
                        ->orderBy('nama')
                        ->get()
                        ->mapWithKeys(fn (AcademicUnit $unit): array => [$unit->id => $unit->nama_lengkap])
                        ->all())
                    ->visible($scopedProdiIds->count() > 1),
            ])
            ->headerActions([
                Action::make('importCsv')
                    ->label('Import CSV')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->disabled()
                    ->tooltip('Coming soon Fase 2'),
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

    public static function getPages(): array
    {
        return [
            'index' => ListMahasiswas::route('/'),
            'create' => CreateMahasiswa::route('/create'),
            'edit' => EditMahasiswa::route('/{record}/edit'),
        ];
    }
}
