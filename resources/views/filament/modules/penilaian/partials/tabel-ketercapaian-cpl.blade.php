@if (empty($ketercapaian))
    <p style="font-size:13px;opacity:.7;margin-bottom:16px;">
        Belum ada CPL yang terpetakan pada mata kuliah ini.
    </p>
@else
    <div style="overflow-x:auto;">
        <table style="width:100%;min-width:640px;border-collapse:collapse;font-size:12px;">
            <thead>
                <tr style="background:rgba(128,128,128,.08);text-align:center;">
                    <th style="padding:8px;">Kode CPL</th>
                    <th style="padding:8px;text-align:left;">Deskripsi Singkat CPL</th>
                    <th style="padding:8px;text-align:left;">Komponen Penilaian Penyumbang Nilai</th>
                    <th style="padding:8px;">Rata-rata Capaian Kelas</th>
                    <th style="padding:8px;">Status Ketercapaian</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ketercapaian as $cplRow)
                    <tr style="border-top:1px solid rgba(128,128,128,.15);vertical-align:top;">
                        <td style="padding:8px;text-align:center;font-weight:700;">{{ $cplRow['cpl_kode'] }}</td>
                        <td style="padding:8px;">{{ $cplRow['cpl_deskripsi'] }}</td>
                        <td style="padding:8px;">
                            @forelse ($cplRow['kontribusi'] as $kontribusi)
                                {{ $kontribusi['nama'] }} ({{ rtrim(rtrim(number_format($kontribusi['bobot'], 2, '.', ''), '0'), '.') }}%)<br>
                            @empty
                                <span style="opacity:.6;">—</span>
                            @endforelse
                        </td>
                        <td style="padding:8px;text-align:center;">
                            {{ $cplRow['rata_rata'] !== null ? rtrim(rtrim(number_format($cplRow['rata_rata'], 2, '.', ''), '0'), '.').'%' : '—' }}
                        </td>
                        <td style="padding:8px;text-align:center;">
                            @if ($cplRow['rata_rata'] === null)
                                <span style="display:inline-block;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:600;background:rgba(128,128,128,.15);color:#6b7280;">
                                    Belum ada data
                                </span>
                            @elseif ($cplRow['tercapai'])
                                <span style="display:inline-block;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:600;background:#dcfce7;color:#166534;">
                                    Tercapai
                                </span>
                            @else
                                <span style="display:inline-block;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:600;background:#fee2e2;color:#b91c1c;">
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
