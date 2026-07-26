<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            Pilih peran dan unit Anda
        </x-slot>

        <p style="font-size:13px;opacity:.75;margin-bottom:16px;">
            Akun Anda memiliki lebih dari satu peran. Pilih peran yang ingin
            digunakan sekarang — menu dan hak akses akan mengikuti peran ini.
            Anda bisa menggantinya kapan saja lewat ikon di bagian bawah menu
            samping.
        </p>

        <form wire:submit="submit">
            {{ $this->form }}

            <div style="margin-top:20px;display:flex;gap:8px;">
                <x-filament::button type="submit">
                    Lanjutkan
                </x-filament::button>

                @if ($this->isImpersonating())
                    <x-filament::button color="warning" wire:click="leaveImpersonate" type="button">
                        Tinggalkan impersonate
                    </x-filament::button>
                @endif
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
