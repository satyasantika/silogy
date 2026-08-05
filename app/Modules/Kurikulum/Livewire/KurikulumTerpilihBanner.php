<?php

namespace App\Modules\Kurikulum\Livewire;

use App\Modules\Kurikulum\Filament\Support\BannerKurikulumDikerjakan;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Banner kurikulum terpilih + modal "Ganti" tanpa meninggalkan halaman.
 */
class KurikulumTerpilihBanner extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /** Keterangan konteks halaman, ditampilkan di bawah garis pemisah banner. */
    public ?string $catatan = null;

    public bool $sebagaiHeaderPanel = false;

    public function gantiAction(): Action
    {
        return Action::make('ganti')
            ->label('Ganti')
            ->icon(Heroicon::ArrowsRightLeft)
            // Tombol solid dipakai (bukan link) supaya tetap terbaca di atas
            // latar gradient banner.
            ->button()
            ->size(Size::Small)
            ->color('gray')
            ->modalHeading('Ganti kurikulum')
            ->modalDescription('Pilih kurikulum yang akan dikerjakan. Halaman ini akan mengikuti pilihan tersebut.')
            ->modalSubmitActionLabel('Kerjakan')
            ->schema([
                Select::make('kurikulum_id')
                    ->label('Kurikulum')
                    ->options(fn (): array => KurikulumTerpilih::options())
                    ->default(fn (): ?string => KurikulumTerpilih::currentId())
                    ->required()
                    ->native(false)
                    ->searchable()
                    ->selectablePlaceholder(false),
            ])
            ->action(function (array $data): void {
                KurikulumTerpilih::set($data['kurikulum_id'] ?? null);

                Notification::make()
                    ->title('Kurikulum diganti')
                    ->body(KurikulumTerpilih::current()?->nama)
                    ->success()
                    ->send();

                $this->js('window.location.reload()');
            });
    }

    public function pilihAction(): Action
    {
        return Action::make('pilih')
            ->label('Pilih kurikulum')
            ->button()
            ->size(Size::Small)
            ->color('warning')
            ->modalHeading('Pilih kurikulum')
            ->modalSubmitActionLabel('Kerjakan')
            ->schema([
                Select::make('kurikulum_id')
                    ->label('Kurikulum')
                    ->options(fn (): array => KurikulumTerpilih::options())
                    ->required()
                    ->native(false)
                    ->searchable(),
            ])
            ->action(function (array $data): void {
                KurikulumTerpilih::set($data['kurikulum_id'] ?? null);

                Notification::make()
                    ->title('Kurikulum dipilih')
                    ->body(KurikulumTerpilih::current()?->nama)
                    ->success()
                    ->send();

                $this->js('window.location.reload()');
            });
    }

    public function render(): View
    {
        $kurikulum = KurikulumTerpilih::current();
        $adaOpsi = KurikulumTerpilih::options() !== [];

        return view('filament.modules.kurikulum.livewire.kurikulum-terpilih-banner', [
            'kurikulum' => $kurikulum,
            'hierarki' => BannerKurikulumDikerjakan::hierarkiUnit(),
            'catatan' => $this->catatan,
            'sebagaiHeaderPanel' => $this->sebagaiHeaderPanel,
            'peringatan' => 'Belum ada kurikulum yang dikerjakan.',
            'arahan' => $adaOpsi
                ? 'Gunakan tombol di samping untuk memilih kurikulum yang akan dikerjakan.'
                : 'Belum ada kurikulum pada unit yang Anda kelola.',
            'adaOpsi' => $adaOpsi,
        ]);
    }
}
