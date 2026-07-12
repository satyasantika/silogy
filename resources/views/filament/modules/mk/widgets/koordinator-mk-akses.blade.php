<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-user-group" heading="Koordinator Mata Kuliah">
        <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;">
            <div style="flex:1;min-width:260px;">
                @if ($jumlahMk > 0)
                    <div style="font-weight:600;font-size:15px;">
                        {{ $jumlahMk }} mata kuliah · {{ $jumlahKurikulum }} kurikulum
                    </div>
                    <p class="text-sm" style="margin-top:6px;opacity:.75;">
                        Anda koordinator MK pada unit:
                        {{ $unitNama->isNotEmpty() ? $unitNama->join(', ', ', dan ') : '—' }}.
                    </p>
                @else
                    <div style="font-size:14px;opacity:.75;">
                        Anda belum menjadi koordinator pada mata kuliah apa pun.
                    </div>
                @endif
            </div>

            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <x-filament::button tag="a" href="{{ $mataKuliahKoordinatorUrl }}" icon="heroicon-m-arrow-right" color="gray">
                    Mata Kuliah Koordinator
                </x-filament::button>

                <x-filament::button tag="a" href="{{ $kelasMkUrl }}" icon="heroicon-m-arrow-right">
                    Kelola Kelas MK
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
