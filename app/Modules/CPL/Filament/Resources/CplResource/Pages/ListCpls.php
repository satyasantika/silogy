<?php

namespace App\Modules\CPL\Filament\Resources\CplResource\Pages;

use App\Modules\CPL\Filament\Resources\CplResource;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplProfilLulusan;
use App\Modules\Kurikulum\Filament\Support\BannerKurikulumDikerjakan;
use App\Modules\Kurikulum\Filament\Support\Concerns\HasKurikulumPipelineNav;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Kurikulum\Support\KurikulumPipeline;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Field;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Str;

class ListCpls extends ListRecords
{
    use HasImporMassal;
    use HasKurikulumPipelineNav;

    protected static string $resource = CplResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makeImporMassalAction()
                ->visible(fn (): bool => CplResource::canCreate() && $this->adaCplKurikulum()),
            CreateAction::make(),
        ];
    }

    protected function getTableEmptyStateActions(): array
    {
        return [
            $this->makeImporMassalAction()
                ->visible(fn (): bool => CplResource::canCreate()),
        ];
    }

    protected function adaCplKurikulum(): bool
    {
        $kurikulum = KurikulumTerpilih::current();

        return $kurikulum instanceof Kurikulum
            && KurikulumPipeline::hasData('cpl', $kurikulum);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
                ...$this->kurikulumPipelineNavComponents(),
            ]);
    }

    protected function kurikulumPipelineStepKey(): string
    {
        return 'cpl';
    }

    protected function importModalHeading(): string
    {
        return 'Impor CPL massal';
    }

    /**
     * @return array<int, Component|Field>
     */
    protected function importContextComponents(): array
    {
        return [
            BannerKurikulumDikerjakan::placeholder(
                'Seluruh baris akan diimpor sebagai CPL pada kurikulum ini.',
                wajibProdi: false,
            ),
        ];
    }

    protected function importColumns(): array
    {
        return [
            ['key' => 'kode', 'label' => 'kode', 'wajib' => true],
            ['key' => 'deskripsi', 'label' => 'deskripsi', 'wajib' => true],
            ['key' => 'domain', 'label' => 'domain', 'wajib' => false],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function importColumnsForContext(array $context = []): array
    {
        if (! $this->isKurikulumProdi()) {
            return $this->importColumns();
        }

        return [
            ['key' => 'profil', 'label' => 'profil lulusan', 'wajib' => false],
            ...$this->importColumns(),
        ];
    }

    protected function importInstructionsExtra(): array
    {
        return [
            'Satu baris = satu CPL pada kurikulum yang sedang dikerjakan (lihat banner di atas).',
            'Domain opsional: kognitif, afektif, dan/atau psikomotorik; lebih dari satu dipisah koma (,), mis. kognitif,afektif.',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function importInstructionsExtraForContext(array $context = []): array
    {
        $extra = $this->importInstructionsExtra();

        if (! $this->isKurikulumProdi()) {
            return $extra;
        }

        return [
            ...$extra,
            'Isi kode profil (mis. PL-1) atau nama profil; lebih dari satu dipisah titik koma (;), mis. Pendidik;Peneliti.',
            'Setiap profil harus sudah ada pada kurikulum prodi yang dipilih sebelum impor.',
            'Pratinjau menampilkan jumlah profil yang terdeteksi dari daftar titik koma.',
        ];
    }

    /**
     * @return list<string>
     */
    protected function importExampleRows(): array
    {
        return [
            "CPL-01\tMampu memahami konsep dasar\tkognitif,afektif",
            "CPL-02\tMampu menganalisis data\t",
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    protected function importExampleRowsForContext(array $context = []): array
    {
        if (! $this->isKurikulumProdi()) {
            return $this->importExampleRows();
        }

        return [
            "Pendidik;Peneliti\tCPL-01\tMampu memahami konsep dasar\tkognitif,afektif",
            "\tCPL-02\tMampu menganalisis data\t",
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
            return ['status' => 'invalid', 'keterangan' => 'Pilih kurikulum akademik terlebih dahulu lewat banner kurikulum.'];
        }

        if ($data['domain'] !== '' && $this->parseDomainImport($data['domain']) === null) {
            return ['status' => 'invalid', 'keterangan' => 'Domain harus kognitif, afektif, dan/atau psikomotorik, dipisah koma.'];
        }

        $ringkasanProfil = $this->ringkasanProfilImport($data);
        $pesanProfil = $this->isKurikulumProdi()
            ? $this->validasiProfilImport($data)
            : null;

        if ($pesanProfil !== null) {
            return ['status' => 'invalid', 'keterangan' => $pesanProfil];
        }

        $existing = Cpl::query()
            ->where('kurikulum_id', $kurikulumId)
            ->where('kode', $data['kode'])
            ->first();

        if ($existing) {
            return [
                'status' => 'duplikat',
                'keterangan' => "Kode CPL sudah ada pada kurikulum ini. {$ringkasanProfil}.",
                'existing_id' => $existing->id,
                'dedup' => mb_strtolower($data['kode']),
            ];
        }

        return [
            'status' => 'baru',
            'keterangan' => $ringkasanProfil,
            'dedup' => mb_strtolower($data['kode']),
        ];
    }

    protected function createImportRow(array $data, array $context): void
    {
        $unitId = $this->unitIdDariKonteks();
        $kurikulumId = $this->kurikulumIdDariKonteks();

        $cpl = Cpl::query()->create([
            'academic_unit_id' => $unitId,
            'kurikulum_id' => $kurikulumId,
            'kode' => $data['kode'],
            'deskripsi' => $data['deskripsi'],
            'domain' => $data['domain'] !== '' ? $this->parseDomainImport($data['domain']) : null,
        ]);

        $this->petakanProfil($cpl, $data);
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function updateImportRow(string $existingId, array $data, array $context): void
    {
        $cpl = Cpl::query()->findOrFail($existingId);

        $cpl->update([
            'deskripsi' => $data['deskripsi'],
            'domain' => $data['domain'] !== '' ? $this->parseDomainImport($data['domain']) : $cpl->domain,
        ]);

        $this->petakanProfil($cpl, $data);
    }

    /**
     * @param  array<string, string>  $data
     */
    protected function petakanProfil(Cpl $cpl, array $data): void
    {
        if (! $this->isKurikulumProdi() || trim($data['profil']) === '') {
            return;
        }

        $kurikulumId = (string) KurikulumTerpilih::currentId();

        foreach ($this->referensiProfilDariBaris($data['profil']) as $referensi) {
            $profil = $this->cariProfilLulusan($kurikulumId, $referensi);

            if (! $profil) {
                continue;
            }

            CplProfilLulusan::query()->firstOrCreate([
                'cpl_id' => $cpl->id,
                'profil_lulusan_id' => $profil->id,
            ]);
        }
    }

    /**
     * @param  array<string, string>  $data
     */
    protected function validasiProfilImport(array $data): ?string
    {
        $referensi = $this->referensiProfilDariBaris($data['profil']);

        if ($referensi === []) {
            return null;
        }

        $kurikulumId = (string) KurikulumTerpilih::currentId();

        foreach ($referensi as $referensiProfil) {
            if (! $this->cariProfilLulusan($kurikulumId, $referensiProfil)) {
                return "Profil lulusan '{$referensiProfil}' tidak ditemukan pada kurikulum prodi ini.";
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $data
     */
    protected function ringkasanProfilImport(array $data): string
    {
        if (! $this->isKurikulumProdi()) {
            return '';
        }

        $jumlah = count($this->referensiProfilDariBaris($data['profil']));

        return $jumlah > 0 ? "{$jumlah} profil terpetakan" : 'Tanpa profil';
    }

    /**
     * @return list<string>
     */
    protected function referensiProfilDariBaris(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        return collect(explode(';', $raw))
            ->map(fn (string $referensi): string => trim($referensi))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function cariProfilLulusan(string $kurikulumId, string $referensi): ?ProfilLulusan
    {
        $normal = mb_strtolower(trim($referensi));

        return ProfilLulusan::query()
            ->where('kurikulum_id', $kurikulumId)
            ->where(function ($query) use ($referensi, $normal): void {
                $query->where('kode', $referensi)
                    ->orWhereRaw('LOWER(TRIM(nama)) = ?', [$normal]);
            })
            ->first();
    }

    /**
     * @return list<string>|null null bila ada domain yang tidak dikenal
     */
    protected function parseDomainImport(string $raw): ?array
    {
        $domains = collect(explode(',', $raw))
            ->map(fn (string $domain): string => Str::lower(trim($domain)))
            ->filter()
            ->unique()
            ->values();

        if ($domains->isEmpty() || $domains->contains(fn (string $domain): bool => ! in_array($domain, ['kognitif', 'afektif', 'psikomotorik'], true))) {
            return null;
        }

        return $domains->all();
    }

    protected function isKurikulumProdi(): bool
    {
        return KurikulumTerpilih::current()?->academicUnit?->isProdi() ?? false;
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
