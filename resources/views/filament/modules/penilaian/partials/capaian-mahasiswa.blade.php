@php
    $mahasiswa ??= null;
    $penilaianTersedia ??= false;
    $radarCpmk ??= ['labels' => [], 'data' => []];
    $radarSubcpmk ??= ['labels' => [], 'data' => []];
    $radarPenugasan ??= ['labels' => [], 'data' => []];
    $kmmId ??= null;
@endphp

@if ($mahasiswa === null)
    <p style="font-size:13px;opacity:.7;">Data mahasiswa tidak ditemukan.</p>
@else
    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:16px;padding:12px 14px;border-radius:10px;background:rgba(128,128,128,.06);">
        <div>
            <span style="display:block;font-size:11px;opacity:.7;">{{ $mahasiswa['nim'] }}</span>
            <strong style="display:block;font-size:14px;text-transform:uppercase;">{{ $mahasiswa['nama'] }}</strong>
        </div>
        <div style="margin-left:auto;text-align:right;">
            <span style="display:block;font-size:11px;opacity:.7;">
                Nilai: {{ $mahasiswa['nilai_angka'] !== null ? rtrim(rtrim(number_format($mahasiswa['nilai_angka'], 2, '.', ''), '0'), '.') : '—' }}
            </span>
            @if ($mahasiswa['nilai_huruf'])
                <span style="display:inline-block;margin-top:2px;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:700;background:{{ $warnaHuruf['bg'] }};color:{{ $warnaHuruf['fg'] }};">
                    {{ $mahasiswa['nilai_huruf'] }}
                </span>
            @endif
        </div>
    </div>

    @if (! $penilaianTersedia)
        <div style="padding:10px 14px;border-radius:8px;background:#fef3c7;color:#92400e;font-size:13px;margin-bottom:16px;">
            Grafik belum dapat ditampilkan karena penilaian mata kuliah mahasiswa ini belum tersedia.
        </div>
    @else
        <div style="display:flex;flex-direction:column;gap:14px;margin-bottom:16px;">
            <div style="border:1px solid rgba(128,128,128,.2);border-radius:10px;padding:14px;">
                <div style="font-weight:600;font-size:13px;margin-bottom:10px;">Grafik Ketercapaian CPMK</div>
                <div>
                    <canvas
                        wire:key="radar-cpmk-mahasiswa-{{ $kmmId }}"
                        x-data
                        x-init="renderRadarSilogy($el, @js($radarCpmk), '#059669')"
                    ></canvas>
                </div>
            </div>
            <div style="border:1px solid rgba(128,128,128,.2);border-radius:10px;padding:14px;">
                <div style="font-weight:600;font-size:13px;margin-bottom:10px;">Grafik Ketercapaian Sub-CPMK</div>
                <div>
                    <canvas
                        wire:key="radar-subcpmk-mahasiswa-{{ $kmmId }}"
                        x-data
                        x-init="renderRadarSilogy($el, @js($radarSubcpmk), '#d97706')"
                    ></canvas>
                </div>
            </div>
            <div style="border:1px solid rgba(128,128,128,.2);border-radius:10px;padding:14px;">
                <div style="font-weight:600;font-size:13px;margin-bottom:10px;">Grafik Besaran Nilai Penugasan</div>
                <div>
                    <canvas
                        wire:key="bar-penugasan-mahasiswa-{{ $kmmId }}"
                        x-data
                        x-init="renderBarSilogy($el, @js($radarPenugasan), '#7c3aed')"
                    ></canvas>
                </div>
            </div>
        </div>
    @endif

    @if (empty($ketercapaian))
        <p style="font-size:13px;opacity:.7;margin-bottom:16px;">Belum ada CPL yang terpetakan pada mata kuliah ini.</p>
    @else
        <div style="font-weight:600;font-size:13px;margin-bottom:8px;">Ketercapaian CPL</div>
        <div style="overflow-x:auto;margin-bottom:16px;">
            <table style="width:100%;min-width:420px;border-collapse:collapse;font-size:12px;">
                <thead>
                    <tr style="background:rgba(128,128,128,.08);text-align:center;">
                        <th style="padding:6px 8px;">Kode CPL</th>
                        <th style="padding:6px 8px;text-align:left;">Deskripsi Singkat CPL</th>
                        <th style="padding:6px 8px;">Nilai</th>
                        <th style="padding:6px 8px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ketercapaian as $cplRow)
                        <tr style="border-top:1px solid rgba(128,128,128,.15);vertical-align:top;">
                            <td style="padding:6px 8px;text-align:center;font-weight:700;">{{ $cplRow['cpl_kode'] }}</td>
                            <td style="padding:6px 8px;">{{ $cplRow['cpl_deskripsi'] }}</td>
                            <td style="padding:6px 8px;text-align:center;">
                                {{ $cplRow['rata_rata'] !== null ? rtrim(rtrim(number_format($cplRow['rata_rata'], 2, '.', ''), '0'), '.').'%' : '—' }}
                            </td>
                            <td style="padding:6px 8px;text-align:center;">
                                @if ($cplRow['rata_rata'] === null)
                                    <span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:600;background:rgba(128,128,128,.15);color:#6b7280;">
                                        Belum ada data
                                    </span>
                                @elseif ($cplRow['tercapai'])
                                    <span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:600;background:#dcfce7;color:#166534;">
                                        Tercapai
                                    </span>
                                @else
                                    <span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:600;background:#fee2e2;color:#b91c1c;">
                                        Tidak Tercapai
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if (! empty($detail))
        <div style="font-weight:600;font-size:13px;margin-bottom:8px;">Rincian CPL → CPMK → Sub-CPMK</div>
        <div style="overflow-x:auto;">
            <table style="width:100%;min-width:720px;border-collapse:collapse;font-size:11px;">
                <thead>
                    <tr style="background:rgba(128,128,128,.08);text-align:center;">
                        <th style="padding:6px;">CPL</th>
                        <th style="padding:6px;">CPMK</th>
                        <th style="padding:6px;">Sub-CPMK</th>
                        <th style="padding:6px;">Sumber Data</th>
                        <th style="padding:6px;">PK</th>
                        <th style="padding:6px;">Nilai</th>
                        <th style="padding:6px;">PK × Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($detail as $baris)
                        <tr style="border-top:1px solid rgba(128,128,128,.15);vertical-align:top;">
                            @if ($baris['cpl_awal'])
                                <td rowspan="{{ $baris['cpl_rowspan'] }}" style="padding:6px;text-align:center;font-weight:700;">{{ $baris['cpl_kode'] }}</td>
                            @endif
                            @if ($baris['cpmk_awal'])
                                <td rowspan="{{ $baris['cpmk_rowspan'] }}" style="padding:6px;text-align:center;">{{ $baris['cpmk_kode'] }}</td>
                            @endif
                            @if ($baris['subcpmk_awal'])
                                <td rowspan="{{ $baris['subcpmk_rowspan'] }}" style="padding:6px;text-align:center;">{{ $baris['subcpmk_kode'] }}</td>
                            @endif
                            <td style="padding:6px;">{{ $baris['sumber_data'] }}</td>
                            <td style="padding:6px;text-align:right;">
                                {{ $baris['pk'] !== null ? rtrim(rtrim(number_format($baris['pk'], 2, '.', ''), '0'), '.').'%' : '—' }}
                            </td>
                            <td style="padding:6px;text-align:right;">
                                {{ $baris['rn'] !== null ? rtrim(rtrim(number_format($baris['rn'], 2, '.', ''), '0'), '.') : '—' }}
                            </td>
                            <td style="padding:6px;text-align:right;">
                                {{ $baris['pk_x_rn'] !== null ? rtrim(rtrim(number_format($baris['pk_x_rn'], 2, '.', ''), '0'), '.').'%' : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endif
