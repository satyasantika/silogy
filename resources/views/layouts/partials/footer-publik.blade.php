<!-- ── FOOTER ── -->
<footer class="footer">
    <div class="footer-c">
        <div class="fb">
            <a class="logo" href="{{ url('/') }}" style="display:inline-flex;">
                <span class="logo-mark" style="width:36px;height:36px;border-radius:8px;">
                    <img src="{{ asset('images/logo.png') }}" alt="Universitas Siliwangi" width="36" height="36">
                </span>
                <span style="font-family:'Nunito',sans-serif;font-size:1.1rem;">SILOGY</span>
            </a>
            <p>Siliwangi Learning Outcomes &amp; Quality Analytics &mdash; Universitas Siliwangi.</p>
            <div class="fb-line"><i class="bi bi-building"></i> Lembaga Penjaminan Mutu dan Pengembangan Pembelajaran</div>
        </div>
        <div class="fc">
            <h4>Fitur</h4>
            <nav class="fl">
                <a href="{{ route('filament.admin.pages.dashboard') }}">Dashboard Analitik</a>
                <a href="{{ route('panduan.peran', ['peran' => 'koordinator-mk']) }}">Pemetaan CPL&ndash;CPMK</a>
                <a href="{{ route('panduan.peran', ['peran' => 'tim-kurikulum']) }}">Manajemen Kurikulum</a>
                <a href="{{ route('panduan.peran', ['peran' => 'dosen-pengampu']) }}">Pengisian Nilai</a>
                <a href="{{ route('panduan.peran', ['peran' => 'pimpinan']) }}">Laporan Mutu</a>
            </nav>
        </div>
        <div class="fc">
            <h4>Panduan Peran</h4>
            <nav class="fl">
                <a href="{{ route('panduan.indeks') }}">Semua panduan</a>
                <a href="{{ route('panduan.peran', ['peran' => 'admin-unit']) }}">Admin Unit</a>
                <a href="{{ route('panduan.peran', ['peran' => 'auditor-mutu']) }}">Auditor Mutu</a>
                <a href="{{ route('panduan.peran', ['peran' => 'alur-end-to-end']) }}">Alur end-to-end</a>
            </nav>
        </div>
        <div class="fc">
            <h4>Kontak</h4>
            <ul class="fct">
                <li><i class="bi bi-building"></i> Universitas Siliwangi</li>
                <li><i class="bi bi-geo-alt-fill"></i> Tasikmalaya, Jawa Barat</li>
                <li><i class="bi bi-envelope-fill"></i> helpdesk@unsil.ac.id</li>
                <li><i class="bi bi-globe"></i>
                    <a href="https://lpmpp.unsil.ac.id" target="_blank" style="color:var(--g500);">lpmpp.unsil.ac.id</a>
                </li>
            </ul>
        </div>
    </div>
    <div class="footer-bot">
        <span>&copy; {{ date('Y') }} SILOGY. All rights reserved. LPMPP Universitas Siliwangi</span>
        <a href="https://lpmpp.unsil.ac.id" target="_blank"><i class="bi bi-globe"></i> lpmpp.unsil.ac.id</a>
    </div>
</footer>
