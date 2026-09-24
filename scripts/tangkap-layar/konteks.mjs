import fs from 'node:fs';
import path from 'node:path';
import { BASIS, DIR_SESI, GAYA_DETERMINISTIK, SANDI, SKALA, VIEWPORT } from './konfig.mjs';

/**
 * Masuk lewat formulir sungguhan, bukan menyuntik sesi: halaman masuk itu
 * sendiri termasuk yang dipotret, dan jalur ini memastikan tangkapan layar
 * selalu mencerminkan alur yang benar-benar dilalui pengguna.
 */
async function masuk(page, akun) {
  await page.goto(`${BASIS}/login`, { waitUntil: 'networkidle' });
  await page.fill('[wire\\:model="data.login"]', akun);
  await page.fill('[wire\\:model="data.password"]', SANDI);
  await page.click('button[type="submit"]');

  // Livewire mengirim AJAX lebih dulu lalu baru mengalihkan halaman, jadi
  // menunggu satu peristiwa navigasi saja mudah meleset. Polling URL jauh
  // lebih tahan banting di sini daripada waitForURL.
  const tenggat = Date.now() + 30_000;
  while (Date.now() < tenggat) {
    if (!new URL(page.url()).pathname.endsWith('/login')) break;
    await page.waitForTimeout(250);
  }

  if (new URL(page.url()).pathname.endsWith('/login')) {
    const galat = await page
      .$$eval('.fi-fo-field-wrp-error-message', (n) => n.map((x) => x.textContent.trim()))
      .catch(() => []);
    throw new Error(`gagal masuk sebagai ${akun}${galat.length ? ': ' + galat.join('; ') : ''}`);
  }

  await page.waitForLoadState('networkidle');
}

/**
 * Akun multi-peran mendarat di "Pilih peran & unit". Pemilihan dilakukan di
 * sini supaya tiap adegan mulai dari keadaan yang sudah pasti.
 */
async function pilihPeranBila(page, peran) {
  if (!page.url().includes('pilih-peran-unit') || !peran) return;

  const kartu = page.locator(`text=${peran}`).first();
  if (await kartu.count()) {
    await kartu.click();
    await page.waitForLoadState('networkidle');
  }
}

/**
 * Beberapa layar baru berisi setelah "konteks kerja" dipilih: Koordinator MK
 * harus menekan Kerjakan pada satu mata kuliah lebih dulu, kalau tidak CPMK,
 * Sub-CPMK, dan Asesmen semuanya tampil kosong.
 *
 * Pemilihan itu tersimpan di sesi peladen, dan cookie sesinya ikut tersimpan
 * di storageState — jadi cukup sekali per akun, lalu bertahan untuk seluruh
 * adegan berikutnya.
 */
const PRASYARAT = {
  'sim-korma': async (page) => {
    // Pemilihan MK tersimpan di sesi peladen, jadi bisa saja sudah terpasang
    // dari penangkapan sebelumnya. Periksa dulu: tombol Kerjakan pada MK yang
    // SEDANG dikerjakan memang dinonaktifkan, dan menunggunya bisa diklik
    // adalah cara paling pasti untuk menggantung selamanya.
    await page.goto(`${BASIS}/cpmk`, { waitUntil: 'networkidle' });
    if (await page.locator('[data-silogy="banner-mk-header-panel"]').count()) return;

    await page.goto(`${BASIS}/mata-kuliah-koordinator`, { waitUntil: 'networkidle' });

    const tombol = page
      .locator('a:not([disabled]):not(.fi-disabled), button:not([disabled])')
      .filter({ hasText: /Kerjakan/i })
      .first();

    if (!(await tombol.count())) return;

    await tombol.click({ timeout: 10_000 }).catch(() => {});
    await page.waitForLoadState('networkidle').catch(() => {});
  },
};

const sudahDisiapkan = new Set();

async function penuhiPrasyarat(page, akun) {
  if (!PRASYARAT[akun] || sudahDisiapkan.has(akun)) return;
  await PRASYARAT[akun](page);
  sudahDisiapkan.add(akun);
}

export async function konteksUntuk(browser, akun, peran) {
  fs.mkdirSync(DIR_SESI, { recursive: true });
  const berkasSesi = path.join(DIR_SESI, `${akun}.json`);

  const opsi = {
    viewport: VIEWPORT,
    deviceScaleFactor: SKALA,
    colorScheme: 'light',
    locale: 'id-ID',
    timezoneId: 'Asia/Jakarta',
    reducedMotion: 'reduce',
  };

  if (fs.existsSync(berkasSesi)) {
    const ctx = await browser.newContext({ ...opsi, storageState: berkasSesi });
    const page = await ctx.newPage();
    await page.goto(`${BASIS}/dashboard`, { waitUntil: 'networkidle' });

    if (!page.url().includes('/login')) {
      await pilihPeranBila(page, peran);
      await penuhiPrasyarat(page, akun);
      return { ctx, page };
    }

    await ctx.close();
    fs.rmSync(berkasSesi, { force: true });
  }

  const ctx = await browser.newContext(opsi);
  const page = await ctx.newPage();
  await masuk(page, akun);
  await pilihPeranBila(page, peran);
  await ctx.storageState({ path: berkasSesi });
  await penuhiPrasyarat(page, akun);

  return { ctx, page };
}

export async function konteksTamu(browser, tema = 'light') {
  const ctx = await browser.newContext({
    viewport: VIEWPORT,
    deviceScaleFactor: SKALA,
    colorScheme: tema,
    locale: 'id-ID',
    timezoneId: 'Asia/Jakarta',
    reducedMotion: 'reduce',
  });
  await ctx.addInitScript((t) => {
    try { localStorage.setItem('silogy-theme', t); } catch (e) { /* mode privat */ }
  }, tema);

  return { ctx, page: await ctx.newPage() };
}

export async function siapkanHalaman(page) {
  await page.addStyleTag({ content: GAYA_DETERMINISTIK });
  await page.waitForTimeout(250);
}
