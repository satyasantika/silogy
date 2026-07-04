<?php

namespace App\Modules\Kurikulum\Filament\Resources\KurikulumResource\Pages;

use App\Modules\Kurikulum\Filament\Resources\KurikulumResource;
use App\Support\Filament\Concerns\ForcesFullPageRender;
use App\Support\Filament\Pages\BaseEditRecord;
use Filament\Actions\DeleteAction;
use Filament\Schemas\Schema;

class EditKurikulum extends BaseEditRecord
{
    use ForcesFullPageRender;

    protected static string $resource = KurikulumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => $this->getRecord()->belumDigunakanDiTabelLain()),
        ];
    }

    /**
     * @return array<class-string>
     */
    public function getRelationManagers(): array
    {
        return [];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }
}
