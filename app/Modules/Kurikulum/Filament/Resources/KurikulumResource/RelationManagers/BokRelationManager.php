<?php

namespace App\Modules\Kurikulum\Filament\Resources\KurikulumResource\RelationManagers;

use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\Kurikulum\Models\Kurikulum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BokRelationManager extends BaseKurikulumRelationManager
{
    public ?string $pendingCplId = null;

    protected static string $relationship = 'boks';

    protected static ?string $title = 'Bahan Kajian (BoK)';

    protected static ?string $modelLabel = 'BoK';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode')
                    ->label('Kode')
                    ->required()
                    ->maxLength(15),

                TextInput::make('nama')
                    ->label('Nama')
                    ->required()
                    ->maxLength(150),

                Textarea::make('deskripsi')
                    ->label('Deskripsi')
                    ->rows(3)
                    ->columnSpanFull(),

                Select::make('cpl_id')
                    ->label('Petakan ke CPL')
                    ->options(function (): array {
                        /** @var Kurikulum $kurikulum */
                        $kurikulum = $this->getOwnerRecord();

                        return Cpl::query()
                            ->where('academic_unit_id', $kurikulum->academic_unit_id)
                            ->pluck('kode', 'id')
                            ->all();
                    })
                    ->searchable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')->label('Kode')->sortable(),
                TextColumn::make('nama')->label('Nama')->searchable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        /** @var Kurikulum $owner */
                        $owner = $this->getOwnerRecord();
                        $this->pendingCplId = $data['cpl_id'] ?? null;
                        unset($data['cpl_id']);
                        $data['academic_unit_id'] = $owner->academic_unit_id;

                        return $data;
                    })
                    ->after(function (Bok $record): void {
                        if (filled($this->pendingCplId)) {
                            CplBok::query()->firstOrCreate([
                                'cpl_id' => $this->pendingCplId,
                                'bok_id' => $record->id,
                            ]);
                            $this->pendingCplId = null;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
