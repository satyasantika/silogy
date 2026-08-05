<x-filament-panels::page>
    @php
        $kurikulum = $this->kurikulum;
    @endphp

    <div
        data-silogy="banner-header-panel"
        style="border-radius:14px;overflow:hidden;border:1px solid rgba(128,128,128,.2);background:var(--gray-50, #f9fafb);"
    >
        @livewire('silogy.kurikulum-terpilih-banner', ['catatan' => null, 'sebagaiHeaderPanel' => true])

        @if ($kurikulum)
            {!! $this->htmlKpiProgressPenilaian('both')->toHtml() !!}
        @endif

        <div style="padding:0;">
            @if (! $kurikulum)
                <p style="padding:14px 16px 16px;font-size:13px;opacity:.75;">
                    Pilih kurikulum yang akan ditinjau lewat banner di atas, lalu buka kembali halaman ini.
                </p>
            @else
                @include('filament.modules.kurikulum.partials.tabel-hasil-analisis-cpl', [
                    'hasilAnalisis' => $hasilAnalisis,
                    'sempitKolomCpl' => true,
                    'rapatKeBanner' => true,
                ])
            @endif
        </div>
    </div>
</x-filament-panels::page>
