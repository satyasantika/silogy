<?php

namespace App\Modules\MK\Filament\Resources\CpmkResource\RelationManagers;

use App\Modules\CPL\Models\CplMk;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\MkCpmk;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MkCpmkRelationManager extends RelationManager
{
    protected static string $relationship = 'mkCpmks';

    protected static ?string $title = 'Pemetaan CPL–CPMK';

    protected static ?string $modelLabel = 'pemetaan CPL–CPMK';

    public static function formatMkCpmkLabel(MkCpmk $mkCpmk): string
    {
        $mkCpmk->loadMissing(['cpmk', 'cplMk.cplBok.cpl']);

        $cplKode = $mkCpmk->cplMk?->cplBok?->cpl?->kode ?? '—';

        return sprintf('%s via %s', $mkCpmk->cpmk?->kode ?? '—', $cplKode);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('cpl_mk_id')
                    ->label('CPL–MK')
                    ->options(function (): array {
                        /** @var Cpmk $cpmk */
                        $cpmk = $this->getOwnerRecord();

                        return CplMk::query()
                            ->where('mk_id', $cpmk->mk_id)
                            ->with('cplBok.cpl')
                            ->get()
                            ->mapWithKeys(fn (CplMk $cplMk): array => [
                                $cplMk->id => sprintf(
                                    '%s (bobot CPL–MK: %s%%)',
                                    $cplMk->cplBok?->cpl?->kode ?? '—',
                                    $cplMk->bobot,
                                ),
                            ])
                            ->all();
                    })
                    ->searchable()
                    ->required(),

                TextInput::make('bobot')
                    ->label('Bobot (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cplMk.cplBok.cpl.kode')
                    ->label('CPL'),

                TextColumn::make('cpmk.kode')
                    ->label('CPMK'),

                TextColumn::make('bobot')
                    ->label('Bobot (%)')
                    ->suffix('%'),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['cpmk', 'cplMk.cplBok.cpl']))
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        /** @var Cpmk $cpmk */
                        $cpmk = $this->getOwnerRecord();
                        $data['cpmk_id'] = $cpmk->id;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
