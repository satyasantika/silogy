<?php

namespace App\Modules\Kurikulum\Filament\Resources\KurikulumResource\Pages;

use App\Modules\Kurikulum\Filament\Resources\KurikulumResource;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\SinkronValidatorKurikulum;
use App\Support\Filament\Pages\BaseSimpleEditRecord;
use Filament\Actions\DeleteAction;

class EditKurikulum extends BaseSimpleEditRecord
{
    protected static string $resource = KurikulumResource::class;

    /** @var array<int, string> */
    protected array $validatorTambahanSebelumnya = [];

    protected function beforeSave(): void
    {
        /** @var Kurikulum $kurikulum */
        $kurikulum = $this->getRecord();

        $this->validatorTambahanSebelumnya = $kurikulum
            ->validatorTambahan()->pluck('users.id')->all();
    }

    protected function afterSave(): void
    {
        /** @var Kurikulum $kurikulum */
        $kurikulum = $this->getRecord();

        // Select ->relationship() menulis pivot tanpa memicu event model.
        app(SinkronValidatorKurikulum::class)
            ->sinkronkan($kurikulum, $this->validatorTambahanSebelumnya);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => $this->getRecord()->belumDigunakanDiTabelLain()),
        ];
    }
}
