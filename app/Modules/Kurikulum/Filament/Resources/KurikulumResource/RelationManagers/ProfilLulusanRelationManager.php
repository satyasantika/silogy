<?php

namespace App\Modules\Kurikulum\Filament\Resources\KurikulumResource\RelationManagers;

use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProfilLulusanRelationManager extends BaseKurikulumRelationManager
{
    use HasImporMassal;

    protected static string $relationship = 'profilLulusan';

    protected static ?string $title = 'Profil Lulusan';

    protected static ?string $modelLabel = 'profil lulusan';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var Kurikulum $ownerRecord */
        $ownerRecord->loadMissing('academicUnit');

        return $ownerRecord->academicUnit->isProdi();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode')
                    ->label('Kode')
                    ->required()
                    ->maxLength(10),

                TextInput::make('nama')
                    ->label('Nama')
                    ->maxLength(150),

                Textarea::make('deskripsi')
                    ->label('Deskripsi')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),

                TextInput::make('urutan')
                    ->label('Urutan')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(255),

                Repeater::make('indikators')
                    ->label('Indikator')
                    ->relationship()
                    ->schema([
                        Textarea::make('nama')
                            ->label('Nama indikator')
                            ->rows(2),
                        Textarea::make('deskripsi')
                            ->label('Deskripsi')
                            ->rows(2),
                    ])
                    ->defaultItems(1)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')->label('Kode')->sortable(),
                TextColumn::make('nama')->label('Nama')->searchable(),
                TextColumn::make('indikators_count')
                    ->label('Indikator')
                    ->counts('indikators'),
                TextColumn::make('urutan')->label('Urutan'),
            ])
            ->headerActions([
                $this->makeImporMassalAction()
                    ->visible(fn (): bool => $this->canCreate()),
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    protected function importModalHeading(): string
    {
        return 'Impor profil lulusan massal';
    }

    protected function importColumns(): array
    {
        return [
            ['key' => 'kode', 'label' => 'kode', 'wajib' => true],
            ['key' => 'nama', 'label' => 'nama', 'wajib' => false],
            ['key' => 'deskripsi', 'label' => 'deskripsi', 'wajib' => true],
            ['key' => 'urutan', 'label' => 'urutan', 'wajib' => false],
        ];
    }

    protected function importHelperNote(): string
    {
        return 'Profil diimpor ke kurikulum yang sedang dibuka.';
    }

    protected function resolveImportRow(array $data, array $context): array
    {
        if ($data['urutan'] !== '' && ! ctype_digit($data['urutan'])) {
            return ['status' => 'invalid', 'keterangan' => 'Urutan harus angka.'];
        }

        $existing = ProfilLulusan::query()
            ->where('kurikulum_id', $this->getOwnerRecord()->getKey())
            ->where('kode', $data['kode'])
            ->first();

        if ($existing) {
            return [
                'status' => 'duplikat',
                'keterangan' => 'Kode profil sudah ada pada kurikulum ini.',
                'existing_id' => $existing->id,
                'dedup' => mb_strtolower($data['kode']),
            ];
        }

        return ['status' => 'baru', 'keterangan' => '', 'dedup' => mb_strtolower($data['kode'])];
    }

    protected function createImportRow(array $data, array $context): void
    {
        ProfilLulusan::query()->create([
            'kurikulum_id' => $this->getOwnerRecord()->getKey(),
            'kode' => $data['kode'],
            'nama' => $data['nama'] ?: null,
            'deskripsi' => $data['deskripsi'],
            'urutan' => $data['urutan'] !== '' ? (int) $data['urutan'] : null,
        ]);
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function updateImportRow(string $existingId, array $data, array $context): void
    {
        $profil = ProfilLulusan::query()->findOrFail($existingId);

        $profil->update([
            'nama' => $data['nama'] ?: $profil->nama,
            'deskripsi' => $data['deskripsi'],
            'urutan' => $data['urutan'] !== '' ? (int) $data['urutan'] : $profil->urutan,
        ]);
    }
}
