@extends('layouts.publik')

@section('judul', 'SILOGY — Siliwangi Learning Outcomes & Quality Analytics')

@section('konten')
<!-- ── HERO ── -->
<section class="hero">
    <div class="hero-c">
        <span class="hero-pill"><i class="bi bi-patch-check-fill"></i> Analytics-Driven OBE Platform</span>
        <h1 class="hero-brand">SILOGY</h1>
        <p class="hero-name">Siliwangi Learning Outcomes &amp; Quality Analytics</p>
        <p class="hero-desc">
            Paradigma pengelolaan mutu pembelajaran Universitas Siliwangi berbasis
            Outcome-Based Education (OBE) yang menjadikan data capaian pembelajaran
            sebagai dasar pengambilan keputusan akademik dan peningkatan mutu berkelanjutan.
        </p>
        <div class="hero-acts">
            @auth
                <a class="hbtn-p" href="{{ route('filament.admin.pages.dashboard') }}">
                    <i class="bi bi-grid-fill"></i> Buka Dasbor
                </a>
            @else
                <a class="hbtn-p" href="{{ route('filament.admin.auth.login') }}">
                    <i class="bi bi-door-open"></i> MASUK
                </a>
            @endauth
            <a class="tbtn-g" href="#why"><i class="bi bi-arrow-down"></i> Pelajari Lebih Lanjut</a>
        </div>
        <div class="hero-tabs">
            <a class="hero-tab on" href="#why"><i class="bi bi-lightbulb"></i> Mengapa SILOGY?</a>
            <a class="hero-tab" href="#pillars"><i class="bi bi-columns-gap"></i> Tiga Pilar</a>
            <a class="hero-tab" href="#ecosystem"><i class="bi bi-diagram-3"></i> Ekosistem</a>
            <a class="hero-tab" href="#impact"><i class="bi bi-trophy"></i> Dampak &amp; Target</a>
        </div>
        <p class="hero-scroll-hint">Scroll untuk menjelajahi</p>
    </div>
</section>

<!-- ── WHY ── -->
<section class="sec why-bg" id="why">
    <div class="sec-c">
        <div class="why-grid">
            <div>
                <span class="eyebrow"><i class="bi bi-lightbulb-fill"></i> Mengapa SILOGY?</span>
                <h2 class="sh">Mutu yang Didorong oleh Bukti,<br>Bukan Sekadar Kepatuhan</h2>
                <p class="body-text">
                    Mutu pendidikan tinggi saat ini ditentukan oleh bukti ketercapaian hasil belajar,
                    bukan sekadar kepatuhan administratif. SILOGY hadir untuk memastikan bahwa setiap
                    proses pembelajaran benar-benar menghasilkan kompetensi lulusan yang terukur,
                    relevan, dan berdaya saing.
                </p>
                <p class="culture-label">SILOGY mendorong budaya mutu yang:</p>
                <div class="culture-list">
                    <div class="ci">
                        <span class="ci-num">01</span>
                        <p>Fokus pada <strong>capaian pembelajaran mahasiswa</strong> sebagai pusat orientasi</p>
                    </div>
                    <div class="ci">
                        <span class="ci-num">02</span>
                        <p>Berbasis <strong>data dan analitik</strong> yang sahih dan dapat diverifikasi</p>
                    </div>
                    <div class="ci">
                        <span class="ci-num">03</span>
                        <p>Berorientasi pada <strong>perbaikan berkelanjutan (CQI)</strong> di setiap siklus akademik</p>
                    </div>
                </div>
            </div>
            <div class="why-aside" style="position:relative;">
                <div class="aside-qmark">&ldquo;</div>
                <p class="aside-quote">Setiap keputusan akademik harus berakar pada data capaian yang nyata. SILOGY hadir sebagai fondasi paradigma tersebut.</p>
                <div class="aside-tags">
                    <div class="aside-tag">
                        <span class="aside-tag-icon"><i class="bi bi-bar-chart-fill"></i></span>
                        <div>
                            <div class="aside-tag-text">Data-Driven Decision</div>
                            <div class="aside-tag-sub">Keputusan akademik berbasis data capaian</div>
                        </div>
                    </div>
                    <div class="aside-tag">
                        <span class="aside-tag-icon"><i class="bi bi-arrow-repeat"></i></span>
                        <div>
                            <div class="aside-tag-text">Continuous Quality Improvement</div>
                            <div class="aside-tag-sub">Perbaikan berkelanjutan setiap siklus</div>
                        </div>
                    </div>
                    <div class="aside-tag">
                        <span class="aside-tag-icon"><i class="bi bi-bullseye"></i></span>
                        <div>
                            <div class="aside-tag-text">Outcome-Based Education</div>
                            <div class="aside-tag-sub">Kompetensi lulusan yang terukur</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="divider"></div>

