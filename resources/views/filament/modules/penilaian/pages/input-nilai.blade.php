<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Pilih Kelas MK
            </x-slot>

            <div class="max-w-xl">
                <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3">
                    <span class="text-sm font-medium text-gray-950 dark:text-white">
                        Kelas MK
                    </span>
                </label>

                <select
                    wire:model.live="kelasMkId"
                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                >
                    <option value="">— Pilih kelas —</option>
                    @foreach ($this->kelasMkOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </x-filament::section>

        @if ($kelasMkId)
            @if (count($columns) === 0 || count($rows) === 0)
                <x-filament::section>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Belum ada mahasiswa terdaftar atau komponen penilaian pada kelas ini.
                    </p>
                </x-filament::section>
            @else
                <x-filament::section>
                    <x-slot name="heading">
                        Matriks Nilai
                    </x-slot>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-max divide-y divide-gray-200 text-sm dark:divide-gray-700">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-800">
                                    <th class="sticky left-0 z-10 bg-gray-50 px-3 py-2 text-left font-semibold dark:bg-gray-800">
                                        Mahasiswa
                                    </th>
                                    @foreach ($columns as $column)
                                        <th class="whitespace-nowrap px-3 py-2 text-center font-semibold">
                                            {{ $column['label'] }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach ($rows as $row)
                                    <tr wire:key="row-{{ $row['id'] }}">
                                        <td class="sticky left-0 z-10 bg-white px-3 py-2 dark:bg-gray-900">
                                            <div class="font-medium">{{ $row['nama'] }}</div>
                                            <div class="text-xs text-gray-500">{{ $row['nim'] }}</div>
                                        </td>
                                        @foreach ($columns as $column)
                                            <td class="px-2 py-2 text-center" wire:key="cell-{{ $row['id'] }}-{{ $column['id'] }}">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="100"
                                                    step="0.01"
                                                    wire:model="nilai.{{ $row['id'] }}.{{ $column['id'] }}"
                                                    class="w-20 rounded-md border-gray-300 text-center text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                                                />
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <x-filament::button wire:click="save" color="primary">
                            Simpan
                        </x-filament::button>

                        @if ($showKalkulasiBadge)
                            <x-filament::badge color="info">
                                Kalkulasi CPL dijalankan…
                            </x-filament::badge>
                        @endif
                    </div>
                </x-filament::section>
            @endif
        @endif
    </div>
</x-filament-panels::page>
