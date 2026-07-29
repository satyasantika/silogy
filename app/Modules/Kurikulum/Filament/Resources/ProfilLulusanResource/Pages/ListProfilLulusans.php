<?php

namespace App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource\Pages;

use App\Modules\Kurikulum\Filament\Concerns\HasImporProfilLulusanMassal;
use App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource;
use App\Modules\Kurikulum\Filament\Support\BannerKurikulumDikerjakan;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Field;
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
     * @return array<int, Component|Field>
     */
    protected function importContextComponents(): array
    {
        return [
            BannerKurikulumDikerjakan::placeholder(
                'Seluruh baris akan diimpor sebagai profil lulusan pada kurikulum prodi ini.',
            ),
        ];
    }

    protected function kurikulumIdUntukImporProfil(array $context): ?string
    {
        $kurikulum = KurikulumTerpilih::current();

        if (! $kurikulum?->academicUnit?->isProdi()) {
            return null;
        }

        return $kurikulum->id;
    }
}
