@php
    $angkatanList = $hasilAnalisis['angkatan_list'];
    $pemetaan = $hasilAnalisis['pemetaan'];
@endphp

@if (empty($pemetaan))
    <p style="font-size:13px;opacity:.7;">
        Belum ada CPL yang dibebankan pada mata kuliah di program studi ini.
    </p>
@else
    <div style="overflow-x:auto;">
        <table style="width:100%;min-width:900px;border-collapse:collapse;font-size:12px;">
            <thead>
                <tr style="background:rgba(128,128,128,.08);text-align:center;">
                    <th rowspan="3" style="padding:8px;vertical-align:middle;">Capaian Pembelajaran Lulusan</th>
                    <th rowspan="3" style="padding:8px;vertical-align:middle;">Aspek Mata Kuliah</th>
                    @if (empty($angkatanList))
                        <th rowspan="3" style="padding:8px;vertical-align:middle;">Rerata Nilai Angkatan</th>
                    @else
                        <th colspan="{{ count($angkatanList) * 2 }}" style="padding:8px;">Rerata Nilai Angkatan dan Jumlah</th>
                    @endif
                    <th rowspan="3" style="padding:8px;vertical-align:middle;">Rerata Nilai</th>
                    <th rowspan="3" style="padding:8px;vertical-align:middle;text-align:end;">Kontribusi MK</th>
                    <th rowspan="3" style="padding:8px;vertical-align:middle;">Ketercapaian CPL</th>
                </tr>
                @if (! empty($angkatanList))
                    <tr style="background:rgba(128,128,128,.08);text-align:center;">
                        @foreach ($angkatanList as $angkatan)
                            <th colspan="2" style="padding:6px;">{{ $angkatan }}</th>
                        @endforeach
                    </tr>
                    <tr style="background:rgba(128,128,128,.08);text-align:center;">
                        @foreach ($angkatanList as $angkatan)
                            <th style="padding:6px;">Rerata</th>
                            <th style="padding:6px;">N</th>
                        @endforeach
                    </tr>
                @endif
            </thead>
            <tbody>
                @foreach ($pemetaan as $baris)
                    @foreach ($baris['mk_rows'] as $i => $mkRow)
                        <tr wire:key="hasil-{{ $baris['cpl_id'] }}-{{ $mkRow['mk_id'] }}" style="border-top:1px solid rgba(128,128,128,.15);vertical-align:top;">
                            @if ($i === 0)
                                <td rowspan="{{ count($baris['mk_rows']) }}" style="padding:8px;background:rgba(37,99,235,.04);">
                                    <div style="display:flex;flex-direction:column;gap:2px;">
                                        <strong style="font-size:14px;color:#1d4ed8;">{{ $baris['cpl_kode'] }}</strong>
                                        <span style="font-size:12px;opacity:.75;">{{ $baris['cpl_deskripsi'] }}</span>
                                        <span style="font-size:11px;opacity:.6;">
                                            Σ kontribusi {{ count($baris['mk_rows']) }} MK = 100%
                                        </span>
                                    </div>
                                </td>
                            @endif
                            <td style="padding:8px;text-align:left;">
                                @if ($this->analisisUnitType() === 'study_program')
                                    {{ $mkRow['nama'] }} ({{ $mkRow['kode'] }})
                                @else
                                    {{ $mkRow['nama'] }}
                                    <span class="silogy-badge silogy-tone-indigo" style="margin-left:6px;">
                                        {{ $mkRow['sks'] }} SKS
                                    </span>
                                @endif
                            </td>
                            @if (empty($angkatanList))
                                <td style="padding:8px;text-align:center;">—</td>
                            @else
                                @foreach ($angkatanList as $angkatan)
                                    @php($sel = $mkRow['per_angkatan'][$angkatan] ?? ['rata_rata' => null, 'n' => 0])
                                    <td style="padding:8px;text-align:center;">
                                        {{ $sel['rata_rata'] !== null ? rtrim(rtrim(number_format($sel['rata_rata'], 2, '.', ''), '0'), '.') : '—' }}
                                    </td>
                                    <td style="padding:8px;text-align:center;">
                                        {{ $sel['n'] > 0 ? $sel['n'] : '—' }}
                                    </td>
                                @endforeach
                            @endif
                            <td style="padding:8px;text-align:center;">
                                {{ $mkRow['rata_rata_keseluruhan'] !== null ? rtrim(rtrim(number_format($mkRow['rata_rata_keseluruhan'], 2, '.', ''), '0'), '.') : '—' }}
                            </td>
                            <td
                                style="padding:8px;text-align:end;"
                                title="Bobot tersimpan pada matriks Interaksi CPL ↔ MK: {{ rtrim(rtrim(number_format($mkRow['bobot_mentah'], 2, '.', ''), '0'), '.') }}%"
                            >
                                {{ rtrim(rtrim(number_format($mkRow['kontribusi'], 2, '.', ''), '0'), '.') }}%
                            </td>
                            @if ($i === 0)
                                <td
                                    rowspan="{{ count($baris['mk_rows']) }}"
                                    style="padding:8px;text-align:center;"
                                    title="Rerata tertimbang: nilai tiap mahasiswa dihitung menurut bobot kontribusi MK ke CPL ini"
                                >
                                    @php($ketercapaian = $baris['ketercapaian'])
                                    @if ($ketercapaian === null || $ketercapaian['rata_rata'] === null)
                                        <div class="silogy-tone-warning" style="display:inline-block;padding:4px 10px;border-radius:8px;font-size:11px;font-weight:600;">
                                            Menunggu selesai penilaian
                                        </div>
                                    @else
                                        <div>
                                            <strong style="font-size:14px;">
                                                {{ rtrim(rtrim(number_format($ketercapaian['rata_rata'], 2, '.', ''), '0'), '.') }}%
                                            </strong>
                                            <div style="font-size:10px;opacity:.7;margin-top:2px;">
                                                {{ $ketercapaian['jumlah_mahasiswa'] }} mahasiswa,
                                                {{ rtrim(rtrim(number_format($ketercapaian['persentase_tercapai'] ?? 0, 2, '.', ''), '0'), '.') }}% capai target
                                            </div>
                                            <div style="margin-top:4px;">
                                                @if ($ketercapaian['tercapai'])
                                                    <span class="silogy-badge silogy-tone-success" style="font-size:10px;">
                                                        CPL tercapai
                                                    </span>
                                                @else
                                                    <span class="silogy-badge silogy-tone-danger" style="font-size:10px;">
                                                        CPL tidak tercapai
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
@endif
