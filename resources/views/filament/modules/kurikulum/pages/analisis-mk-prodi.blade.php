<x-filament-panels::page>
    <x-filament::section icon="heroicon-o-building-office-2" heading="Pilih Program Studi">
        @php
            $prodiOptions = $this->prodiOptions;
        @endphp

        @if (empty($prodiOptions))
            <p style="font-size:13px;opacity:.75;">
                Anda belum ditugaskan sebagai Pimpinan atau Tim Kurikulum pada program studi manapun.
            </p>
        @else
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                @foreach ($prodiOptions as $id => $nama)
                    <button
                        type="button"
                        wire:click="pilihProdi('{{ $id }}')"
                        wire:key="prodi-card-{{ $id }}"
                        style="cursor:pointer;text-align:left;min-width:180px;padding:10px 14px;border-radius:10px;
                            border:2px solid {{ $prodiId === $id ? '#2563eb' : 'rgba(128,128,128,.35)' }};
                            background:{{ $prodiId === $id ? '#dbeafe' : 'transparent' }};"
                    >
                        <span style="display:block;font-weight:600;font-size:13px;">{{ $nama }}</span>
                    </button>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    @if ($prodiId)
        @php
            $kurikulum = $this->kurikulum;
        @endphp

        @if (! $kurikulum)
            <div style="margin-top:16px;">
                <x-filament::section icon="heroicon-o-information-circle" heading="Belum ada kurikulum aktif">
                    Program studi ini belum memiliki kurikulum berstatus aktif.
                </x-filament::section>
            </div>
        @else
            <div style="margin-top:16px;" x-data="{ tabAktif: 'pemetaan' }">
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;">
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

                <div x-show="tabAktif === 'pemetaan'">
                    <x-filament::section
                        icon="heroicon-o-squares-plus"
                        heading="Pemetaan Rencana Asesmen CPL"
                        description="Pemetaan kontribusi mata kuliah terhadap capaian CPL"
                    >
                        @include('filament.modules.kurikulum.partials.tabel-pemetaan-cpl-mk', ['pemetaan' => $this->pemetaanCplMk])
                    </x-filament::section>
                </div>

                <div x-show="tabAktif === 'hasil'">
                    <x-filament::section
                        icon="heroicon-o-clipboard-document-check"
                        heading="Hasil Analisis Asesmen CPL"
                        description="Ringkasan analisis asesmen berdasarkan kurikulum aktif"
                    >
                        @include('filament.modules.kurikulum.partials.tabel-hasil-analisis-cpl', ['hasilAnalisis' => $hasilAnalisis])
                    </x-filament::section>
                </div>

                <div x-show="tabAktif === 'grafik'">
                    <x-filament::section
                        icon="heroicon-o-presentation-chart-line"
                        heading="Grafik CPL"
                        description="Rerata nilai per mata kuliah penyumbang tiap CPL"
                    >
                        @include('filament.modules.kurikulum.partials.grafik-radar-cpl', ['radarPerCpl' => $this->radarPerCpl])
                    </x-filament::section>
                </div>

                <div x-show="tabAktif === 'mahasiswa'">
                    <x-filament::section
                        icon="heroicon-o-chart-bar"
                        heading="Hasil Analisis Asesmen CPL per Mahasiswa"
                        description="Informasi pencapaian CPL setiap mahasiswa"
                    >
                        {{ $this->table }}
                    </x-filament::section>
                </div>
            </div>

            @include('filament.shared.charts.radar-bar-chartjs')
        @endif
    @endif
</x-filament-panels::page>
