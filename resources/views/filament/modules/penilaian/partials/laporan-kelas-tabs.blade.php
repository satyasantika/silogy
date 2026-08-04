<div x-data="{ subTab: 'portofolio' }">
    @php
        $subTabs = [
            'portofolio' => 'Portofolio',
            'cpl-v1' => 'Evaluasi CPL v1',
            'cpl-v2' => 'Evaluasi CPL v2',
            'mahasiswa' => 'Hasil Analisis per Mahasiswa',
            'laporan-lengkap' => 'Laporan',
        ];
    @endphp

    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;">
        @foreach ($subTabs as $subTabKey => $subTabLabel)
            <button
                type="button"
                @click="subTab = '{{ $subTabKey }}'"
                :class="subTab === '{{ $subTabKey }}' ? 'silogy-tone-info' : ''"
                :style="subTab === '{{ $subTabKey }}'
                    ? 'padding:6px 14px;border-radius:999px;font-weight:600;font-size:12px;cursor:pointer;border:1px solid #2563eb;'
                    : 'padding:6px 14px;border-radius:999px;font-weight:600;font-size:12px;cursor:pointer;border:1px solid rgba(128,128,128,.3);background:transparent;color:inherit;'"
            >
                {{ $subTabLabel }}
            </button>
        @endforeach
    </div>

    <div x-show="subTab === 'portofolio'">
        <x-filament::section
            icon="heroicon-o-document-chart-bar"
            heading="Portofolio"
            description="Rekap nilai akhir dan nilai komponen evaluasi per jenis penilaian untuk kelas terpilih. Mahasiswa diurutkan berdasarkan NIM. Halaman ini hanya untuk dibaca."
        >
            @include('filament.modules.penilaian.partials.tabel-workcloud', [
                'kolomEvaluasi' => $kolomEvaluasi,
                'rows' => $portofolioRows,
                'nilaiEvaluasi' => $this->nilaiEvaluasi,
                'rataRataEvaluasi' => $this->rataRataEvaluasi,
                'wireKeySuffix' => 'portofolio',
            ])
        </x-filament::section>
    </div>

    <div x-show="subTab === 'cpl-v1'">
        <x-filament::section
            icon="heroicon-o-academic-cap"
            heading="Evaluasi Ketercapaian CPL"
            description="Evaluasi capaian CPL berdasarkan data nilai kelas terpilih."
        >
            <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
                <div style="padding:14px 20px;border-radius:10px;border:1px solid rgba(37,99,235,.3);background:rgba(37,99,235,.08);min-width:220px;">
                    <span style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;opacity:.7;">Target Kelulusan CPL</span>
                    <strong style="display:block;font-size:28px;font-weight:700;color:#2563eb;margin-top:4px;">{{ $targetCapaianLulusan }}%</strong>
                    <span style="display:block;font-size:11px;opacity:.65;margin-top:4px;">Persentase minimum ketercapaian rata-rata kelas</span>
                </div>
            </div>

            <div style="border:1px solid rgba(128,128,128,.2);border-radius:10px;padding:14px;margin-bottom:16px;">
                <div style="font-weight:600;font-size:13px;margin-bottom:10px;">
                    Evaluasi Ketercapaian CPL
                </div>
                @include('filament.modules.penilaian.partials.tabel-ketercapaian-cpl', ['ketercapaian' => $ketercapaianCpl])
            </div>

            <div style="border:1px solid rgba(128,128,128,.2);border-radius:10px;padding:14px;">
                <div style="font-weight:600;font-size:13px;margin-bottom:10px;">
                    Distribusi Nilai &amp; Kesimpulan
                </div>
                @include('filament.modules.penilaian.partials.tabel-distribusi-nilai', ['distribusi' => $distribusiNilaiHuruf])
            </div>
        </x-filament::section>
    </div>

    <div x-show="subTab === 'cpl-v2'">
        <x-filament::section
            icon="heroicon-o-chart-bar"
            heading="Rekapitulasi Ketercapaian Sumbangan CPL"
            description="Rekap kontribusi CPL pada mata kuliah untuk kelas terpilih."
        >
            @include('filament.modules.penilaian.partials.tabel-detail-cpl-cpmk-subcpmk', [
                'detail' => $detailCplCpmkSubcpmk,
                'target' => $targetCapaianLulusan,
                'rataRataKeseluruhan' => $this->rataRataKeseluruhanCpl,
            ])
        </x-filament::section>
    </div>

    <div x-show="subTab === 'mahasiswa'">
        <x-filament::section
            icon="heroicon-o-user-group"
            heading="Hasil Analisis MK Dosen per Mahasiswa"
            description="Ringkasan capaian mahasiswa pada mata kuliah terpilih."
        >
            @if (empty($rows))
                <p style="font-size:13px;opacity:.7;">Belum ada mahasiswa terdaftar pada kelas ini.</p>
            @else
                <div style="overflow-x:auto;">
                    <table style="width:100%;min-width:520px;border-collapse:collapse;font-size:13px;">
                        <thead>
                            <tr style="background:rgba(128,128,128,.08);text-align:center;">
                                <th style="padding:8px;text-align:left;">NPM</th>
                                <th style="padding:8px;text-align:left;">Nama</th>
                                <th style="padding:8px;">Nilai Angka</th>
                                <th style="padding:8px;">Nilai Huruf</th>
                                <th style="padding:8px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr style="border-top:1px solid rgba(128,128,128,.15);">
                                    <td style="padding:8px;"><strong>{{ $row['nim'] }}</strong></td>
                                    <td style="padding:8px;text-transform:uppercase;">{{ $row['nama'] }}</td>
                                    <td style="padding:8px;text-align:center;">
                                        {{ $row['nilai_angka'] !== null ? rtrim(rtrim(number_format($row['nilai_angka'], 2, '.', ''), '0'), '.') : '—' }}
                                    </td>
                                    <td style="padding:8px;text-align:center;">
                                        @if ($row['nilai_huruf'])
                                            @php($warnaHurufBaris = $this->warnaNilaiHuruf($row['nilai_huruf']))
                                            <span class="silogy-badge {{ $warnaHurufBaris['class'] }}" style="padding:1px 8px;">
                                                {{ $row['nilai_huruf'] }}
                                            </span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td style="padding:8px;text-align:center;">
                                        <x-filament::actions
                                            :actions="[($this->capaianMahasiswaAction())(['kmmId' => $row['id']])]"
                                            alignment="center"
                                        />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>

    <div x-show="subTab === 'laporan-lengkap'">
        <x-filament::section
            icon="heroicon-o-clipboard-document-check"
            heading="Laporan Mata Kuliah ke Prodi"
            description="Portofolio penilaian dan evaluasi untuk kelas terpilih."
        >
            <div style="display:flex;justify-content:flex-end;margin-bottom:12px;">
                <x-filament::button size="sm" color="gray" icon="heroicon-o-printer" onclick="window.print()">
                    Cetak
                </x-filament::button>
            </div>

            <div style="border:1px solid rgba(128,128,128,.2);border-radius:10px;padding:14px;margin-bottom:16px;">
                <div style="font-weight:600;font-size:13px;margin-bottom:10px;">A. Identitas Mata Kuliah</div>
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <tbody>
                        <tr>
                            <th style="text-align:left;padding:6px 8px;width:200px;white-space:nowrap;">Mata Kuliah</th>
                            <td style="padding:6px 8px;">{{ $this->identitasMk['nama'] }}</td>
                        </tr>
                        <tr>
                            <th style="text-align:left;padding:6px 8px;">Kode MK / SKS</th>
                            <td style="padding:6px 8px;">{{ $this->identitasMk['kode'] }} / {{ $this->identitasMk['sks'] }} SKS</td>
                        </tr>
                        <tr>
                            <th style="text-align:left;padding:6px 8px;">Semester</th>
                            <td style="padding:6px 8px;">{{ $this->identitasMk['semester'] }}</td>
                        </tr>
                        <tr>
                            <th style="text-align:left;padding:6px 8px;">Dosen Pengampu</th>
                            <td style="padding:6px 8px;">{{ $this->identitasMk['dosen'] }}</td>
                        </tr>
                        <tr>
                            <th style="text-align:left;padding:6px 8px;">Target Kelulusan CPL</th>
                            <td style="padding:6px 8px;">{{ $this->identitasMk['target'] }}%</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            @include('filament.modules.penilaian.partials.rencana-evaluasi-table', ['rencana' => $rencanaEvaluasi])

            <div style="margin-top:16px;font-weight:600;font-size:13px;margin-bottom:10px;">B. Tabel Nilai Mahasiswa (Workcloud Utama)</div>
            @include('filament.modules.penilaian.partials.tabel-workcloud', [
                'kolomEvaluasi' => $kolomEvaluasi,
                'rows' => $portofolioRows,
                'nilaiEvaluasi' => $this->nilaiEvaluasi,
                'rataRataEvaluasi' => $this->rataRataEvaluasi,
                'wireKeySuffix' => 'laporan',
            ])

            <div style="margin-top:16px;font-weight:600;font-size:13px;margin-bottom:10px;">C1. Evaluasi Ketercapaian CPL</div>
            @include('filament.modules.penilaian.partials.tabel-ketercapaian-cpl', ['ketercapaian' => $ketercapaianCpl])

            <div style="margin-top:16px;font-weight:600;font-size:13px;margin-bottom:10px;">C2. Detail Ketercapaian CPL-CPMK-SubCPMK</div>
            @include('filament.modules.penilaian.partials.tabel-detail-cpl-cpmk-subcpmk', [
                'detail' => $detailCplCpmkSubcpmk,
                'target' => $targetCapaianLulusan,
                'rataRataKeseluruhan' => $this->rataRataKeseluruhanCpl,
            ])

            <div style="margin-top:16px;font-weight:600;font-size:13px;margin-bottom:10px;">D. Distribusi Nilai</div>
            @include('filament.modules.penilaian.partials.tabel-distribusi-nilai', ['distribusi' => $distribusiNilaiHuruf])

            <div style="margin-top:24px;display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;">
                <div style="border:1px solid rgba(128,128,128,.2);border-radius:10px;padding:14px;">
                    <div style="font-weight:600;font-size:13px;margin-bottom:10px;">E1. Jaring Laba-laba Ketercapaian CPL</div>
                    <div style="aspect-ratio:1/1;width:100%;position:relative;">
                        <canvas
                            wire:key="radar-cpl-{{ $kelasMkId }}"
                            x-data
                            x-init="renderRadarSilogy($el, @js($this->radarData['cpl'] ?? ['labels' => [], 'data' => []]), '#2563eb')"
                        ></canvas>
                    </div>
                </div>
                <div style="border:1px solid rgba(128,128,128,.2);border-radius:10px;padding:14px;">
                    <div style="font-weight:600;font-size:13px;margin-bottom:10px;">E2. Jaring Laba-laba Ketercapaian CPMK</div>
                    <div style="aspect-ratio:1/1;width:100%;position:relative;">
                        <canvas
                            wire:key="radar-cpmk-{{ $kelasMkId }}"
                            x-data
                            x-init="renderRadarSilogy($el, @js($this->radarData['cpmk'] ?? ['labels' => [], 'data' => []]), '#059669')"
                        ></canvas>
                    </div>
                </div>
                <div style="border:1px solid rgba(128,128,128,.2);border-radius:10px;padding:14px;">
                    <div style="font-weight:600;font-size:13px;margin-bottom:10px;">E3. Jaring Laba-laba Ketercapaian Sub-CPMK</div>
                    <div style="aspect-ratio:1/1;width:100%;position:relative;">
                        <canvas
                            wire:key="radar-subcpmk-{{ $kelasMkId }}"
                            x-data
                            x-init="renderRadarSilogy($el, @js($this->radarData['subcpmk'] ?? ['labels' => [], 'data' => []]), '#d97706')"
                        ></canvas>
                    </div>
                </div>
                <div style="border:1px solid rgba(128,128,128,.2);border-radius:10px;padding:14px;">
                    <div style="font-weight:600;font-size:13px;margin-bottom:10px;">E4. Jaring Laba-laba Rata-rata Penugasan</div>
                    <div style="aspect-ratio:1/1;width:100%;position:relative;">
                        <canvas
                            wire:key="radar-asesmen-{{ $kelasMkId }}"
                            x-data
                            x-init="renderRadarSilogy($el, @js($this->radarData['asesmen'] ?? ['labels' => [], 'data' => []]), '#7c3aed')"
                        ></canvas>
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>
</div>

@once
    <style>
        .portofolio-sticky {
            border-right: 1px solid rgba(128, 128, 128, 0.25);
            box-shadow: 4px 0 8px -4px rgba(0, 0, 0, 0.12);
        }

        .portofolio-sticky-head {
            background: #f4f4f5;
            border-bottom: 2px solid rgba(128, 128, 128, 0.35);
        }

        .portofolio-sticky-cell {
            background: #ffffff;
        }

        .portofolio-sticky-foot {
            background: #dbeafe;
        }

        .portofolio-sticky-topcol {
            background: #f4f4f5;
            border-bottom: 2px solid rgba(128, 128, 128, 0.35);
        }

        .dark .portofolio-sticky-head {
            background: #27272a;
        }

        .dark .portofolio-sticky-cell {
            background: #18181b;
        }

        .dark .portofolio-sticky-foot {
            background: #0b3914;
        }

        .dark .portofolio-sticky-topcol {
            background: #27272a;
        }
    </style>
@endonce

@include('filament.shared.charts.radar-bar-chartjs')
