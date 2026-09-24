<!-- ── NAVBAR ── -->
<nav class="navbar">
    <div class="nav-c">
        <a class="logo" href="{{ url('/') }}">
            <span class="logo-mark">
                <img src="{{ asset('images/logo.png') }}" alt="Universitas Siliwangi" width="42" height="42">
            </span>
            <span>
                SILOGY
                <span class="logo-sub">Siliwangi Learning Outcomes &amp; Quality Analytics</span>
            </span>
        </a>
        <div class="nav-r">
            <button class="theme-toggle" id="themeToggle" onclick="cycleTheme()" title="Ganti tema">
                <i class="bi bi-circle-half" id="themeIcon"></i>
            </button>
            @auth
                <a class="nbtn nbtn-green" href="{{ route('filament.admin.pages.dashboard') }}">
                    <i class="bi bi-grid-fill"></i> Dasbor
                </a>
            @else
                <a class="nbtn nbtn-green" href="{{ route('filament.admin.auth.login') }}">
                    <i class="bi bi-box-arrow-in-right"></i> MASUK
                </a>
            @endauth
        </div>
    </div>
</nav>
