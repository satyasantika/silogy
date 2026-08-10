<?php

namespace App\Modules\Kalender\Filament\Resources;

use App\Modules\Kalender\Filament\Resources\SemesterResource\Pages\CreateSemester;
use App\Modules\Kalender\Filament\Resources\SemesterResource\Pages\EditSemester;
use App\Modules\Kalender\Filament\Resources\SemesterResource\Pages\ListSemesters;
use App\Modules\Kalender\Models\Semester;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SemesterResource extends Resource
{
    protected static ?string $model = Semester::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    // Menu Super Admin (satu-satunya role yang bisa melihat resource ini)
    // tampil datar tanpa header grup — sama seperti AcademicUnitResource.
    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'semester';

    protected static ?string $pluralModelLabel = 'semester';

    protected static ?string $recordTitleAttribute = 'nama';

    protected static ?string $slug = 'semesters';

    /**
     * Menu semester hanya untuk Super Admin, sama seperti unit akademik —
     * role operasional bekerja lewat semester yang sudah aktif/terpilih.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }

    /**
     * @return array<string, string>
     */
    public static function jenisOptions(): array
    {
        return [
            'ganjil' => 'Ganjil',
            'genap' => 'Genap',
            'pendek' => 'Pendek',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas semester')
                    ->schema([
                        TextInput::make('kode')
                            ->label('Kode')
                            ->required()
                            ->maxLength(5)
                            ->unique(ignoreRecord: true),

                        TextInput::make('nama')
                            ->label('Nama')
                            ->required()
                            ->maxLength(50),

                        Select::make('jenis')
                            ->label('Jenis')
                            ->options(static::jenisOptions())
                            ->required(),

                        Toggle::make('status_aktif')
                            ->label('Jadikan semester aktif')
                            ->helperText('Semester aktif lain akan otomatis dinonaktifkan.')
                            ->default(false),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Periode')
                    ->schema([
                        TextInput::make('tahun_mulai')
                            ->label('Tahun mulai')
                            ->required()
                            ->numeric(),

                        TextInput::make('tahun_selesai')
                            ->label('Tahun selesai')
                            ->required()
                            ->numeric(),

                        DatePicker::make('tanggal_mulai')
                            ->label('Tanggal mulai'),

                        DatePicker::make('tanggal_selesai')
                            ->label('Tanggal selesai'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('tahun_mulai', 'desc')
            ->columns([
                TextColumn::make('kode')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('jenis')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => static::jenisOptions()[$state] ?? $state),

                TextColumn::make('tahun_mulai')
                    ->label('Tahun mulai')
                    ->sortable(),

                TextColumn::make('tahun_selesai')
                    ->label('Tahun selesai')
                    ->sortable(),

                TextColumn::make('tanggal_mulai')
                    ->label('Mulai')
                    ->date()
                    ->placeholder('—'),

                TextColumn::make('tanggal_selesai')
                    ->label('Selesai')
                    ->date()
                    ->placeholder('—'),

                IconColumn::make('status_aktif')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('jenis')
                    ->label('Jenis')
                    ->options(static::jenisOptions()),
                TernaryFilter::make('status_aktif')
                    ->label('Status aktif'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSemesters::route('/'),
            'create' => CreateSemester::route('/create'),
            'edit' => EditSemester::route('/{record}/edit'),
        ];
    }
}
