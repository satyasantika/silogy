<?php

namespace App\Modules\MK\Filament\Resources\SubcpmkResource\Pages;

use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\MK\Filament\Resources\SubcpmkResource;
use App\Modules\MK\Filament\Support\Concerns\HasImporMkSemesterKonteks;
use App\Modules\MK\Filament\Support\Concerns\HasMkPipelineNav;
use App\Modules\MK\Filament\Support\Concerns\HasSalinAntarSemesterMassal;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\MK\Services\SubcpmkKompetensiParser;
use App\Modules\MK\Services\SubcpmkSalinSemesterService;
use App\Modules\MK\Support\MkTerpilih;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Field;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;

class ListSubcpmks extends ListRecords
{
    use HasImporMassal;
    use HasImporMkSemesterKonteks;
    use HasMkPipelineNav;
    use HasSalinAntarSemesterMassal;

    protected static string $resource = SubcpmkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makeImporMassalAction()
                ->visible(fn (): bool => SubcpmkResource::canCreate() && $this->adaSubcpmkSemester()),
            CreateAction::make(),
        ];
    }

    protected function getTableEmptyStateActions(): array
    {
        return [
            $this->makeImporMassalAction()
                ->visible(fn (): bool => SubcpmkResource::canCreate()),
            $this->makeSalinAntarSemesterAction(),
        ];
    }

    protected function adaSubcpmkSemester(): bool
    {
        $mkId = MkTerpilih::currentId();

        if (blank($mkId)) {
            return false;
        }

        $semesterId = SemesterTerpilih::currentId($mkId) ?? SemesterTerpilih::defaultId();

        if (blank($semesterId)) {
            return false;
        }

        return Subcpmk::query()
            ->where('semester_id', $semesterId)
            ->whereHas('mkCpmk.cpmk', fn ($query) => $query->where('mk_id', $mkId))
            ->exists();
    }

    protected function salinAntarSemesterEntitasLabel(): string
    {
        return 'Sub-CPMK';
    }

    protected function salinAntarSemesterMkId(): ?string
    {
        return MkTerpilih::currentId();
    }

    protected function salinAntarSemesterTargetSemesterId(): ?string
    {
        $mkId = MkTerpilih::currentId();

        if (blank($mkId)) {
            return null;
        }

        return SemesterTerpilih::currentId($mkId) ?? SemesterTerpilih::defaultId();
    }

    protected function salinAntarSemesterResolveBaris(string $sumberSemesterId, string $mkId, string $targetSemesterId): array
    {
        return app(SubcpmkSalinSemesterService::class)->resolveBaris($sumberSemesterId, $mkId, $targetSemesterId);
    }

    protected function salinAntarSemesterJalankan(array $rows, string $modeDuplikat, string $mkId, string $targetSemesterId): array
    {
        return app(SubcpmkSalinSemesterService::class)->jalankan($rows, $modeDuplikat, $mkId, $targetSemesterId);
    }

    protected function salinAntarSemesterSemesterIdsDenganData(string $mkId): array
    {
        return app(SubcpmkSalinSemesterService::class)->semesterIdsDenganData($mkId);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
                ...$this->mkPipelineNavComponents(),
            ]);
    }

    protected function mkPipelineStepKey(): string
    {
        return 'subcpmk';
    }

    protected function importModalHeading(): string
    {
        return 'Impor Sub-CPMK massal';
    }

    /**
     * @return list<string>
     */
    protected function importContextKeys(): array
    {
        return $this->importMkSemesterContextKeys();
    }

    /**
     * @return array<int, Component|Field>
     */
    protected function importContextComponents(): array
    {
        return $this->importMkSemesterContextComponents(
            SubcpmkResource::scopedKoordinatorMkOptions(),
        );
    }

    protected function importColumns(): array
    {
        return [
            ['key' => 'kode_cpmk', 'label' => 'kode CPMK', 'wajib' => true],
            ['key' => 'kode', 'label' => 'kode sub-CPMK', 'wajib' => true],
            ['key' => 'deskripsi', 'label' => 'deskripsi', 'wajib' => true],
            ['key' => 'kompetensi', 'label' => 'kompetensi (C/A/P)', 'wajib' => false],
            ['key' => 'indikator', 'label' => 'indikator', 'wajib' => false],
            ['key' => 'evaluasi', 'label' => 'evaluasi', 'wajib' => false],
        ];
    }

    protected function importHelperNote(): string
    {
        return 'Kode CPMK harus sudah ada pada mata kuliah yang dipilih di atas dan telah dipetakan ke CPL–MK. '
            .'Semester (Koordinator MK) diambil dari master semester. '
            .'Kompetensi opsional: taksonomi Bloom dipisah koma, contoh C3,A2,P2.';
    }

    /**
     * @return list<string>
     */
    protected function importExampleRows(): array
    {
        return [
            "CPMK-01\tSUB-01\tMenjelaskan definisi\tC3,A2,P2\tIndikator A\tUTS dan kuis",
            "CPMK-01\tSUB-02\tMenerapkan rumus\tC4\t\t",
        ];
    }

    protected function resolveImportRow(array $data, array $context): array
    {
        $validasiKonteks = $this->validasiKonteksImporMkSemester($context);

        if ($validasiKonteks !== null) {
            return $validasiKonteks;
        }

        $context = $this->normalizeImportContext($context);

        if (filled($data['kompetensi'] ?? null)) {
            $validasiKompetensi = SubcpmkKompetensiParser::validasi($data['kompetensi']);

            if (! $validasiKompetensi['valid']) {
                return ['status' => 'invalid', 'keterangan' => $validasiKompetensi['keterangan']];
            }
        }

        $mkCpmk = $this->mkCpmkDariKode($data['kode_cpmk'], $context);

        if ($mkCpmk === null) {
            return ['status' => 'invalid', 'keterangan' => "CPMK '{$data['kode_cpmk']}' tidak ditemukan pada MK ini atau belum dipetakan ke CPL–MK."];
        }

        $existing = Subcpmk::query()
            ->where('mk_cpmk_id', $mkCpmk->id)
            ->where('semester_id', $context['import_semester_id'])
            ->where('kode', $data['kode'])
            ->first();

        $dedup = mb_strtolower($data['kode_cpmk'].'/'.$data['kode']);

        if ($existing) {
            return [
                'status' => 'duplikat',
                'keterangan' => 'Kode Sub-CPMK sudah ada pada CPMK dan semester ini.',
                'existing_id' => $existing->id,
                'dedup' => $dedup,
            ];
        }

        return ['status' => 'baru', 'keterangan' => '', 'dedup' => $dedup];
    }

    protected function createImportRow(array $data, array $context): void
    {
        $context = $this->normalizeImportContext($context);
        $mkCpmk = $this->mkCpmkDariKode($data['kode_cpmk'], $context);
        $bloom = filled($data['kompetensi'] ?? null)
            ? SubcpmkKompetensiParser::parse($data['kompetensi'])
            : [];

        Subcpmk::query()->create([
            'mk_cpmk_id' => $mkCpmk?->id,
            'semester_id' => $context['import_semester_id'],
            'kode' => $data['kode'],
            'deskripsi' => $data['deskripsi'],
            'indikator' => $data['indikator'] ?: null,
            'evaluasi' => $data['evaluasi'] ?: null,
            ...$bloom,
        ]);
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function updateImportRow(string $existingId, array $data, array $context): void
    {
        $subcpmk = Subcpmk::query()->findOrFail($existingId);
        $payload = [
            'deskripsi' => $data['deskripsi'],
            'indikator' => $data['indikator'] ?: $subcpmk->indikator,
            'evaluasi' => filled($data['evaluasi'] ?? null) ? $data['evaluasi'] : $subcpmk->evaluasi,
        ];

        if (filled($data['kompetensi'] ?? null)) {
            $payload = [...$payload, ...SubcpmkKompetensiParser::parse($data['kompetensi'])];
        }

        $subcpmk->update($payload);
    }

    /**
     * Pemetaan CPL–MK milik CPMK berkode tersebut pada MK konteks.
     *
     * @param  array<string, mixed>  $context
     */
    protected function mkCpmkDariKode(string $kodeCpmk, array $context): ?MkCpmk
    {
        $cpmk = Cpmk::query()
            ->where('mk_id', $context['import_mk_id'] ?? null)
            ->where('kode', $kodeCpmk)
            ->first();

        if (! $cpmk) {
            return null;
        }

        return MkCpmk::query()->where('cpmk_id', $cpmk->id)->first();
    }
}
