<div class="lp-wrap" id="lp-wrap">

{{-- CDN assets --}}
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" href="{{ asset('favicon.png') }}" sizes="32x32">
<link rel="apple-touch-icon" href="{{ asset('images/favicon-48x48.png') }}">
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=Nunito:400,600,700,800,900&family=Nunito+Sans:400,600,700" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    /* ── TOKENS ── */
    :root {
        --g300: #86efac; --g400: #4ade80;
        --g500: #22c55e; --g600: #16a34a;
        --g700: #15803d; --g800: #166534; --g900: #14532d;
        --ink: #060d1a; --dark2: #0c1628;
        --bd-d: rgba(255,255,255,.09); --bd-d2: rgba(255,255,255,.14);
        --tw: #f0f6ff; --tws: #8ba3c0; --twss: #546880;

        /* Right panel (light by default) */
        --rp-bg:           #ffffff;
        --rp-text:         #182435;
        --rp-muted:        #6b7a8d;
        --rp-border:       #e8edf3;
        --rp-input-bg:     #f5f7fb;
        --rp-input-border: #e8edf3;
        --rp-input-text:   #182435;
        --rp-label:        #182435;
        --rp-eyebrow-line: linear-gradient(90deg, #e8edf3, transparent);
        --rp-back-color:   #6b7a8d;
        --rp-back-hover:   #15803d;
        --rp-sub-link:     #16a34a;
        --rp-sub-link-hover: #22c55e;
    }

    /* Auto dark */
    @media (prefers-color-scheme: dark) {
        :root:not([data-theme="light"]) {
            --rp-bg:           #0d1627;
            --rp-text:         #f0f6ff;
            --rp-muted:        #8ba3c0;
            --rp-border:       rgba(255,255,255,.1);
            --rp-input-bg:     rgba(255,255,255,.06);
            --rp-input-border: rgba(255,255,255,.12);
            --rp-input-text:   #f0f6ff;
            --rp-label:        #e2e8f0;
            --rp-eyebrow-line: linear-gradient(90deg,rgba(255,255,255,.1),transparent);
            --rp-back-color:   #8ba3c0;
            --rp-back-hover:   #4ade80;
            --rp-sub-link:     #4ade80;
            --rp-sub-link-hover: #86efac;
        }
    }
    /* Force dark */
    [data-theme="dark"] {
        --rp-bg:           #0d1627;
        --rp-text:         #f0f6ff;
        --rp-muted:        #8ba3c0;
        --rp-border:       rgba(255,255,255,.1);
        --rp-input-bg:     rgba(255,255,255,.06);
        --rp-input-border: rgba(255,255,255,.12);
        --rp-input-text:   #f0f6ff;
        --rp-label:        #e2e8f0;
        --rp-eyebrow-line: linear-gradient(90deg,rgba(255,255,255,.1),transparent);
        --rp-back-color:   #8ba3c0;
        --rp-back-hover:   #4ade80;
        --rp-sub-link:     #4ade80;
        --rp-sub-link-hover: #86efac;
    }
    /* Force light */
    [data-theme="light"] {
        --rp-bg:           #ffffff;
        --rp-text:         #182435;
        --rp-muted:        #6b7a8d;
        --rp-border:       #e8edf3;
        --rp-input-bg:     #f5f7fb;
        --rp-input-border: #e8edf3;
        --rp-input-text:   #182435;
        --rp-label:        #182435;
        --rp-eyebrow-line: linear-gradient(90deg, #e8edf3, transparent);
        --rp-back-color:   #6b7a8d;
        --rp-back-hover:   #15803d;
        --rp-sub-link:     #16a34a;
        --rp-sub-link-hover: #22c55e;
    }

    /* ── FILAMENT RESET ── */
    html, body {
        height: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: var(--rp-bg) !important;
    }
    .fi-body, .fi-simple-layout, .fi-simple-page,
    .fi-body > *, .fi-body > * > * {
        padding: 0 !important;
        max-width: none !important;
        background: transparent !important;
        min-height: unset !important;
        display: block !important;
        width: 100% !important;
        gap: 0 !important;
        margin: 0 !important;
    }

    /* ── SPLIT LAYOUT ── */
    .lp-wrap {
        position: fixed; inset: 0;
        display: grid; grid-template-columns: 1fr 1fr;
        font-family: 'Nunito Sans', 'Nunito', sans-serif;
        overflow: hidden;
    }

    /* ── LEFT PANEL (always dark) ── */
    .lp-left {
        position: relative; overflow: hidden;
        background: linear-gradient(162deg, var(--ink) 0%, var(--dark2) 50%, #091a10 100%);
        display: flex; flex-direction: column; padding: 3rem 3.5rem;
    }
    .lp-left::before {
        content: ''; position: absolute; inset: 0; pointer-events: none;
        background:
            radial-gradient(ellipse at 80% 100%, rgba(22,163,74,.22) 0%, transparent 55%),
            radial-gradient(ellipse at 20% 0%,   rgba(74,222,128,.07) 0%, transparent 45%);
    }
    .lp-left::after {
        content: ''; position: absolute; inset: 0; pointer-events: none;
        background-image:
            linear-gradient(rgba(74,222,128,.035) 1px, transparent 1px),
            linear-gradient(90deg, rgba(74,222,128,.035) 1px, transparent 1px);
        background-size: 52px 52px;
        mask-image: radial-gradient(ellipse at 40% 60%, black 20%, transparent 70%);
        -webkit-mask-image: radial-gradient(ellipse at 40% 60%, black 20%, transparent 70%);
    }
    .lp-left-inner {
        position: relative; z-index: 1;
        display: flex; flex-direction: column;
        height: 100%; justify-content: space-between;
        overflow-y: auto;
    }

    /* Logo */
    .lp-logo {
        display: flex; align-items: center; gap: .75rem;
        color: var(--tw); font-family: 'Nunito', sans-serif;
        font-weight: 900; font-size: 1.05rem; letter-spacing: -.02em;
        margin-bottom: 3.5rem; text-decoration: none;
    }
    .lp-logo-mark {
        width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0;
        display: grid; place-items: center; overflow: hidden;
        background: transparent;
    }
    .lp-logo-mark img {
        width: 100%; height: 100%; object-fit: contain; display: block;
    }
    .lp-logo-sub { display: block; font-size: .6rem; font-weight: 600; color: var(--tws); line-height: 1.2; margin-top: 2px; }

    /* Hero copy */
    .lp-eyebrow {
        display: inline-flex; align-items: center; gap: .4rem;
        font-size: .7rem; font-family: 'Nunito', sans-serif; font-weight: 900;
        text-transform: uppercase; letter-spacing: .1em; color: var(--g400);
        background: rgba(22,163,74,.12); border: 1px solid rgba(74,222,128,.25);
        border-radius: 999px; padding: .26rem .85rem; margin-bottom: 1.5rem;
    }
    .lp-h1 {
        font-family: 'Nunito', sans-serif;
        font-size: clamp(2.4rem, 3.8vw, 3.6rem); font-weight: 900;
        letter-spacing: -.045em; line-height: 1; color: var(--tw); margin-bottom: .6rem;
    }
    .lp-tagline {
        font-family: 'Nunito', sans-serif;
        font-size: clamp(.85rem, 1.1vw, 1rem); font-weight: 700;
        background: linear-gradient(100deg, var(--g300), var(--g400));
        -webkit-background-clip: text; background-clip: text; color: transparent;
        margin-bottom: 1.4rem;
    }
    .lp-desc { color: var(--tws); font-size: .9rem; line-height: 1.82; max-width: 360px; margin-bottom: 2.2rem; }

    /* Benefits */
    .lp-benefits { display: flex; flex-direction: column; gap: .65rem; margin-bottom: auto; }
    .lp-bi {
        display: flex; align-items: flex-start; gap: .75rem;
        background: rgba(255,255,255,.04); border: 1px solid var(--bd-d);
        border-radius: 12px; padding: .85rem 1rem;
    }
    .lp-bi-icon {
        width: 30px; height: 30px; border-radius: 8px; flex-shrink: 0;
        background: rgba(22,163,74,.15); border: 1px solid rgba(74,222,128,.2);
        display: grid; place-items: center; color: var(--g400); font-size: .85rem;
    }
    .lp-bi-text { font-size: .84rem; color: var(--tw); line-height: 1.55; }
    .lp-bi-text span { color: var(--tws); display: block; font-size: .78rem; margin-top: .1rem; }

    /* Left footer */
    .lp-left-foot {
        margin-top: 2.5rem; padding-top: 1.5rem;
        border-top: 1px solid var(--bd-d);
        color: var(--twss); font-size: .77rem; line-height: 1.65;
    }
    .lp-left-foot a { color: var(--g500); transition: .14s; }
    .lp-left-foot a:hover { color: var(--g400); }

    /* ── RIGHT PANEL ── */
    .lp-right {
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        padding: 3rem 2.5rem;
        background: var(--rp-bg);
        overflow-y: auto;
        transition: background .2s;
    }
    .lp-form-box { width: 100%; max-width: 380px; }

    /* Top bar: back + theme toggle */
    .lp-top-bar {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 2rem;
    }
    .lp-back {
        display: inline-flex; align-items: center; gap: .4rem;
        font-size: .8rem; font-weight: 700; color: var(--rp-back-color);
        text-decoration: none; transition: color .14s;
    }
    .lp-back:hover { color: var(--rp-back-hover); }
    .lp-theme-btn {
        width: 34px; height: 34px; border-radius: 9px;
        display: grid; place-items: center; cursor: pointer;
        border: 1px solid var(--rp-border);
        background: transparent; color: var(--rp-muted); font-size: .95rem;
        transition: all .18s ease;
    }
    .lp-theme-btn:hover { color: var(--rp-text); background: rgba(128,128,128,.06); }

    /* Form heading */
    .lp-form-eyebrow {
        font-size: .7rem; font-family: 'Nunito', sans-serif; font-weight: 900;
        text-transform: uppercase; letter-spacing: .12em;
        color: var(--g700); margin-bottom: 1.1rem;
        display: flex; align-items: center; gap: .5rem;
    }
    .lp-form-eyebrow::after {
        content: ''; flex: 1; height: 1px;
        background: var(--rp-eyebrow-line);
    }
    .lp-form-h {
        font-family: 'Nunito', sans-serif;
        font-size: 1.65rem; font-weight: 900; letter-spacing: -.03em;
        color: var(--rp-text); margin-bottom: .35rem; transition: color .2s;
    }
    .lp-form-sub { color: var(--rp-muted); font-size: .87rem; line-height: 1.6; margin-bottom: 1.8rem; transition: color .2s; }

    /* Form fields */
    .lp-field { margin-bottom: 1.1rem; }
    .lp-label {
        display: block; font-family: 'Nunito', sans-serif;
        font-size: .8rem; font-weight: 800; color: var(--rp-label);
        letter-spacing: .02em; margin-bottom: .45rem; transition: color .2s;
    }
    .lp-input {
        width: 100%; height: 46px;
        background: var(--rp-input-bg); border: 1.5px solid var(--rp-input-border);
        border-radius: 11px; padding: 0 1rem;
        font-family: 'Nunito Sans', sans-serif; font-size: .93rem;
        color: var(--rp-input-text); outline: none;
        transition: border-color .16s, box-shadow .16s, background .16s, color .2s;
        box-sizing: border-box;
    }
    .lp-input:focus { background: var(--rp-bg); border-color: var(--g600); box-shadow: 0 0 0 3px rgba(22,163,74,.12); }
    .lp-input.is-invalid { border-color: #ef4444; }
    .lp-input.is-invalid:focus { box-shadow: 0 0 0 3px rgba(239,68,68,.1); }
    .lp-input::placeholder { color: var(--rp-muted); opacity: .6; }
    .lp-invalid { color: #dc2626; font-size: .8rem; margin-top: .35rem; font-weight: 700; }

    /* Password toggle */
    .lp-pw-wrap { position: relative; }
    .lp-pw-toggle {
        position: absolute; right: 1rem; top: 50%; transform: translateY(-50%);
        background: none; border: none; cursor: pointer;
        color: var(--rp-muted); font-size: 1rem; padding: 0; line-height: 1;
        transition: color .14s;
    }
    .lp-pw-toggle:hover { color: var(--g700); }

    /* Submit button */
    .lp-btn {
        display: flex; align-items: center; justify-content: center; gap: .5rem;
        width: 100%; height: 48px; border: none; border-radius: 12px;
        font-family: 'Nunito', sans-serif; font-weight: 900; font-size: .97rem;
        background: linear-gradient(135deg, var(--g700) 0%, var(--g500) 100%);
        color: #fff; cursor: pointer; margin-top: 1.5rem;
        box-shadow: 0 8px 28px rgba(22,163,74,.4), inset 0 1px 0 rgba(255,255,255,.18);
        transition: all .18s ease;
    }
    .lp-btn:hover:not(:disabled) { filter: brightness(1.1); transform: translateY(-2px); box-shadow: 0 14px 36px rgba(22,163,74,.5); }
    .lp-btn:active { transform: translateY(0); }
    .lp-btn:disabled { opacity: .7; cursor: not-allowed; transform: none; }

    /* Form footer */
    .lp-form-foot { margin-top: 1.6rem; text-align: center; color: var(--rp-muted); font-size: .78rem; line-height: 1.7; }
    .lp-form-foot a { color: var(--rp-sub-link); font-weight: 700; transition: .14s; }
    .lp-form-foot a:hover { color: var(--rp-sub-link-hover); }

    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── RESPONSIVE ── */
    @media (max-width: 860px) {
        .lp-wrap { position: relative; display: block; overflow: visible; }
        .lp-left { padding: 2rem 2.2rem; min-height: auto; }
        .lp-left-inner { height: auto; }
        .lp-logo { margin-bottom: 2rem; }
        .lp-h1 { font-size: 2.2rem; }
        .lp-desc { max-width: 100%; }
        .lp-benefits { display: none; }
        .lp-left-foot { margin-top: 1.5rem; }
        .lp-right { min-height: 100vh; padding: 2.2rem; }
    }
    @media (max-width: 480px) {
        .lp-left { padding: 1.75rem; }
        .lp-right { padding: 1.75rem 1.25rem; }
    }
</style>

{{-- ── LEFT PANEL ── --}}
<div class="lp-left">
    <div class="lp-left-inner">

        <a href="{{ url('/') }}" class="lp-logo">
            <span class="lp-logo-mark">
                <img src="{{ asset('images/logo.png') }}" alt="Universitas Siliwangi" width="40" height="40">
            </span>
            <span>
                SILOGY
                <span class="lp-logo-sub">Siliwangi Learning Outcomes &amp; Quality Analytics</span>
            </span>
        </a>

        <div>
            <span class="lp-eyebrow"><i class="bi bi-patch-check-fill"></i> Analytics-Driven OBE Platform</span>
            <h1 class="lp-h1">SILOGY</h1>
            <p class="lp-tagline">From learning data to academic quality.</p>
            <p class="lp-desc">
                Paradigma mutu pembelajaran berbasis <em>Outcome-Based Education</em>
                yang menjadikan data capaian sebagai dasar setiap keputusan akademik.
            </p>

            <div class="lp-benefits">
                <div class="lp-bi">
                    <span class="lp-bi-icon"><i class="bi bi-rulers"></i></span>
                    <div class="lp-bi-text">
                        Measurement
                        <span>Pengukuran terstruktur ketercapaian CPMK, Sub-CPMK, dan CPL</span>
                    </div>
                </div>
                <div class="lp-bi">
                    <span class="lp-bi-icon"><i class="bi bi-graph-up-arrow"></i></span>
                    <div class="lp-bi-text">
                        Analytics
                        <span>Analisis pola, tren, dan kesenjangan mutu capaian pembelajaran</span>
                    </div>
                </div>
                <div class="lp-bi">
                    <span class="lp-bi-icon"><i class="bi bi-arrow-clockwise"></i></span>
                    <div class="lp-bi-text">
                        Continuous Improvement
                        <span>Perbaikan pembelajaran, asesmen, dan kurikulum berbasis bukti</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="lp-left-foot">
            &copy; {{ date('Y') }} SILOGY &mdash; Universitas Siliwangi<br>
            Lembaga Penjaminan Mutu dan Pengembangan Pembelajaran &middot;
            <a href="https://lpmpp.unsil.ac.id" target="_blank">lpmpp.unsil.ac.id</a>
        </div>
    </div>
</div>

{{-- ── RIGHT PANEL ── --}}
<div class="lp-right">
    <div class="lp-form-box">

        <div class="lp-top-bar">
            <a href="{{ url('/') }}" class="lp-back">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
            <button class="lp-theme-btn" id="lpThemeBtn" onclick="loginCycleTheme()" title="Ganti tema">
                <i class="bi bi-circle-half" id="lpThemeIcon"></i>
            </button>
        </div>

        <p class="lp-form-eyebrow"><i class="bi bi-lock-fill"></i> Akses Sistem</p>
        <h2 class="lp-form-h">Selamat datang kembali</h2>
        <p class="lp-form-sub">
            Masuk dengan akun yang telah terdaftar untuk mengakses
            dashboard pengelolaan mutu akademik SILOGY.
        </p>

        <form wire:submit="authenticate" autocomplete="on">

            <div class="lp-field">
                <label for="lp-login" class="lp-label">Username atau Email</label>
                <input
                    id="lp-login"
                    wire:model="data.login"
                    type="text"
                    class="lp-input {{ $errors->has('data.login') ? 'is-invalid' : '' }}"
                    placeholder="Username atau alamat email"
                    required
                    autocomplete="username"
                    autofocus
                >
                @error('data.login')
                    <div class="lp-invalid"><i class="bi bi-exclamation-circle"></i> {{ $message }}</div>
                @enderror
            </div>

            <div class="lp-field">
                <label for="lp-password" class="lp-label">Password</label>
                <div class="lp-pw-wrap">
                    <input
                        id="lp-password"
                        wire:model="data.password"
                        type="password"
                        class="lp-input {{ $errors->has('data.password') ? 'is-invalid' : '' }}"
                        placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                        required
                        autocomplete="current-password"
                        style="padding-right:2.8rem;"
                    >
                    <button type="button" class="lp-pw-toggle" onclick="lpTogglePw()" aria-label="Tampilkan/sembunyikan password">
                        <i class="bi bi-eye" id="lpPwIcon"></i>
                    </button>
                </div>
                @error('data.password')
                    <div class="lp-invalid"><i class="bi bi-exclamation-circle"></i> {{ $message }}</div>
                @enderror
            </div>

            <button
                type="submit"
                class="lp-btn"
                wire:loading.attr="disabled"
                wire:target="authenticate"
            >
                <span wire:loading.remove wire:target="authenticate">
                    <i class="bi bi-door-open"></i> Masuk ke SILOGY
                </span>
                <span wire:loading wire:target="authenticate">
                    <i class="bi bi-hourglass-split" style="animation:spin .8s linear infinite;"></i> Memproses...
                </span>
            </button>

        </form>

        <div class="lp-form-foot">
            Lupa password?
            <a href="{{ route('filament.admin.auth.password-reset.request') }}">Atur ulang di sini</a>
            <br>
            Kendala akun? Hubungi
            <a href="https://lpmpp.unsil.ac.id" target="_blank">tim LPMPP Unsil</a>
        </div>
    </div>
</div>

<script>
    const LP_ICONS = { dark: 'bi-moon-stars-fill', light: 'bi-sun-fill', auto: 'bi-circle-half' };

    function lpApplyTheme(theme) {
        const root = document.documentElement;
        if (!theme || theme === 'auto') {
            root.removeAttribute('data-theme');
        } else {
            root.setAttribute('data-theme', theme);
        }
        const icon = document.getElementById('lpThemeIcon');
        if (icon) icon.className = 'bi ' + LP_ICONS[theme || 'auto'];
    }

    function loginCycleTheme() {
        const current = localStorage.getItem('silogy-theme') || 'auto';
        const order   = ['auto', 'dark', 'light'];
        const next    = order[(order.indexOf(current) + 1) % order.length];
        localStorage.setItem('silogy-theme', next);
        lpApplyTheme(next);
    }

    (function () {
        const stored = localStorage.getItem('silogy-theme') || 'auto';
        lpApplyTheme(stored);
    })();

    function lpTogglePw() {
        const pw = document.getElementById('lp-password');
        const ic = document.getElementById('lpPwIcon');
        if (pw.type === 'password') {
            pw.type = 'text';
            ic.className = 'bi bi-eye-slash';
        } else {
            pw.type = 'password';
            ic.className = 'bi bi-eye';
        }
    }
</script>

</div>
