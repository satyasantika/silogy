<?php

namespace App\Modules\MK\Filament\Resources\CpmkResource\Pages;

use App\Modules\MK\Filament\Resources\CpmkResource;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Services\CpmkCplPemetaanService;
use App\Support\Filament\Pages\BaseSimpleEditRecord;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;

class EditCpmk extends BaseSimpleEditRecord
{
    protected static string $resource = CpmkResource::class;

    /** @var list<string> */
    private array $kodeCplTerpilih = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Cpmk $record */
        $record = $this->getRecord();
        $data['kode_cpl_terpetakan'] = CpmkCplPemetaanService::kodeCplTerpetakan($record);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->kodeCplTerpilih = array_values(array_filter((array) ($data['kode_cpl_terpetakan'] ?? [])));
        unset($data['kode_cpl_terpetakan']);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Cpmk $record */
        $record = $this->getRecord();
        $tertahan = CpmkCplPemetaanService::sinkronkanCpl($record, $this->kodeCplTerpilih);

        if ($tertahan !== []) {
            Notification::make()
                ->title('Sebagian pemetaan CPL tidak dilepas')
                ->body('CPL '.implode(', ', $tertahan).' masih punya Sub-CPMK. Hapus Sub-CPMK terkait terlebih dahulu.')
                ->warning()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => $this->getRecord()->belumDiinteraksikan()),
        ];
    }
}
