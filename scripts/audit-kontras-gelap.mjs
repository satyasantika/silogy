#!/usr/bin/env node
/**
 * Audit kontras mode gelap SILOGY (WCAG AA).
 *
 * Memakai ulang masuk/sesi dari scripts/tangkap-layar (formulir sungguhan dan
 * "Coba sebagai …" untuk akun sim-*), mengaktifkan mode gelap, lalu untuk tiap
 * (peran, halaman) menghitung rasio kontras setiap teks yang tampak. Hanya
 * MEMBACA: tidak pernah mengirim formulir; modal dibuka lalu ditutup tanpa
 * konfirmasi.
 *
 *   node scripts/audit-kontras-gelap.mjs [--peran=sim-validator] [--halaman=slug] [--autotest]
 *
 * Kode keluar: 0 bersih / autotest lulus, 1 ada temuan, 2 galat (termasuk --peran/--halaman
 * yang tak cocok), 3 tanpa temuan tetapi ada halaman tak tercapai (belum bisa disebut bersih).
 * `--help` menampilkan ringkasan ini.
 * Keluaran: .superpowers/sdd/2026-11-24-kontras-mode-gelap/audit/{temuan.json,laporan.md}
 */
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';
import { AKAR, BASIS } from './tangkap-layar/konfig.mjs';
import { konteksUntuk, siapkanHalaman } from './tangkap-layar/konteks.mjs';

const DIR_KELUARAN = path.join(AKAR, '.superpowers/sdd/2026-11-24-kontras-mode-gelap/audit');

