<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-book-open" heading="Kurikulum yang dikerjakan">
        @if ($adminProdiMode)
            <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;">
                <div style="flex:1;min-width:220px;">
                    @if ($kurikulum)
                        <div style="font-weight:600;font-size:15px;">{{ $kurikulum->nama }}</div>
                    @else
                        <div style="font-size:14px;opacity:.75;">Belum ada kurikulum untuk program studi Anda.</div>
                    @endif
                    <p class="text-sm" style="margin-top:6px;opacity:.75;">
                        Halaman Mata Kuliah, Penawaran MK, dan Kelas MK mengikuti kurikulum ini.
                    </p>
                </div>

                <x-filament::button tag="a" href="{{ $kelolaKelasUrl }}" icon="heroicon-m-arrow-right">
                    Kelola Kelas MK
                </x-filament::button>
            </div>
        @else
            <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:end;">
                <div style="flex:1;min-width:260px;">
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="kurikulumId">
                            @foreach ($options as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    <p class="text-sm" style="margin-top:6px;opacity:.75;">
                        Halaman Profil, CPL, BoK, Mata Kuliah, Penawaran MK, Kelas MK, Interaksi, dan Analisis MK Prodi mengikuti kurikulum aktif ini.
                    </p>
                </div>

                <x-filament::button tag="a" href="{{ $kelolaUrl }}" icon="heroicon-m-arrow-right">
                    Kelola kurikulum
                </x-filament::button>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