<!-- ── PILLARS ── -->
<section class="sec pillars-bg" id="pillars">
    <div class="sec-c">
        <div class="hd hd-center">
            <span class="eyebrow"><i class="bi bi-columns-gap"></i> Tiga Pilar Utama</span>
            <h2 class="sh">SILOGY Dibangun atas Tiga Pilar</h2>
            <p class="sp">Setiap pilar mewakili tahapan sistematis dalam siklus penjaminan mutu berbasis capaian pembelajaran.</p>
        </div>
        <div class="pillars-grid">
            <div class="pillar p1">
                <div class="pillar-seq">Pilar 01</div>
                <div class="pillar-ico"><i class="bi bi-rulers"></i></div>
                <div class="pillar-name">Measurement</div>
                <p class="pillar-desc">Pengukuran terstruktur ketercapaian CPMK, Sub-CPMK, dan CPL dari setiap mata kuliah secara konsisten dan terdokumentasi.</p>
            </div>
            <div class="pillar p2">
                <div class="pillar-seq">Pilar 02</div>
                <div class="pillar-ico"><i class="bi bi-graph-up-arrow"></i></div>
                <div class="pillar-name">Analytics</div>
                <p class="pillar-desc">Analisis data capaian pembelajaran untuk mengidentifikasi pola, tren, dan kesenjangan mutu — menghasilkan insight yang dapat ditindaklanjuti.</p>
            </div>
            <div class="pillar p3">
                <div class="pillar-seq">Pilar 03</div>
                <div class="pillar-ico"><i class="bi bi-arrow-clockwise"></i></div>
                <div class="pillar-name">Improvement</div>
                <p class="pillar-desc">Pemanfaatan hasil analisis untuk perbaikan berkelanjutan pada proses pembelajaran, instrumen asesmen, dan desain kurikulum.</p>
            </div>
        </div>
    </div>
</section>

<div class="divider"></div>

