@php
    $wireKeySuffix ??= 'workcloud';
@endphp

<div style="overflow-x:auto;" wire:key="matriks-{{ $wireKeySuffix }}-{{ $kelasMkId }}">
    <table class="portofolio-matrix" style="width:100%;min-width:max-content;border-collapse:collapse;font-size:13px;">
        <thead>
            <tr style="text-align:center;">
                <th
                    rowspan="2"
                    class="portofolio-sticky portofolio-sticky-head"
                    style="position:sticky;left:0;top:0;z-index:3;padding:8px 10px;min-width:44px;"
                >
                    No
                </th>
                <th
                    rowspan="2"
                    class="portofolio-sticky portofolio-sticky-head"
                    style="position:sticky;left:44px;top:0;z-index:3;padding:8px 10px;min-width:190px;text-align:left;"
                >
                    Mahasiswa
                </th>
                <th colspan="2" class="portofolio-sticky-topcol" style="position:sticky;top:0;z-index:2;padding:8px 10px;">
                    Nilai Akhir
                </th>
                <th colspan="{{ max(count($kolomEvaluasi), 1) }}" class="portofolio-sticky-topcol" style="position:sticky;top:0;z-index:2;padding:8px 10px;">
                    Nilai Komponen Evaluasi
                </th>
            </tr>
            <tr style="text-align:center;">
                <th class="portofolio-sticky-topcol" style="position:sticky;top:33px;z-index:2;padding:8px 6px;min-width:70px;">
                    Nilai
                </th>
                <th class="portofolio-sticky-topcol" style="position:sticky;top:33px;z-index:2;padding:8px 6px;min-width:70px;">
                    Grade
                </th>
                @forelse ($kolomEvaluasi as $kolom)
                    <th
                        class="portofolio-sticky-topcol"
                        style="position:sticky;top:33px;z-index:2;padding:8px 6px;white-space:nowrap;min-width:120px;vertical-align:top;"
                        title="{{ $kolom['label'] }}"
                    >
                        <span style="display:inline-block;padding:1px 8px;border-radius:999px;font-size:10px;font-weight:700;background:#e0e7ff;color:#3730a3;margin-bottom:4px;">
                            {{ rtrim(rtrim(number_format($kolom['bobot'], 2, '.', ''), '0'), '.') }}%
                        </span>
                        <span style="display:block;font-weight:700;font-size:12px;">{{ $kolom['label'] }}</span>
                        <span style="display:flex;align-items:center;gap:4px;justify-content:center;margin-top:3px;">
                            <span style="font-size:9px;opacity:.7;">CPL:</span>
                            @if ($kolom['cpl'])
                                <span style="display:inline-block;padding:1px 7px;border-radius:6px;font-size:9px;font-weight:600;background:#18181b;color:#fff;">
                                    {{ $kolom['cpl'] }}
                                </span>
                            @else
                                <span style="display:inline-block;padding:1px 7px;border-radius:6px;font-size:9px;font-weight:700;background:#ef4444;color:#fff;">
                                    -
                                </span>
                            @endif
                        </span>
                    </th>
                @empty
                    <th class="portofolio-sticky-topcol" style="position:sticky;top:33px;z-index:2;padding:8px 6px;">—</th>
                @endforelse
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr
                    wire:key="{{ $wireKeySuffix }}-row-{{ $row['id'] }}"
                    style="border-bottom:1px solid rgba(128,128,128,.2);"
                >
                    <td class="portofolio-sticky portofolio-sticky-cell" style="position:sticky;left:0;z-index:1;padding:8px 10px;text-align:center;">
                        {{ $loop->iteration }}
                    </td>
                    <td class="portofolio-sticky portofolio-sticky-cell" style="position:sticky;left:44px;z-index:1;padding:8px 10px;">
                        <span style="display:block;font-size:11px;opacity:.7;">{{ $row['nim'] }}</span>
                        <strong style="display:block;font-size:12px;text-transform:uppercase;">{{ $row['nama'] }}</strong>
                    </td>
                    <td style="padding:6px;text-align:center;font-weight:700;">
                        {{ $row['nilai_angka'] !== null ? rtrim(rtrim(number_format($row['nilai_angka'], 2, '.', ''), '0'), '.') : '—' }}
                    </td>
                    <td style="padding:6px;text-align:center;">
                        @if ($row['nilai_huruf'])
                            @php($warnaHurufBaris = $this->warnaNilaiHuruf($row['nilai_huruf']))
                            <span style="display:inline-block;padding:1px 9px;border-radius:6px;font-size:11px;font-weight:700;background:{{ $warnaHurufBaris['bg'] }};color:{{ $warnaHurufBaris['fg'] }};">
                                {{ $row['nilai_huruf'] }}
                            </span>
                        @else
                            —
                        @endif
                    </td>
                    @forelse ($kolomEvaluasi as $kolom)
                        @php($nilaiSel = $nilaiEvaluasi[$row['id']][$kolom['id']] ?? 0.0)
                        <td style="padding:6px;text-align:center;">
                            {{ rtrim(rtrim(number_format($nilaiSel, 2, '.', ''), '0'), '.') ?: '0' }}
                        </td>
                    @empty
                        <td style="padding:6px;text-align:center;">—</td>
                    @endforelse
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="border-top:2px solid rgba(128,128,128,.35);background:rgba(37,99,235,.08);font-weight:700;">
                <td
                    colspan="2"
                    class="portofolio-sticky portofolio-sticky-foot"
                    style="position:sticky;left:0;z-index:1;padding:8px 10px;"
                >
                    Rata-rata Kelas
                </td>
                <td style="padding:6px;text-align:center;">
                    {{ $this->rataRataNilaiAkhir !== null ? rtrim(rtrim(number_format($this->rataRataNilaiAkhir, 2, '.', ''), '0'), '.') : '—' }}
                </td>
                <td style="padding:6px;text-align:center;">
                    @if ($this->hurufRataRataKelas)
                        @php($warnaHurufRataRata = $this->warnaNilaiHuruf($this->hurufRataRataKelas))
                        <span style="display:inline-block;padding:1px 9px;border-radius:6px;font-size:11px;font-weight:700;background:{{ $warnaHurufRataRata['bg'] }};color:{{ $warnaHurufRataRata['fg'] }};">
                            {{ $this->hurufRataRataKelas }}
                        </span>
                    @else
                        —
                    @endif
                </td>
                @forelse ($kolomEvaluasi as $kolom)
                    @php($rataRataSel = $rataRataEvaluasi[$kolom['id']] ?? null)
                    <td style="padding:6px;text-align:center;">
                        {{ $rataRataSel !== null ? rtrim(rtrim(number_format($rataRataSel, 2, '.', ''), '0'), '.') : '—' }}
                    </td>
                @empty
                    <td style="padding:6px;text-align:center;">—</td>
                @endforelse
            </tr>
        </tfoot>
    </table>
</div>
