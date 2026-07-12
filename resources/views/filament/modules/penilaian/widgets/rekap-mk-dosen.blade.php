<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-clipboard-document-check" heading="Mata Kuliah Diampu per Semester">
        @if ($rows->isEmpty())
            <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;">
                <div style="font-size:14px;opacity:.75;">
                    Anda belum ditugaskan sebagai dosen pengampu pada kelas mana pun.
                </div>

                <x-filament::button tag="a" href="{{ $penilaianUrl }}" icon="heroicon-m-arrow-right">
                    Buka Penilaian
                </x-filament::button>
            </div>
        @else
            <div style="display:flex;flex-direction:column;gap:8px;">
                @foreach ($rows as $row)
                    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;padding:10px 12px;border:1px solid rgba(128,128,128,.2);border-radius:8px;">
                        <div style="flex:1;min-width:200px;">
                            <div style="font-weight:600;font-size:14px;">{{ $row['semester']->nama }}</div>
                            <p class="text-sm" style="margin-top:2px;opacity:.75;">
                                {{ $row['jumlah_mk'] }} mata kuliah diampu
                            </p>
                        </div>

                        <x-filament::button tag="a" href="{{ $row['url'] }}" icon="heroicon-m-arrow-right" color="gray" size="sm">
                            Lihat Penilaian
                        </x-filament::button>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
