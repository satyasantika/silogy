{{-- Bento KPI roster peserta kelas: semester terpilih vs semua semester. --}}
@php
    $semesterLabel = $semester_label ?? null;
    $semesterKelas = (int) ($semester_kelas ?? 0);
    $semesterMahasiswa = (int) ($semester_mahasiswa ?? 0);
    $semuaKelas = (int) ($semua_kelas ?? 0);
    $semuaMahasiswa = (int) ($semua_mahasiswa ?? 0);
    $tampilSemester = (bool) ($tampil_semester ?? false);
@endphp

<section class="silogy-peserta-kpi" aria-label="Ringkasan kelas dan mahasiswa">
    <div class="silogy-peserta-kpi__bento{{ $tampilSemester ? '' : ' silogy-peserta-kpi__bento--solo' }}" role="group">
        @if ($tampilSemester)
            <div class="silogy-peserta-kpi__pane silogy-peserta-kpi__pane--semester">
                <div class="silogy-peserta-kpi__eyebrow">Semester terpilih</div>
                <div class="silogy-peserta-kpi__caption">{{ $semesterLabel ?: '—' }}</div>
                <div class="silogy-peserta-kpi__tiles">
                    <div class="silogy-peserta-kpi__tile">
                        <span class="silogy-peserta-kpi__label">Total kelas</span>
                        <span class="silogy-peserta-kpi__value">{{ number_format($semesterKelas, 0, ',', '.') }}</span>
                    </div>
                    <div class="silogy-peserta-kpi__tile">
                        <span class="silogy-peserta-kpi__label">Mahasiswa</span>
                        <span class="silogy-peserta-kpi__value">{{ number_format($semesterMahasiswa, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>

            <div class="silogy-peserta-kpi__rule" aria-hidden="true"></div>
        @endif

        <div class="silogy-peserta-kpi__pane silogy-peserta-kpi__pane--semua">
            <div class="silogy-peserta-kpi__eyebrow">Semua semester</div>
            <div class="silogy-peserta-kpi__caption">Akumulasi kontrak pada mata kuliah ini</div>
            <div class="silogy-peserta-kpi__tiles">
                <div class="silogy-peserta-kpi__tile">
                    <span class="silogy-peserta-kpi__label">Total kelas</span>
                    <span class="silogy-peserta-kpi__value silogy-peserta-kpi__value--gold">{{ number_format($semuaKelas, 0, ',', '.') }}</span>
                </div>
                <div class="silogy-peserta-kpi__tile">
                    <span class="silogy-peserta-kpi__label">Mahasiswa</span>
                    <span class="silogy-peserta-kpi__value silogy-peserta-kpi__value--gold">{{ number_format($semuaMahasiswa, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>
</section>
