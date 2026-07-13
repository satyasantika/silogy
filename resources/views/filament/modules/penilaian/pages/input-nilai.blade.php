<x-filament-panels::page>
    <x-filament::section icon="heroicon-o-academic-cap" heading="Pilih Kelas MK">
        <div style="display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:12px;">
            <div style="font-size:13px;line-height:1.7;">
                <div>
                    <span style="opacity:.7;">Mata kuliah terpilih (dari Penilaian):</span>
                    <strong>{{ $this->mkTerpilih?->nama ?? '—' }}</strong>
                </div>
                <div>
                    <span style="opacity:.7;">Semester:</span>
                    <strong>{{ $this->semesterTerpilih }}</strong>
                </div>
            </div>

            <a
                href="{{ \App\Modules\Penilaian\Filament\Resources\PenilaianDosenResource::getUrl('index') }}"
                style="font-size:12px;font-weight:600;text-decoration:underline;white-space:nowrap;"
            >
                ← Pilih mata kuliah lain
            </a>
        </div>

        @if (! $this->mkTerpilih)
            <p style="font-size:13px;opacity:.75;">
                Anda belum memiliki kelas yang diampu pada semester ini.
            </p>
        @elseif (empty($this->kelasCards))
            <p style="font-size:13px;opacity:.75;">
                Anda tidak memiliki kelas untuk mata kuliah ini pada semester terpilih.
            </p>
        @else
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                @foreach ($this->kelasCards as $kelas)
                    @php
                        $aktif = $kelasMkId === $kelas['id'];
                        $sudahDinilai = $kelas['sudah_dinilai'];
                        $border = $aktif ? '#2563eb' : ($sudahDinilai ? '#86efac' : '#fcd34d');
                        $background = $aktif ? '#dbeafe' : ($sudahDinilai ? '#dcfce7' : '#fef3c7');
                        $color = $sudahDinilai ? '#166534' : '#92400e';
                    @endphp
                    <button
                        type="button"
                        wire:click="pilihKelas('{{ $kelas['id'] }}')"
                        wire:key="kelas-card-{{ $kelas['id'] }}"
                        style="cursor:pointer;text-align:left;min-width:150px;padding:10px 14px;border-radius:10px;
                            border:2px solid {{ $border }};background:{{ $background }};color:{{ $color }};"
                    >
                        <span style="display:block;font-weight:700;font-size:13px;">Kelas {{ $kelas['kode_kelas'] }}</span>
                        <span style="display:block;font-size:12px;margin-top:2px;">
                            {{ $kelas['jumlah_mahasiswa'] }} mhs
                            @if ($sudahDinilai)
                                · rata-rata {{ $kelas['rata_rata'] }}
                            @else
                                · Belum dinilai
                            @endif
                        </span>
                    </button>
                @endforeach
            </div>

            <div style="margin-top:12px;padding:10px 12px;border-radius:8px;background:rgba(128,128,128,.08);font-size:12px;">
                <strong>Seluruh kelas pada MK ini:</strong>
                {{ $this->ringkasanSeluruhKelas['jumlah_mahasiswa'] }} mahasiswa
                @if ($this->ringkasanSeluruhKelas['sudah_dinilai'])
                    · rata-rata {{ $this->ringkasanSeluruhKelas['rata_rata'] }}
                @else
                    · Belum dinilai
                @endif
            </div>
        @endif
    </x-filament::section>

    @if ($kelasMkId)
        @if ($penugasanBelumSelesai)
            <div style="margin-top:16px;">
            <x-filament::section
                icon="heroicon-o-clock"
                heading="Penugasan belum selesai"
            >
                Koordinator MK belum menyelesaikan penugasan kelas ini
                (komponen penilaian harus berbobot total 100% dan seluruhnya
                terpetakan ke Sub-CPMK). Penilaian akan terbuka setelahnya.
            </x-filament::section>
            </div>
        @elseif (count($columns) === 0 || count($rows) === 0)
            <div style="margin-top:16px;">
            <x-filament::section
                icon="heroicon-o-information-circle"
                heading="Data belum lengkap"
            >
                Belum ada mahasiswa terdaftar atau komponen penilaian pada kelas ini.
            </x-filament::section>
            </div>
        @else
            <div style="margin-top:16px;" x-data="{ tabUtama: 'penilaian' }">
                <div style="display:flex;gap:6px;margin-bottom:12px;">
                    <button
                        type="button"
                        @click="tabUtama = 'penilaian'"
                        :style="tabUtama === 'penilaian'
                            ? 'padding:8px 18px;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;border:1px solid #2563eb;background:#2563eb;color:#fff;'
                            : 'padding:8px 18px;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;border:1px solid rgba(128,128,128,.35);background:transparent;color:inherit;'"
                    >
                        Penilaian
                    </button>
                    <button
                        type="button"
                        @click="tabUtama = 'laporan'"
                        :style="tabUtama === 'laporan'
                            ? 'padding:8px 18px;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;border:1px solid #2563eb;background:#2563eb;color:#fff;'
                            : 'padding:8px 18px;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;border:1px solid rgba(128,128,128,.35);background:transparent;color:inherit;'"
                    >
                        Laporan
                    </button>
                </div>

                <div x-show="tabUtama === 'penilaian'">
                    <x-filament::section
                        icon="heroicon-o-table-cells"
                        heading="Penilaian"
                        description="Kolom mengikuti asesmen (komponen penilaian) yang diset koordinator MK untuk mata kuliah ini. Isi nilai 0–100 lalu klik Simpan."
                    >
                        <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px;margin-bottom:12px;">
                            <x-filament::actions
                                :actions="$this->getMatriksActionsKiri()"
                                alignment="start"
                            />

                            <x-filament::actions
                                :actions="$this->getMatriksActionsKanan()"
                                alignment="end"
                            />
                        </div>

                        <div style="overflow-x:auto;" wire:key="matriks-input-nilai-{{ $kelasMkId }}">
                            <table class="input-nilai-matrix" style="width:100%;min-width:max-content;border-collapse:collapse;font-size:13px;">
                                <thead>
                                    <tr style="text-align:left;">
                                        <th class="input-nilai-sticky input-nilai-sticky-head" style="position:sticky;left:0;top:0;z-index:3;padding:8px 10px;min-width:190px;">
                                            Mahasiswa
                                        </th>
                                        @foreach ($this->columnsTampil as $column)
                                            <th
                                                class="input-nilai-sticky-topcol"
                                                style="position:sticky;top:0;z-index:2;padding:8px 6px;text-align:center;white-space:nowrap;min-width:110px;vertical-align:top;"
                                                title="{{ $column['label'] }}"
                                            >
                                                <span style="display:inline-block;padding:1px 8px;border-radius:999px;font-size:10px;font-weight:700;background:#e0e7ff;color:#3730a3;margin-bottom:4px;">
                                                    {{ rtrim(rtrim(number_format($column['bobot'], 2, '.', ''), '0'), '.') }}%
                                                </span>
                                                <span style="display:block;font-weight:700;font-size:12px;">{{ $column['asesmen'] }}</span>
                                                <span style="display:flex;gap:3px;justify-content:center;flex-wrap:wrap;margin-top:3px;">
                                                    @if ($column['evaluasi_kode'])
                                                        <span style="display:inline-block;padding:1px 6px;border-radius:6px;font-size:9px;font-weight:600;background:rgba(128,128,128,.15);opacity:.85;">
                                                            {{ $column['evaluasi_kode'] }}
                                                        </span>
                                                    @endif
                                                    @if ($column['cpl'])
                                                        <span style="display:inline-block;padding:1px 6px;border-radius:6px;font-size:9px;font-weight:600;background:#fde68a;color:#92400e;">
                                                            {{ $column['cpl'] }}
                                                        </span>
                                                    @endif
                                                </span>
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        <tr
                                            wire:key="row-{{ $row['id'] }}"
                                            style="border-bottom:1px solid rgba(128,128,128,.2);"
                                        >
                                            <td class="input-nilai-sticky input-nilai-sticky-cell" style="position:sticky;left:0;z-index:1;padding:8px 10px;">
                                                <span style="display:block;font-size:11px;opacity:.7;">{{ $row['nim'] }}</span>
                                                <strong style="display:block;font-size:12px;text-transform:uppercase;">{{ $row['nama'] }}</strong>
                                                <span style="display:flex;align-items:center;gap:5px;margin-top:3px;">
                                                    <span style="font-size:11px;opacity:.75;">
                                                        Nilai: {{ $row['nilai_angka'] !== null ? rtrim(rtrim(number_format($row['nilai_angka'], 2, '.', ''), '0'), '.') : '—' }}
                                                    </span>
                                                    @if ($row['nilai_huruf'])
                                                        @php
                                                            $warnaHuruf = $this->warnaNilaiHuruf($row['nilai_huruf']);
                                                        @endphp
                                                        <span style="display:inline-block;padding:1px 7px;border-radius:6px;font-size:10px;font-weight:700;background:{{ $warnaHuruf['bg'] }};color:{{ $warnaHuruf['fg'] }};">
                                                            {{ $row['nilai_huruf'] }}
                                                        </span>
                                                    @endif
                                                </span>
                                            </td>
                                            @foreach ($this->columnsTampil as $column)
                                                <td
                                                    style="padding:6px;text-align:center;"
                                                    wire:key="cell-{{ $row['id'] }}-{{ $column['id'] }}"
                                                >
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        max="100"
                                                        step="0.01"
                                                        wire:model="nilai.{{ $row['id'] }}.{{ $column['id'] }}"
                                                        style="width:74px;padding:4px 6px;border:1.5px solid rgba(128,128,128,.5);border-radius:6px;background:transparent;text-align:center;"
                                                        placeholder="—"
                                                    />
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr style="border-top:2px solid rgba(128,128,128,.35);background:rgba(37,99,235,.08);font-weight:700;">
                                        <td class="input-nilai-sticky input-nilai-sticky-foot" style="position:sticky;left:0;z-index:1;padding:8px 10px;">
                                            Rata-rata Kelas
                                        </td>
                                        @foreach ($this->columnsTampil as $column)
                                            @php
                                                $rataRata = $this->rataRataKelas[$column['id']] ?? null;
                                            @endphp
                                            <td style="padding:6px;text-align:center;">
                                                {{ $rataRata !== null ? rtrim(rtrim(number_format($rataRata, 2, '.', ''), '0'), '.') : '—' }}
                                            </td>
                                        @endforeach
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div style="margin-top:16px;display:flex;flex-wrap:wrap;align-items:center;gap:12px;">
                            <x-filament::button
                                wire:click="save"
                                wire:loading.attr="disabled"
                                wire:target="save"
                                color="primary"
                                icon="heroicon-o-check"
                            >
                                <span wire:loading.remove wire:target="save">Simpan</span>
                                <span wire:loading wire:target="save">Menyimpan…</span>
                            </x-filament::button>

                            @if ($showKalkulasiBadge)
                                <x-filament::badge color="info">
                                    Kalkulasi CPL dijalankan…
                                </x-filament::badge>
                            @endif
                        </div>
                    </x-filament::section>
                </div>

                <div x-show="tabUtama === 'laporan'" x-data="{ subTab: 'portofolio' }">
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
                                :style="subTab === '{{ $subTabKey }}'
                                    ? 'padding:6px 14px;border-radius:999px;font-weight:600;font-size:12px;cursor:pointer;border:1px solid #2563eb;background:#dbeafe;color:#1d4ed8;'
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
                                                            <span style="display:inline-block;padding:1px 8px;border-radius:999px;font-size:11px;font-weight:700;background:{{ $warnaHurufBaris['bg'] }};color:{{ $warnaHurufBaris['fg'] }};">
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
                                    <div>
                                        <canvas
                                            wire:key="radar-cpl-{{ $kelasMkId }}"
                                            x-data
                                            x-init="renderRadarSilogy($el, @js($this->radarData['cpl'] ?? ['labels' => [], 'data' => []]), '#2563eb')"
                                        ></canvas>
                                    </div>
                                </div>
                                <div style="border:1px solid rgba(128,128,128,.2);border-radius:10px;padding:14px;">
                                    <div style="font-weight:600;font-size:13px;margin-bottom:10px;">E2. Jaring Laba-laba Ketercapaian CPMK</div>
                                    <div>
                                        <canvas
                                            wire:key="radar-cpmk-{{ $kelasMkId }}"
                                            x-data
                                            x-init="renderRadarSilogy($el, @js($this->radarData['cpmk'] ?? ['labels' => [], 'data' => []]), '#059669')"
                                        ></canvas>
                                    </div>
                                </div>
                                <div style="border:1px solid rgba(128,128,128,.2);border-radius:10px;padding:14px;">
                                    <div style="font-weight:600;font-size:13px;margin-bottom:10px;">E3. Jaring Laba-laba Ketercapaian Sub-CPMK</div>
                                    <div>
                                        <canvas
                                            wire:key="radar-subcpmk-{{ $kelasMkId }}"
                                            x-data
                                            x-init="renderRadarSilogy($el, @js($this->radarData['subcpmk'] ?? ['labels' => [], 'data' => []]), '#d97706')"
                                        ></canvas>
                                    </div>
                                </div>
                                <div style="border:1px solid rgba(128,128,128,.2);border-radius:10px;padding:14px;">
                                    <div style="font-weight:600;font-size:13px;margin-bottom:10px;">E4. Jaring Laba-laba Rata-rata Penugasan</div>
                                    <div>
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
            </div>

            @once
                <style>
                    .input-nilai-sticky {
                        border-right: 1px solid rgba(128, 128, 128, 0.25);
                        box-shadow: 4px 0 8px -4px rgba(0, 0, 0, 0.12);
                    }

                    .input-nilai-sticky-head {
                        background: #f4f4f5;
                        border-bottom: 2px solid rgba(128, 128, 128, 0.35);
                    }

                    .input-nilai-sticky-cell {
                        background: #ffffff;
                    }

                    .input-nilai-sticky-foot {
                        background: #dbeafe;
                    }

                    .input-nilai-sticky-topcol {
                        background: #f4f4f5;
                        border-bottom: 2px solid rgba(128, 128, 128, 0.35);
                    }

                    .dark .input-nilai-sticky-head {
                        background: #27272a;
                    }

                    .dark .input-nilai-sticky-cell {
                        background: #18181b;
                    }

                    .dark .input-nilai-sticky-foot {
                        background: #1e3a5f;
                    }

                    .dark .input-nilai-sticky-topcol {
                        background: #27272a;
                    }

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
                        background: #1e3a5f;
                    }

                    .dark .portofolio-sticky-topcol {
                        background: #27272a;
                    }
                </style>
            @endonce

            @once
                {{-- Chart.js bawaan Filament dibundel privat di dalam paketnya (tidak
                    ter-expose sebagai window.Chart), jadi 4 grafik jaring laba-laba
                    pada tab Laporan memuat Chart.js sendiri lewat CDN. --}}
                <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
                <script>
                    // Kanvas grafik bisa saja masih tersembunyi (display:none) saat
                    // dibuat — mis. di dalam sub-tab Laporan yang belum aktif, atau di
                    // dalam modal Capaian yang baru dimorph ke DOM sebelum benar-benar
                    // terbuka. Chart.js membaca ukuran kontainer saat itu juga, jadi
                    // kalau masih 0x0 grafiknya tampak kosong walau datanya benar —
                    // paksa resize begitu frame berikutnya (setelah tab/modal terlihat)
                    // dan begitu modal manapun terbuka, untuk berjaga-jaga.
                    function paksaResizeSetelahTerlihat(chart) {
                        requestAnimationFrame(() => chart.resize());
                        window.addEventListener('open-modal', () => chart.resize(), { once: true });
                    }

                    window.renderRadarSilogy = function (canvas, dataset, color) {
                        if (! canvas || typeof Chart === 'undefined') {
                            return;
                        }

                        if (canvas._radarChartInstance) {
                            canvas._radarChartInstance.destroy();
                        }

                        canvas._radarChartInstance = new Chart(canvas, {
                            type: 'radar',
                            data: {
                                labels: dataset.labels,
                                datasets: [{
                                    label: 'Nilai',
                                    data: dataset.data,
                                    backgroundColor: color + '33',
                                    borderColor: color,
                                    pointBackgroundColor: color,
                                }],
                            },
                            options: {
                                // Tinggi kanvas dihitung dari LEBAR kontainer dibagi
                                // aspectRatio (bukan tinggi tetap di CSS) — lebih
                                // tahan banting terhadap kanvas yang sempat dibuat
                                // saat kontainernya masih tersembunyi (di dalam tab
                                // atau modal yang belum aktif), karena hanya
                                // butuh lebar yang benar untuk menghitung ulang
                                // tinggi yang proporsional lewat resize().
                                aspectRatio: 1.3,
                                scales: {
                                    r: {
                                        min: 0,
                                        max: 100,
                                    },
                                },
                                plugins: {
                                    legend: {
                                        display: false,
                                    },
                                },
                            },
                        });

                        paksaResizeSetelahTerlihat(canvas._radarChartInstance);
                    };

                    window.renderBarSilogy = function (canvas, dataset, color) {
                        if (! canvas || typeof Chart === 'undefined') {
                            return;
                        }

                        if (canvas._barChartInstance) {
                            canvas._barChartInstance.destroy();
                        }

                        canvas._barChartInstance = new Chart(canvas, {
                            type: 'bar',
                            data: {
                                labels: dataset.labels,
                                datasets: [{
                                    label: 'Nilai',
                                    data: dataset.data,
                                    backgroundColor: color + '55',
                                    borderColor: color,
                                    borderWidth: 1,
                                }],
                            },
                            options: {
                                aspectRatio: 2.2,
                                scales: {
                                    y: {
                                        min: 0,
                                        max: 100,
                                    },
                                },
                                plugins: {
                                    legend: {
                                        display: false,
                                    },
                                },
                            },
                        });

                        paksaResizeSetelahTerlihat(canvas._barChartInstance);
                    };
                </script>
            @endonce
        @endif
    @endif
</x-filament-panels::page>
