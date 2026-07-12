<?php

namespace App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\RelationManagers;

use App\Modules\MK\Models\Subcpmk;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Services\NormalisasiBobotSubcpmkService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubcpmkKomponenPenilaianRelationManager extends RelationManager
{
    protected static string $relationship = 'subcpmkKomponens';

    protected static ?string $title = 'Sub-CPMK pada Komponen';

    protected static ?string $modelLabel = 'Sub-CPMK';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('subcpmk_id')
                    ->label('Sub-CPMK')
                    ->options(function (): array {
                        /** @var KomponenPenilaian $komponen */
                        $komponen = $this->getOwnerRecord();
                        $komponen->loadMissing('kelasMk.mkUnit');

                        $mkId = $komponen->kelasMk?->mkUnit?->mk_id;

                        if ($mkId === null) {
                            return [];
                        }

                        return Subcpmk::query()
                            ->whereHas(
                                'mkCpmk.cpmk',
                                fn (Builder $query): Builder => $query->where('mk_id', $mkId),
                            )
                            ->with('mkCpmk.cpmk')
                            ->orderBy('kode')
                            ->get()
                            ->mapWithKeys(fn (Subcpmk $subcpmk): array => [
                                $subcpmk->id => sprintf(
                                    '%s – %s',
                                    $subcpmk->kode,
                                    $subcpmk->cpmk?->kode ?? '—',
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
                    ->default(100)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subcpmk.kode')
                    ->label('Sub-CPMK'),

                TextColumn::make('subcpmk.cpmk.kode')
                    ->label('CPMK')
                    ->getStateUsing(fn ($record): string => $record->subcpmk?->cpmk?->kode ?? '—'),

                TextColumn::make('bobot')
                    ->label('Bobot (%)')
                    ->suffix('%'),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['subcpmk.mkCpmk.cpmk']))
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Sub-CPMK'),
                Action::make('normalisasiBobotSubcpmk')
                    ->label('Normalisasi Bobot')
                    ->icon('heroicon-o-scale')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Normalisasi bobot Sub-CPMK')
                    ->modalDescription(
                        'Bobot tiap Sub-CPMK yang berinteraksi dengan asesmen ini akan disesuaikan '
                        .'secara proporsional lalu dibulatkan ke bilangan bulat terdekat, sehingga '
                        .'totalnya tepat 100%.',
                    )
                    ->modalSubmitActionLabel('Normalisasi')
                    ->visible(function (): bool {
                        /** @var KomponenPenilaian $komponen */
                        $komponen = $this->getOwnerRecord();
                        $total = (float) $komponen->subcpmkKomponens()->sum('bobot');

                        return $total > 0 && abs($total - 100) > 0.01;
                    })
                    ->action(function (): void {
                        /** @var KomponenPenilaian $komponen */
                        $komponen = $this->getOwnerRecord();

                        $hasil = app(NormalisasiBobotSubcpmkService::class)->normalisasi($komponen);

                        match ($hasil['status']) {
                            'dinormalisasi' => Notification::make()
                                ->title('Bobot Sub-CPMK berhasil dinormalisasi')
                                ->body(sprintf(
                                    'Total sebelumnya %.2f%% untuk %d Sub-CPMK, kini totalnya tepat 100%%.',
                                    $hasil['total_sebelum'],
                                    $hasil['jumlah'],
                                ))
                                ->success()
                                ->send(),
                            'sudah_pas' => Notification::make()
                                ->title('Total bobot sudah 100%')
                                ->body('Tidak ada perubahan yang diperlukan.')
                                ->info()
                                ->send(),
                            default => Notification::make()
                                ->title('Belum ada Sub-CPMK untuk dinormalisasi')
                                ->warning()
                                ->send(),
                        };
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
