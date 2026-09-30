{{-- Gaya khusus halaman panduan.
     Seluruhnya bersandar pada token yang sudah ada di layouts/partials/gaya-publik
     (--g500, --c-text, --c-card, …), jadi mode gelap/terang ikut beranda tanpa
     satu pun nilai warna yang disalin ulang. Sebelumnya palet Unsil hidup di tiga
     tempat berbeda; ini menutup salah satunya. --}}
<style>
    .pd-wrap { max-width: 1180px; margin: 0 auto; padding: 0 20px; }

    .pd-hero { background: var(--sec-alt); padding: 110px 0 44px; border-bottom: 1px solid var(--c-card-border); }
    .pd-hero .pd-wrap { display: flex; flex-direction: column; gap: 14px; }
    .pd-eyebrow {
        align-self: flex-start; display: inline-flex; align-items: center; gap: 8px;
        padding: 6px 14px; border-radius: 999px; font-size: .78rem; font-weight: 700;
        letter-spacing: .04em; text-transform: uppercase;
        color: var(--c-eyebrow-color); background: var(--c-eyebrow-bg);
        border: 1px solid var(--c-eyebrow-border);
    }
    .pd-hero h1 { font-family: 'Nunito', sans-serif; font-size: clamp(1.9rem, 4vw, 2.9rem); line-height: 1.15; color: var(--c-text); }
    .pd-hero p.pd-lead { color: var(--c-body); font-size: 1.05rem; max-width: 62ch; }

    .pd-kembali { color: var(--c-muted); font-size: .9rem; display: inline-flex; align-items: center; gap: 6px; }
    .pd-kembali:hover { color: var(--g500); }

    /* ── Kartu coba peran ── */
    .pd-coba {
        margin-top: 8px; display: flex; flex-wrap: wrap; align-items: center; gap: 16px;
        padding: 18px 20px; border-radius: var(--R);
        background: var(--c-card); border: 1px solid var(--c-card-border2);
    }
    .pd-coba-teks { flex: 1 1 320px; }
    .pd-coba-teks strong { color: var(--c-text); }
    .pd-coba-teks p { color: var(--c-muted); font-size: .9rem; margin-top: 4px; }
    .pd-akun { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .85rem; color: var(--c-text); }
    .pd-btn {
        display: inline-flex; align-items: center; gap: 8px; padding: 11px 20px;
        border-radius: 10px; font-weight: 700; font-size: .92rem; border: 0; cursor: pointer;
        background: var(--g500); color: #fff;
    }
    .pd-btn:hover { background: var(--g600); }
    .pd-btn[disabled] { opacity: .45; cursor: not-allowed; }
    .pd-btn-luar { background: transparent; color: var(--g500); border: 1px solid var(--c-card-border2); }
    .pd-btn-luar:hover { background: var(--c-eyebrow-bg); }
    .pd-pesan { margin-top: 10px; font-size: .88rem; color: var(--c-muted); }
    .pd-galat {
        margin-top: 12px; padding: 12px 16px; border-radius: 10px; font-size: .9rem;
        background: rgba(193,18,31,.08); color: #c1121f; border: 1px solid rgba(193,18,31,.25);
    }

    /* ── Tata letak isi ── */
    .pd-badan { background: var(--sec-base); padding: 40px 0 72px; }
    .pd-kolom { display: grid; grid-template-columns: 240px minmax(0, 1fr); gap: 48px; align-items: start; }
    @media (max-width: 900px) { .pd-kolom { grid-template-columns: 1fr; gap: 24px; } }

    .pd-daftar-isi { position: sticky; top: 90px; max-height: calc(100vh - 120px); overflow-y: auto; }
    @media (max-width: 900px) { .pd-daftar-isi { position: static; max-height: none; } }
    .pd-daftar-isi h2 {
        font-size: .76rem; text-transform: uppercase; letter-spacing: .06em;
        color: var(--c-muted); margin-bottom: 10px; font-family: 'Nunito Sans', sans-serif;
    }
    .pd-daftar-isi a {
        display: block; padding: 5px 0 5px 12px; font-size: .88rem; color: var(--c-muted);
        border-left: 2px solid var(--c-card-border); line-height: 1.4;
    }
    .pd-daftar-isi a:hover { color: var(--g500); border-left-color: var(--g500); }
    .pd-daftar-isi a.taraf-3 { padding-left: 24px; font-size: .84rem; }

    /* ── Isi Markdown ── */
    .pd-isi { color: var(--c-body); font-size: 1rem; line-height: 1.75; min-width: 0; }
    .pd-isi > *:first-child { margin-top: 0; }
    .pd-isi h2 {
        font-family: 'Nunito', sans-serif; font-size: 1.5rem; color: var(--c-text);
        margin: 44px 0 14px; padding-bottom: 8px; border-bottom: 1px solid var(--c-card-border);
        scroll-margin-top: 90px;
    }
    .pd-isi h3 { font-family: 'Nunito', sans-serif; font-size: 1.15rem; color: var(--c-text); margin: 28px 0 10px; scroll-margin-top: 90px; }
    .pd-isi p { margin: 12px 0; }
    .pd-isi ul, .pd-isi ol { margin: 12px 0 12px 22px; }
    .pd-isi ul { list-style: disc; }
    .pd-isi ol { list-style: decimal; }
    .pd-isi li { margin: 6px 0; }
    .pd-isi a { color: var(--g500); text-decoration: underline; text-underline-offset: 2px; }
    .pd-isi strong { color: var(--c-text); }
    .pd-isi code {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .86em;
        padding: 2px 6px; border-radius: 5px; background: var(--c-eyebrow-bg); color: var(--c-text);
    }
    .pd-isi pre {
        margin: 16px 0; padding: 16px; border-radius: 10px; overflow-x: auto;
        background: var(--ink); color: var(--tw); font-size: .82rem; line-height: 1.5;
    }
    .pd-isi pre code { background: none; color: inherit; padding: 0; }
    .pd-isi blockquote {
        margin: 16px 0; padding: 12px 18px; border-left: 3px solid var(--g500);
        background: var(--c-eyebrow-bg); border-radius: 0 8px 8px 0; color: var(--c-body);
    }
    .pd-isi blockquote p { margin: 4px 0; }
    .pd-isi table { width: 100%; border-collapse: collapse; margin: 18px 0; font-size: .9rem; display: block; overflow-x: auto; }
    .pd-isi th, .pd-isi td { padding: 9px 12px; border: 1px solid var(--c-card-border); text-align: left; vertical-align: top; }
    .pd-isi th { background: var(--sec-alt); color: var(--c-text); font-weight: 700; }
    .pd-isi hr { border: 0; height: 1px; background: var(--c-card-border); margin: 32px 0; }

    /* ── Figur bertangkapan layar asli ── */
    .pd-gbr { margin: 26px 0; }
    .pd-bingkai { position: relative; display: block; border-radius: 12px; overflow: hidden;
        border: 1px solid var(--c-card-border2); box-shadow: 0 6px 22px var(--c-shadow); }
    .pd-bingkai img { display: block; width: 100%; height: auto; }
    .pd-gbr figcaption { margin-top: 9px; font-size: .86rem; color: var(--c-muted); font-style: italic; }

    /* Badge memakai persen terhadap kotak gambar — kotak itu persis selebar dan
       setinggi gambar, jadi penunjuk tetap menempel pada elemen yang sama di
       lebar layar berapa pun. translate(-50%,-50%) karena manifes menyimpan
       TITIK PUSAT elemen yang ditunjuk. */
    .pd-badge {
        position: absolute; transform: translate(-50%, -50%); pointer-events: none;
        width: 1.7rem; height: 1.7rem; line-height: 1.7rem; border-radius: 50%;
        background: #c1121f; color: #fff; font-weight: 800; font-size: .84rem;
        text-align: center; box-shadow: 0 0 0 2px #fff, 0 2px 7px rgba(0,0,0,.4);
        font-family: 'Nunito', sans-serif;
    }
    @media (max-width: 640px) {
        .pd-badge { width: 1.25rem; height: 1.25rem; line-height: 1.25rem; font-size: .64rem; box-shadow: 0 0 0 1.5px #fff, 0 1px 4px rgba(0,0,0,.4); }
    }
    .pd-legenda { list-style: none !important; margin: 12px 0 0 !important; display: grid; gap: 7px; }
    .pd-legenda li { display: flex; align-items: flex-start; gap: 9px; margin: 0 !important; font-size: .9rem; }
    .pd-nomor {
        flex: 0 0 auto; width: 1.35rem; height: 1.35rem; line-height: 1.35rem; border-radius: 50%;
        background: #c1121f; color: #fff; font-weight: 800; font-size: .74rem; text-align: center;
        font-family: 'Nunito', sans-serif; margin-top: 2px;
    }

    /* ── Kisi kartu peran ── */
    .pd-kisi { display: grid; grid-template-columns: repeat(auto-fill, minmax(255px, 1fr)); gap: 16px; }
    .pd-kartu {
        display: block; padding: 22px; border-radius: var(--R); background: var(--c-card);
        border: 1px solid var(--c-card-border); transition: none;
    }
    .pd-kartu:hover { border-color: var(--c-card-hover-border); box-shadow: 0 6px 20px var(--c-shadow); }
    .pd-kartu i { font-size: 1.5rem; color: var(--g500); }
    .pd-kartu h3 { font-family: 'Nunito', sans-serif; font-size: 1.1rem; color: var(--c-text); margin: 10px 0 6px; }
    .pd-kartu p { color: var(--c-muted); font-size: .89rem; line-height: 1.55; }

    .pd-lanjut { margin-top: 48px; padding-top: 24px; border-top: 1px solid var(--c-card-border); }
    .pd-lanjut h2 { font-family: 'Nunito', sans-serif; font-size: 1.15rem; color: var(--c-text); margin-bottom: 14px; }
</style>