<!-- ── ECOSYSTEM ── -->
<section class="sec eco-bg" id="ecosystem">
    <div class="sec-c">
        <div class="hd">
            <span class="eyebrow"><i class="bi bi-diagram-3-fill"></i> Ekosistem Akademik</span>
            <h2 class="sh">SILOGY sebagai Landasan Filosofis dan Paradigma Akademik</h2>
            <p class="sp">
                SILOGY berperan sebagai landasan filosofis dan paradigma akademik yang berjalan
                berdampingan dengan sistem penjaminan mutu Universitas Siliwangi.
            </p>
        </div>
        <div class="eco-grid">
            <a class="eco-card" href="https://silaris.unsil.ac.id/" target="_blank" rel="noopener">
                <span class="eco-ico"><i class="bi bi-shield-check"></i></span>
                <div style="flex:1;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap;">
                        <div class="eco-name">SILARIS</div>
                        <span style="font-size:.74rem;font-family:'Nunito',sans-serif;font-weight:800;color:var(--g700);display:inline-flex;align-items:center;gap:.3rem;">
                            silaris.unsil.ac.id <i class="bi bi-arrow-up-right"></i>
                        </span>
                    </div>
                    <div class="eco-full">Siliwangi Learning and Quality Assurance System</div>
                    <p class="eco-desc">
                        Sistem penjaminan mutu pembelajaran yang mencakup pengelolaan standar, audit internal,
                        dan dokumentasi proses mutu akademik yang menjadi wujud operasional paradigma SILOGY.
                    </p>
                </div>
            </a>
        </div>
        <div class="eco-motto">
            <div class="eco-motto-text">
                <strong>Satu Paradigma &middot; Satu Data Capaian &middot; Satu Visi Peningkatan Mutu</strong>
                <span>
                    Melalui sinergi ini, pengelolaan mutu akademik di Universitas Siliwangi didasarkan pada
                    satu paradigma, satu data capaian pembelajaran, dan satu visi peningkatan mutu berkelanjutan.
                </span>
            </div>
            <div class="eco-chips">
                <span class="eco-chip"><i class="bi bi-link-45deg"></i> Terintegrasi</span>
                <span class="eco-chip"><i class="bi bi-database-fill-check"></i> Satu Data</span>
                <span class="eco-chip"><i class="bi bi-arrow-repeat"></i> CQI</span>
            </div>
        </div>
    </div>
</section>

<div class="divider"></div>

<!-- ── IMPACT ── -->

<section class="sec impact-bg" id="impact">
    <div class="sec-c">
        <div class="impact-grid">
            <div>
                <span class="eyebrow"><i class="bi bi-trophy-fill"></i> Dampak &amp; Target</span>
                <h2 class="sh">Melalui SILOGY, Universitas Siliwangi Menargetkan</h2>
                <div class="impact-list">
                    <div class="ii">
                        <span class="ii-ico"><i class="bi bi-check2-all"></i></span>
                        <p>Peningkatan <strong>kualitas pembelajaran berbasis bukti</strong> yang terukur dan dapat dipertanggungjawabkan</p>
                    </div>
                    <div class="ii">
                        <span class="ii-ico"><i class="bi bi-arrow-left-right"></i></span>
                        <p>Konsistensi <strong>implementasi OBE lintas program studi</strong> di seluruh fakultas</p>
                    </div>
                    <div class="ii">
                        <span class="ii-ico"><i class="bi bi-bullseye"></i></span>
                        <p>Keputusan akademik yang <strong>objektif dan terukur</strong> berdasarkan data capaian nyata</p>
                    </div>
                    <div class="ii">
                        <span class="ii-ico"><i class="bi bi-building-fill-check"></i></span>
                        <p>Penguatan <strong>budaya mutu akademik institusi</strong> yang berkelanjutan dan mengakar</p>
                    </div>
                </div>
            </div>
            <div class="cta-c" style="position:relative;">
                <div class="cta-c-ew">Mulai Sekarang</div>
                <h3>Siap Memulai?</h3>
                <p>Bergabunglah dengan sistem penjaminan mutu pendidikan yang terintegrasi dan modern bersama SILOGY.</p>
                @auth
                    <a class="cta-c-btn" href="{{ route('filament.admin.pages.dashboard') }}">
                        <i class="bi bi-grid-fill"></i> Buka Dasbor
                    </a>
                @else
                    <a class="cta-c-btn" href="{{ route('filament.admin.auth.login') }}">
                        <i class="bi bi-door-open"></i> Masuk ke Sistem
                    </a>
                @endauth
                <p class="cta-c-note">Butuh bantuan? Hubungi tim IT Universitas Siliwangi.</p>
            </div>
        </div>
    </div>
</section>
@endsection

@push('skrip')
<script>
    /* ── HERO TABS ── */
    document.querySelectorAll('.hero-tab').forEach(tab => {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.hero-tab').forEach(t => t.classList.remove('on'));
            this.classList.add('on');
        });
    });
</script>
@endpush
