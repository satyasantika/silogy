<?php

namespace App\Modules\Kurikulum\Filament\Resources\KurikulumResource\RelationManagers;

use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplProfilLulusan;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CplRelationManager extends BaseKurikulumRelationManager
{
    protected static string $relationship = 'cpls';

    protected static ?string $title = 'CPL';

    protected static ?string $modelLabel = 'CPL';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode')
                    ->label('Kode')
                    ->required()
                    ->maxLength(15),

                Textarea::make('deskripsi')
                    ->label('Deskripsi')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),

                Select::make('domain')
                    ->label('Domain')
                    ->options([
                        'kognitif' => 'Kognitif',
                        'afektif' => 'Afektif',
                        'psikomotorik' => 'Psikomotorik',
                        'gabungan' => 'Gabungan',
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')->label('Kode')->sortable(),
                TextColumn::make('deskripsi')->label('Deskripsi')->limit(60),
                TextColumn::make('domain')->label('Domain')->badge(),
                TextColumn::make('profil_mappings')
                    ->label('Profil terpetakan')
                    ->state(function (Cpl $record): string {
                        $count = $record->cplProfilLulusan()->count();

                        return (string) $count;
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        /** @var Kurikulum $owner */
                        $owner = $this->getOwnerRecord();
                        $data['academic_unit_id'] = $owner->academic_unit_id;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('petakanProfil')
                    ->label('Petakan ke Profil')
                    ->icon('heroicon-o-link')
                    ->visible(fn (): bool => $this->getOwnerRecord()->academicUnit->isProdi())
                    ->form(function (Cpl $record): array {
                        /** @var Kurikulum $kurikulum */
                        $kurikulum = $this->getOwnerRecord();

                        return [
                            Select::make('profil_lulusan_id')
                                ->label('Profil lulusan')
                                ->options(
                                    ProfilLulusan::query()
                                        ->where('kurikulum_id', $kurikulum->id)
                                        ->pluck('nama', 'id')
                                        ->map(fn ($nama, $id) => $nama ?: ProfilLulusan::find($id)?->kode)
                                        ->all(),
                                )
                                ->required(),
                        ];
                    })
                    ->action(function (Cpl $record, array $data): void {
                        CplProfilLulusan::query()->firstOrCreate([
                            'cpl_id' => $record->id,
                            'profil_lulusan_id' => $data['profil_lulusan_id'],
                        ]);

                        Notification::make()
                            ->title('CPL dipetakan ke profil lulusan')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make(),
            ]);
    }
}
