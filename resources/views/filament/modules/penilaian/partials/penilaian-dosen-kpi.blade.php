{{-- KPI bento pengampu MK: MK / siap / menunggu + donut progress semester terpilih. --}}
@php
    $semesterLabel = $semester_label ?? '—';
    $jumlahProdi = (int) ($jumlah_prodi ?? 0);
    $jumlahMk = (int) ($jumlah_mk ?? 0);
    $jumlahKelas = (int) ($jumlah_kelas ?? 0);
    $kelasSiap = (int) ($kelas_siap ?? 0);
    $kelasDinilai = (int) ($kelas_dinilai ?? 0);
    $kelasMenunggu = (int) ($kelas_menunggu_asesmen ?? 0);
    $mkMenunggu = (int) ($mk_menunggu ?? 0);
    $progress = max(0, min(100, (int) ($progress_persen ?? 0)));
    $progressCaption = $kelasSiap > 0
        ? sprintf('%d dari %d kelas siap sudah dinilai', $kelasDinilai, $kelasSiap)
        : ($jumlahKelas > 0
            ? 'Belum ada kelas dengan asesmen siap dari koordinator'
            : 'Belum ada kelas pada semester ini');
@endphp

<section class="silogy-penilaian-kpi" aria-label="Ringkasan pengampu mata kuliah">
    <div class="silogy-penilaian-kpi__bento" role="group">
        <div class="silogy-penilaian-kpi__pane silogy-penilaian-kpi__pane--counts">
            <div class="silogy-penilaian-kpi__eyebrow">Semester terpilih</div>
            <div class="silogy-penilaian-kpi__caption">{{ $semesterLabel }}</div>
            <div class="silogy-penilaian-kpi__tiles">
                <div class="silogy-penilaian-kpi__tile">
                    <span class="silogy-penilaian-kpi__label">Mata kuliah</span>
                    <span class="silogy-penilaian-kpi__value">{{ number_format($jumlahMk, 0, ',', '.') }}</span>
                    @if ($mkMenunggu > 0)
                        <span class="silogy-penilaian-kpi__tile-hint">
                            {{ number_format($mkMenunggu, 0, ',', '.') }} menunggu asesmen
                        </span>
                    @endif
                </div>
                <div class="silogy-penilaian-kpi__tile">
                    <span class="silogy-penilaian-kpi__label">Kelas siap dinilai</span>
                    <span class="silogy-penilaian-kpi__value silogy-penilaian-kpi__value--gold">{{ number_format($kelasSiap, 0, ',', '.') }}</span>
                    <span class="silogy-penilaian-kpi__tile-hint">
                        dari {{ number_format($jumlahKelas, 0, ',', '.') }} kelas · {{ number_format($jumlahProdi, 0, ',', '.') }} prodi
                    </span>
                </div>
                <div class="silogy-penilaian-kpi__tile">
                    <span class="silogy-penilaian-kpi__label">Menunggu asesmen</span>
                    <span class="silogy-penilaian-kpi__value">{{ number_format($kelasMenunggu, 0, ',', '.') }}</span>
                    <span class="silogy-penilaian-kpi__tile-hint">
                        persiapan koordinator MK
                    </span>
                </div>
            </div>
        </div>

        <div class="silogy-penilaian-kpi__rule" aria-hidden="true"></div>

        <div class="silogy-penilaian-kpi__pane silogy-penilaian-kpi__pane--progress">
            <div class="silogy-penilaian-kpi__eyebrow">Progress penilaian</div>
            <div class="silogy-penilaian-kpi__caption">Kelas dengan asesmen siap</div>
            <div class="silogy-penilaian-kpi__progress">
                <div
                    class="silogy-penilaian-kpi__donut"
                    style="--pct: {{ $progress }};"
                    role="img"
                    aria-label="Progress penilaian {{ $progress }} persen"
                >
                    <span class="silogy-penilaian-kpi__donut-ring" aria-hidden="true"></span>
                    <span class="silogy-penilaian-kpi__donut-hole">
                        <span class="silogy-penilaian-kpi__donut-value">{{ $progress }}%</span>
                    </span>
                </div>
                <div class="silogy-penilaian-kpi__progress-meta">
                    <span class="silogy-penilaian-kpi__progress-title">{{ $progressCaption }}</span>
                    <span class="silogy-penilaian-kpi__progress-sub">
                        Dinilai {{ number_format($kelasDinilai, 0, ',', '.') }}
                        · Siap {{ number_format($kelasSiap, 0, ',', '.') }}
                        · Menunggu {{ number_format($kelasMenunggu, 0, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</section>
