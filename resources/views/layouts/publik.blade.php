<!DOCTYPE html>
<html lang="id" id="html-root">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('deskripsi', 'SILOGY — Siliwangi Learning Outcomes &amp; Quality Analytics; platform pengelolaan mutu akademik berbasis OBE untuk Universitas Siliwangi.')">
    <title>@yield('judul', 'SILOGY — Siliwangi Learning Outcomes & Quality Analytics')</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('images/favicon-48x48.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito:400,500,600,700,800,900&family=Nunito+Sans:400,600,700" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @include('layouts.partials.gaya-publik')
    @stack('gaya')
</head>
<body>

@include('layouts.partials.navbar-publik')

@yield('konten')

@include('layouts.partials.footer-publik')

<script>
    /* ── THEME SYSTEM ── */
    const ICONS = { dark: 'bi-moon-stars-fill', light: 'bi-sun-fill', auto: 'bi-circle-half' };
    const LABELS = { dark: 'Mode Gelap', light: 'Mode Terang', auto: 'Otomatis (Sistem)' };

    function applyTheme(theme) {
        const root = document.documentElement;
        if (!theme || theme === 'auto') {
            root.removeAttribute('data-theme');
        } else {
            root.setAttribute('data-theme', theme);
        }
        const icon = document.getElementById('themeIcon');
        const btn  = document.getElementById('themeToggle');
        const key  = theme || 'auto';
        if (icon) icon.className = 'bi ' + ICONS[key];
        if (btn)  btn.title = LABELS[key];
    }

    function cycleTheme() {
        const current = localStorage.getItem('silogy-theme') || 'auto';
        const order   = ['auto', 'dark', 'light'];
        const next    = order[(order.indexOf(current) + 1) % order.length];
        localStorage.setItem('silogy-theme', next);
        applyTheme(next);
    }

    /* Init on load */
    (function () {
        const stored = localStorage.getItem('silogy-theme') || 'auto';
        applyTheme(stored);
    })();
</script>
@stack('skrip')
</body>
</html>
