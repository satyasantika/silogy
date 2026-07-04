<div>
    @if (count($roles) > 1)
        <div class="px-2 pb-3">
            <label class="fi-fo-field-label" style="display:block;font-size:11px;font-weight:600;opacity:.75;margin-bottom:4px;">
                <x-filament::icon icon="heroicon-m-user-circle" style="display:inline-block;width:14px;height:14px;vertical-align:-2px;" />
                Peran aktif
            </label>

            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="activeRole">
                    <option value="">Semua peran</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role }}">{{ $role }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
    @endif
</div>
