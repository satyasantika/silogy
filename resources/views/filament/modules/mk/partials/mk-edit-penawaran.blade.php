{{-- Panel penawaran MK + rekap kontrak lintas semester (EditMk). --}}
@php
    $penawaran = $penawaran ?? [];
    $totalKelas = (int) ($total_kelas ?? 0);
    $totalMahasiswa = (int) ($total_mahasiswa ?? 0);
    $adaPenawaran = $penawaran !== [];
    $penawaranDetailId = $penawaranDetailId ?? null;
@endphp

<div class="silogy-mk-edit-penawaran" style="margin-top:1.75rem;">
    <div style="border:1px solid rgba(71,85,105,.22);border-radius:14px;overflow:hidden;background:rgba(248,250,252,.55);">
        <div style="padding:16px 18px;border-bottom:1px solid rgba(71,85,105,.14);display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start;justify-content:space-between;">
            <div style="min-width:200px;flex:1 1 240px;">
                <div style="font-weight:700;font-size:15px;letter-spacing:-.01em;color:rgb(15,23,42);">
                    Penawaran mata kuliah
                </div>
                <div style="font-size:12.5px;line-height:1.45;opacity:.72;margin-top:4px;max-width:36rem;">
                    Semua penawaran (adaptasi) di prodi/unit mana pun untuk mata kuliah ini, beserta total kelas dan mahasiswa kontrak di seluruh semester kalender.
                </div>
            </div>

            @if ($adaPenawaran)
                <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:stretch;">
                    <div style="min-width:108px;padding:10px 14px;border-radius:10px;background:rgba(15,23,42,.04);border:1px solid rgba(71,85,105,.12);">
                        <div style="font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;opacity:.55;">Kelas</div>
                        <div style="font-size:22px;font-weight:700;font-variant-numeric:tabular-nums;line-height:1.15;margin-top:2px;color:rgb(15,23,42);">
                            {{ $totalKelas }}
                        </div>
                    </div>
                    <div style="min-width:108px;padding:10px 14px;border-radius:10px;background:rgba(15,23,42,.04);border:1px solid rgba(71,85,105,.12);">
                        <div style="font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;opacity:.55;">Mahasiswa</div>
                        <div style="font-size:22px;font-weight:700;font-variant-numeric:tabular-nums;line-height:1.15;margin-top:2px;color:rgb(15,23,42);">
                            {{ $totalMahasiswa }}
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div style="padding:16px 18px 18px;">
            @if (! $adaPenawaran)
                <p style="font-size:13px;opacity:.7;margin:0;">
                    Belum ada penawaran di prodi mana pun untuk mata kuliah ini.
                </p>
            @else
                <div style="overflow-x:auto;border:1px solid rgba(71,85,105,.16);border-radius:12px;background:#fff;">
                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <thead>
                            <tr style="background:rgba(71,85,105,.06);text-align:left;">
                                <th style="padding:10px 12px;font-weight:600;">Kode</th>
                                <th style="padding:10px 12px;font-weight:600;">Unit</th>
                                <th style="padding:10px 12px;font-weight:600;">Kurikulum</th>
                                <th style="padding:10px 12px;font-weight:600;text-align:center;">Semester ke-</th>
                                <th style="padding:10px 12px;font-weight:600;text-align:center;">Kelas</th>
                                <th style="padding:10px 12px;font-weight:600;text-align:center;">Mahasiswa</th>
                                <th style="padding:10px 12px;font-weight:600;text-align:center;">Status</th>
                                <th style="padding:10px 12px;font-weight:600;text-align:right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($penawaran as $baris)
                                @php
                                    $terbuka = $penawaranDetailId === $baris['id'];
                                @endphp
                                <tr style="border-top:1px solid rgba(71,85,105,.12);{{ $terbuka ? 'background:rgba(37,99,235,.04);' : '' }}">
                                    <td style="padding:10px 12px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-weight:600;">
                                        {{ $baris['kode'] }}
                                    </td>
                                    <td style="padding:10px 12px;">{{ $baris['unit'] }}</td>
                                    <td style="padding:10px 12px;font-size:12.5px;">{{ $baris['kurikulum'] }}</td>
                                    <td style="padding:10px 12px;text-align:center;font-variant-numeric:tabular-nums;">
                                        {{ $baris['semester_ke'] ?? '—' }}
                                    </td>
                                    <td style="padding:10px 12px;text-align:center;">
                                        @if ($baris['jumlah_kelas'] > 0)
                                            <span style="display:inline-flex;align-items:center;border-radius:6px;padding:2px 8px;font-size:12px;font-weight:600;background:rgba(37,99,235,.08);color:rgb(29,78,216);">
                                                {{ $baris['jumlah_kelas'] }}
                                            </span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td style="padding:10px 12px;text-align:center;">
                                        @if ($baris['jumlah_mahasiswa'] > 0)
                                            <span style="display:inline-flex;align-items:center;border-radius:6px;padding:2px 8px;font-size:12px;font-weight:600;background:rgba(37,99,235,.08);color:rgb(29,78,216);">
                                                {{ $baris['jumlah_mahasiswa'] }}
                                            </span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td style="padding:10px 12px;text-align:center;">
                                        {{ $baris['is_active'] ? 'Aktif' : 'Nonaktif' }}
                                    </td>
                                    <td style="padding:10px 12px;text-align:right;white-space:nowrap;">
                                        <button
                                            type="button"
                                            wire:click="toggleDetailPenawaran('{{ $baris['id'] }}')"
                                            style="appearance:none;border:0;background:transparent;padding:0;font-size:13px;font-weight:600;color:{{ $terbuka ? 'rgb(29,78,216)' : 'rgb(51,65,85)' }};cursor:pointer;text-decoration:underline;text-underline-offset:3px;"
                                        >
                                            {{ $terbuka ? 'Tutup' : 'Detail' }}
                                        </button>
                                    </td>
                                </tr>

                                @if ($terbuka)
                                    <tr wire:key="rincian-semester-{{ $baris['id'] }}" style="border-top:1px solid rgba(71,85,105,.1);background:rgba(248,250,252,.9);">
                                        <td colspan="8" style="padding:12px 14px 16px;">
                                            <div style="font-size:12.5px;font-weight:700;color:rgb(15,23,42);margin-bottom:8px;">
                                                Rincian semester
                                                <span style="font-weight:500;opacity:.65;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;">
                                                    · {{ $baris['kode'] }}
                                                </span>
                                            </div>

                                            @if ($baris['per_semester'] === [])
                                                <p style="font-size:13px;opacity:.7;margin:0;">
                                                    Belum ada kelas kontrak pada semester mana pun untuk penawaran ini.
                                                </p>
                                            @else
                                                <div style="overflow-x:auto;border:1px solid rgba(71,85,105,.14);border-radius:10px;background:#fff;">
                                                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                                                        <thead>
                                                            <tr style="background:rgba(71,85,105,.05);text-align:left;">
                                                                <th style="padding:9px 12px;font-weight:600;">Semester</th>
                                                                <th style="padding:9px 12px;font-weight:600;">Kode</th>
                                                                <th style="padding:9px 12px;font-weight:600;text-align:center;">Kelas</th>
                                                                <th style="padding:9px 12px;font-weight:600;text-align:center;">Mahasiswa</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach ($baris['per_semester'] as $semester)
                                                                <tr style="border-top:1px solid rgba(71,85,105,.1);">
                                                                    <td style="padding:9px 12px;">{{ $semester['nama'] }}</td>
                                                                    <td style="padding:9px 12px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-variant-numeric:tabular-nums;">
                                                                        {{ $semester['kode'] }}
                                                                    </td>
                                                                    <td style="padding:9px 12px;text-align:center;">
                                                                        @if ($semester['jumlah_kelas'] > 0)
                                                                            <span style="display:inline-flex;align-items:center;border-radius:6px;padding:2px 8px;font-size:12px;font-weight:600;background:rgba(37,99,235,.08);color:rgb(29,78,216);">
                                                                                {{ $semester['jumlah_kelas'] }}
                                                                            </span>
                                                                        @else
                                                                            —
                                                                        @endif
                                                                    </td>
                                                                    <td style="padding:9px 12px;text-align:center;">
                                                                        @if ($semester['jumlah_mahasiswa'] > 0)
                                                                            <span style="display:inline-flex;align-items:center;border-radius:6px;padding:2px 8px;font-size:12px;font-weight:600;background:rgba(37,99,235,.08);color:rgb(29,78,216);">
                                                                                {{ $semester['jumlah_mahasiswa'] }}
                                                                            </span>
                                                                        @else
                                                                            —
                                                                        @endif
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
