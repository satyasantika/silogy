<x-filament-panels::page>
    @php
        $kurikulum = $this->kurikulum;
    @endphp

    <div
        data-silogy="banner-header-panel"
        style="border-radius:14px;overflow:hidden;border:1px solid rgba(128,128,128,.2);background:var(--gray-50, #f9fafb);"
    >
        @livewire('silogy.kurikulum-terpilih-banner', ['catatan' => null, 'sebagaiHeaderPanel' => true])

        <div style="padding:14px 16px 16px;">
            @if (! $kurikulum)
                <p style="font-size:13px;opacity:.75;">
                    Pilih kurikulum yang akan ditinjau lewat banner di atas, lalu buka kembali halaman ini.
                </p>
            @else
                <div style="font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;opacity:.55;margin-bottom:6px;">
                    Analisis per Mahasiswa
                </div>
                <p style="margin:0 0 12px;font-size:12px;line-height:1.5;opacity:.8;">
                    Mahasiswa yang mengontrak mata kuliah pada kurikulum yang dikerjakan, beserta IPK dan capaian CPL-nya
                </p>
                {{ $this->table }}
            @endif
        </div>
    </div>

    @if ($kurikulum)
        @include('filament.shared.charts.radar-bar-chartjs')
    @endif
</x-filament-panels::page>
