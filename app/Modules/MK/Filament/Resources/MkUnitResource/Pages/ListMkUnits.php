<?php

namespace App\Modules\MK\Filament\Resources\MkUnitResource\Pages;

use App\Modules\Kurikulum\Filament\Support\Concerns\HasKurikulumPipelineNav;
use App\Modules\MK\Filament\Concerns\HasAdaptasiMkMassal;
use App\Modules\MK\Filament\Concerns\HasImporUpdateMkUnitMassal;
use App\Modules\MK\Filament\Concerns\HasSalinMkUnitMassal;
use App\Modules\MK\Filament\Resources\MkUnitResource;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;

class ListMkUnits extends ListRecords
{
    use HasAdaptasiMkMassal;
    use HasImporUpdateMkUnitMassal;
    use HasKurikulumPipelineNav;
    use HasSalinMkUnitMassal;

    protected static string $resource = MkUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makeAdaptasiMkMassalAction()
                ->visible(fn (): bool => MkUnitResource::canCreate()),
            // Alat lanjutan (salin ke Excel & update kode/semester) dikumpulkan
            // di satu trigger dropdown supaya header tidak penuh tombol.
            ActionGroup::make([
                $this->makeSalinMkUnitAction(),
                $this->makeUpdateMkUnitMassalAction(),
            ])
                ->label('Alat penawaran MK')
                ->icon(Heroicon::OutlinedWrenchScrewdriver)
                ->color('gray')
                ->button()
                ->visible(fn (): bool => MkUnitResource::canCreate()),
        ];
    }

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
        return 'mk_unit';
    }
}
