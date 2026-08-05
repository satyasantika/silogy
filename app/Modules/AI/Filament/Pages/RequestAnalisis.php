<?php

namespace App\Modules\AI\Filament\Pages;

use App\Models\User;
use App\Modules\AI\Exceptions\GeminiQuotaExceededException;
use App\Modules\AI\Jobs\RunAnalisisAiJob;
use App\Modules\AI\Models\AnalisisAi;
use App\Modules\AI\Policies\AnalisisAiPolicy;
use App\Modules\AI\Services\GeminiCostGuard;
use App\Modules\AI\Support\AnalisisAiUnitOptions;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Support\Filament\DelegasiMenu;
use App\Support\Filament\NavigationGroupPeran;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class RequestAnalisis extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'Minta Analisis';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroupPeran::resolve('AI Analisis');
    }

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'ai/request';

    protected static ?string $title = 'Minta Analisis AI';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && app(AnalisisAiPolicy::class)->create($user);
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Pimpinan fokus ke laporan analisis CPL di sidebar.
        if (DelegasiMenu::peranAktifPimpinan()) {
            return false;
        }

        return static::canAccess();
    }

    public function mount(): void
    {
        $unitOptions = AnalisisAiUnitOptions::forRequestForm();
        $defaultUnit = count($unitOptions) === 1 ? array_key_first($unitOptions) : null;

        $this->form->fill([
            'academic_unit_id' => $defaultUnit,
            'semester_id' => Semester::query()->where('status_aktif', true)->value('id'),
            'jenis' => 'ringkasan_cpl',
        ]);
    }

    public function refreshPreview(): void
    {
        // Trigger re-render sidebar (wire:poll).
    }

    /**
     * @return Collection<int, AnalisisAi>
     */
    public function getRecentAnalisis(): Collection
    {
        $userId = auth()->id();

        if ($userId === null) {
            return collect();
        }

        return AnalisisAi::query()
            ->with(['academicUnit', 'semester'])
            ->where('dibuat_oleh', $userId)
            ->latest()
            ->limit(5)
            ->get();
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        $unit = AcademicUnit::query()->findOrFail($data['academic_unit_id']);

        try {
            app(GeminiCostGuard::class)->check($user, $unit);
        } catch (GeminiQuotaExceededException $e) {
            Notification::make()
                ->title('Kuota analisis AI habis')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $analisis = AnalisisAi::query()->create([
            'academic_unit_id' => $unit->id,
            'semester_id' => $data['semester_id'],
            'jenis' => $data['jenis'],
            'status' => 'queued',
            'prompt' => '-',
            'dibuat_oleh' => $user->id,
        ]);

        RunAnalisisAiJob::dispatch($analisis->id);

        Notification::make()
            ->title('Analisis dijadwalkan. Cek kembali dalam 30 detik.')
            ->success()
            ->send();

        $this->refreshPreview();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('academic_unit_id')
                    ->label('Unit akademik')
                    ->options(fn (): array => AnalisisAiUnitOptions::forRequestForm())
                    ->searchable()
                    ->required(),

                Select::make('semester_id')
                    ->label('Semester')
                    ->options(fn (): array => Semester::query()
                        ->orderByDesc('tahun_mulai')
                        ->pluck('nama', 'id')
                        ->all())
                    ->required(),

                Select::make('jenis')
                    ->label('Jenis analisis')
                    ->options([
                        'ringkasan_cpl' => 'Ringkasan CPL',
                        'rekomendasi_kurikulum' => 'Rekomendasi kurikulum',
                        'tren_capaian' => 'Tren capaian',
                    ])
                    ->required(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.modules.ai.pages.request-analisis-poll'),
                Grid::make(3)
                    ->schema([
                        $this->getFormContentComponent()->columnSpan(2),
                        Section::make('Preview terbaru')
                            ->description('Diperbarui otomatis setiap 15 detik')
                            ->schema([
                                View::make('filament.modules.ai.components.recent-preview'),
                            ])
                            ->columnSpan(1),
                    ]),
            ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('requestAnalisisForm')
            ->livewireSubmitHandler('submit')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment(Alignment::Start)
                    ->key('request-analisis-actions'),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('submit')
                ->label('Minta analisis')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->submit('submit'),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return static::$title ?? 'Minta Analisis AI';
    }
}
