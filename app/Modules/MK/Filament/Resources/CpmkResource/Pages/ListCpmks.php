<?php

namespace App\Modules\MK\Filament\Resources\CpmkResource\Pages;

use App\Models\User;
use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\MK\Filament\Resources\CpmkResource;
use App\Modules\MK\Filament\Support\Concerns\HasMkPipelineNav;
use App\Modules\MK\Filament\Support\Concerns\HasPakaiUlangAntarSemester;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\CpmkSemester;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Services\CpmkCplPemetaanService;
use App\Modules\MK\Services\CpmkPakaiUlangSemesterService;
use App\Modules\MK\Services\CpmkResetService;
use App\Modules\MK\Services\PerubahanCpmkService;
use App\Modules\MK\Support\GerbangPerubahanCpmk;
use App\Modules\MK\Support\MkTerpilih;
use App\Support\Filament\Concerns\HasImporMassal;
use App\Support\Filament\Concerns\HasResetTrigger;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Auth;

class ListCpmks extends ListRecords
{
    use HasImporMassal;
    use HasMkPipelineNav;
    use HasPakaiUlangAntarSemester;
    use HasResetTrigger;

    protected static string $resource = CpmkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makePakaiUlangAction(),
            $this->makeAjukanPerubahanAction(),
            $this->makeImporMassalAction()
                ->visible(fn (): bool => CpmkResource::canCreate()),
            $this->makeResetTriggerAction(),
        ];
    }

    /**
     * Muncul justru ketika CPMK TIDAK boleh diubah: semester ini sudah punya
     * CPMK berjalan dan belum ada persetujuan. Inilah jalan keluarnya —
     * koordinator mengajukan alasan, Tim Kurikulum unit yang memutuskan.
     */
    protected function makeAjukanPerubahanAction(): Action
    {
        return Action::make('ajukanPerubahanCpmk')
            ->label('Ajukan perubahan CPMK')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('warning')
            ->modalHeading('Ajukan perubahan CPMK ke Tim Kurikulum')
            ->modalDescription(
                'CPMK terpetakan ke CPL, jadi perubahannya perlu disetujui Tim Kurikulum unit pemilik '
                .'mata kuliah ini. Memakai ulang CPMK semester lalu tidak perlu lewat sini.',
            )
            ->modalSubmitActionLabel('Kirim usulan')
            ->schema([
                Textarea::make('alasan')
                    ->label('Alasan perubahan')
                    ->required()
                    ->rows(4)
                    ->helperText('Jelaskan apa yang perlu diubah dan mengapa — ini yang dibaca Tim Kurikulum.'),
            ])
            ->visible(fn (): bool => $this->bolehMengajukanPerubahan())
            ->action(function (array $data): void {
                $mk = MkTerpilih::current();
                $semesterId = $this->semesterKonteks();
                $user = Auth::user();

                if (! $mk instanceof Mk || $semesterId === null || ! $user instanceof User) {
                    return;
                }

                app(PerubahanCpmkService::class)->ajukan($mk, $semesterId, $user, (string) $data['alasan']);

                Notification::make()
                    ->title('Usulan terkirim')
                    ->body('Tim Kurikulum akan meninjau usulan perubahan CPMK ini.')
                    ->success()
                    ->send();
            });
    }

    private function bolehMengajukanPerubahan(): bool
    {
        $mk = MkTerpilih::current();
        $semesterId = $this->semesterKonteks();
        $user = Auth::user();

        if (! $mk instanceof Mk || $semesterId === null || ! $user instanceof User) {
            return false;
        }

        return $user->can('kelola_cpmk')
            && GerbangPerubahanCpmk::butuhPersetujuan($mk, $semesterId, $user)
            && GerbangPerubahanCpmk::usulanTerbuka($mk, $semesterId) === null;
    }

    protected function getTableEmptyStateActions(): array
    {
        return [
            $this->makePakaiUlangAction(),
            $this->makeImporMassalAction()
                ->visible(fn (): bool => CpmkResource::canCreate()),
        ];
    }

    protected function pakaiUlangEntitasLabel(): string
    {
        return 'CPMK';
    }

    protected function pakaiUlangMkId(): ?string
    {
        return MkTerpilih::currentId();
    }

    protected function pakaiUlangTargetSemesterId(): ?string
    {
        return $this->semesterKonteks();
    }

    protected function pakaiUlangResolveBaris(string $sumberSemesterId, string $mkId, string $targetSemesterId): array
    {
        return app(CpmkPakaiUlangSemesterService::class)->resolveBaris($sumberSemesterId, $mkId, $targetSemesterId);
    }

    protected function pakaiUlangJalankan(array $rows, string $sumberSemesterId, string $mkId, string $targetSemesterId): array
    {
        return app(CpmkPakaiUlangSemesterService::class)->jalankan($rows, $mkId, $targetSemesterId);
    }

    protected function pakaiUlangSemesterIdsDenganData(string $mkId): array
    {
        return app(CpmkPakaiUlangSemesterService::class)->semesterIdsDenganData($mkId);
    }

    protected function semesterKonteks(): ?string
    {
        $mkId = MkTerpilih::currentId();

        if (blank($mkId)) {
            return null;
        }

        $semesterId = SemesterTerpilih::currentId($mkId) ?? SemesterTerpilih::defaultId();

        return filled($semesterId) ? (string) $semesterId : null;
    }

    protected function resetEntitasLabel(): string
    {
        return 'CPMK';
    }

    protected function resetModalDescription(): string
    {
        return 'Tindakan ini akan mengosongkan CPMK mata kuliah ini pada semester yang sedang dipilih. '
            .'CPMK yang juga dipakai semester lain tetap ada di semester tersebut. '
            .'Tindakan ini tidak dapat dibatalkan.';
    }

    protected function resetBisaDilakukan(): bool
    {
        $mk = MkTerpilih::current();
        $semesterId = $this->semesterKonteks();

        return $mk instanceof Mk
            && $semesterId !== null
            && app(CpmkResetService::class)->bisaDireset($mk, $semesterId);
    }

    protected function resetJalankan(): void
    {
        $mk = MkTerpilih::current();
        $semesterId = $this->semesterKonteks();

        if ($mk instanceof Mk && $semesterId !== null) {
            app(CpmkResetService::class)->reset($mk, $semesterId);
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
                ...$this->mkPipelineNavComponents(),
            ]);
    }

    protected function mkPipelineStepKey(): string
    {
        return 'cpmk';
    }

    protected function importModalHeading(): string
    {
        return 'Impor CPMK massal';
    }

    /**
     * @return list<string>
     */
    protected function importContextKeys(): array
    {
        return ['import_mk_id'];
    }

    /**
     * @return array<int, Component|Field>
     */
    protected function importContextComponents(): array
    {
        $mkTerpilih = MkTerpilih::currentId();
        $mkOptions = CpmkResource::scopedKoordinatorMkOptions();

        if (filled($mkTerpilih) && array_key_exists($mkTerpilih, $mkOptions)) {
            return [
                Select::make('import_mk_id')
                    ->label('Mata kuliah')
                    ->options([$mkTerpilih => $mkOptions[$mkTerpilih]])
                    ->default($mkTerpilih)
                    ->disabled()
                    ->dehydrated(),
            ];
        }

        return [
            Select::make('import_mk_id')
                ->label('Mata kuliah')
                ->options($mkOptions)
                ->searchable()
                ->required()
                ->default(count($mkOptions) === 1 ? array_key_first($mkOptions) : null),
        ];
    }

    protected function importColumns(): array
    {
        return [
            ['key' => 'kode', 'label' => 'kode', 'wajib' => true],
            ['key' => 'deskripsi', 'label' => 'deskripsi', 'wajib' => true],
            ['key' => 'kode_cpl_terpetakan', 'label' => 'kode CPL terpetakan', 'wajib' => false],
        ];
    }

    protected function importHelperNote(): string
    {
        return 'Seluruh baris diimpor sebagai CPMK dari mata kuliah yang dipilih di atas. '
            .'Kolom kode CPL terpetakan (opsional) langsung memetakan CPMK ke CPL bila CPL–MK sudah ada.';
    }

    /**
     * @return list<string>
     */
    protected function importExampleRows(): array
    {
        return [
            "CPMK-01\tMahasiswa memahami konsep dasar\tCPL-01",
            "CPMK-02\tMahasiswa mampu menganalisis masalah\t",
        ];
    }

    protected function resolveImportRow(array $data, array $context): array
    {
        if (blank($context['import_mk_id'] ?? null)) {
            return ['status' => 'invalid', 'keterangan' => 'Pilih mata kuliah terlebih dahulu.'];
        }

        if (filled($data['kode_cpl_terpetakan'] ?? null)) {
            $validasi = CpmkCplPemetaanService::validasiKodeCplUntukMk(
                $data['kode_cpl_terpetakan'],
                (string) $context['import_mk_id'],
            );

            if (! $validasi['valid']) {
                return ['status' => 'invalid', 'keterangan' => $validasi['keterangan']];
            }
        }

        $existing = Cpmk::query()
            ->where('mk_id', $context['import_mk_id'])
            ->where('kode', $data['kode'])
            ->when(
                filled($this->semesterKonteks()),
                fn ($query) => $query->untukSemester((string) $this->semesterKonteks()),
            )
            ->first();

        if ($existing) {
            return [
                'status' => 'duplikat',
                'keterangan' => 'Kode CPMK sudah ada pada MK ini.',
                'existing_id' => $existing->id,
                'dedup' => mb_strtolower($data['kode']),
            ];
        }

        return ['status' => 'baru', 'keterangan' => '', 'dedup' => mb_strtolower($data['kode'])];
    }

    protected function createImportRow(array $data, array $context): void
    {
        // Kode CPMK unik per MK (uq_cpmk_mk_kode): bila kodenya sudah ada
        // untuk semester lain, yang ditambahkan cukup lampiran semesternya —
        // bukan baris baru berisi kode yang sama.
        $cpmk = Cpmk::query()->firstOrCreate(
            [
                'mk_id' => $context['import_mk_id'],
                'kode' => $data['kode'],
            ],
            ['deskripsi' => $data['deskripsi']],
        );

        $semesterId = $this->semesterKonteks();

        if ($semesterId !== null) {
            CpmkSemester::query()->firstOrCreate([
                'cpmk_id' => $cpmk->id,
                'semester_id' => $semesterId,
            ]);
        }

        if (filled($data['kode_cpl_terpetakan'] ?? null)) {
            CpmkCplPemetaanService::petakanCpmkKeCpl($cpmk, $data['kode_cpl_terpetakan']);
        }
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function updateImportRow(string $existingId, array $data, array $context): void
    {
        $cpmk = Cpmk::query()->findOrFail($existingId);

        $cpmk->update([
            'deskripsi' => $data['deskripsi'],
        ]);

        if (filled($data['kode_cpl_terpetakan'] ?? null)) {
            CpmkCplPemetaanService::petakanCpmkKeCpl($cpmk, $data['kode_cpl_terpetakan']);
        }
    }
}
