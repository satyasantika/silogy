<?php

namespace App\Modules\CPL\Filament\Resources\CplResource\Pages;

use App\Modules\CPL\Filament\Resources\CplResource;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplKodeOverride;
use App\Modules\Kurikulum\Support\CplBokAdaptasiScope;
use App\Support\Filament\Pages\BaseSimpleEditRecord;
use Filament\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Model;

class EditCpl extends BaseSimpleEditRecord
{
    protected static string $resource = CplResource::class;

    /**
     * Unit milik saya yang benar-benar mengadaptasi CPL ini (bila asing),
     * atau null bila CPL ini milik saya sendiri / bukan bagian dari unit
     * manapun yang saya kelola (seharusnya tak pernah tercapai — record
     * asing-tak-teradaptasi sudah ditolak sejak route-model-binding oleh
     * CplResource::getEloquentQuery()).
     */
    protected function overrideUnitId(Cpl $cpl): ?string
    {
        return CplResource::scopedTimKurikulumUnitIds()
            ->first(fn (string $unitId): bool => CplBokAdaptasiScope::adaptedCplIdsAcrossUnit($unitId)->contains($cpl->id));
    }

    protected function isAsing(Cpl $cpl): bool
    {
        return ! CplResource::scopedTimKurikulumUnitIds()->contains($cpl->academic_unit_id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Cpl $record */
        if ($this->isAsing($record)) {
            $unitId = $this->overrideUnitId($record);

            if ($unitId !== null) {
                CplKodeOverride::query()->updateOrCreate(
                    ['academic_unit_id' => $unitId, 'cpl_id' => $record->id],
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
                    /** @var Cpl $cpl */
                    $cpl = $this->getRecord();

                    return ! $this->isAsing($cpl) && $cpl->belumDiinteraksikan();
                }),
        ];
    }
}
