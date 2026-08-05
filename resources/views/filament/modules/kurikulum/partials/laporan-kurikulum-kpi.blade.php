{{--
  KPI bento donat progress penilaian.
  - compact: versi card kurikulum (vertikal)
  - page: versi halaman laporan (horizontal / solo)
  - tampil_mk / tampil_mahasiswa: kontrol pane yang ditampilkan
--}}
@php
    $compact = (bool) ($compact ?? false);
    $page = (bool) ($page ?? false);
    $nested = (bool) ($nested ?? false);
    $tampilMk = (bool) ($tampil_mk ?? true);
    $tampilMahasiswa = (bool) ($tampil_mahasiswa ?? true);

    $mkTotal = (int) ($mk_total ?? 0);
    $mkDinilai = (int) ($mk_dinilai ?? 0);
    $mkProgress = max(0, min(100, (int) ($mk_progress_persen ?? 0)));
    $mhsTotal = (int) ($mahasiswa_total ?? 0);
    $mhsDinilai = (int) ($mahasiswa_dinilai ?? 0);
    $mhsProgress = max(0, min(100, (int) ($mahasiswa_progress_persen ?? 0)));

    $mkCaption = $mkTotal > 0
        ? sprintf('%d dari %d mata kuliah sudah dinilai', $mkDinilai, $mkTotal)
        : 'Belum ada penawaran mata kuliah';

    $mhsCaption = $mhsTotal > 0
        ? sprintf('%d dari %d kontrak sudah dinilai', $mhsDinilai, $mhsTotal)
        : 'Belum ada kontrak mahasiswa';

    $solo = ($tampilMk xor $tampilMahasiswa);
    $sectionClass = 'silogy-penilaian-kpi silogy-laporan-kurikulum-kpi'
        .($compact ? ' silogy-laporan-kurikulum-kpi--card' : '')
        .($page ? ' silogy-laporan-kurikulum-kpi--page' : '')
        .($nested ? ' silogy-laporan-kurikulum-kpi--nested' : '')
        .($solo ? ' silogy-laporan-kurikulum-kpi--solo' : '');

    $bentoClass = 'silogy-penilaian-kpi__bento silogy-laporan-kurikulum-kpi__bento'
        .($compact ? ' silogy-laporan-kurikulum-kpi__bento--card' : '')
        .($solo ? ' silogy-laporan-kurikulum-kpi__bento--solo' : '');
@endphp

<section
    class="{{ $sectionClass }}"
    aria-label="Progress penilaian kurikulum"
    onclick="event.stopPropagation()"
>
    <div class="{{ $bentoClass }}" role="group">
        @if ($tampilMk)
            <div class="silogy-penilaian-kpi__pane silogy-penilaian-kpi__pane--progress">
                <div class="silogy-penilaian-kpi__eyebrow">Penilaian mata kuliah</div>
                <div class="silogy-penilaian-kpi__caption">Pengisian nilai pada penawaran MK</div>
                <div class="silogy-penilaian-kpi__progress">
                    <div
                        class="silogy-penilaian-kpi__donut"
                        style="--pct: {{ $mkProgress }};"
                        role="img"
                        aria-label="Progress penilaian mata kuliah {{ $mkProgress }} persen"
                    >
                        <span class="silogy-penilaian-kpi__donut-ring" aria-hidden="true"></span>
                        <span class="silogy-penilaian-kpi__donut-hole">
                            <span class="silogy-penilaian-kpi__donut-value">{{ $mkProgress }}%</span>
                        </span>
                    </div>
                    <div class="silogy-penilaian-kpi__progress-meta">
                        <span class="silogy-penilaian-kpi__progress-title">{{ $mkCaption }}</span>
                        <span class="silogy-penilaian-kpi__progress-sub">
                            Dinilai {{ number_format($mkDinilai, 0, ',', '.') }}
                            · Total {{ number_format($mkTotal, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>
        @endif

        @if ($tampilMk && $tampilMahasiswa)
            <div class="silogy-penilaian-kpi__rule" aria-hidden="true"></div>
        @endif

        @if ($tampilMahasiswa)
            <div class="silogy-penilaian-kpi__pane silogy-penilaian-kpi__pane--progress">
                <div class="silogy-penilaian-kpi__eyebrow">Penilaian mahasiswa</div>
                <div class="silogy-penilaian-kpi__caption">Pengisian nilai kontrak mata kuliah</div>
                <div class="silogy-penilaian-kpi__progress">
                    <div
                        class="silogy-penilaian-kpi__donut"
                        style="--pct: {{ $mhsProgress }};"
                        role="img"
                        aria-label="Progress penilaian mahasiswa {{ $mhsProgress }} persen"
                    >
                        <span class="silogy-penilaian-kpi__donut-ring" aria-hidden="true"></span>
                        <span class="silogy-penilaian-kpi__donut-hole">
                            <span class="silogy-penilaian-kpi__donut-value">{{ $mhsProgress }}%</span>
                        </span>
                    </div>
                    <div class="silogy-penilaian-kpi__progress-meta">
                        <span class="silogy-penilaian-kpi__progress-title">{{ $mhsCaption }}</span>
                        <span class="silogy-penilaian-kpi__progress-sub">
                            Dinilai {{ number_format($mhsDinilai, 0, ',', '.') }}
                            · Total {{ number_format($mhsTotal, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</section>
