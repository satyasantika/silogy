<?php

namespace App\Modules\MK\Filament\Support\Concerns;

use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\MK\Support\MkTerpilih;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Konteks impor massal: mata kuliah (MK terpilih) + semester (hanya Koordinator MK).
 */
trait HasImporMkSemesterKonteks
{
    /**
     * @return list<string>
     */
    protected function importMkSemesterContextKeys(): array
    {
        $keys = ['import_mk_id'];

        if (SemesterTerpilih::berlakuUntukUser()) {
            $keys[] = 'import_semester_id';
        }

        return $keys;
    }

    /**
     * @param  array<string, string>  $mkOptions
     * @return array<int, Component|Field>
     */
    protected function importMkSemesterContextComponents(array $mkOptions): array
    {
        $mkTerpilih = MkTerpilih::currentId();

        $mkSelect = filled($mkTerpilih) && array_key_exists($mkTerpilih, $mkOptions)
            ? Select::make('import_mk_id')
                ->label('Mata kuliah')
                ->options([$mkTerpilih => $mkOptions[$mkTerpilih]])
                ->default($mkTerpilih)
                ->disabled()
                ->dehydrated()
            : Select::make('import_mk_id')
                ->label('Mata kuliah')
                ->options($mkOptions)
                ->searchable()
                ->required()
                ->default(count($mkOptions) === 1 ? array_key_first($mkOptions) : null)
                ->live()
                ->afterStateUpdated(function (Set $set, ?string $state): void {
                    if (blank($state)) {
                        $set('import_semester_id', null);

                        return;
                    }

                    if (! SemesterTerpilih::berlakuUntukUser()) {
                        return;
                    }

                    SemesterTerpilih::set($state, SemesterTerpilih::defaultId());
                    $set('import_semester_id', SemesterTerpilih::currentId($state));
                });

        $components = [$mkSelect];

        if (SemesterTerpilih::berlakuUntukUser()) {
            $components[] = $this->importSemesterSelect($mkTerpilih);
        }

        return $components;
    }

    protected function importSemesterSelect(?string $mkTerpilih): Select
    {
        $semesterSelect = Select::make('import_semester_id')
            ->label('Semester')
            ->options(function (Get $get) use ($mkTerpilih): array {
                $mkId = $this->importMkIdDariKonteks($mkTerpilih, $get);

                if (blank($mkId)) {
                    return [];
                }

                return SemesterTerpilih::optionsSemua();
            })
            ->default(function (Get $get) use ($mkTerpilih): ?string {
                $mkId = $this->importMkIdDariKonteks($mkTerpilih, $get);

                if (blank($mkId)) {
                    return null;
                }

                return SemesterTerpilih::currentId($mkId)
                    ?? SemesterTerpilih::defaultId();
            })
            ->searchable()
            ->required()
            ->live()
            ->disabled(fn (Get $get): bool => blank($this->importMkIdDariKonteks($mkTerpilih, $get)))
            ->helperText('Semester diambil dari master semester.');

        if (filled($mkTerpilih)) {
            $semesterSelect->afterStateHydrated(function (Select $component) use ($mkTerpilih): void {
                $default = SemesterTerpilih::currentId($mkTerpilih)
                    ?? SemesterTerpilih::defaultId();

                if (filled($default)) {
                    $component->state($default);
                    SemesterTerpilih::set($mkTerpilih, $default);
                }
            });
        }

        return $semesterSelect;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function normalizeImportContext(array $context): array
    {
        $semesterId = $this->resolveImportSemesterId($context);

        if (filled($semesterId)) {
            $context['import_semester_id'] = $semesterId;
        }

        return $context;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function resolveImportSemesterId(array $context): ?string
    {
        if (SemesterTerpilih::berlakuUntukUser()) {
            $semesterId = $context['import_semester_id'] ?? null;

            return filled($semesterId) ? (string) $semesterId : null;
        }

        return SemesterTerpilih::defaultId();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{status: string, keterangan: string}|null
     */
    protected function validasiKonteksImporMkSemester(array $context): ?array
    {
        if (blank($context['import_mk_id'] ?? null)) {
            return ['status' => 'invalid', 'keterangan' => 'Pilih mata kuliah terlebih dahulu.'];
        }

        if (SemesterTerpilih::berlakuUntukUser()) {
            if (blank($context['import_semester_id'] ?? null)) {
                return ['status' => 'invalid', 'keterangan' => 'Pilih mata kuliah dan semester terlebih dahulu.'];
            }

            SemesterTerpilih::set(
                (string) $context['import_mk_id'],
                (string) $context['import_semester_id'],
            );

            return SemesterTerpilih::validasiUntukImpor((string) $context['import_semester_id']);
        }

        $semesterId = $this->resolveImportSemesterId($context);

        if (blank($semesterId)) {
            return ['status' => 'invalid', 'keterangan' => 'Semester aktif belum tersedia.'];
        }

        return SemesterTerpilih::validasiUntukImpor($semesterId);
    }

    protected function importMkIdDariKonteks(?string $mkTerpilih, Get $get): ?string
    {
        if (filled($mkTerpilih)) {
            return $mkTerpilih;
        }

        $fromForm = $get('import_mk_id');

        return filled($fromForm) ? (string) $fromForm : null;
    }
}
