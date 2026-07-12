@php
    $wireKeySuffix ??= 'workcloud';
@endphp

<div style="overflow-x:auto;" wire:key="matriks-{{ $wireKeySuffix }}-{{ $kelasMkId }}">
    <table class="portofolio-matrix" style="width:100%;min-width:max-content;border-collapse:collapse;font-size:13px;">
        <thead>
            <tr style="text-align:left;">
                <th class="portofolio-sticky portofolio-sticky-head" style="position:sticky;left:0;top:0;z-index:3;padding:8px 10px;min-width:190px;">
                    Mahasiswa
                </th>
                @foreach ($columns as $column)
                    <th
                        class="portofolio-sticky-topcol"
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
                    wire:key="{{ $wireKeySuffix }}-row-{{ $row['id'] }}"
                    style="border-bottom:1px solid rgba(128,128,128,.2);"
                >
                    <td class="portofolio-sticky portofolio-sticky-cell" style="position:sticky;left:0;z-index:1;padding:8px 10px;">
                        <span style="display:block;font-size:11px;opacity:.7;">{{ $row['nim'] }}</span>
                        <strong style="display:block;font-size:12px;text-transform:uppercase;">{{ $row['nama'] }}</strong>
                        <span style="display:flex;align-items:center;gap:5px;margin-top:3px;">
                            <span style="font-size:11px;opacity:.75;">
                                Nilai: {{ $row['nilai_angka'] !== null ? rtrim(rtrim(number_format($row['nilai_angka'], 2, '.', ''), '0'), '.') : '—' }}
                            </span>
                            @if ($row['nilai_huruf'])
                                @php($warnaHurufBaris = $this->warnaNilaiHuruf($row['nilai_huruf']))
                                <span style="display:inline-block;padding:1px 7px;border-radius:6px;font-size:10px;font-weight:700;background:{{ $warnaHurufBaris['bg'] }};color:{{ $warnaHurufBaris['fg'] }};">
                                    {{ $row['nilai_huruf'] }}
                                </span>
                            @endif
                        </span>
                    </td>
                    @foreach ($columns as $column)
                        @php($nilaiSel = $nilai[$row['id']][$column['id']] ?? null)
                        <td style="padding:6px;text-align:center;">
                            <div style="min-width:64px;padding:4px 6px;border:1.5px solid rgba(128,128,128,.3);border-radius:6px;text-align:center;display:inline-block;">
                                {{ $nilaiSel !== null ? rtrim(rtrim(number_format((float) $nilaiSel, 2, '.', ''), '0'), '.') : '—' }}
                            </div>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="border-top:2px solid rgba(128,128,128,.35);background:rgba(37,99,235,.08);font-weight:700;">
                <td class="portofolio-sticky portofolio-sticky-foot" style="position:sticky;left:0;z-index:1;padding:8px 10px;">
                    Rata-rata Kelas
                </td>
                @foreach ($columns as $column)
                    @php($rataRataSel = $this->rataRataKelas[$column['id']] ?? null)
                    <td style="padding:6px;text-align:center;">
                        {{ $rataRataSel !== null ? rtrim(rtrim(number_format($rataRataSel, 2, '.', ''), '0'), '.') : '—' }}
                    </td>
                @endforeach
            </tr>
        </tfoot>
    </table>
</div>
