@php
    $angka = fn (float $n): string => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    $adaTakDibedakan = collect($ketercapaianSubcpmk)->contains(fn (array $baris): bool => $baris['dasar'] === 'tak_dibedakan');
@endphp

@if (empty($ketercapaianSubcpmk))
    <p style="font-size:13px;opacity:.7;margin-bottom:16px;">
        Belum ada Sub-CPMK yang terpetakan pada asesmen kelas ini.
    </p>
@else
    <div style="overflow-x:auto;">
        <table style="width:100%;min-width:640px;border-collapse:collapse;font-size:12px;">
            <thead>
                <tr style="background:rgba(128,128,128,.08);text-align:center;">
                    <th style="padding:8px;text-align:left;">Sub-CPMK</th>
                    <th style="padding:8px;">Rata-rata kelas</th>
                    <th style="padding:8px;">Mahasiswa mencapai target</th>
                    <th style="padding:8px;">Status</th>
                    <th style="padding:8px;">Dasar</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ketercapaianSubcpmk as $baris)
                    <tr style="border-top:1px solid rgba(128,128,128,.15);vertical-align:top;">
                        <td style="padding:8px;">
                            <strong>{{ $baris['kode'] }}</strong>
                            @if ($baris['nama'])
                                <div style="opacity:.75;">{{ $baris['nama'] }}</div>
                            @endif
                        </td>
                        <td style="padding:8px;text-align:center;">
                            {{ $baris['rata_rata'] !== null ? $angka($baris['rata_rata']).'%' : '—' }}
                        </td>
                        <td style="padding:8px;text-align:center;">
                            @if ($baris['dinilai'] > 0)
                                {{ $baris['mencapai'] }}/{{ $baris['dinilai'] }} ({{ round($baris['mencapai'] / $baris['dinilai'] * 100) }}%)
                            @else
                                —
                            @endif
                        </td>
                        <td style="padding:8px;text-align:center;">
                            @if ($baris['status'] === 'tercapai')
                                <span class="silogy-badge silogy-tone-success" style="font-size:11px;">Tercapai</span>
                            @elseif ($baris['status'] === 'lemah')
                                <span class="silogy-badge silogy-tone-warning" style="font-size:11px;">Lemah</span>
                            @else
                                <span class="silogy-badge silogy-tone-neutral" style="font-size:11px;">Belum ada data</span>
                            @endif
                            @if ($baris['terlemah'])
                                <span class="silogy-badge silogy-tone-danger" style="font-size:11px;">Terlemah</span>
                            @endif
                        </td>
                        <td style="padding:8px;text-align:center;">
                            {{ $baris['dasar'] === 'terpisah' ? 'Terukur terpisah' : 'Tidak dapat dibedakan' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($adaTakDibedakan)
        <p style="font-size:12px;opacity:.75;margin-top:8px;">
            Sub-CPMK bertanda "Tidak dapat dibedakan" hanya diukur lewat asesmen bersama yang tidak dirinci, sehingga angkanya sama dengan Sub-CPMK lain pada asesmen itu.
        </p>
    @endif
@endif
