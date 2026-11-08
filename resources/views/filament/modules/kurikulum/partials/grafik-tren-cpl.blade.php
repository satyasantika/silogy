{{-- Grafik garis "Tren Ketercapaian CPL Antarsemester" + tabel datanya.
     $tren: keluaran TrenCplSemesterService::tren(); $judul opsional. --}}
@php
    $judul ??= 'Tren Ketercapaian CPL Antarsemester';
    $jumlahSemester = count($tren['semesters'] ?? []);
@endphp

<section data-silogy="grafik-tren-cpl" style="border:1px solid rgba(128,128,128,.2);border-radius:10px;padding:14px;margin-top:16px;">
    <div style="font-weight:600;font-size:14px;margin-bottom:2px;">{{ $judul }}</div>
    <p style="margin:0 0 12px;font-size:12px;line-height:1.5;opacity:.75;">
        Rerata ketercapaian tiap CPL per semester; garis abu-abu putus-putus = target capaian {{ $tren['target'] ?? 75 }}.
    </p>

    @if ($jumlahSemester === 0)
        <p style="font-size:13px;opacity:.75;margin:0;">Belum ada nilai yang bisa ditampilkan.</p>
    @else
        @if ($jumlahSemester === 1)
            <div class="silogy-tone-info" style="padding:8px 12px;border-radius:8px;font-size:12px;margin-bottom:12px;">
                Baru 1 semester yang dinilai ({{ $tren['semesters'][0]['label'] }}). Tren muncul setelah minimal 2 semester dinilai.
            </div>
        @endif

        <div style="position:relative;width:100%;height:340px;">
            <canvas
                wire:key="tren-cpl-{{ md5(json_encode($tren)) }}"
                x-data
                x-init="renderTrenSilogy($el, @js($tren))"
                role="img"
                aria-label="{{ $judul }}"
            ></canvas>
        </div>

        <details style="margin-top:10px;font-size:12px;">
            <summary style="cursor:pointer;opacity:.8;">Tabel data</summary>
            <div style="overflow-x:auto;margin-top:8px;">
                <table style="width:100%;border-collapse:collapse;font-size:12px;">
                    <thead>
                        <tr style="border-bottom:1px solid rgba(128,128,128,.3);">
                            <th style="text-align:left;padding:6px 8px;">CPL</th>
                            @foreach ($tren['semesters'] as $semester)
                                <th style="text-align:right;padding:6px 8px;white-space:nowrap;">{{ $semester['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tren['cpl'] as $baris)
                            <tr style="border-bottom:1px solid rgba(128,128,128,.15);">
                                <td style="padding:6px 8px;font-weight:600;">{{ $baris['kode'] }}</td>
                                @foreach ($baris['data'] as $nilai)
                                    <td style="text-align:right;padding:6px 8px;font-variant-numeric:tabular-nums;">
                                        {{ $nilai === null ? '—' : number_format($nilai, 2, ',', '.') }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>

        <p style="margin:8px 0 0;font-size:11px;line-height:1.5;opacity:.6;">
            Catatan: per semester, kontribusi MK ke CPL dibagi ulang hanya di antara MK yang dinilai pada semester itu,
            sehingga angkanya tidak persis sama dengan ketercapaian kumulatif di tabel Hasil Analisis CPL.
        </p>
    @endif
</section>