// ── Fungsi audit di dalam halaman ─────────────────────────────────────────
// Berdiri sendiri (diserialisasi Playwright), dipakai juga oleh --autotest.
function auditarDom(opsi) {
  const akar = opsi.akar ? document.querySelector(opsi.akar) : document.body;
  const cv = document.createElement('canvas');
  cv.width = cv.height = 1;
  const g = cv.getContext('2d', { willReadFrequently: true });

  const cacheWarna = new Map();
  function parse(c) {
    if (cacheWarna.has(c)) return cacheWarna.get(c);
    let r;
    const m = c.match(/^rgba?\(\s*([\d.]+)[ ,]+([\d.]+)[ ,]+([\d.]+)(?:\s*[,/]\s*([\d.]+%?))?\s*\)$/);
    if (m) {
      let a = m[4] === undefined ? 1 : m[4].endsWith('%') ? parseFloat(m[4]) / 100 : parseFloat(m[4]);
      r = [+m[1], +m[2], +m[3], a];
    } else {
      // oklch(), color(), color-mix() dll: ambil lewat kanvas di atas hitam dan putih
      const lukis = (latar) => {
        g.globalCompositeOperation = 'source-over';
        g.fillStyle = latar; g.fillRect(0, 0, 1, 1);
        g.fillStyle = c; g.fillRect(0, 0, 1, 1);
        return g.getImageData(0, 0, 1, 1).data;
      };
      const h = lukis('#000'), p = lukis('#fff');
      const a = Math.min(1, Math.max(0, 1 - (p[0] - h[0]) / 255));
      r = a > 0 ? [h[0] / a, h[1] / a, h[2] / a, a] : [0, 0, 0, 0];
    }
    cacheWarna.set(c, r);
    return r;
  }
  const lum = ([r, gg, b]) => {
    const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
    return 0.2126 * f(r) + 0.7152 * f(gg) + 0.0722 * f(b);
  };
  const rasio = (a, b) => {
    const la = lum(a), lb = lum(b);
    return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
  };
  const hex = (c, a) => '#' + c.map((v) => Math.round(v).toString(16).padStart(2, '0')).join('') + (a !== undefined && a < 0.995 ? `@${a.toFixed(2)}` : '');

  function selektor(el) {
    const bagian = [];
    for (let n = el, i = 0; n && n.nodeType === 1 && n !== document.body && i < 3; n = n.parentElement, i++) {
      let s = n.tagName.toLowerCase();
      if (n.id && !/\d{3,}|^[a-f0-9-]{20,}$/.test(n.id)) s += '#' + n.id;
      const ds = n.getAttribute('data-silogy');
      if (ds) s += `[data-silogy=${ds}]`;
      const k = [...n.classList].filter((x) => !/[:\[\]/]/.test(x) && x.length < 32).slice(0, 2);
      if (k.length) s += '.' + k.join('.');
      bagian.unshift(s);
    }
    return bagian.join(' > ');
  }

  // Penanda sebab: deklarasi warna/latar inline terdekat, kalau tidak ada kelas.
  function tandaSebab(el) {
    for (let n = el, i = 0; n && n.nodeType === 1 && i < 5; n = n.parentElement, i++) {
      const st = n.getAttribute('style') || '';
      const dek = st.split(';').map((x) => x.trim().toLowerCase()).filter((x) => /^(color|background(-color)?)\s*:/.test(x));
      if (dek.length) return 'inline: ' + dek.join('; ');
    }
    const k = [...el.classList].filter((x) => !/[:\[\]/]/.test(x)).slice(0, 3);
    return 'kelas: ' + (k.join('.') || el.tagName.toLowerCase());
  }

  const rectCache = new Map();
  const rect = (n) => { if (!rectCache.has(n)) rectCache.set(n, n.getBoundingClientRect()); return rectCache.get(n); };

  // Rantai akar -> el: latar efektif, opasitas kumulatif, indikasi gradien.
  const memo = new Map();
  function lapis(el) {
    if (memo.has(el)) return memo.get(el);
    const ind = el.parentElement ? lapis(el.parentElement) : { C: [255, 255, 255], O: 1, opaq: false, grad: null, img: null };
    const cs = getComputedStyle(el);
    const o = parseFloat(cs.opacity);
    const O = ind.O * (isNaN(o) ? 1 : o);
    const bg = parse(cs.backgroundColor);
    const a = bg[3] * O;
    const C = [0, 1, 2].map((i) => ind.C[i] * (1 - a) + bg[i] * a);
    let { opaq, grad, img } = ind;
    const bi = cs.backgroundImage || 'none';
    if (/gradient\(/.test(bi)) {
      // Hanya gradien yang tak terukur: warna di bawah teks berubah-ubah.
      grad = selektor(el) + ' => ' + bi.slice(0, 60);
    } else if (a >= 0.995) { opaq = true; grad = null; img = null; }
    // url(...) (mis. ikon panah <select>) tidak mengubah latar di bawah teks: tetap dihitung, diberi tanda.
    if (bi !== 'none' && !/gradient\(/.test(bi)) {
      // Ikon kecil (mis. panah <select>): tak berulang dan ukurannya jauh di bawah kotak elemen.
      const r = el.getBoundingClientRect();
      const ukuran = cs.backgroundSize.split(',')[0].trim().split(/\s+/).map((v) => (v.endsWith('px') ? parseFloat(v) : NaN));
      const lw = ukuran[0], lh = ukuran.length > 1 ? ukuran[1] : ukuran[0];
      const kecil = cs.backgroundRepeat.startsWith('no-repeat') && lw > 0 && lh > 0 && lw * lh <= 0.25 * r.width * r.height;
      img = { sel: selektor(el), kelas: kecil ? 'ikon-kecil' : 'gambar-lebar' };
    }
    const hasil = { C, O, opaq, grad, img };
    memo.set(el, hasil);
    return hasil;
  }

  function terlihat(el, cs) {
    if (cs.visibility !== 'visible' || cs.display === 'none') return false;
    const r = rect(el);
    if (r.width <= 1 || r.height <= 1) return false;
    if (r.right + scrollX <= 0 || r.bottom + scrollY <= 0) return false;
    if (lapis(el).O <= 0.01) return false;
    for (let n = el.parentElement; n && n !== document.documentElement; n = n.parentElement) {
      const s = getComputedStyle(n);
      // Hanya pemotongan keras (hidden/clip) yang menyembunyikan; auto/scroll bisa dijangkau dengan menggulir.
      const keras = (v) => v === 'hidden' || v === 'clip';
      if (keras(s.overflowX) || keras(s.overflowY)) {
        const p = rect(n);
        if (p.width === 0 || p.height === 0) return false;
        if (keras(s.overflowX) && (r.right <= p.left || r.left >= p.right)) return false;
        if (keras(s.overflowY) && (r.bottom <= p.top || r.top >= p.bottom)) return false;
      }
    }
    return true;
  }

  const hasil = { total: 0, achados: [], takTentu: [], bergambar: [], dilewati: 0 };
  function nilai(el, teks, warnaCss, cs, sumber) {
    const lp = lapis(el);
    const fg = parse(warnaCss);
    const aFg = fg[3] * lp.O;
    const warnaTeks = [0, 1, 2].map((i) => lp.C[i] * (1 - aFg) + fg[i] * aFg);
    // lp.C sudah memuat latar leluhur+elemen; opasitas teks dihitung di atasnya
    const latar = lp.C;
    hasil.total++;
    const px = parseFloat(cs.fontSize);
    const tebal = parseInt(cs.fontWeight, 10) >= 700;
    const besar = px >= 24 || (px >= 18.66 && tebal);
    const dasar = {
      selector: selektor(el), teks: teks.replace(/\s+/g, ' ').trim().slice(0, 60),
      warnaTeks: hex(fg.slice(0, 3), aFg), latarEfektif: hex(latar),
      ukuranFont: Math.round(px * 10) / 10, tebal: tebal ? 700 : parseInt(cs.fontWeight, 10) || 400,
      sebab: tandaSebab(el), sumber, fx: el.closest('[data-fx]')?.getAttribute('data-fx') || null,
      latarSumber: lp.opaq ? 'opak' : 'kanvas-putih', adaGambar: Boolean(lp.img), gambarKelas: lp.img ? lp.img.kelas : null, gambarDi: lp.img ? lp.img.sel : null,
    };
    if (lp.grad) { hasil.takTentu.push({ ...dasar, gradien: lp.grad }); return; }
    const rs = rasio(warnaTeks, latar);
    if (rs < (besar ? 3 : 4.5)) hasil.achados.push({ ...dasar, rasio: Math.round(rs * 100) / 100, besar });
    else if (lp.img) hasil.bergambar.push({ ...dasar, rasio: Math.round(rs * 100) / 100 });
  }

  const JANGAN = new Set(['SCRIPT', 'STYLE', 'NOSCRIPT', 'TEMPLATE', 'OPTION', 'OPTGROUP', 'TITLE', 'HEAD', 'TEXTAREA']);
  const penjelajah = document.createTreeWalker(akar, NodeFilter.SHOW_TEXT);
  const unit = new Map();
  for (let t = penjelajah.nextNode(); t; t = penjelajah.nextNode()) {
    const txt = t.nodeValue;
    const el = t.parentElement;
    if (!el || !txt.trim() || JANGAN.has(el.tagName) || el.closest('select')) continue;
    unit.set(el, (unit.get(el) || '') + ' ' + txt);
  }
  for (const [el, teks] of unit) {
    const cs = getComputedStyle(el);
    if (!terlihat(el, cs)) continue;
    if (el.closest('[disabled],[aria-disabled="true"],[inert]')) { hasil.dilewati++; continue; }
    const isi = cs.webkitTextFillColor && cs.webkitTextFillColor !== cs.color ? cs.webkitTextFillColor : cs.color;
    if (cs.backgroundClip === 'text' || cs.webkitBackgroundClip === 'text') {
      hasil.takTentu.push({ selector: selektor(el), teks: teks.trim().slice(0, 60), gradien: 'background-clip:text', sebab: tandaSebab(el) });
      continue;
    }
    nilai(el, teks, isi, cs, 'teks');
  }
  // Kontrol formulir: nilai terpilih / isi / placeholder
  for (const el of akar.querySelectorAll('input,textarea,select')) {
    if (['hidden', 'checkbox', 'radio', 'file', 'range', 'color', 'submit', 'button', 'image'].includes(el.type)) continue;
    const cs = getComputedStyle(el);
    if (!terlihat(el, cs)) continue;
    if (el.disabled) { hasil.dilewati++; continue; }
    let teks = '', warna = cs.color, sumber = 'nilai';
    if (el.tagName === 'SELECT') teks = el.selectedOptions[0]?.textContent || '';
    else if (el.value) teks = el.value;
    else if (el.placeholder) { teks = el.placeholder; warna = getComputedStyle(el, '::placeholder').color; sumber = 'placeholder'; }
    if (teks.trim()) nilai(el, teks, warna, cs, sumber);
  }
  return hasil;
}

// ── Halaman yang diaudit ─────────────────────────────────────────────────
const tunggu = (page, ms = 500) => page.waitForTimeout(ms);
const klikBila = async (page, loc) => {
  if (!(await loc.count())) return false;
  const ok = await loc.first().click({ timeout: 4000 }).then(() => true, () => false);
  await tunggu(page, 700);
  return ok;
};
const tutupModal = async (page) => {
  const batal = page.locator('.fi-modal-window').getByRole('button', { name: /^(Batal|Tutup|Cancel)$/i }).first();
  if (await batal.count()) await batal.click().catch(() => {});
  else await page.keyboard.press('Escape');
  await tunggu(page, 400);
};
/** Buka modal Filament dengan klik; hanya sah bila jendela modal benar-benar muncul. */
const bukaModal = (loc) => async (page) => {
  if (!(await klikBila(page, loc(page)))) return false; // tombol nonaktif pada contoh terisi
  const ada = await page.locator('.fi-modal-window').first().isVisible().catch(() => false);
  return ada;
};
// Daftar MK tidak punya tautan Ubah; id rekaman dibaca dari wire:key baris pertama.
const keMkEdit = async (page, awalan) => {
  const kunci = await page.locator('table tbody tr[wire\\:key*="records."]').first().getAttribute('wire:key').catch(() => null);
  const id = kunci?.match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/)?.[0];
  if (!id) return false;
  const r = await page.goto(`${BASIS}${awalan}/mks/${id}/edit`, { waitUntil: 'networkidle' });
  return r.status() < 400;
};
// Koordinator MK: halaman penilaian/matriks baru terbuka (bukan 403) setelah satu MK "Dikerjakan".
// Itu hanya mengisi sesi peladen (MkTerpilih), bukan data akademik; tombolnya aktif juga pada contoh terisi.
const kerjakanMk = async (page, awalan) => {
  await page.goto(`${BASIS}${awalan}/mata-kuliah-koordinator`, { waitUntil: 'networkidle' });
  await klikBila(page, page.locator('a,button').filter({ hasText: /Kerjakan/i }).first());
  await tunggu(page, 3000);
};
// Tombol Keluar hanya ikon bertooltip; kaitnya atribut x-tooltip, bukan nama aksesibel.
const KELUAR = (page) => page.locator(`button[x-tooltip*="'Keluar'"]:visible`);

const UMUM = [
  { slug: 'dasbor', url: '/dashboard' },
  { slug: 'pilih-peran', url: '/pilih-peran-unit' },
  { slug: 'modal-keluar', url: '/dashboard', buka: bukaModal(KELUAR), tutup: tutupModal },
];
const PERAN = {
  'superadmin': { peran: null, halaman: [...UMUM,
    { slug: 'unit-akademik', url: '/academic-units' }, { slug: 'pengguna', url: '/users' },
    { slug: 'semester', url: '/semesters' }, { slug: 'simulasi', url: '/simulasi' }, { slug: 'log-aktivitas', url: '/activity-logs' }] },
  // Akun nyata (data dev): contoh terisi sim-* memblokir halaman ubah (403), jadi Edit MK hanya terjangkau di sini.
  'timkur': { peran: null, halaman: [...UMUM,
    { slug: 'mk-daftar', url: '/mks' }, { slug: 'mk-edit', url: '/mks', buka: keMkEdit },
    { slug: 'mk-edit-detail', url: '/mks', buka: async (p, a) => (await keMkEdit(p, a)) && klikBila(p, p.getByRole('button', { name: /^Detail$/ })) },
    { slug: 'kurikulum-daftar', url: '/kurikulums' }, { slug: 'matriks-cpl-mk', url: '/interaksi/cpl-mk' },
    // timkur nyata juga berperan Validator Nilai (akun `korma` nyata tidak punya peran apa pun -> 403 sah, dihapus dari daftar).
    { slug: 'validasi-nilai', url: '/penilaian/validasi-nilai' }] },
  'sim-adminprodi': { peran: 'Admin', halaman: [...UMUM, { slug: 'kelas-mk', url: '/kelas-mks' }, { slug: 'kelas-mk-form', url: '/kelas-mks/create' }] },
  'sim-timkur': { peran: null, halaman: [...UMUM,
    { slug: 'kurikulum-daftar', url: '/kurikulums' }, { slug: 'cpl', url: '/cpls' },
    { slug: 'modal-impor-cpl', url: '/cpls', buka: bukaModal((p) => p.getByRole('button', { name: /Impor massal/i })), tutup: tutupModal },
    { slug: 'mk-daftar', url: '/mks' }, { slug: 'mk-edit', url: '/mks', buka: keMkEdit },
    { slug: 'mk-edit-detail', url: '/mks', buka: async (p, a) => (await keMkEdit(p, a)) && klikBila(p, p.getByRole('button', { name: /^Detail$/ })) },
    { slug: 'penawaran-mk', url: '/mk-units' }, { slug: 'matriks-cpl-mk', url: '/interaksi/cpl-mk' },
    { slug: 'matriks-profil-cpl', url: '/interaksi/profil-cpl' }, { slug: 'analisis-mk-prodi', url: '/laporan/analisis-mk-prodi' },
    { slug: 'usulan-cpmk', url: '/usulan-perubahan-cpmk' },
    { slug: 'modal-tolak-usulan', url: '/usulan-perubahan-cpmk', buka: bukaModal((p) => p.getByRole('button', { name: /^Tolak$/ })), tutup: tutupModal }] },
  'sim-korma': { peran: null, halaman: [...UMUM,
    { slug: 'mk-koordinator', url: '/mata-kuliah-koordinator' }, { slug: 'cpmk', url: '/cpmk', pra: kerjakanMk }, { slug: 'subcpmk', url: '/subcpmk', pra: kerjakanMk },
    { slug: 'komponen-penilaian', url: '/komponen-penilaian', pra: kerjakanMk }, { slug: 'matriks-cpl-cpmk', url: '/interaksi/cpl-cpmk', pra: kerjakanMk },
    { slug: 'laporan-koordinator', url: '/penilaian/laporan-koordinator', pra: kerjakanMk }] },
  'sim-dosen': { peran: null, halaman: [...UMUM,
    { slug: 'kelas-diampu', url: '/penilaian' }, { slug: 'input-nilai', url: '/penilaian/input-nilai' }] },
  'sim-validator': { peran: null, halaman: [...UMUM,
    { slug: 'validasi-nilai', url: '/penilaian/validasi-nilai' },
    { slug: 'validasi-nilai-laporan', url: '/penilaian/validasi-nilai', buka: async (p) => {
      if (!(await klikBila(p, p.locator('[data-silogy="validasi-nilai-daftar"] tbody tr:first-child').getByRole('button', { name: /^Lihat$/ })))) return false;
      await p.locator('[data-silogy="validasi-nilai-laporan"]').waitFor({ timeout: 15_000 }).catch(() => {});
      return true;
    } }] },
  'sim-kaprodi': { peran: null, halaman: [...UMUM,
    { slug: 'kurikulum-pimpinan', url: '/laporan/kurikulums' }, { slug: 'hasil-cpl', url: '/laporan/hasil-analisis-cpl' },
    { slug: 'grafik-cpl', url: '/laporan/grafik-cpl' }, { slug: 'cpl-mahasiswa', url: '/laporan/analisis-cpl-mahasiswa' }] },
  'sim-auditor': { peran: null, halaman: [...UMUM, { slug: 'log-aktivitas', url: '/activity-logs' }, { slug: 'analisis-ai', url: '/ai-analisis' }] },
};

// ── Mode gelap ───────────────────────────────────────────────────────────
// Filament menyimpan tema di localStorage `theme`; skrip kepala menambah kelas `dark`.
async function aktifkanGelap(ctx, page) {
  await ctx.addInitScript(() => { try { localStorage.setItem('theme', 'dark'); } catch (e) { /* mode privat */ } });
  await page.emulateMedia({ colorScheme: 'dark' });
}
const gelapAktif = (page) => page.evaluate(() => document.documentElement.classList.contains('dark'));

async function auditHalaman(page, h, awalan) {
  if (h.pra) await h.pra(page, awalan);
  const resp = await page.goto(BASIS + awalan + h.url, { waitUntil: 'networkidle' });
  if (resp && resp.status() >= 400) return { takTercapai: `HTTP ${resp.status()} (halaman galat/tanpa izin untuk peran ini)` };
  if (!(await gelapAktif(page))) {
    throw new Error(`mode gelap TIDAK aktif di ${page.url()} (kelas "dark" tidak ada di <html>)`);
  }
  if (h.buka) {
    const ok = await h.buka(page, awalan);
    if (!ok) return { takTercapai: 'langkah buka tidak berhasil (elemen/modal tidak ada)' };
    await page.waitForLoadState('networkidle').catch(() => {});
  }
  const url = new URL(page.url());
  if (!h.buka && url.pathname.replace(awalan, '') !== h.url && !url.pathname.endsWith(h.url)) {
    return { takTercapai: `dialihkan ke ${url.pathname}` };
  }
  await siapkanHalaman(page);
  await tunggu(page, 400);
  const r = await page.evaluate(auditarDom, {});
  if (h.tutup) await h.tutup(page);
  return r;
}

// ── Autotest ─────────────────────────────────────────────────────────────
const FIXTURE = `<style>
  #__fx input.pb::placeholder{color:#262626} #__fx input.pg::placeholder{color:#e5e5e5}
</style><div id="__fx">
  <div data-fx="buruk-putih-di-putih" style="background:#fff;color:#f5f5f5">teks hampir putih di putih</div>
  <div data-fx="buruk-gelap-di-gelap" style="background:#111;color:#2a2a2a">teks gelap di gelap</div>
  <div style="background:#1a1a1a"><div data-fx="buruk-semitransparan" style="background:rgba(255,255,255,.08);color:#333">gelap di atas putih transparan</div></div>
  <div style="background:#000"><span data-fx="buruk-opasitas" style="color:#fff;opacity:.3">putih pudar opasitas</span></div>
  <div style="background:#fff"><span data-fx="buruk-kecil-abu" style="color:#999;font-size:14px">abu 14px</span></div>
  <div data-fx="buruk-oklch" style="background:#111;color:oklch(0.32 0 0)">oklch gelap di gelap</div>
  <div data-fx="buruk-color-mix" style="background:color-mix(in srgb, #fff 90%, #000);color:#eee">putih di color-mix terang</div>
  <div data-fx="buruk-color-srgb" style="background:#111;color:color(srgb 0.15 0.15 0.15)">color(srgb) gelap</div>
  <input data-fx="buruk-placeholder" class="pb" placeholder="placeholder gelap" style="background:#111;border:0">
  <input data-fx="buruk-nilai-input" value="isi gelap" style="background:#111;color:#2a2a2a;border:0">
  <select data-fx="buruk-select" style="background:#111;color:#2a2a2a;border:0"><option>pilihan gelap</option></select>
  <div data-fx="buruk-text-fill" style="background:#111;color:#fff;-webkit-text-fill-color:#262626">fill gelap</div>
  <div data-fx="buruk-gambar-url" style="background:#111 url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%221%22 height=%221%22/%3E') no-repeat right;color:#2a2a2a">gelap di gelap dengan ikon url</div>
  <div data-fx="baik-terang-di-gelap" style="background:#111;color:#fff">putih di gelap</div>
  <div data-fx="baik-gelap-di-terang" style="background:#fff;color:#111">gelap di putih</div>
  <div style="background:#1a1a1a"><div data-fx="baik-semitransparan" style="background:rgba(255,255,255,.08);color:#eee">terang di atas semitransparan</div></div>
  <div style="background:#000"><span data-fx="baik-opasitas" style="color:#fff;opacity:.9">putih opasitas .9</span></div>
  <div style="background:#fff"><span data-fx="baik-besar" style="color:#777;font-size:26px">besar #777 (4.48 tapi besar)</span></div>
  <div data-fx="baik-oklch" style="background:#111;color:oklch(0.95 0 0)">oklch terang di gelap</div>
  <div data-fx="baik-color-mix" style="background:color-mix(in srgb, #fff 90%, #000);color:#111">gelap di color-mix terang</div>
  <input data-fx="baik-placeholder" class="pg" placeholder="placeholder terang" style="background:#111;border:0">
  <input data-fx="baik-nilai-input" value="isi terang" style="background:#111;color:#eee;border:0">
  <select data-fx="baik-select" style="background:#111;color:#eee;border:0"><option>pilihan terang</option></select>
  <div data-fx="baik-ikon-kecil" style="background:#111 url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%221%22 height=%221%22/%3E') no-repeat right .5rem center / 16px 16px;color:#fff;height:40px">terang di gelap, ikon kecil</div>
  <div data-fx="baik-gambar-lebar" style="background:#111 url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%221%22 height=%221%22/%3E') center / cover;color:#fff;height:40px">terang di gelap, gambar lebar</div>
  <div data-fx="baik-gambar-url" style="background:#111 url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%221%22 height=%221%22/%3E') no-repeat right;color:#fff">terang di gelap dengan ikon url</div>
  <div style="width:80px;height:20px;overflow:auto;background:#111"><div data-fx="baik-gulir" style="margin-top:60px;color:#fff;white-space:nowrap">di luar kotak tapi dapat digulir</div></div>
  <div style="background:#000"><span data-fx="tersembunyi-opasitas0" style="color:#111;opacity:0">tidak tampak</span><span data-fx="tersembunyi-display" style="display:none;color:#000">tidak tampak</span></div>
  <div style="width:80px;height:20px;overflow:hidden;background:#111"><div data-fx="tersembunyi-terpotong" style="margin-top:60px;color:#222">terpotong hidden</div></div>
  <div data-fx="takTentu-gradien" style="background:linear-gradient(90deg,#fff,#000);color:#888">di atas gradien</div>
</div>`;

const BURUK = ['buruk-putih-di-putih', 'buruk-gelap-di-gelap', 'buruk-semitransparan', 'buruk-opasitas', 'buruk-kecil-abu',
  'buruk-oklch', 'buruk-color-mix', 'buruk-color-srgb', 'buruk-placeholder', 'buruk-nilai-input', 'buruk-select', 'buruk-text-fill', 'buruk-gambar-url'];
const BAIK = ['baik-terang-di-gelap', 'baik-gelap-di-terang', 'baik-semitransparan', 'baik-opasitas', 'baik-besar', 'baik-oklch', 'baik-color-mix',
  'baik-placeholder', 'baik-nilai-input', 'baik-select', 'baik-gambar-url', 'baik-ikon-kecil', 'baik-gambar-lebar', 'baik-gulir', 'tersembunyi-opasitas0', 'tersembunyi-display', 'tersembunyi-terpotong', 'takTentu-gradien'];

async function autotest(browser) {
  // Halaman kosong: tidak bergantung pada aplikasi yang berjalan.
  const ctx = await browser.newContext({ viewport: VIEWPORT_UJI, colorScheme: 'dark' });
  const page = await ctx.newPage();
  await page.setContent(`<!doctype html><html class="dark"><body style="margin:0">${FIXTURE}</body></html>`);
  const r = await page.evaluate(auditarDom, { akar: '#__fx' });
  await ctx.close();

  const kena = new Map(r.achados.map((a) => [a.fx, a.rasio]));
  const tak = new Set(r.takTentu.map((a) => a.fx));
  const gagal = [];
  for (const k of BURUK) if (!kena.has(k)) gagal.push(`seharusnya DITANDAI: ${k}`);
  for (const k of BAIK) if (kena.has(k)) gagal.push(`seharusnya TIDAK ditandai: ${k} (rasio ${kena.get(k)})`);
  if (!tak.has('takTentu-gradien')) gagal.push('gradien seharusnya masuk daftar tak-tentu');
  const kelasGambar = new Map(r.bergambar.map((a) => [a.fx, a.gambarKelas]));
  if (kelasGambar.get('baik-ikon-kecil') !== 'ikon-kecil') gagal.push(`baik-ikon-kecil harus ikon-kecil (dapat ${kelasGambar.get('baik-ikon-kecil')})`);
  if (kelasGambar.get('baik-gambar-lebar') !== 'gambar-lebar') gagal.push(`baik-gambar-lebar harus gambar-lebar dan tampil di daftar cek visual (dapat ${kelasGambar.get('baik-gambar-lebar')})`);
  console.log('Autotest: latar bergambar lulus =', JSON.stringify(Object.fromEntries(kelasGambar)));
  for (const k of ['buruk-gambar-url', 'baik-gambar-url']) if (tak.has(k)) gagal.push(`${k}: url() tidak boleh tak-tentu`);
  console.log('Autotest: ditandai =', JSON.stringify(Object.fromEntries(kena)), '| tak-tentu =', [...tak].join(','));
  console.log(`Autotest: ${r.total} simpul teks diaudit pada fixture (${BURUK.length} buruk, ${BAIK.length} baik/tersembunyi)`);
  if (gagal.length) { gagal.forEach((x) => console.error('  GAGAL', x)); return 1; }
  console.log('Autotest LULUS: semua pola buruk ditandai, semua pola baik/tersembunyi tidak.');
  return 0;
}
const VIEWPORT_UJI = { width: 1440, height: 900 };

// ── Laporan ──────────────────────────────────────────────────────────────
function tulisLaporan(baru, dijalankan) {
  // Lari sebagian digabung dengan lari sebelumnya; hanya pasangan (peran, halaman) yang
  // benar-benar dijalankan kali ini yang menggantikan data lama.
  const kunci = (x) => `${x.peran}/${x.halaman}`;
  const berkas = path.join(DIR_KELUARAN, 'temuan.json');
  const lama = fs.existsSync(berkas) ? JSON.parse(fs.readFileSync(berkas, 'utf8')) : {};
  const luar = (x) => !dijalankan.has(kunci(x));
  const semua = [...(lama.temuan ?? []).filter(luar), ...baru.semua];
  const halamanLog = [...(lama.halaman ?? []).filter(luar), ...baru.halamanLog];
  const takTercapai = [...(lama.takTercapai ?? []).filter(luar), ...baru.takTercapai];
  const takTentu = [...(lama.takTentu ?? []).filter(luar), ...baru.takTentu];
  const bergambar = [...(lama.bergambar ?? []).filter(luar), ...baru.bergambar];
  const cekVisual = bergambar.filter((b) => b.gambarKelas === 'gambar-lebar');
  semua.sort((a, b) => a.rasio - b.rasio);
  fs.mkdirSync(DIR_KELUARAN, { recursive: true });
  const keluar = (a) => ({ halaman: a.halaman, peran: a.peran, selector: a.selector, teks: a.teks, warnaTeks: a.warnaTeks,
    latarEfektif: a.latarEfektif, rasio: a.rasio, ukuranFont: a.ukuranFont, tebal: a.tebal, grup: a.grup, latarSumber: a.latarSumber, sumber: a.sumber, sebab: a.sebab, adaGambar: a.adaGambar, gambarKelas: a.gambarKelas });
  fs.writeFileSync(path.join(DIR_KELUARAN, 'temuan.json'), JSON.stringify({
    dibuat: new Date().toISOString(), basis: BASIS, ringkas: { temuan: semua.length, takTentu: takTentu.length, takTercapai: takTercapai.length, bergambarLulus: bergambar.length, cekVisual: cekVisual.length },
    halaman: halamanLog, temuan: semua.map(keluar), takTentu, takTercapai, bergambar }, null, 2));

  const grup = new Map();
  for (const a of semua) {
    const k = a.grup;
    const g = grup.get(k) ?? { k, n: 0, min: 99, halaman: new Set(), contoh: a };
    g.n++; g.min = Math.min(g.min, a.rasio); g.halaman.add(`${a.peran}/${a.halaman}`); grup.set(k, g);
  }
  const esc = (s) => String(s).replace(/\|/g, '\\|');
  // Contoh ringkas terburuk: paling banyak 3 per kelompok sebab, supaya satu sebab tak memenuhi tabel.
  const hitung = new Map();
  const ringkas = semua.filter((a) => { const n = (hitung.get(a.grup) ?? 0) + 1; hitung.set(a.grup, n); return n <= 3; }).slice(0, 100);
  const L = [`# Audit kontras mode gelap`, '', `Dibuat ${new Date().toISOString()} terhadap ${BASIS}. Total temuan: **${semua.length}** (rasio < 4.5, teks besar < 3). Tak-tentu (gradien): ${takTentu.length}.`, '',
    `**Halaman diaudit: ${halamanLog.length}. Halaman TAK TERCAPAI: ${takTercapai.length}** (bukan berarti bersih; lihat bagian tak tercapai). Latar bergambar yang LULUS: ${bergambar.length} simpul (${cekVisual.length} berlatar gambar-lebar, perlu cek visual).`, '',
    '## Simpul teks per halaman', '', '| Peran | Halaman | Diaudit | Temuan | Dilewati |', '|---|---|---|---|---|',
    ...halamanLog.map((h) => `| ${h.peran} | ${h.halaman} | ${h.diaudit} | ${h.temuan} | ${h.dilewati} |`), '',
    '## Halaman tak tercapai', '', ...(takTercapai.length ? takTercapai.map((t) => `- ${t.peran}/${t.halaman}: ${t.alasan}`) : ['(tidak ada)']), '',
    '## Temuan dikelompokkan menurut sebab', '', '| # | Sebab (inline/kelas) dan warna | Jumlah | Rasio min | Halaman | Contoh |', '|---|---|---|---|---|---|',
    ...[...grup.values()].sort((a, b) => a.min - b.min).map((g, i) => `| ${i + 1} | ${esc(g.k)} | ${g.n} | ${g.min} | ${esc([...g.halaman].slice(0, 6).join(', '))}${g.halaman.size > 6 ? ' …' : ''} | ${esc(g.contoh.selector)} "${esc(g.contoh.teks)}" |`), '',
    '## Temuan terburuk (maks. 3 per kelompok sebab, 100 baris)', '', '| Rasio | Peran | Halaman | Teks | Warna teks | Latar | px/tebal | img | Selector |', '|---|---|---|---|---|---|---|---|---|',
    ...ringkas.map((a) => `| ${a.rasio} | ${a.peran} | ${a.halaman} | ${esc(a.teks)} | ${a.warnaTeks} | ${a.latarEfektif} | ${a.ukuranFont}/${a.tebal} | ${a.gambarKelas ?? ''} | ${esc(a.selector)} |`), '',
    '## Perlu cek visual (latar bergambar)', '', 'Teks lulus menurut warna latar, tetapi ada `url()` lebar di belakangnya (rasio tak memperhitungkan gambar). Ikon kecil (mis. panah select) dihitung terpisah dan tidak didaftar.', '',
    ...(cekVisual.length ? (() => {
      const m = new Map();
      for (const b of cekVisual) { const k = `${b.gambarDi} | ${b.sebab}`; const g = m.get(k) ?? { k, n: 0, h: new Set(), c: b }; g.n++; g.h.add(`${b.peran}/${b.halaman}`); m.set(k, g); }
      return [...m.values()].map((g) => `- ${g.n}x ${esc(g.k)} — contoh "${esc(g.c.teks)}" (${esc([...g.h].slice(0, 4).join(', '))})`);
    })() : ['(tidak ada)']), '',
    '## Tak-tentu (gradien/background-clip:text; rasio tidak ditebak)', '', ...(takTentu.length ? takTentu.slice(0, 60).map((t) => `- ${t.peran}/${t.halaman}: ${t.selector} "${t.teks}" di atas ${t.gradien}`) : ['(tidak ada)']), '',
    '## Keterbatasan', '',
    '- `<select>` native: popup opsi tidak terjangkau DOM, hanya nilai terpilih yang diaudit.',
    '- Pseudo-elemen (::before/::after, ikon CSS), bayangan teks, gambar, dan teks di dalam kanvas/grafik tidak diaudit.',
    '- Opasitas leluhur dimodelkan sebagai pengali alfa tiap lapis (aproksimasi grup opasitas).',
    '- Latar `kanvas-putih` berarti tak ada leluhur opak; pada mode gelap itu patut dicurigai.',
    '- Keadaan hover/fokus/animasi dimatikan; hanya keadaan diam yang diaudit.',
    '- Konten di dalam kontainer overflow auto/scroll diaudit walau di luar kotak tampak (dijangkau dengan menggulir); hanya overflow hidden/clip yang menyembunyikan. Teks yang terpotong oleh hidden/clip tidak diaudit.',
    '- `background-image: url(...)` tetap dihitung dengan warna latar (kolom adaGambar di JSON); hanya `*-gradient()` dan background-clip:text yang tak-tentu.'];
  fs.writeFileSync(path.join(DIR_KELUARAN, 'laporan.md'), L.join('\n') + '\n');
  return { semua, halamanLog, takTercapai, takTentu };
}

// ── Utama ────────────────────────────────────────────────────────────────
if (process.argv.includes('--help') || process.argv.includes('-h')) {
  console.log(`Audit kontras mode gelap SILOGY (WCAG AA)
Pakai: APP_URL=http://localhost:8017 node scripts/audit-kontras-gelap.mjs [opsi]
  --peran=AKUN      hanya satu akun (${Object.keys(PERAN).join(', ')})
  --halaman=SLUG    hanya halaman ber-slug itu (di semua akun yang memilikinya)
  --autotest        uji fungsi audit pada fixture (tanpa aplikasi); gagal bila ada regresi
  --help, -h        pesan ini
Kode keluar: 0 bersih / autotest lulus; 1 ada temuan; 2 galat (termasuk --peran/--halaman tak cocok);
             3 tanpa temuan tetapi ada halaman tak tercapai (belum bisa disebut bersih).
Keluaran: .superpowers/sdd/2026-11-24-kontras-mode-gelap/audit/{temuan.json,laporan.md}`);
  process.exit(0);
}
const arg = (n) => process.argv.find((a) => a.startsWith(`--${n}=`))?.split('=')[1];
async function utama() {
  const browser = await chromium.launch();
  try {
    if (process.argv.includes('--autotest')) return await autotest(browser);

    const fPeran = arg('peran'), fHalaman = arg('halaman');
    if (fHalaman && !Object.values(PERAN).some((r) => r.halaman.some((h) => h.slug === fHalaman))) {
      throw new Error(`halaman tidak dikenal: ${fHalaman}`);
    }
    const akunDaftar = Object.keys(PERAN).filter((a) => !fPeran || a === fPeran);
    if (!akunDaftar.length) throw new Error(`peran tidak dikenal: ${fPeran}. Pilihan: ${Object.keys(PERAN).join(', ')}`);

    const semua = [], halamanLog = [], takTercapai = [], takTentu = [], bergambar = [], dijalankan = new Set();
    for (const akun of akunDaftar) {
      const daftar = PERAN[akun].halaman.filter((h) => !fHalaman || h.slug === fHalaman);
      if (!daftar.length) continue;
      daftar.forEach((h) => dijalankan.add(`${akun}/${h.slug}`));
      let konteks;
      try {
        konteks = await konteksUntuk(browser, akun, PERAN[akun].peran);
      } catch (e) {
        daftar.forEach((h) => takTercapai.push({ peran: akun, halaman: h.slug, alasan: `gagal masuk: ${e.message}` }));
        console.error(`✗ ${akun}: gagal masuk: ${e.message}`);
        continue;
      }
      const { ctx, page, awalan = '' } = konteks;
      page.setDefaultTimeout(15_000);
      page.setDefaultNavigationTimeout(30_000);
      await aktifkanGelap(ctx, page);
      for (const h of daftar) {
        try {
          const r = await auditHalaman(page, h, awalan);
          if (r.takTercapai) {
            takTercapai.push({ peran: akun, halaman: h.slug, alasan: r.takTercapai });
            console.log(`– ${akun}/${h.slug}: tak tercapai (${r.takTercapai})`);
            continue;
          }
          const unik = new Set();
          for (const a of r.achados) {
            const k = `${a.selector}|${a.teks}|${a.rasio}`;
            if (unik.has(k)) continue;
            unik.add(k);
            semua.push({ ...a, halaman: h.slug, peran: akun, grup: `${a.sumber === 'teks' ? '' : `[${a.sumber}] `}${a.sebab} | ${a.warnaTeks} on ${a.latarEfektif}` });
          }
          r.takTentu.forEach((t) => takTentu.push({ ...t, halaman: h.slug, peran: akun }));
          const ub = new Set();
          for (const b of r.bergambar) { const k = `${b.selector}|${b.teks}`; if (!ub.has(k)) { ub.add(k); bergambar.push({ halaman: h.slug, peran: akun, selector: b.selector, teks: b.teks, rasio: b.rasio, gambarKelas: b.gambarKelas, gambarDi: b.gambarDi, sebab: b.sebab }); } }
          halamanLog.push({ peran: akun, halaman: h.slug, diaudit: r.total, temuan: unik.size, dilewati: r.dilewati });
          const nol = r.total === 0 ? '  ← 0 simpul: halaman gagal dimuat?' : '';
          console.log(`✓ ${akun}/${h.slug}: ${r.total} simpul, ${unik.size} temuan${nol}`);
          if (r.total === 0) takTercapai.push({ peran: akun, halaman: h.slug, alasan: '0 simpul teks teraudit' });
        } catch (e) {
          if (/mode gelap TIDAK aktif/.test(e.message)) throw e;
          takTercapai.push({ peran: akun, halaman: h.slug, alasan: e.message.split('\n')[0] });
          console.log(`– ${akun}/${h.slug}: galat (${e.message.split('\n')[0]})`);
        }
      }
      await ctx.close();
    }
    if (!dijalankan.size) throw new Error(`tidak ada pasangan (peran, halaman) yang cocok dengan --peran=${fPeran ?? '*'} --halaman=${fHalaman ?? '*'}`);
    const g = tulisLaporan({ semua, halamanLog, takTercapai, takTentu, bergambar }, dijalankan);
    console.log(`\nSelesai: ${halamanLog.length} halaman diaudit, ${takTercapai.length} TAK TERCAPAI, ${semua.length} temuan, ${takTentu.length} tak-tentu (lari ini; gabungan: ${g.halamanLog.length} diaudit, ${g.takTercapai.length} tak tercapai, ${g.semua.length} temuan).`);
    if (takTercapai.length) console.error(`PERINGATAN: ${takTercapai.length} halaman tak tercapai pada lari ini; hasil bersih belum berarti aman.`);
    console.log(`Laporan: ${path.relative(AKAR, DIR_KELUARAN)}/laporan.md dan temuan.json`);
    // 1 = ada temuan, 3 = tanpa temuan tetapi ada halaman tak tercapai (belum bisa disebut bersih), 0 = bersih.
    return semua.length ? 1 : takTercapai.length ? 3 : 0;
  } finally {
    await browser.close();
  }
}

utama().then((k) => process.exit(k), (e) => { console.error('GALAT:', e.message); process.exit(2); });
