<?php

namespace App\Modules\MK\Filament\Resources\PerubahanCpmkResource\Pages;

use App\Modules\MK\Filament\Resources\PerubahanCpmkResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ViewPerubahanCpmk extends ViewRecord
{
    protected static string $resource = PerubahanCpmkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ListPerubahanCpmks::setujuiAction(),
            ListPerubahanCpmks::tolakAction(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Usulan')
                    ->schema([
                        TextEntry::make('mk.nama')->label('Mata Kuliah'),
                        TextEntry::make('semester.nama')->label('Semester'),
                        TextEntry::make('academicUnit.nama')->label('Unit akademik'),
                        TextEntry::make('diajukanOleh.name')->label('Diajukan oleh'),
                        TextEntry::make('created_at')->label('Diajukan')->dateTime('d M Y H:i'),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('alasan')->label('Alasan perubahan')->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('CPMK yang berlaku saat usulan diajukan')
                    ->description('Potret ini diambil saat pengajuan, agar terlihat apa yang hendak diganti.')
                    ->schema([
                        TextEntry::make('ringkasan_usulan')
                            ->hiddenLabel()
                            ->html()
                            ->formatStateUsing(function (mixed $state): HtmlString {
                                $baris = collect(is_array($state) ? $state : [])
                                    ->map(fn (array $item): string => sprintf(
                                        '<li><strong>%s</strong> — %s</li>',
                                        e((string) ($item['kode'] ?? '—')),
                                        e((string) ($item['deskripsi'] ?? '—')),
                                    ))
                                    ->join('');

                                return new HtmlString(
                                    $baris === ''
                                        ? '<p class="text-sm opacity-70">Belum ada CPMK pada semester itu.</p>'
                                        : '<ul class="list-disc space-y-1 ps-5 text-sm">'.$baris.'</ul>',
                                );
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Keputusan')
                    ->schema([
                        TextEntry::make('ditinjauOleh.name')->label('Ditinjau oleh')->placeholder('—'),
                        TextEntry::make('ditinjau_pada')->label('Ditinjau pada')->dateTime('d M Y H:i')->placeholder('—'),
                        TextEntry::make('catatan_peninjau')->label('Catatan')->placeholder('—')->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(fn ($record): bool => ! $record->menunggu()),
            ]);
    }
}
