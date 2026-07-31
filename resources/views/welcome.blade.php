<!DOCTYPE html>
<html lang="id" id="html-root">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="SILOGY — Siliwangi Learning Outcomes &amp; Quality Analytics; platform pengelolaan mutu akademik berbasis OBE untuk Universitas Siliwangi.">
    <title>SILOGY &mdash; Siliwangi Learning Outcomes &amp; Quality Analytics</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('images/favicon-48x48.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito:400,500,600,700,800,900&family=Nunito+Sans:400,600,700" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* ── RESET ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Nunito Sans', 'Nunito', sans-serif; overflow-x: hidden; -webkit-font-smoothing: antialiased; }
        a { text-decoration: none; color: inherit; }
        img { display: block; max-width: 100%; }
        ul { list-style: none; }

        /* ══════════════════════════════════════════
           DESIGN TOKENS — dua mode: light & dark
        ══════════════════════════════════════════ */

        /* ── BASE (light default) ── */
        :root {
            /* Green palette — Hijau Unsil ramp, same in both modes */
            --g200: #cceecc; --g300: #80d980; --g400: #4dcc4d;
            --g500: #009900; --g600: #008500; --g700: #007000;
            --g800: #0a4d14; --g900: #0b3914;

            /* Footer/dark accents (always dark — Hijau Tua Gelap) */
            --ink:  #0b3914; --dark2: #123f1a; --dark3: #06210c;
            --tw:   #f0f6ff; --tws:  #8ba3c0; --twss: #546880;
            --bd-d: rgba(255,255,255,.09); --bd-d2: rgba(255,255,255,.14);
            --card-d: rgba(255,255,255,.055);

            /* ── SECTION variables (switch on theme) ── */
            --sec-alt:  #f5f7fb;
            --sec-base: #ffffff;
            --c-text:   #212529;
            --c-muted:  #6c757d;
            --c-body:   #495057;
            --c-card:   #ffffff;
            --c-card-border: #e8edf3;
            --c-card-border2: #d0d9e4;
            --c-card-hover-border: rgba(0,133,0,.28);
            --c-shadow: rgba(0,133,0,.07);
            --c-eyebrow-color: var(--g700);
            --c-eyebrow-bg:    rgba(0,133,0,.07);
            --c-eyebrow-border:rgba(0,133,0,.18);
            --c-sh-color: #212529;
            --c-divider: linear-gradient(90deg,transparent,#e8edf3,transparent);
            --c-nav-bg: rgba(245,247,251,.92);
            --c-nav-border: #e8edf3;
            --c-nav-logo: #212529;
            --c-nav-link: #6c757d;
            --c-nav-link-hover: #212529;
            --R: 14px;
        }

        /* ── DARK MODE (forced) ── */
        [data-theme="dark"] {
            --sec-alt:  #070e1c;
            --sec-base: #060d1a;
            --c-text:   #f0f6ff;
            --c-muted:  #8ba3c0;
            --c-body:   #8ba3c0;
            --c-card:   rgba(255,255,255,.055);
            --c-card-border: rgba(255,255,255,.09);
            --c-card-border2:rgba(255,255,255,.14);
            --c-card-hover-border: rgba(77,204,77,.3);
            --c-shadow: rgba(0,0,0,.4);
            --c-eyebrow-color: var(--g400);
            --c-eyebrow-bg:    rgba(0,133,0,.12);
            --c-eyebrow-border:rgba(77,204,77,.25);
            --c-sh-color: #f0f6ff;
            --c-divider: linear-gradient(90deg,transparent,rgba(255,255,255,.1),transparent);
            --c-nav-bg: rgba(6,13,26,.92);
            --c-nav-border: rgba(255,255,255,.09);
            --c-nav-logo: #f0f6ff;
            --c-nav-link: #8ba3c0;
            --c-nav-link-hover: #f0f6ff;
        }

        /* ── AUTO DARK (system) ── */
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]):not([data-theme="dark"]) {
                --sec-alt:  #070e1c;
                --sec-base: #060d1a;
                --c-text:   #f0f6ff;
                --c-muted:  #8ba3c0;
                --c-body:   #8ba3c0;
                --c-card:   rgba(255,255,255,.055);
                --c-card-border: rgba(255,255,255,.09);
                --c-card-border2:rgba(255,255,255,.14);
                --c-card-hover-border: rgba(77,204,77,.3);
                --c-shadow: rgba(0,0,0,.4);
                --c-eyebrow-color: var(--g400);
                --c-eyebrow-bg:    rgba(0,133,0,.12);
                --c-eyebrow-border:rgba(77,204,77,.25);
                --c-sh-color: #f0f6ff;
                --c-divider: linear-gradient(90deg,transparent,rgba(255,255,255,.1),transparent);
                --c-nav-bg: rgba(6,13,26,.92);
                --c-nav-border: rgba(255,255,255,.09);
                --c-nav-logo: #f0f6ff;
                --c-nav-link: #8ba3c0;
                --c-nav-link-hover: #f0f6ff;
            }
        }

        /* body bg follows section base */
        body { background: var(--sec-base); color: var(--c-text); transition: background .2s, color .2s; }

        /* ── NAVBAR ── */
        .navbar {
            position: sticky; top: 0; z-index: 500;
            background: var(--c-nav-bg);
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px) saturate(180%);
            border-bottom: 3px solid rgba(0,153,0,.35);
            transition: background .2s, border-color .2s;
        }
        .nav-c {
            width: min(1200px, 92%); margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between;
            padding: .85rem 0;
        }
        .logo {
            display: flex; align-items: center; gap: .8rem;
            color: var(--c-nav-logo);
            font-family: 'Nunito', sans-serif; font-weight: 900;
            font-size: 1.18rem; letter-spacing: -.02em;
            transition: color .2s;
        }
        .logo-mark {
            position: relative; flex-shrink: 0;
            width: 42px; height: 42px; border-radius: 10px;
            display: grid; place-items: center;
            overflow: hidden;
            background: transparent;
        }
        .logo-mark img {
            width: 100%; height: 100%; object-fit: contain; display: block;
        }
        .logo-sub {
            display: block; font-family: 'Nunito Sans', sans-serif;
            font-size: .64rem; font-weight: 600; color: var(--c-muted);
            line-height: 1.2; margin-top: 2px; letter-spacing: .015em;
            transition: color .2s;
        }
        .nav-r { display: flex; align-items: center; gap: .5rem; }
        .nbtn {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .5rem 1.1rem; border-radius: 9px;
            font-family: 'Nunito', sans-serif; font-weight: 800; font-size: .85rem;
            transition: all .18s ease; cursor: pointer;
        }
        .nbtn-ghost {
            color: var(--c-muted); border: 1px solid var(--c-card-border); background: transparent;
        }
        .nbtn-ghost:hover { color: var(--c-text); border-color: var(--c-card-border2); background: rgba(128,128,128,.07); }
        .nbtn-green {
            background: linear-gradient(135deg, var(--g700) 0%, var(--g500) 100%);
            color: #fff; border: 1px solid transparent;
            box-shadow: 0 4px 16px rgba(0,133,0,.4), inset 0 1px 0 rgba(255,255,255,.15);
        }
        .nbtn-green:hover { background: #FFD700; color: #000; transform: translateY(-1px); box-shadow: 0 7px 22px rgba(255,215,0,.4); }

        /* Theme toggle */
        .theme-toggle {
            width: 36px; height: 36px; border-radius: 9px;
            display: grid; place-items: center; cursor: pointer;
            border: 1px solid var(--c-card-border);
            background: transparent; color: var(--c-muted);
            font-size: 1rem; transition: all .18s ease;
        }
        .theme-toggle:hover { color: var(--c-text); border-color: var(--c-card-border2); background: rgba(128,128,128,.07); }

        /* ── HERO (clean/modern — ikut light/dark toggle) ── */
        .hero {
            position: relative; overflow: hidden;
            background: var(--sec-alt);
        }
        .hero::after {
            content: ''; position: absolute; inset: 0; pointer-events: none;
            background-image:
                linear-gradient(rgba(0,153,0,.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0,153,0,.05) 1px, transparent 1px);
            background-size: 60px 60px;
            mask-image: radial-gradient(ellipse at 50% 60%, black 20%, transparent 70%);
            -webkit-mask-image: radial-gradient(ellipse at 50% 60%, black 20%, transparent 70%);
        }
        .hero::before {
            content: ''; position: absolute; inset: 0; pointer-events: none;
            background:
                radial-gradient(ellipse at 50% 110%, rgba(0,153,0,.07) 0%, transparent 55%),
                radial-gradient(ellipse at 80% -30%, rgba(255,215,0,.05) 0%, transparent 45%),
                radial-gradient(ellipse at 10% 30%,  rgba(0,153,0,.05) 0%, transparent 40%);
        }
        .hero-c {
            position: relative; z-index: 1;
            width: min(860px, 92%); margin: 0 auto;
            text-align: center; padding: 8rem 0 7rem;
        }
        .hero-pill {
            display: inline-flex; align-items: center; gap: .5rem;
            background: var(--c-eyebrow-bg); border: 1px solid var(--c-eyebrow-border);
            color: var(--c-eyebrow-color); font-size: .73rem; font-weight: 800;
            font-family: 'Nunito', sans-serif; border-radius: 999px;
            padding: .32rem 1rem; margin-bottom: 1.6rem;
            text-transform: uppercase; letter-spacing: .08em;
        }
        .hero-brand {
            font-family: 'Nunito', sans-serif;
            font-size: clamp(3rem, 5.5vw, 5rem); font-weight: 900;
            line-height: .95; color: var(--c-text); letter-spacing: -.04em; margin-bottom: .8rem;
        }
        .hero-name {
            font-family: 'Nunito', sans-serif;
            font-size: clamp(.95rem, 1.5vw, 1.15rem); font-weight: 700;
            line-height: 1.4; margin-bottom: 1.4rem;
            background: linear-gradient(100deg, var(--g700) 0%, var(--g600) 60%);
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .hero-desc {
            color: var(--c-muted); font-size: 1rem; line-height: 1.88;
            max-width: 600px; margin: 0 auto 2.5rem; padding: 0 .5rem;
        }
        .hero-acts { display: flex; align-items: center; justify-content: center; gap: .8rem; flex-wrap: wrap; margin-bottom: 2.5rem; }
        .hbtn-p {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: .85rem 2.2rem; border-radius: 12px;
            font-family: 'Nunito', sans-serif; font-weight: 900; font-size: .97rem;
            background: linear-gradient(135deg, var(--g700) 0%, var(--g500) 100%);
            color: #fff;
            box-shadow: 0 8px 30px rgba(0,133,0,.45), inset 0 1px 0 rgba(255,255,255,.18);
            transition: all .18s ease;
        }
        .hbtn-p:hover { transform: translateY(-2px); background: #FFD700; color: #000; box-shadow: 0 14px 38px rgba(255,215,0,.4); }
        .tbtn-g {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: .82rem 1.9rem; border-radius: 12px;
            font-family: 'Nunito', sans-serif; font-weight: 800; font-size: .95rem;
            border: 1px solid var(--c-card-border); color: var(--c-muted); background: transparent;
            transition: all .18s ease;
        }
        .tbtn-g:hover { background: rgba(0,0,0,.04); border-color: var(--c-card-border2); color: var(--c-text); }
        .hero-tabs { display: flex; gap: .55rem; flex-wrap: wrap; justify-content: center; }
        .hero-tab {
            display: inline-flex; align-items: center; gap: .38rem;
            padding: .4rem 1.1rem; border-radius: 999px; font-size: .75rem;
            font-family: 'Nunito', sans-serif; font-weight: 800; letter-spacing: .06em;
            text-transform: uppercase;
            border: 1px solid var(--c-card-border); color: var(--c-muted);
            cursor: pointer; transition: all .18s ease;
        }
        .hero-tab:hover { background: rgba(0,0,0,.04); border-color: var(--c-card-border2); color: var(--c-text); transform: translateY(-1px); }
        .hero-tab.on { background: var(--c-eyebrow-bg); border-color: var(--c-eyebrow-border); color: var(--g700); }
        .hero-tab.on:hover { background: rgba(0,153,0,.16); border-color: rgba(0,153,0,.4); color: var(--g800); }
        .hero-scroll-hint {
            display: flex; align-items: center; justify-content: center; gap: .6rem;
            margin-top: 3.5rem; color: var(--c-muted); font-size: .78rem;
            font-family: 'Nunito', sans-serif; font-weight: 700;
            letter-spacing: .06em; text-transform: uppercase;
        }
        .hero-scroll-hint::before, .hero-scroll-hint::after {
            content: ''; flex: 0 0 48px; height: 1px;
        }
        .hero-scroll-hint::before { background: linear-gradient(90deg, transparent, var(--c-card-border)); }
        .hero-scroll-hint::after  { background: linear-gradient(90deg, var(--c-card-border), transparent); }

        /* ── SHARED ── */
        .sec { padding: 5.5rem 0; transition: background .2s; }
        .sec-c { width: min(1200px, 92%); margin: 0 auto; }
        .eyebrow {
            display: inline-flex; align-items: center; gap: .45rem;
            font-size: .72rem; font-family: 'Nunito', sans-serif; font-weight: 900;
            text-transform: uppercase; letter-spacing: .1em;
            color: var(--c-eyebrow-color);
            background: var(--c-eyebrow-bg);
            border: 1px solid var(--c-eyebrow-border);
            border-radius: 999px; padding: .26rem .9rem; margin-bottom: .9rem;
            transition: all .2s;
        }
        .sh {
            font-family: 'Nunito', sans-serif;
            font-size: clamp(1.5rem, 2.5vw, 2.1rem); font-weight: 900;
            color: var(--c-sh-color); letter-spacing: -.025em;
            line-height: 1.15; margin-bottom: .6rem; transition: color .2s;
        }
        .sp { color: var(--c-muted); font-size: .97rem; line-height: 1.78; max-width: 560px; transition: color .2s; }
        .hd { margin-bottom: 2.5rem; }
        .hd-center { text-align: center; display: flex; flex-direction: column; align-items: center; }
        .hd-center .sp { text-align: center; }

        /* ── WHY ── */
        .why-bg { background: var(--sec-alt); }
        .why-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4.5rem; align-items: start; }
        .body-text { color: var(--c-body); font-size: .97rem; line-height: 1.82; margin-bottom: 1.8rem; transition: color .2s; }
        .culture-label {
            font-family: 'Nunito', sans-serif; font-size: .73rem; font-weight: 900;
            text-transform: uppercase; letter-spacing: .1em;
            color: var(--c-eyebrow-color); margin-bottom: 1rem; transition: color .2s;
        }
        .culture-list { display: flex; flex-direction: column; gap: .7rem; }
        .ci {
            display: flex; align-items: flex-start; gap: 1rem;
            background: var(--c-card); border: 1px solid var(--c-card-border);
            border-radius: 14px; padding: 1rem 1.2rem; transition: all .2s ease;
        }
        .ci:hover { border-color: var(--c-card-hover-border); box-shadow: 0 6px 20px var(--c-shadow); transform: translateX(4px); }
        .ci-num {
            width: 30px; height: 30px; border-radius: 9px; flex-shrink: 0;
            background: linear-gradient(135deg, var(--g800), var(--g600));
            display: grid; place-items: center; margin-top: .05rem;
            color: #fff; font-family: 'Nunito', sans-serif; font-size: .7rem; font-weight: 900;
        }
        .ci p { font-size: .92rem; color: var(--c-text); line-height: 1.55; transition: color .2s; }
        .ci strong { color: var(--g800); }
        [data-theme="dark"] .ci strong,
        @media (prefers-color-scheme: dark) { .ci strong } { color: var(--g400); }
        .why-aside {
            position: sticky; top: 5rem;
            background: linear-gradient(155deg, var(--ink) 0%, #0f2e15 100%);
            border-radius: 24px; padding: 2.75rem 2.25rem;
            border: 1px solid var(--bd-d);
            box-shadow: 0 32px 80px rgba(0,0,0,.22), 0 0 0 1px rgba(77,204,77,.06);
            overflow: hidden; position: relative;
        }
        .why-aside::before {
            content: ''; position: absolute; inset: 0; pointer-events: none;
            background: radial-gradient(ellipse at 80% 0%, rgba(77,204,77,.09) 0%, transparent 55%);
        }
        .aside-qmark { font-family: Georgia,serif; font-size: 5rem; line-height: .8; color: var(--g600); opacity: .18; margin-bottom: .25rem; }
        .aside-quote { font-size: 1.05rem; font-style: italic; line-height: 1.68; color: var(--tw); font-weight: 600; margin-bottom: 2rem; }
        .aside-tags { display: flex; flex-direction: column; gap: .6rem; }
        .aside-tag { display: flex; align-items: center; gap: .7rem; background: rgba(255,255,255,.05); border: 1px solid var(--bd-d); border-radius: 11px; padding: .7rem 1rem; }
        .aside-tag-icon { width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0; background: rgba(0,133,0,.15); border: 1px solid rgba(77,204,77,.2); display: grid; place-items: center; color: var(--g400); font-size: .9rem; }
        .aside-tag-text { font-size: .85rem; font-weight: 700; color: var(--tw); }
        .aside-tag-sub  { font-size: .74rem; color: var(--tws); }

        /* ── PILLARS ── */
        .pillars-bg { background: var(--sec-base); }
        .pillars-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; }
        .pillar { border-radius: 22px; padding: 2.25rem 2rem; position: relative; overflow: hidden; transition: transform .22s ease, box-shadow .22s ease; }
        .pillar:hover { transform: translateY(-6px); }
        .p1 { background: var(--ink); border: 1px solid rgba(77,204,77,.12); box-shadow: 0 20px 60px rgba(0,0,0,.2); }
        .p2 { background: linear-gradient(155deg, var(--g800) 0%, var(--g600) 100%); border: 1px solid rgba(128,217,128,.2); box-shadow: 0 24px 70px rgba(0,133,0,.35); }
        .p3 { background: linear-gradient(155deg, var(--dark2) 0%, var(--ink) 100%); border: 1px solid rgba(255,255,255,.08); box-shadow: 0 20px 60px rgba(0,0,0,.2); }
        .pillar::after { content: ''; position: absolute; bottom: -55px; right: -55px; width: 160px; height: 160px; border-radius: 50%; background: rgba(255,255,255,.04); pointer-events: none; }
        .pillar-seq { font-size: .7rem; font-family: 'Nunito',sans-serif; font-weight: 900; text-transform: uppercase; letter-spacing: .15em; color: rgba(255,255,255,.28); margin-bottom: 1.25rem; }
        .pillar-ico { width: 54px; height: 54px; border-radius: 15px; background: rgba(255,255,255,.09); border: 1px solid rgba(255,255,255,.13); display: grid; place-items: center; color: var(--g300); font-size: 1.45rem; margin-bottom: 1.3rem; }
        .p2 .pillar-ico { background: rgba(255,255,255,.15); color: #fff; }
        .pillar-name { font-family: 'Nunito',sans-serif; font-size: 1.65rem; font-weight: 900; letter-spacing: -.03em; color: var(--tw); line-height: 1; margin-bottom: .75rem; }
        .pillar-desc { font-size: .88rem; color: rgba(255,255,255,.58); line-height: 1.72; }

        /* ── ECOSYSTEM ── */
        .eco-bg { background: var(--sec-alt); }
        .eco-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        .eco-card { background: var(--c-card); border: 1px solid var(--c-card-border); border-radius: 18px; padding: 1.75rem 1.8rem; display: flex; gap: 1.35rem; align-items: flex-start; transition: all .2s ease; }
        .eco-card:hover { border-color: var(--c-card-hover-border); box-shadow: 0 12px 36px var(--c-shadow); transform: translateY(-3px); }
        .eco-ico { width: 52px; height: 52px; border-radius: 14px; flex-shrink: 0; background: linear-gradient(145deg, var(--ink) 0%, #10331a 100%); border: 1px solid rgba(77,204,77,.18); display: grid; place-items: center; color: var(--g400); font-size: 1.35rem; }
        .eco-name { font-family: 'Nunito',sans-serif; font-size: 1.1rem; font-weight: 900; letter-spacing: -.01em; margin-bottom: .2rem; color: var(--c-text); transition: color .2s; }
        .eco-full { font-size: .82rem; font-weight: 700; color: var(--g700); margin-bottom: .55rem; }
        .eco-desc { font-size: .9rem; color: var(--c-muted); line-height: 1.68; }
        .eco-motto { background: linear-gradient(145deg, var(--ink) 0%, #0e2b13 100%); border: 1px solid var(--bd-d); border-radius: 18px; padding: 1.6rem 2rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.25rem; margin-top: 1.25rem; }
        .eco-motto-text strong { color: var(--tw); display: block; font-family: 'Nunito',sans-serif; font-size: 1rem; font-weight: 900; margin-bottom: .3rem; letter-spacing: -.01em; }
        .eco-motto-text span  { color: var(--tws); font-size: .87rem; line-height: 1.55; }
        .eco-chips { display: flex; gap: .5rem; flex-wrap: wrap; }
        .eco-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .32rem .9rem; border-radius: 999px; font-size: .74rem; font-family: 'Nunito',sans-serif; font-weight: 800; background: rgba(0,133,0,.13); border: 1px solid rgba(77,204,77,.22); color: var(--g400); }

        /* ── IMPACT ── */
        .impact-bg { background: var(--sec-base); }
        .impact-grid { display: grid; grid-template-columns: 1fr .85fr; gap: 4rem; align-items: start; }
        .impact-list { display: flex; flex-direction: column; gap: .85rem; margin-top: 1.5rem; }
        .ii { display: flex; align-items: flex-start; gap: 1.1rem; background: var(--c-card); border: 1px solid var(--c-card-border); border-radius: 15px; padding: 1.15rem 1.35rem; transition: all .2s ease; }
        .ii:hover { border-color: var(--c-card-hover-border); background: var(--c-card); box-shadow: 0 6px 22px var(--c-shadow); transform: translateX(4px); }
        .ii-ico { width: 40px; height: 40px; border-radius: 11px; flex-shrink: 0; background: linear-gradient(135deg, var(--g800), var(--g500)); display: grid; place-items: center; color: #fff; font-size: 1rem; box-shadow: 0 4px 12px rgba(0,133,0,.3); }
        .ii p { font-size: .93rem; color: var(--c-text); line-height: 1.56; padding-top: .22rem; transition: color .2s; }
        .ii strong { color: var(--g800); }
        [data-theme="dark"] .ii strong { color: var(--g400); }

        /* ── CTA CARD ── */
        .cta-c { position: sticky; top: 5rem; background: linear-gradient(155deg, var(--g900) 0%, var(--g700) 60%, var(--g600) 100%); border-radius: 24px; padding: 2.75rem 2.25rem; color: #fff; overflow: hidden; box-shadow: 0 30px 80px rgba(0,133,0,.4), inset 0 1px 0 rgba(255,255,255,.12); }
        .cta-c::before { content: ''; position: absolute; top: -70px; right: -70px; width: 240px; height: 240px; border-radius: 50%; background: rgba(255,255,255,.07); pointer-events: none; }
        .cta-c::after  { content: ''; position: absolute; bottom: -50px; left: -40px; width: 160px; height: 160px; border-radius: 50%; background: rgba(255,255,255,.04); pointer-events: none; }
        .cta-c-ew  { font-size: .72rem; font-family: 'Nunito',sans-serif; font-weight: 900; text-transform: uppercase; letter-spacing: .1em; color: rgba(255,255,255,.55); margin-bottom: .8rem; }
        .cta-c h3  { font-family: 'Nunito',sans-serif; font-size: 1.5rem; font-weight: 900; letter-spacing: -.02em; margin-bottom: .75rem; }
        .cta-c p   { font-size: .91rem; opacity: .78; line-height: 1.7; margin-bottom: 1.75rem; }
        .cta-c-btn { display: inline-flex; align-items: center; gap: .5rem; background: #fff; color: var(--g900); font-family: 'Nunito',sans-serif; font-weight: 900; font-size: .93rem; padding: .78rem 1.65rem; border-radius: 12px; box-shadow: 0 6px 20px rgba(0,0,0,.18); transition: all .18s ease; }
        .cta-c-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(0,0,0,.24); }
        .cta-c-note { font-size: .77rem; opacity: .5; margin-top: 1rem; }

        /* ── FOOTER (always dark) ── */
        .footer { background: var(--ink); border-top: 1px solid var(--bd-d); }
        .footer-c { width: min(1200px,92%); margin: 0 auto; padding: 4rem 0 2.5rem; display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 3.5rem; }
        .fb p { color: var(--tws); font-size: .87rem; line-height: 1.72; margin-top: .9rem; max-width: 290px; }
        .fb-line { display: flex; align-items: center; gap: .5rem; color: var(--twss); font-size: .82rem; margin-top: .8rem; }
        .fb-line i { color: var(--g600); }
        .fc h4 { font-family: 'Nunito',sans-serif; color: var(--tw); font-size: .8rem; font-weight: 900; text-transform: uppercase; letter-spacing: .1em; margin-bottom: 1.1rem; }
        .fl { display: flex; flex-direction: column; gap: .6rem; }
        .fl a { color: var(--tws); font-size: .87rem; transition: .14s; }
        .fl a:hover { color: var(--g400); padding-left: 2px; }
        .fct { display: flex; flex-direction: column; gap: .7rem; }
        .fct li { display: flex; align-items: flex-start; gap: .5rem; color: var(--tws); font-size: .87rem; line-height: 1.55; }
        .fct li i { color: var(--g600); margin-top: .15rem; flex-shrink: 0; }
        .footer-bot { width: min(1200px,92%); margin: 0 auto; padding: 1.3rem 0; border-top: 1px solid var(--bd-d); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .5rem; }
        .footer-bot span { color: var(--twss); font-size: .79rem; }
        .footer-bot a { color: var(--g500); font-size: .79rem; transition: .14s; }
        .footer-bot a:hover { color: var(--g400); }

        .divider { width: min(1200px,92%); margin: 0 auto; height: 1px; background: var(--c-divider); }

        /* ── RESPONSIVE ── */
        @media (max-width: 1024px) {
            .footer-c { grid-template-columns: 1fr 1fr; }
            .pillars-grid { grid-template-columns: 1fr 1fr; }
            .impact-grid { grid-template-columns: 1fr; gap: 2.5rem; }
            .cta-c { position: static; }
        }
        @media (max-width: 860px) {
            .hero-c { padding: 5.5rem 0 5rem; }
            .why-grid { grid-template-columns: 1fr; gap: 2.5rem; }
            .why-aside { position: static !important; }
            .eco-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            .pillars-grid { grid-template-columns: 1fr; }
            .footer-c { grid-template-columns: 1fr; gap: 2.5rem; }
            .hero-brand { letter-spacing: -.03em; }
            .sec { padding: 4rem 0; }
        }
    </style>
</head>
<body>

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
                <a href="#">Pemetaan CPL&ndash;CPMK</a>
                <a href="#">Manajemen Kurikulum</a>
                <a href="#">Pengisian Nilai</a>
                <a href="#">Laporan Mutu</a>
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

    /* ── HERO TABS ── */
    document.querySelectorAll('.hero-tab').forEach(tab => {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.hero-tab').forEach(t => t.classList.remove('on'));
            this.classList.add('on');
        });
    });
</script>
</body>
</html>
