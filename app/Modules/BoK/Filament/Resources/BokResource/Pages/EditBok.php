<?php

namespace App\Modules\BoK\Filament\Resources\BokResource\Pages;

use App\Modules\BoK\Filament\Resources\BokResource;
use App\Modules\BoK\Models\Bok;
use App\Modules\BoK\Models\BokKodeOverride;
use App\Modules\Kurikulum\Support\CplBokAdaptasiScope;
use App\Support\Filament\Pages\BaseSimpleEditRecord;
use Filament\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Model;

class EditBok extends BaseSimpleEditRecord
{
    protected static string $resource = BokResource::class;

    /**
     * Unit milik saya yang benar-benar mengadaptasi BoK ini (bila asing),
     * atau null bila BoK ini milik saya sendiri / bukan bagian dari unit
     * manapun yang saya kelola (seharusnya tak pernah tercapai — record
     * asing-tak-teradaptasi sudah ditolak sejak route-model-binding oleh
     * BokResource::getEloquentQuery()).
     */
    protected function overrideUnitId(Bok $bok): ?string
    {
        return BokResource::scopedTimKurikulumUnitIds()
            ->first(fn (string $unitId): bool => CplBokAdaptasiScope::adaptedBokIds($unitId)->contains($bok->id));
    }

    protected function isAsing(Bok $bok): bool
    {
        return ! BokResource::scopedTimKurikulumUnitIds()->contains($bok->academic_unit_id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Bok $record */
        if ($this->isAsing($record)) {
            $unitId = $this->overrideUnitId($record);

            if ($unitId !== null) {
                BokKodeOverride::query()->updateOrCreate(
                    ['academic_unit_id' => $unitId, 'bok_id' => $record->id],
                    ['kode' => $data['kode_override']],
                );
            }

            return $record;
        }

        return parent::handleRecordUpdate($record, collect($data)->except('kode_override')->all());
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(function (): bool {
                    /** @var Bok $bok */
                    $bok = $this->getRecord();

                    return ! $this->isAsing($bok) && $bok->belumDiinteraksikan();
                }),
        ];
    }
}
