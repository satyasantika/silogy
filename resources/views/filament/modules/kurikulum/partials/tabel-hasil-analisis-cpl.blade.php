@php
    $angkatanList = $hasilAnalisis['angkatan_list'];
    $pemetaan = $hasilAnalisis['pemetaan'];
    $sempitKolomCpl = (bool) ($sempitKolomCpl ?? false);
    $rapatKeBanner = (bool) ($rapatKeBanner ?? false);
    $targetCapaian = (int) ($this->kurikulum?->target_capaian_lulusan ?? 75);
    // Kolom CPL tetap lebih sempit dari default, tapi teks deskripsi lengkap (wrap, tanpa clamp).
    $cplThStyle = $sempitKolomCpl
        ? 'padding:8px 6px;vertical-align:middle;width:11rem;max-width:12rem;font-size:11px;line-height:1.25;'
        : 'padding:8px;vertical-align:middle;';
    $cplTdStyle = $sempitKolomCpl
        ? 'padding:8px 6px;background:rgba(37,99,235,.04);width:11rem;max-width:12rem;'
        : 'padding:8px;background:rgba(37,99,235,.04);';
    $emptyPad = $rapatKeBanner ? 'padding:14px 16px 16px;' : '';
@endphp

@if (empty($pemetaan))
    <p style="font-size:13px;opacity:.7;{{ $emptyPad }}">
        Belum ada CPL yang dibebankan pada mata kuliah di program studi ini.
    </p>
@else
    <div style="overflow-x:auto;">
        <table style="width:100%;min-width:{{ $sempitKolomCpl ? '720px' : '820px' }};border-collapse:collapse;font-size:12px;">
            <thead>
                <tr style="background:rgba(128,128,128,.08);text-align:center;">
                    <th rowspan="{{ empty($angkatanList) ? 1 : 2 }}" style="{{ $cplThStyle }}">Capaian Pembelajaran Lulusan</th>
                    <th rowspan="{{ empty($angkatanList) ? 1 : 2 }}" style="padding:8px;vertical-align:middle;">Aspek Mata Kuliah</th>
                    @if (empty($angkatanList))
                        <th style="padding:8px;vertical-align:middle;">Rerata Angkatan</th>
                    @else
                        <th colspan="{{ count($angkatanList) }}" style="padding:8px 8px 4px;font-size:11px;letter-spacing:.04em;text-transform:uppercase;opacity:.7;">
                            Rerata Angkatan
                        </th>
                    @endif
                    <th rowspan="{{ empty($angkatanList) ? 1 : 2 }}" style="padding:8px;vertical-align:middle;">Rerata Nilai</th>
                    <th rowspan="{{ empty($angkatanList) ? 1 : 2 }}" style="padding:8px;vertical-align:middle;text-align:end;">Kontribusi MK</th>
                    <th rowspan="{{ empty($angkatanList) ? 1 : 2 }}" style="padding:8px;vertical-align:middle;">Ketercapaian CPL</th>
                </tr>
                @if (! empty($angkatanList))
                    <tr style="background:rgba(128,128,128,.08);text-align:center;">
                        @foreach ($angkatanList as $angkatan)
                            <th style="padding:4px 8px 8px;font-size:12px;font-weight:700;font-variant-numeric:tabular-nums;">{{ $angkatan }}</th>
                        @endforeach
                    </tr>
                @endif
            </thead>
            <tbody>
                @foreach ($pemetaan as $baris)
                    @foreach ($baris['mk_rows'] as $i => $mkRow)
                        @php
                            $rerataMk = $mkRow['rata_rata_keseluruhan'];
                            $mkCapaian = $rerataMk === null
                                ? null
                                : ($rerataMk >= $targetCapaian ? 'success' : 'warning');
                        @endphp
                        <tr
                            wire:key="hasil-{{ $baris['cpl_id'] }}-{{ $mkRow['mk_id'] }}"
                            @if ($mkCapaian) data-silogy-mk-capaian="{{ $mkCapaian }}" @endif
                            style="border-top:1px solid rgba(128,128,128,.15);vertical-align:top;"
                        >
                            @if ($i === 0)
                                <td rowspan="{{ count($baris['mk_rows']) }}" style="{{ $cplTdStyle }}">
                                    <div style="display:flex;flex-direction:column;gap:2px;">
                                        <strong style="font-size:{{ $sempitKolomCpl ? '13px' : '14px' }};color:#1d4ed8;">{{ $baris['cpl_kode'] }}</strong>
                                        <span style="font-size:{{ $sempitKolomCpl ? '11px' : '12px' }};opacity:.75;line-height:1.4;">{{ $baris['cpl_deskripsi'] }}</span>
                                        <span style="font-size:10px;opacity:.6;">
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
                                @endif
                                <span class="silogy-badge silogy-tone-indigo silogy-badge--sks" style="margin-left:5px;padding:0 5px;border-radius:4px;font-size:9px;font-weight:600;letter-spacing:.02em;line-height:1.45;vertical-align:1px;">
                                    {{ $mkRow['sks'] }} SKS
                                </span>
                            </td>
                            @if (empty($angkatanList))
                                <td style="padding:8px;text-align:center;">—</td>
                            @else
                                @foreach ($angkatanList as $angkatan)
                                    @php($sel = $mkRow['per_angkatan'][$angkatan] ?? ['rata_rata' => null, 'n' => 0])
                                    <td style="padding:6px 8px;text-align:center;white-space:nowrap;" title="Angkatan {{ $angkatan }}: rerata nilai dan jumlah mahasiswa (N)">
                                        @if ($sel['rata_rata'] === null)
                                            <span style="opacity:.45;">—</span>
                                        @else
                                            <div style="font-variant-numeric:tabular-nums;font-weight:600;font-size:12px;line-height:1.15;">
                                                {{ rtrim(rtrim(number_format($sel['rata_rata'], 2, '.', ''), '0'), '.') }}
                                            </div>
                                            <div style="font-size:10px;opacity:.55;margin-top:2px;letter-spacing:.03em;font-variant-numeric:tabular-nums;">
                                                n={{ $sel['n'] }}
                                            </div>
                                        @endif
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
                                    title="Σ (nilai MK × kontribusi MK) / 100 per mahasiswa, lalu dirata-rata — MK belum dinilai menekan capaian sesuai porsinya"
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
