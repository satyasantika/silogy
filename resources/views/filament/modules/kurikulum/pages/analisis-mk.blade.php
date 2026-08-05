<x-filament-panels::page>
    @php
        $kurikulum = $this->kurikulum;
    @endphp

    <div
        data-silogy="banner-header-panel"
        style="border-radius:14px;overflow:hidden;border:1px solid rgba(128,128,128,.2);background:var(--gray-50, #f9fafb);"
    >
        @livewire('silogy.kurikulum-terpilih-banner', ['catatan' => null, 'sebagaiHeaderPanel' => true])

        <div style="padding:14px 16px 16px;" x-data="{ tabAktif: 'pemetaan' }">
            @if (! $kurikulum)
                <p style="font-size:13px;opacity:.75;">
                    Pilih atau kerjakan kurikulum level yang sesuai di daftar kurikulum terlebih dahulu, lalu buka kembali halaman ini.
                </p>
            @else
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;">
                    @foreach ([
                        'pemetaan' => 'Pemetaan Rencana Asesmen CPL',
                        'hasil' => 'Hasil Analisis Asesmen CPL',
                        'grafik' => 'Grafik CPL',
                        'mahasiswa' => 'Hasil Analisis Asesmen CPL per Mahasiswa',
                    ] as $tabKey => $tabLabel)
                        <button
                            type="button"
                            @click="tabAktif = '{{ $tabKey }}'"
                            :style="tabAktif === '{{ $tabKey }}'
                                ? 'padding:8px 16px;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;border:1px solid #2563eb;background:#2563eb;color:#fff;'
                                : 'padding:8px 16px;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;border:1px solid rgba(128,128,128,.35);background:transparent;color:inherit;'"
                        >
                            {{ $tabLabel }}
                        </button>
                    @endforeach
                </div>

                <div x-show="tabAktif === 'pemetaan'" x-cloak>
                    <div style="font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;opacity:.55;margin-bottom:6px;">
                        Pemetaan Rencana Asesmen CPL
                    </div>
                    <p style="margin:0 0 12px;font-size:12px;line-height:1.5;opacity:.8;">
                        Pemetaan kontribusi mata kuliah terhadap capaian CPL (bobot × SKS, dinormalisasi per CPL = 100%)
                    </p>
                    @include('filament.modules.kurikulum.partials.tabel-pemetaan-cpl-mk', ['pemetaan' => $this->pemetaanCplMk])
                </div>

                <div x-show="tabAktif === 'hasil'" x-cloak>
                    <div style="font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;opacity:.55;margin-bottom:6px;">
                        Hasil Analisis Asesmen CPL
                    </div>
                    <p style="margin:0 0 12px;font-size:12px;line-height:1.5;opacity:.8;">
                        Ringkasan analisis asesmen berdasarkan kurikulum terpilih
                    </p>
                    @include('filament.modules.kurikulum.partials.tabel-hasil-analisis-cpl', ['hasilAnalisis' => $hasilAnalisis])
                </div>

                <div x-show="tabAktif === 'grafik'" x-cloak>
                    <div style="font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;opacity:.55;margin-bottom:6px;">
                        Grafik CPL
                    </div>
                    <p style="margin:0 0 12px;font-size:12px;line-height:1.5;opacity:.8;">
                        Rerata nilai per mata kuliah penyumbang tiap CPL
                    </p>
                    @include('filament.modules.kurikulum.partials.grafik-radar-cpl', ['radarPerCpl' => $this->radarPerCpl])
                </div>

                <div x-show="tabAktif === 'mahasiswa'" x-cloak>
                    <div style="font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;opacity:.55;margin-bottom:6px;">
                        Hasil Analisis Asesmen CPL per Mahasiswa
                    </div>
                    <p style="margin:0 0 12px;font-size:12px;line-height:1.5;opacity:.8;">
                        Mahasiswa yang mengontrak mata kuliah pada kurikulum yang dikerjakan, beserta IPK dan capaian CPL-nya
                    </p>
                    {{ $this->table }}
                </div>
            @endif
        </div>
    </div>

    @if ($kurikulum)
        @include('filament.shared.charts.radar-bar-chartjs')
    @endif
</x-filament-panels::page>
