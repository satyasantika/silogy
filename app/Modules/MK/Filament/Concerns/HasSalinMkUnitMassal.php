<?php

namespace App\Modules\MK\Filament\Concerns;

use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Services\MkUnitSalinExportService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

trait HasSalinMkUnitMassal
{
    protected function makeSalinMkUnitAction(): Action
    {
        return Action::make('salinMkUnit')
            ->label('Salin mata kuliah')
            ->icon(Heroicon::OutlinedClipboard)
            ->color('gray')
            ->modalHeading('Salin daftar mata kuliah')
            ->modalDescription('Salin data ke Excel, isi kode dan semester, lalu gunakan tombol update massal.')
            ->modalSubmitActionLabel('Salin ke clipboard')
            ->modalCancelActionLabel('Tutup')
            ->schema([
                Placeholder::make('salin_petunjuk')
                    ->hiddenLabel()
                    ->content(fn (): HtmlString => new HtmlString(
                        '<p class="text-sm text-gray-600 dark:text-gray-300">'
                        .'Kolom: <strong>mata kuliah</strong>, <strong>kode</strong>, <strong>semester ke-</strong> '
                        .'(pemisah tab, siap tempel ke Excel). Data mengikuti filter kurikulum prodi yang aktif.</p>'
                    )),
                Textarea::make('export_text')
                    ->label('Data penawaran MK')
                    ->default(fn (): string => $this->teksSalinMkUnit())
                    ->readOnly()
                    ->rows(12)
                    ->extraAttributes(['class' => 'font-mono text-xs']),
            ])
            ->action(function (): void {
                $teks = $this->teksSalinMkUnit();

                if ($teks === '') {
                    Notification::make()
                        ->title('Tidak ada data')
                        ->body('Pilih kurikulum prodi yang memiliki penawaran MK terlebih dahulu.')
                        ->warning()
                        ->send();

                    return;
                }

                $this->js('navigator.clipboard.writeText('.json_encode($teks).')');

                Notification::make()
                    ->title('Disalin ke clipboard')
                    ->body('Tempel ke Excel, sesuaikan kode dan semester, lalu impor lewat update massal.')
                    ->success()
                    ->send();
            });
    }

    protected function teksSalinMkUnit(): string
    {
        $kurikulum = KurikulumTerpilih::current();

        if (! $kurikulum?->academicUnit?->isProdi()) {
            return '';
        }

        return app(MkUnitSalinExportService::class)->exportTsv($kurikulum->academic_unit_id);
    }
}
