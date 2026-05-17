<x-filament-widgets::widget>
    <x-filament::section heading="Workflow Kurikulum">
        <div class="flex flex-wrap items-start gap-y-4">
            @foreach ($this->getSteps() as $index => $step)
                <div class="flex min-w-[6rem] flex-1 flex-col items-center px-1 text-center">
                    <div @class([
                        'flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-semibold',
                        'border-gray-300 bg-gray-100 text-gray-500 dark:border-gray-600 dark:bg-gray-800' => $step['status'] === 'locked',
                        'border-primary-500 bg-primary-500 text-white shadow-sm' => $step['status'] === 'current',
                        'border-success-500 bg-success-500 text-white' => $step['status'] === 'completed',
                        'border-warning-500 bg-warning-50 text-warning-700 ring-2 ring-warning-200 dark:bg-warning-500/10 dark:text-warning-300' => $step['status'] === 'next',
                    ])>
                        @if ($step['status'] === 'completed')
                            <span aria-hidden="true">✓</span>
                        @elseif ($step['status'] === 'locked')
                            <span aria-hidden="true" title="Belum dapat diakses">🔒</span>
                        @else
                            {{ $index + 1 }}
                        @endif
                    </div>
                    <p @class([
                        'mt-2 text-xs font-medium leading-tight',
                        'text-gray-400 dark:text-gray-500' => $step['status'] === 'locked',
                        'text-primary-600 dark:text-primary-400' => $step['status'] === 'current',
                        'text-success-600 dark:text-success-400' => $step['status'] === 'completed',
                        'text-warning-600 dark:text-warning-400' => $step['status'] === 'next',
                    ])>
                        {{ $step['label'] }}
                    </p>
                </div>
                @if (! $loop->last)
                    <div @class([
                        'hidden h-0.5 w-4 shrink-0 self-center md:block md:w-6 lg:w-8',
                        'bg-success-400' => $step['status'] === 'completed',
                        'bg-gray-200 dark:bg-gray-700' => $step['status'] !== 'completed',
                    ])></div>
                @endif
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
