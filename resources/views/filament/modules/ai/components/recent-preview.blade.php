@php
    /** @var \App\Modules\AI\Filament\Pages\RequestAnalisis $this */
    $items = $this->getRecentAnalisis();
@endphp

<div class="space-y-3">
    @forelse ($items as $item)
        <div
            @class([
                'rounded-lg border p-3 text-sm',
                'border-gray-200 dark:border-gray-700',
            ])
            wire:key="analisis-preview-{{ $item->id }}"
        >
            <div class="mb-2 flex items-center justify-between gap-2">
                <span class="font-medium">{{ $item->academicUnit?->nama_lengkap }}</span>
                <x-filament::badge :color="\App\Modules\AI\Support\AnalisisAiStatus::colorFor($item)">
                    {{ \App\Modules\AI\Support\AnalisisAiStatus::labelFor($item) }}
                </x-filament::badge>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ $item->semester?->kode }} · {{ \App\Modules\AI\Filament\Resources\AnalisisAiResource::jenisOptions()[$item->jenis] ?? $item->jenis }}
                · {{ $item->created_at?->diffForHumans() }}
            </p>
            @if (filled($item->hasil))
                <div class="prose prose-sm mt-3 max-w-none dark:prose-invert">
                    {!! str($item->hasil)->markdown()->sanitizeHtml() !!}
                </div>
            @else
                <p class="mt-2 text-xs italic text-gray-500">Menunggu hasil dari Gemini…</p>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Belum ada permintaan analisis. Isi formulir lalu kirim permintaan pertama Anda.
        </p>
    @endforelse
</div>
