<x-filament-panels::page>
    @include('filament.modules.kurikulum.partials.kurikulum-terpilih-banner', [
        'catatan' => 'Seluruh pemetaan, hasil asesmen, dan grafik CPL di bawah dihitung dari kurikulum prodi ini.',
    ])

    @php
        $kurikulum = $this->kurikulum;
    @endphp

    @if (! $kurikulum)
        <div style="margin-top:16px;">
            <x-filament::section icon="heroicon-o-information-circle" heading="Belum ada kurikulum terpilih">
                Pilih kurikulum di daftar kurikulum terlebih dahulu, lalu buka kembali halaman ini.
            </x-filament::section>
        </div>
    @elseif (! ($kurikulum->academicUnit?->isProdi() ?? false))
        <div style="margin-top:16px;">
            <x-filament::section icon="heroicon-o-information-circle" heading="Kurikulum bukan program studi">
                Analisis MK Prodi hanya tersedia untuk kurikulum tingkat program studi.
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
                    description="Ringkasan analisis asesmen berdasarkan kurikulum terpilih"
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
                    description="Mahasiswa yang mengontrak mata kuliah pada kurikulum yang dikerjakan, beserta IPK dan capaian CPL-nya"
                >
                    {{ $this->table }}
                </x-filament::section>
            </div>
        </div>

        @include('filament.shared.charts.radar-bar-chartjs')
    @endif
</x-filament-panels::page>
