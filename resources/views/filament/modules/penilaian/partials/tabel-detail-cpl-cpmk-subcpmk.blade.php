@php
    $target ??= null;
    $rataRataKeseluruhan ??= null;
@endphp

@if (empty($detail))
    <p style="font-size:13px;opacity:.7;">
        Belum ada CPL yang terpetakan pada mata kuliah ini.
    </p>
@else
    <div style="overflow-x:auto;">
        <table style="width:100%;min-width:960px;border-collapse:collapse;font-size:12px;">
            <thead>
                <tr style="background:rgba(128,128,128,.08);text-align:center;">
                    <th style="padding:8px;">CPL yang dibebankan pada MK</th>
                    <th style="padding:8px;">CPMK yang Relevan dengan CPL</th>
                    <th style="padding:8px;">SubCPMK sebagai kemampuan akhir yang relevan</th>
                    <th style="padding:8px;">Indikator</th>
                    <th style="padding:8px;">Sumber Data sesuai Indikator</th>
                    <th style="padding:8px;">Perkiraan Bobot (PK)</th>
                    <th style="padding:8px;">Rerata Nilai (RN)</th>
                    <th style="padding:8px;">PK × RN</th>
                    <th style="padding:8px;">Ketercapaian</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($detail as $baris)
                    <tr style="border-top:1px solid rgba(128,128,128,.15);vertical-align:top;">
                        @if ($baris['cpl_awal'])
                            <td rowspan="{{ $baris['cpl_rowspan'] }}" style="padding:8px;background:rgba(37,99,235,.04);">
                                <strong>{{ $baris['cpl_kode'] }}</strong>
                                <div style="opacity:.75;margin-top:2px;">{{ $baris['cpl_deskripsi'] }}</div>
                            </td>
                        @endif
                        @if ($baris['cpmk_awal'])
                            <td rowspan="{{ $baris['cpmk_rowspan'] }}" style="padding:8px;">
                                <strong>{{ $baris['cpmk_kode'] }}</strong>
                                <div style="opacity:.75;margin-top:2px;">{{ $baris['cpmk_deskripsi'] }}</div>
                                @if ($baris['cpmk_rata_rata'] !== null)
                                    <div style="margin-top:4px;font-size:10px;opacity:.65;">
                                        (ketercapaian: {{ rtrim(rtrim(number_format($baris['cpmk_rata_rata'], 2, '.', ''), '0'), '.') }}%)
                                    </div>
                                @endif
                            </td>
                        @endif
                        @if ($baris['subcpmk_awal'])
                            <td rowspan="{{ $baris['subcpmk_rowspan'] }}" style="padding:8px;">
                                <strong>{{ $baris['subcpmk_kode'] }}</strong>
                                <div style="opacity:.75;margin-top:2px;">{{ $baris['subcpmk_deskripsi'] }}</div>
                                @if ($baris['subcpmk_rata_rata'] !== null)
                                    <div style="margin-top:4px;font-size:10px;opacity:.65;">
                                        (ketercapaian: {{ rtrim(rtrim(number_format($baris['subcpmk_rata_rata'], 2, '.', ''), '0'), '.') }}%)
                                    </div>
                                @endif
                            </td>
                        @endif
                        <td style="padding:8px;">{{ $baris['indikator'] }}</td>
                        <td style="padding:8px;">{{ $baris['sumber_data'] }}</td>
                        <td style="padding:8px;text-align:right;">
                            {{ $baris['pk'] !== null ? rtrim(rtrim(number_format($baris['pk'], 2, '.', ''), '0'), '.').'%' : '—' }}
                        </td>
                        <td style="padding:8px;text-align:right;">
                            {{ $baris['rn'] !== null ? rtrim(rtrim(number_format($baris['rn'], 2, '.', ''), '0'), '.') : '—' }}
                        </td>
                        <td style="padding:8px;text-align:right;">
                            {{ $baris['pk_x_rn'] !== null ? rtrim(rtrim(number_format($baris['pk_x_rn'], 2, '.', ''), '0'), '.').'%' : '—' }}
                        </td>
                        @if ($baris['cpl_awal'])
                            <td rowspan="{{ $baris['cpl_rowspan'] }}" style="padding:8px;text-align:center;background:rgba(37,99,235,.04);">
                                <strong style="display:block;font-size:15px;">
                                    {{ $baris['cpl_rata_rata'] !== null ? rtrim(rtrim(number_format($baris['cpl_rata_rata'], 2, '.', ''), '0'), '.').'%' : '—' }}
                                </strong>
                                <span style="display:block;font-size:10px;opacity:.6;margin-bottom:4px;">
                                    dari target {{ $baris['cpl_target'] }}%
                                </span>
                                @if ($baris['cpl_rata_rata'] === null)
                                    <span style="display:inline-block;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:600;background:rgba(128,128,128,.15);color:#6b7280;">
                                        Belum ada data
                                    </span>
                                @elseif ($baris['cpl_tercapai'])
                                    <span style="display:inline-block;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:600;background:#dcfce7;color:#166534;">
                                        Tercapai
                                    </span>
                                @else
                                    <span style="display:inline-block;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:600;background:#fee2e2;color:#b91c1c;">
                                        Tidak Tercapai
                                    </span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="border-top:2px solid rgba(128,128,128,.35);background:rgba(22,163,74,.06);">
                    <td colspan="9" style="padding:14px;">
                        <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;">
                            <div style="font-weight:600;font-size:13px;color:#166534;">
                                Persentase ketercapaian MK terhadap perkiraan sumbangan ke CPL
                            </div>
                            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                                <div style="border:1px solid rgba(22,163,74,.3);border-radius:10px;padding:8px 16px;background:#fff;text-align:right;">
                                    <div style="font-size:10px;font-weight:600;text-transform:uppercase;opacity:.7;">
                                        Target Kelulusan CPL
                                    </div>
                                    <div style="font-size:20px;font-weight:700;">
                                        {{ $target !== null ? rtrim(rtrim(number_format($target, 2, '.', ''), '0'), '.').'%' : '—' }}
                                    </div>
                                </div>
                                <div style="border:1px solid rgba(37,99,235,.3);border-radius:10px;padding:8px 16px;background:#fff;text-align:right;">
                                    <div style="font-size:10px;font-weight:600;text-transform:uppercase;opacity:.7;">
                                        Rata-rata Ketercapaian
                                    </div>
                                    <div
                                        style="font-size:20px;font-weight:700;color:{{ $rataRataKeseluruhan !== null && $target !== null && $rataRataKeseluruhan >= $target ? '#16a34a' : '#b91c1c' }};"
                                    >
                                        {{ $rataRataKeseluruhan !== null ? rtrim(rtrim(number_format($rataRataKeseluruhan, 2, '.', ''), '0'), '.').'%' : '—' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
@endif
