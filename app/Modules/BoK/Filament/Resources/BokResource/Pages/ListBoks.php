<?php

namespace App\Modules\BoK\Filament\Resources\BokResource\Pages;

use App\Modules\BoK\Filament\Resources\BokResource;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
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
            'Satu baris = satu bahan kajian (BoK) pada kurikulum yang sedang dikerjakan (lihat banner di atas halaman).',
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
        $unitId = $this->unitIdDariKonteks();
        $kurikulumId = $this->kurikulumIdDariKonteks();

        if (blank($unitId) || blank($kurikulumId)) {
            return ['status' => 'invalid', 'keterangan' => 'Pilih kurikulum akademik terlebih dahulu lewat banner di atas halaman.'];
        }

        if ($data['kode_cpl'] !== '') {
            $pesanInvalid = $this->validasiKodeCpl($data['kode_cpl'], $kurikulumId);

            if ($pesanInvalid !== null) {
                return ['status' => 'invalid', 'keterangan' => $pesanInvalid];
            }
        }

        $existing = Bok::query()
            ->where('kurikulum_id', $kurikulumId)
            ->where('kode', $data['kode'])
            ->first();

        if ($existing) {
            return [
                'status' => 'duplikat',
                'keterangan' => 'Kode BoK sudah ada pada kurikulum ini.',
                'existing_id' => $existing->id,
                'dedup' => mb_strtolower($data['kode']),
            ];
        }

        return ['status' => 'baru', 'keterangan' => '', 'dedup' => mb_strtolower($data['kode'])];
    }

    protected function createImportRow(array $data, array $context): void
    {
        $unitId = $this->unitIdDariKonteks();
        $kurikulumId = $this->kurikulumIdDariKonteks();

        $bok = Bok::query()->create([
            'academic_unit_id' => $unitId,
            'kurikulum_id' => $kurikulumId,
            'kode' => $data['kode'],
            'nama' => $data['nama'],
            'deskripsi' => $data['deskripsi'] ?: null,
        ]);

        $this->petakanCpl($bok, $data);
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

        $this->petakanCpl($bok, $data);
    }

    /**
     * @param  array<string, string>  $data
     */
    protected function petakanCpl(Bok $bok, array $data): void
    {
        $kurikulumId = $this->kurikulumIdDariKonteks();

        if (blank($kurikulumId)) {
            return;
        }

        foreach ($this->kodeCplDariBaris($data['kode_cpl']) as $kodeCpl) {
            $cpl = Cpl::query()
                ->where('kurikulum_id', $kurikulumId)
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

    protected function validasiKodeCpl(string $raw, string $kurikulumId): ?string
    {
        $kodes = $this->kodeCplDariBaris($raw);

        if ($kodes === []) {
            return null;
        }

        foreach ($kodes as $kode) {
            $cplAda = Cpl::query()
                ->where('kurikulum_id', $kurikulumId)
                ->where('kode', $kode)
                ->exists();

            if (! $cplAda) {
                return "CPL '{$kode}' tidak ditemukan pada kurikulum ini.";
            }
        }

        return null;
    }

    /**
     * Unit pemilik diturunkan dari kurikulum yang sedang dikerjakan (banner).
     */
    protected function unitIdDariKonteks(): ?string
    {
        return KurikulumTerpilih::current()?->academic_unit_id;
    }

    protected function kurikulumIdDariKonteks(): ?string
    {
        return KurikulumTerpilih::currentId();
    }
}
