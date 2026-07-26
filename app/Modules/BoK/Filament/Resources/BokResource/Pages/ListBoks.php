<?php

namespace App\Modules\BoK\Filament\Resources\BokResource\Pages;

use App\Modules\BoK\Filament\Resources\BokResource;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Str;

class ListBoks extends ListRecords
{
    use HasImporMassal;

    protected static string $resource = BokResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makeImporMassalAction()
                ->visible(fn (): bool => BokResource::canCreate()),
            CreateAction::make(),
        ];
    }

    protected function importModalHeading(): string
    {
        return 'Impor bahan kajian (BoK) massal';
    }

    /**
     * @return list<string>
     */
    protected function importContextKeys(): array
    {
        return ['import_kurikulum_id'];
    }

    /**
     * @return array<int, Component|Field>
     */
    protected function importContextComponents(): array
    {
        return [
            Select::make('import_kurikulum_id')
                ->label('Kurikulum')
                ->options(KurikulumTerpilih::options())
                ->searchable()
                ->required()
                ->default(fn (): ?string => KurikulumTerpilih::currentId()),
        ];
    }

    protected function importColumns(): array
    {
        return [
            ['key' => 'kode', 'label' => 'kode', 'wajib' => true],
            ['key' => 'nama', 'label' => 'nama', 'wajib' => true],
            ['key' => 'deskripsi', 'label' => 'deskripsi', 'wajib' => false],
            ['key' => 'kode_cpl', 'label' => 'kode CPL terpetakan', 'wajib' => false],
        ];
    }

    protected function importInstructionsExtra(): array
    {
        return [
            'Satu baris = satu bahan kajian (BoK) pada unit kurikulum yang dipilih di atas.',
            'Kode CPL terpetakan boleh lebih dari satu; pisahkan dengan titik koma (;), mis. CPL-01;CPL-02.',
            'Setiap kode CPL harus sudah ada pada unit yang sama sebelum impor.',
        ];
    }

    /**
     * @return list<string>
     */
    protected function importExampleRows(): array
    {
        return [
            "BOK-01\tAljabar\tDasar aljabar linear\tCPL-01;CPL-02",
            "BOK-02\tStatistik\t\t",
        ];
    }

    protected function importHelperNote(): string
    {
        return '';
    }

    protected function resolveImportRow(array $data, array $context): array
    {
        $unitId = $this->unitIdDariKonteks($context);

        if (blank($unitId)) {
            return ['status' => 'invalid', 'keterangan' => 'Pilih kurikulum akademik terlebih dahulu.'];
        }

        if ($data['kode_cpl'] !== '') {
            $pesanInvalid = $this->validasiKodeCpl($data['kode_cpl'], $unitId);

            if ($pesanInvalid !== null) {
                return ['status' => 'invalid', 'keterangan' => $pesanInvalid];
            }
        }

        $existing = Bok::query()
            ->where('academic_unit_id', $unitId)
            ->where('kode', $data['kode'])
            ->first();

        if ($existing) {
            return [
                'status' => 'duplikat',
                'keterangan' => 'Kode BoK sudah ada pada unit ini.',
                'existing_id' => $existing->id,
                'dedup' => mb_strtolower($data['kode']),
            ];
        }

        return ['status' => 'baru', 'keterangan' => '', 'dedup' => mb_strtolower($data['kode'])];
    }

    protected function createImportRow(array $data, array $context): void
    {
        $unitId = $this->unitIdDariKonteks($context);
        $bok = Bok::query()->create([
            'academic_unit_id' => $unitId,
            'kode' => $data['kode'],
            'nama' => $data['nama'],
            'deskripsi' => $data['deskripsi'] ?: null,
        ]);

        $this->petakanCpl($bok, $data, $context);
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function updateImportRow(string $existingId, array $data, array $context): void
    {
        $bok = Bok::query()->findOrFail($existingId);

        $bok->update([
            'nama' => $data['nama'],
            'deskripsi' => $data['deskripsi'] ?: $bok->deskripsi,
        ]);

        $this->petakanCpl($bok, $data, $context);
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function petakanCpl(Bok $bok, array $data, array $context): void
    {
        $unitId = $this->unitIdDariKonteks($context);

        if (blank($unitId)) {
            return;
        }

        foreach ($this->kodeCplDariBaris($data['kode_cpl']) as $kodeCpl) {
            $cpl = Cpl::query()
                ->where('academic_unit_id', $unitId)
                ->where('kode', $kodeCpl)
                ->first();

            if ($cpl) {
                CplBok::query()->firstOrCreate(
                    ['cpl_id' => $cpl->id, 'bok_id' => $bok->id],
                    ['id' => (string) Str::uuid()],
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    protected function kodeCplDariBaris(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        return collect(explode(';', $raw))
            ->map(fn (string $kode): string => trim($kode))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function validasiKodeCpl(string $raw, string $unitId): ?string
    {
        $kodes = $this->kodeCplDariBaris($raw);

        if ($kodes === []) {
            return null;
        }

        foreach ($kodes as $kode) {
            $cplAda = Cpl::query()
                ->where('academic_unit_id', $unitId)
                ->where('kode', $kode)
                ->exists();

            if (! $cplAda) {
                return "CPL '{$kode}' tidak ditemukan pada unit ini.";
            }
        }

        return null;
    }

    /**
     * Unit pemilik diturunkan dari kurikulum terpilih pada konteks impor.
     *
     * @param  array<string, mixed>  $context
     */
    protected function unitIdDariKonteks(array $context): ?string
    {
        $kurikulumId = $context['import_kurikulum_id'] ?? null;

        if (blank($kurikulumId)) {
            return null;
        }

        return Kurikulum::query()->whereKey($kurikulumId)->value('academic_unit_id');
    }
}
