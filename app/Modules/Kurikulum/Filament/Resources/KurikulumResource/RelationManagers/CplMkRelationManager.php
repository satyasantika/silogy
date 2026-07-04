<?php

namespace App\Modules\Kurikulum\Filament\Resources\KurikulumResource\RelationManagers;

use App\Modules\CPL\Models\CplBok;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Models\Mk;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CplMkRelationManager extends BaseKurikulumRelationManager
{
    protected static string $relationship = 'cplMks';

    protected static ?string $title = 'Pemetaan MK (CPL → BoK → MK)';

    protected static ?string $modelLabel = 'pemetaan MK';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('cpl_bok_id')
                    ->label('CPL + BoK')
                    ->options(function (): array {
                        /** @var Kurikulum $kurikulum */
                        $kurikulum = $this->getOwnerRecord();

                        return CplBok::query()
                            ->whereHas('cpl', fn ($q) => $q->where('academic_unit_id', $kurikulum->academic_unit_id))
                            ->with(['cpl', 'bok'])
                            ->get()
                            ->mapWithKeys(fn (CplBok $cb): array => [
                                $cb->id => "{$cb->cpl->kode} → {$cb->bok->kode}",
                            ])
                            ->all();
                    })
                    ->searchable()
                    ->required(),

                Select::make('mk_id')
                    ->label('Mata kuliah')
                    ->options(function (): array {
                        /** @var Kurikulum $kurikulum */
                        $kurikulum = $this->getOwnerRecord();

                        return Mk::query()
                            ->where('academic_unit_id', $kurikulum->academic_unit_id)
                            ->pluck('nama', 'id')
                            ->all();
                    })
                    ->searchable()
                    ->required(),

                TextInput::make('bobot')
                    ->label('Bobot (%)')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->maxValue(100),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cplBok.cpl.kode')->label('CPL'),
                TextColumn::make('cplBok.bok.kode')->label('BoK'),
                TextColumn::make('mk.nama')->label('Mata kuliah'),
                TextColumn::make('bobot')->label('Bobot (%)'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
