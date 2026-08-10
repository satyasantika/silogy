<?php

namespace App\Modules\Kurikulum\Filament\Pages;

use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Services\KurikulumResetService;
use App\Support\Filament\Concerns\ForcesFullPageRender;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Daftar seluruh kurikulum untuk Super Admin — lihat + reset saja, TANPA
 * edit/create/kerjakan. Sengaja BUKAN halaman dari KurikulumResource
 * (yang policy-nya memang menolak Super Admin secara struktural, lihat
 * DelegasiMenu) — halaman ini gerbang otorisasinya sendiri, ruang lingkupnya
 * sempit dan berdiri sendiri.
 */
class DaftarKurikulumSuperAdmin extends Page implements HasActions, HasTable
{
    use ForcesFullPageRender;
    use InteractsWithActions;
    use InteractsWithTable;

    protected string $view = 'filament.modules.kurikulum.pages.daftar-kurikulum-super-admin';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    // Menu Super Admin tampil datar tanpa header grup, sama seperti
    // AcademicUnitResource/SemesterResource.
    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Kurikulum';

    protected static ?string $title = 'Kurikulum';

    protected static ?string $slug = 'kurikulums-super-admin';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Kurikulum::query()->with('academicUnit')->withCount([
                'profilLulusan', 'cpls', 'boks', 'mks', 'mkUnits',
            ]))
            ->columns([
                TextColumn::make('kode')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tahun')
                    ->label('Tahun')
                    ->sortable(),

                TextColumn::make('academicUnit.nama_lengkap')
                    ->label('Unit akademik')
                    ->searchable(),

                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Aktif' : 'Nonaktif')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),

                TextColumn::make('mks_count')->label('MK')->alignCenter(),
                TextColumn::make('cpls_count')->label('CPL')->alignCenter(),
                TextColumn::make('boks_count')->label('BoK')->alignCenter(),
                TextColumn::make('mk_units_count')->label('Penawaran')->alignCenter(),
                TextColumn::make('profil_lulusan_count')->label('Profil Lulusan')->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('academic_unit_id')
                    ->label('Unit akademik')
                    ->options(fn (): array => AcademicUnit::query()->orderBy('nama')->pluck('nama', 'id')->all()),

                TernaryFilter::make('is_active')
                    ->label('Status aktif'),
            ])
            ->recordActions([
                $this->resetKurikulumAction(),
            ]);
    }

    protected function resetKurikulumAction(): Action
    {
        return Action::make('resetKurikulum')
            ->label('Reset data')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (Kurikulum $record): string => 'Reset kurikulum '.$record->nama.'?')
            ->modalDescription(function (Kurikulum $record): string {
                $record->loadCount(['profilLulusan', 'cpls', 'boks', 'mks', 'mkUnits']);

                return sprintf(
                    'Tindakan ini akan menghapus %d profil lulusan, %d CPL, %d BoK, %d mata kuliah, dan %d penawaran MK '
                    .'beserta seluruh data turunannya (pemetaan CPL-BoK, CPL-MK, CPMK, Sub-CPMK, komponen asesmen, '
                    .'kelas, dan nilai mahasiswa) pada kurikulum ini. Baris kurikulum itu sendiri TIDAK dihapus — '
                    .'kurikulum kembali seperti baru dibuat. Tindakan ini tidak dapat dibatalkan.',
                    $record->profil_lulusan_count,
                    $record->cpls_count,
                    $record->boks_count,
                    $record->mks_count,
                    $record->mk_units_count,
                );
            })
            ->modalSubmitActionLabel('Ya, reset kurikulum ini')
            ->action(function (Kurikulum $record): void {
                abort_unless(auth()->user()?->hasRole('Super Admin'), 403);

                app(KurikulumResetService::class)->reset($record);

                Notification::make()
                    ->title('Kurikulum direset')
                    ->body($record->nama.' telah dikosongkan kembali ke kondisi baru dibuat.')
                    ->success()
                    ->send();

                $this->resetTable();
            });
    }
}
