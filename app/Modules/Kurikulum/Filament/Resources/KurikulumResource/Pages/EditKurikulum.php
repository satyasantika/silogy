<?php

namespace App\Modules\Kurikulum\Filament\Resources\KurikulumResource\Pages;

use App\Modules\Kurikulum\Filament\Resources\KurikulumResource;
use App\Support\Filament\Pages\BaseSimpleEditRecord;
use App\Modules\Kurikulum\Support\SinkronValidatorKurikulum;
use Filament\Actions\DeleteAction;

class EditKurikulum extends BaseSimpleEditRecord
{
    protected static string $resource = KurikulumResource::class;

    /** @var array<int, string> */
    protected array $validatorTambahanSebelumnya = [];

    protected function beforeSave(): void
    {
        $this->validatorTambahanSebelumnya = $this->getRecord()
            ->validatorTambahan()->pluck('users.id')->all();
    }

    protected function afterSave(): void
    {
        // Select ->relationship() menulis pivot tanpa memicu event model.
        app(SinkronValidatorKurikulum::class)
            ->sinkronkan($this->getRecord(), $this->validatorTambahanSebelumnya);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => $this->getRecord()->belumDigunakanDiTabelLain()),
        ];
    }
}
