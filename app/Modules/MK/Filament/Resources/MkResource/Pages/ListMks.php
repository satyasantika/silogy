<?php

namespace App\Modules\MK\Filament\Resources\MkResource\Pages;

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Kurikulum\Filament\Support\BannerKurikulumDikerjakan;
use App\Modules\Kurikulum\Filament\Support\Concerns\HasKurikulumPipelineNav;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\MkResource;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Services\MkResetService;
use App\Support\Filament\Concerns\HasImporMassal;
use App\Support\Filament\Concerns\HasResetTrigger;
use Filament\Forms\Components\Field;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Str;

class ListMks extends ListRecords
{
    use HasImporMassal;
    use HasKurikulumPipelineNav;
    use HasResetTrigger;

    protected static string $resource = MkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makeImporMassalAction()
                ->visible(fn (): bool => MkResource::canCreate()),
            $this->makeResetTriggerAction(),
        ];
    }

    protected function getTableEmptyStateActions(): array
    {
        return [
            $this->makeImporMassalAction()
                ->visible(fn (): bool => MkResource::canCreate()),
        ];
    }

    protected function resetEntitasLabel(): string
    {
        return 'Mata Kuliah';
    }

    protected function resetModalDescription(): string
    {
        return 'Tindakan ini akan menghapus seluruh Mata Kuliah pada kurikulum ini. Tindakan ini tidak dapat dibatalkan.';
    }

    protected function resetBisaDilakukan(): bool
    {
        $kurikulum = KurikulumTerpilih::current();

        return $kurikulum instanceof Kurikulum
            && app(MkResetService::class)->bisaDireset($kurikulum);
    }

    protected function resetJalankan(): void
    {
        $kurikulum = KurikulumTerpilih::current();

        if ($kurikulum instanceof Kurikulum) {
            app(MkResetService::class)->reset($kurikulum);
        }
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
        return 'mk';
    }

    protected function importModalHeading(): string
    {
        return 'Impor mata kuliah massal';
    }

    /**
     * @return array<int, Component|Field>
     */
    protected function importContextComponents(): array
    {
        return [
            BannerKurikulumDikerjakan::placeholder(
                'Seluruh baris akan diimpor sebagai mata kuliah pada kurikulum ini.',
                wajibProdi: false,
            ),
        ];
    }

    protected function importColumns(): array
    {
        return [
            ['key' => 'nama', 'label' => 'nama', 'wajib' => true],
            ['key' => 'sks_teori', 'label' => 'sks teori', 'wajib' => true],
            ['key' => 'sks_praktik', 'label' => 'sks praktik', 'wajib' => false],
            ['key' => 'sks_lapangan', 'label' => 'sks lapangan', 'wajib' => false],
            ['key' => 'jenis', 'label' => 'jenis', 'wajib' => true],
            ['key' => 'kode_bok', 'label' => 'kode bahan kajian', 'wajib' => false],
            ['key' => 'nidn_koordinator', 'label' => 'NIDN koordinator', 'wajib' => false],
        ];
    }

    protected function importInstructionsExtra(): array
    {
        return [
            'Satu baris = satu mata kuliah pada kurikulum yang sedang dikerjakan (lihat banner di atas halaman).',
            'Jenis: wajib, pilihan, atau praktikum.',
            'SKS praktik dan SKS lapangan opsional; kosongkan atau isi 0 bila tidak ada.',
            'Kode bahan kajian opsional; lebih dari satu kode dipisah titik koma (;), mis. BOK-01;BOK-02.',
            'Setiap BoK harus sudah ada dan sudah dipetakan ke CPL (matriks CPL↔BoK) sebelum impor.',
            'NIDN koordinator opsional; kosongkan bila belum ditetapkan.',
        ];
    }

    /**
     * @return list<string>
     */
    protected function importExampleRows(): array
    {
        return [
            "Kalkulus\t3\t1\t0\twajib\tBOK-01;BOK-02\t0000000030",
            "Teori Saja\t3\t\t\twajib\t\t",
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
            return ['status' => 'invalid', 'keterangan' => 'Pilih kurikulum pemilik MK terlebih dahulu lewat banner di atas halaman.'];
        }

        foreach (['sks_teori', 'sks_praktik', 'sks_lapangan'] as $key) {
            if ($data[$key] !== '' && ! ctype_digit($data[$key])) {
                return ['status' => 'invalid', 'keterangan' => 'SKS harus berupa angka.'];
            }
        }

        if (! in_array(Str::lower($data['jenis']), ['wajib', 'pilihan', 'praktikum'], true)) {
            return ['status' => 'invalid', 'keterangan' => 'Jenis harus wajib, pilihan, atau praktikum.'];
        }

        if ($data['nidn_koordinator'] !== ''
            && ! User::query()->where('nidn', $data['nidn_koordinator'])->exists()) {
            return ['status' => 'invalid', 'keterangan' => "NIDN koordinator '{$data['nidn_koordinator']}' tidak ditemukan."];
        }

        if ($data['kode_bok'] !== '') {
            $pesanInvalid = $this->validasiKodeBok($data['kode_bok'], $kurikulumId);

            if ($pesanInvalid !== null) {
                return ['status' => 'invalid', 'keterangan' => $pesanInvalid];
            }
        }

        $existing = Mk::query()
            ->where('kurikulum_id', $kurikulumId)
            ->where('nama', $data['nama'])
            ->first();

        if ($existing) {
            return [
                'status' => 'duplikat',
                'keterangan' => 'MK dengan nama ini sudah ada pada kurikulum ini.',
                'existing_id' => $existing->id,
                'dedup' => mb_strtolower($data['nama']),
            ];
        }

        return ['status' => 'baru', 'keterangan' => '', 'dedup' => mb_strtolower($data['nama'])];
    }

    protected function createImportRow(array $data, array $context): void
    {
        $unitId = $this->unitIdDariKonteks();
        $kurikulumId = $this->kurikulumIdDariKonteks();
        [$teori, $praktik, $lapangan] = $this->sksDariData($data);

        Mk::query()->create([
            'academic_unit_id' => $unitId,
            'kurikulum_id' => $kurikulumId,
            'nama' => $data['nama'],
            'sks_teori' => $teori,
            'sks_praktik' => $praktik,
            'sks_lapangan' => $lapangan,
            'sks' => $teori + $praktik + $lapangan,
            'jenis' => Str::lower($data['jenis']),
            'koordinator_mk_id' => $this->koordinatorId($data),
            'state' => 'draft',
            'is_active' => true,
        ]);

        $mk = Mk::query()
            ->where('kurikulum_id', $kurikulumId)
            ->where('nama', $data['nama'])
            ->firstOrFail();

        $this->petakanBok($mk, $data);
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function updateImportRow(string $existingId, array $data, array $context): void
    {
        [$teori, $praktik, $lapangan] = $this->sksDariData($data);
        $mk = Mk::query()->findOrFail($existingId);

        $mk->update([
            'sks_teori' => $teori,
            'sks_praktik' => $praktik,
            'sks_lapangan' => $lapangan,
            'sks' => $teori + $praktik + $lapangan,
            'jenis' => Str::lower($data['jenis']),
            'koordinator_mk_id' => $this->koordinatorId($data) ?? $mk->koordinator_mk_id,
        ]);

        $this->petakanBok($mk, $data);
    }

    /**
     * @param  array<string, string>  $data
     * @return array{0: int, 1: int, 2: int}
     */
    protected function sksDariData(array $data): array
    {
        return [
            (int) $data['sks_teori'],
            $data['sks_praktik'] !== '' ? (int) $data['sks_praktik'] : 0,
            $data['sks_lapangan'] !== '' ? (int) $data['sks_lapangan'] : 0,
        ];
    }

    /**
     * @param  array<string, string>  $data
     */
    protected function koordinatorId(array $data): ?string
    {
        if ($data['nidn_koordinator'] === '') {
            return null;
        }

        return User::query()->where('nidn', $data['nidn_koordinator'])->value('id');
    }

    /**
     * @param  array<string, string>  $data
     */
    protected function petakanBok(Mk $mk, array $data): void
    {
        $kurikulumId = $this->kurikulumIdDariKonteks();

        if (blank($kurikulumId) || $data['kode_bok'] === '') {
            return;
        }

        $cplBokIds = collect($this->kodeBokDariBaris($data['kode_bok']))
            ->flatMap(function (string $kodeBok) use ($kurikulumId): array {
                $bok = Bok::query()
                    ->where('kurikulum_id', $kurikulumId)
                    ->where('kode', $kodeBok)
                    ->first();

                if (! $bok) {
                    return [];
                }

                return CplBok::query()
                    ->where('bok_id', $bok->id)
                    ->pluck('id')
                    ->all();
            })
            ->unique()
            ->values();

        if ($cplBokIds->isEmpty()) {
            return;
        }

        $bobotPerPivot = round(100 / $cplBokIds->count(), 2);
        $sisaBobot = 100.0;

        foreach ($cplBokIds as $index => $cplBokId) {
            $bobot = $index === $cplBokIds->count() - 1
                ? round($sisaBobot, 2)
                : $bobotPerPivot;

            $sisaBobot -= $bobot;

            CplMk::query()->firstOrCreate(
                ['cpl_bok_id' => $cplBokId, 'mk_id' => $mk->id],
                ['bobot' => max($bobot, 0.01)],
            );
        }
    }

    /**
     * @return list<string>
     */
    protected function kodeBokDariBaris(string $raw): array
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

    protected function validasiKodeBok(string $raw, string $kurikulumId): ?string
    {
        $kodes = $this->kodeBokDariBaris($raw);

        if ($kodes === []) {
            return null;
        }

        foreach ($kodes as $kode) {
            $bok = Bok::query()
                ->where('kurikulum_id', $kurikulumId)
                ->where('kode', $kode)
                ->first();

            if (! $bok) {
                return "BoK '{$kode}' tidak ditemukan pada kurikulum ini.";
            }

            if (! CplBok::query()->where('bok_id', $bok->id)->exists()) {
                return "BoK '{$kode}' belum dipetakan ke CPL pada matriks CPL↔BoK.";
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
