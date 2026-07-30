<?php

namespace App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource\Pages;

use App\Modules\Kurikulum\Filament\Concerns\HasImporProfilLulusanMassal;
use App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource;
use App\Modules\Kurikulum\Filament\Support\BannerKurikulumDikerjakan;
use App\Modules\Kurikulum\Filament\Support\Concerns\HasKurikulumPipelineNav;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Field;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;

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
    use HasKurikulumPipelineNav;

    protected static string $resource = ProfilLulusanResource::class;

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
                ...$this->kurikulumPipelineNavComponents(),
            ]);
    }

    protected function kurikulumPipelineStepKey(): string
    {
        return 'profil_lulusan';
    }

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
