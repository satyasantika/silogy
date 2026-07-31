<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-book-open" heading="Mata Kuliah Sedang Dikerjakan">
        @if (empty($options))
            <div style="font-size:14px;opacity:.75;">
                Anda belum ditugaskan sebagai dosen pengampu pada kelas mana pun.
            </div>
        @else
            <div style="display:flex;flex-direction:column;gap:6px;max-width:480px;">
                <label for="mkId" style="font-size:12px;text-transform:uppercase;letter-spacing:.04em;opacity:.65;">
                    Pilih mata kuliah yang sedang dikerjakan
                </label>

                <x-filament::input.wrapper>
                    <x-filament::input.select id="mkId" wire:model.live="mkId">
                        <option value="">— Belum dipilih —</option>
                        @foreach ($options as $id => $nama)
                            <option value="{{ $id }}">{{ $nama }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
