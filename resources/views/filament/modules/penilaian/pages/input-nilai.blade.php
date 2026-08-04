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
            <div
                style="margin-top:16px;"
                x-data="{
                    tabUtama: (new URLSearchParams(window.location.search).get('tab') === 'laporan')
                        ? 'laporan'
                        : 'penilaian'
                }"
            >
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
                                                <span class="silogy-badge silogy-tone-indigo" style="font-size:10px;margin-bottom:4px;">
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
                                                        <span class="silogy-badge silogy-tone-warning" style="padding:1px 6px;border-radius:6px;font-size:9px;">
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
                                                        <span class="silogy-badge {{ $warnaHuruf['class'] }}" style="padding:1px 7px;border-radius:6px;font-size:10px;">
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
                                                        wire:model.blur="nilai.{{ $row['id'] }}.{{ $column['id'] }}"
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

                        @if ($this->adaPerubahanNilai())
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
                        @elseif ($showKalkulasiBadge)
                            <div style="margin-top:16px;">
                                <x-filament::badge color="info">
                                    Kalkulasi CPL dijalankan…
                                </x-filament::badge>
                            </div>
                        @endif
                    </x-filament::section>
                </div>

                <div x-show="tabUtama === 'laporan'">
                    @include('filament.modules.penilaian.partials.laporan-kelas-tabs')
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
                        background: #0b3914;
                    }

                    .dark .input-nilai-sticky-topcol {
                        background: #27272a;
                    }
                </style>
            @endonce
        @endif
    @endif
</x-filament-panels::page>
