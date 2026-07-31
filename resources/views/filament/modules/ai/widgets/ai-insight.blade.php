@php
    /** @var \App\Modules\AI\Filament\Widgets\AiInsightWidget $this */
    $analisis = $this->getLatestAnalisis();
    $unitId = $this->pageFilters['academic_unit_id'] ?? null;
    $semesterId = $this->pageFilters['semester_id'] ?? null;
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        heading="Insight AI — Capaian CPL"
        description="Ringkasan analisis Gemini untuk unit dan semester yang sedang difilter"
    >
        @if ($unitId === null || $semesterId === null)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Pilih unit akademik dan semester pada filter dashboard untuk melihat insight AI.
            </p>
        @elseif ($analisis !== null)
            <div class="mb-4 flex flex-wrap items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <x-filament::badge :color="$this->statusColor($analisis)">
                    {{ $this->statusLabel($analisis) }}
                </x-filament::badge>
                <span>{{ $analisis->academicUnit?->nama_lengkap }} · {{ $analisis->semester?->nama }}</span>
                <span class="text-gray-400">· {{ $analisis->created_at?->diffForHumans() }}</span>
                @if ($analisis->model_ai)
                    <span class="text-gray-400">· {{ $analisis->model_ai }}</span>
                @endif
            </div>

            <div class="prose prose-sm max-w-none dark:prose-invert">
                {!! str($analisis->hasil)->markdown()->sanitizeHtml() !!}
            </div>
        @else
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Belum ada analisis AI untuk kombinasi unit dan semester ini.
                    Minta ringkasan CPL otomatis dari Gemini.
                </p>
                <x-filament::button
                    wire:click="mintaAnalisisSekarang"
                    icon="heroicon-o-sparkles"
                >
                    Minta Analisis Sekarang
                </x-filament::button>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
