<div>
    @if (count($roles) > 1)
        <x-filament::dropdown placement="bottom-end" teleport>
            <x-slot name="trigger">
                <x-filament::icon-button
                    icon="heroicon-o-user-circle"
                    label="Ganti peran aktif"
                    tooltip="Peran aktif: {{ $activeRole }}"
                />
            </x-slot>

            <x-filament::dropdown.header icon="heroicon-m-user-circle">
                Peran aktif
            </x-filament::dropdown.header>

            <x-filament::dropdown.list>
                @foreach ($roles as $role)
                    <x-filament::dropdown.list.item
                        wire:click="$set('activeRole', '{{ $role }}')"
                        :icon="$role === $activeRole ? 'heroicon-m-check' : null"
                        :color="$role === $activeRole ? 'primary' : 'gray'"
                    >
                        {{ $role }}
                    </x-filament::dropdown.list.item>
                @endforeach
            </x-filament::dropdown.list>
        </x-filament::dropdown>
    @endif
</div>
