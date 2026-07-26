<!DOCTYPE html>
<html lang="id" id="err-root">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('code') | @yield('status') — SILOGY</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('images/favicon-48x48.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito:400,600,700,800,900&family=Nunito+Sans:400,600,700" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --g300: #86efac; --g400: #4ade80;
            --g500: #22c55e; --g600: #16a34a; --g700: #15803d;
            --ink: #060d1a; --dark2: #0c1628;
            --tw: #f0f6ff; --tws: #8ba3c0; --twss: #546880;
            --bd-d: rgba(255,255,255,.09);
            --card-bg: rgba(255,255,255,.045);
            --card-border: rgba(255,255,255,.1);
            --warn: #fbbf24; --danger: #f87171;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            min-height: 100%;
            font-family: 'Nunito Sans', 'Nunito', sans-serif;
            background: linear-gradient(162deg, var(--ink) 0%, var(--dark2) 52%, #091a10 100%);
            color: var(--tw);
            -webkit-font-smoothing: antialiased;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(ellipse at 85% 95%, rgba(22,163,74,.2) 0%, transparent 55%),
                radial-gradient(ellipse at 10% 5%, rgba(74,222,128,.06) 0%, transparent 45%);
        }

        body::after {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            background-image:
                linear-gradient(rgba(74,222,128,.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(74,222,128,.03) 1px, transparent 1px);
            background-size: 52px 52px;
            mask-image: radial-gradient(ellipse at 50% 40%, black 15%, transparent 72%);
            -webkit-mask-image: radial-gradient(ellipse at 50% 40%, black 15%, transparent 72%);
        }

        .err-shell {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.25rem 2.5rem;
        }

        .err-logo {
            display: inline-flex;
            align-items: center;
            gap: .7rem;
            margin-bottom: 2rem;
            color: var(--tw);
            font-family: 'Nunito', sans-serif;
            font-weight: 900;
            font-size: 1rem;
            letter-spacing: -.02em;
            text-decoration: none;
        }

        .err-logo-mark {
            width: 38px;
            height: 38px;
            border-radius: 9px;
            display: grid;
            place-items: center;
            overflow: hidden;
            background: transparent;
        }
        .err-logo-mark img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .err-card {
            width: 100%;
            max-width: 560px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 22px;
            padding: 2.2rem 2rem;
            backdrop-filter: blur(10px);
            box-shadow: 0 24px 60px rgba(0,0,0,.35);
        }

        .err-icon-wrap {
            width: 72px;
            height: 72px;
            border-radius: 18px;
            display: grid;
            place-items: center;
            font-size: 2rem;
            margin-bottom: 1.4rem;
            border: 1px solid rgba(255,255,255,.12);
            background: rgba(255,255,255,.04);
        }

        .err-code-line {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: .55rem;
            margin-bottom: .85rem;
        }

        .err-code {
            font-family: 'Nunito', sans-serif;
            font-size: clamp(2.6rem, 8vw, 3.4rem);
            font-weight: 900;
            letter-spacing: -.04em;
            line-height: 1;
            color: var(--tw);
        }

        .err-sep {
            color: var(--twss);
            font-weight: 700;
            font-size: 1.5rem;
        }

        .err-status {
            font-family: 'Nunito', sans-serif;
            font-size: clamp(1rem, 3vw, 1.25rem);
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .err-title {
            font-family: 'Nunito', sans-serif;
            font-size: 1.35rem;
            font-weight: 900;
            letter-spacing: -.02em;
            margin-bottom: .65rem;
        }

        .err-message {
            color: var(--tws);
            font-size: .95rem;
            line-height: 1.75;
            margin-bottom: 1.75rem;
        }

        .err-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .65rem;
        }

        .err-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            min-height: 44px;
            padding: 0 1.05rem;
            border-radius: 11px;
            border: 1px solid transparent;
            font-family: 'Nunito', sans-serif;
            font-size: .88rem;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            transition: transform .16s ease, filter .16s ease, background .16s ease, border-color .16s ease;
        }

        .err-btn:hover { transform: translateY(-1px); filter: brightness(1.06); }
        .err-btn:active { transform: translateY(0); }

        .err-btn-ghost {
            background: rgba(255,255,255,.06);
            border-color: rgba(255,255,255,.12);
            color: var(--tw);
        }

        .err-btn-ghost:hover { background: rgba(255,255,255,.1); }

        .err-btn-primary {
            background: linear-gradient(135deg, var(--g700) 0%, var(--g500) 100%);
            color: #fff;
            box-shadow: 0 8px 24px rgba(22,163,74,.35);
        }

        .err-btn-danger {
            background: rgba(248,113,113,.12);
            border-color: rgba(248,113,113,.35);
            color: #fecaca;
        }

        .err-btn-danger:hover { background: rgba(248,113,113,.2); }

        .err-foot {
            margin-top: 1.6rem;
            text-align: center;
            color: var(--twss);
            font-size: .78rem;
        }

        @media (max-width: 520px) {
            .err-card { padding: 1.75rem 1.35rem; }
            .err-actions { flex-direction: column; }
            .err-btn { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="err-shell">
        <a href="{{ url('/') }}" class="err-logo">
            <span class="err-logo-mark">
                <img src="{{ asset('images/logo.png') }}" alt="Universitas Siliwangi" width="38" height="38">
            </span>
            <span>SILOGY</span>
        </a>

        <main class="err-card" role="alert" aria-live="polite">
            <div class="err-icon-wrap" style="color: @yield('accent', '#4ade80')">
                <i class="bi @yield('icon')"></i>
            </div>

            <div class="err-code-line">
                <span class="err-code">@yield('code')</span>
                <span class="err-sep">|</span>
                <span class="err-status" style="color: @yield('accent', '#4ade80')">@yield('status')</span>
            </div>

            <h1 class="err-title">@yield('title')</h1>
            <p class="err-message">@yield('message')</p>

            <div class="err-actions">
                <button type="button" class="err-btn err-btn-ghost" onclick="errGoBack()">
                    <i class="bi bi-arrow-left"></i>
                    Kembali
                </button>

                <a href="{{ url('/dashboard') }}" class="err-btn err-btn-primary">
                    <i class="bi bi-speedometer2"></i>
                    Dashboard
                </a>

                @if (auth()->check() && app('impersonate')->isImpersonating())
                    <a href="{{ route('filament-impersonate.leave') }}" class="err-btn err-btn-ghost">
                        <i class="bi bi-arrow-90deg-left"></i>
                        Tinggalkan impersonate
                    </a>
                @endif

                @auth
                    <form method="POST" action="{{ route('filament.admin.auth.logout') }}" style="display:contents;">
                        @csrf
                        <button type="submit" class="err-btn err-btn-danger">
                            <i class="bi bi-box-arrow-right"></i>
                            Logout
                        </button>
                    </form>
                @else
                    <a href="{{ route('filament.admin.auth.login') }}" class="err-btn err-btn-danger">
                        <i class="bi bi-door-open"></i>
                        Masuk
                    </a>
                @endauth
            </div>
        </main>

        <p class="err-foot">&copy; {{ date('Y') }} SILOGY — Universitas Siliwangi</p>
    </div>

    <script>
        function errGoBack() {
            if (window.history.length > 1) {
                window.history.back();
                return;
            }

            window.location.href = @json(url('/'));
        }
    </script>
</body>
</html>
