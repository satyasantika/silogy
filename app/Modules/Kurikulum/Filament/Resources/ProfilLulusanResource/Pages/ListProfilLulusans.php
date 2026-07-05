<?php

namespace App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource\Pages;

use App\Modules\Kurikulum\Filament\Concerns\HasImporProfilLulusanMassal;
use App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;

class ListProfilLulusans extends ListRecords
{
    use HasImporMassal, HasImporProfilLulusanMassal {
        HasImporProfilLulusanMassal::importColumns insteadof HasImporMassal;
        HasImporProfilLulusanMassal::importInstructionsExtra insteadof HasImporMassal;
        HasImporProfilLulusanMassal::importExampleRows insteadof HasImporMassal;
        HasImporProfilLulusanMassal::resolveImportRow insteadof HasImporMassal;
        HasImporProfilLulusanMassal::createImportRow insteadof HasImporMassal;
        HasImporProfilLulusanMassal::updateImportRow insteadof HasImporMassal;
    }

    protected static string $resource = ProfilLulusanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makeImporMassalAction()
                ->visible(fn (): bool => ProfilLulusanResource::bisaKelola()),
            CreateAction::make(),
        ];
    }

    protected function importModalHeading(): string
    {
        return 'Impor profil lulusan massal';
    }

    /**
     * @return list<string>
     */
    protected function importContextKeys(): array
    {
        return ['import_kurikulum_id'];
    }

    /**
     * @return array<int, Component|Field>
     */
    protected function importContextComponents(): array
    {
        return [
            Select::make('import_kurikulum_id')
                ->label('Kurikulum (prodi)')
                ->options(ProfilLulusanResource::kurikulumProdiOptions())
                ->searchable()
                ->required()
                ->default(fn (): ?string => KurikulumTerpilih::current()?->academicUnit?->isProdi()
                    ? KurikulumTerpilih::currentId()
                    : null),
        ];
    }

    protected function importHelperNote(): string
    {
        return 'Seluruh baris diimpor ke kurikulum yang dipilih di atas.';
    }

    protected function kurikulumIdUntukImporProfil(array $context): ?string
    {
        $id = $context['import_kurikulum_id'] ?? null;

        return blank($id) ? null : (string) $id;
    }
}
