import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';
import { ADEGAN } from './adegan.mjs';
import { BASIS, BERKAS_MANIFES, DIR_ASET, DIR_REGRESI } from './konfig.mjs';
import { konteksTamu, konteksUntuk, siapkanHalaman } from './konteks.mjs';

const arg = (nama) => {
  const p = process.argv.find((a) => a.startsWith(`--${nama}=`));
  return p ? p.slice(nama.length + 3) : null;
};
const punya = (nama) => process.argv.includes(`--${nama}`);

const saring = arg('adegan')?.split(',').map((s) => s.trim()).filter(Boolean) ?? null;
const saringPeran = arg('peran');
const modePeriksa = punya('periksa');

const terpilih = ADEGAN.filter((a) => {
  if (saring && !saring.includes(a.nama)) return false;
  if (saringPeran && a.akun !== saringPeran) return false;
  return true;
});

/** Ganti teks yang berubah tiap saat agar diff git tetap kosong. */
async function pasangTopeng(page, topeng) {
  if (!topeng?.length) return;
  await page.evaluate((daftar) => {
    for (const { pilih, jadi } of daftar) {
      for (const el of document.querySelectorAll(pilih)) {
        el.textContent = jadi;
      }
    }
  }, topeng);
}

/** Ubah kotak batas selector menjadi titik pusat dalam persen terhadap area potret. */
async function hitungSorot(page, sorot, kotak) {
  const hasil = [];

  for (const [i, s] of (sorot ?? []).entries()) {
    const lokator = page.locator(s.pilih).first();

    if (!(await lokator.count())) {
      console.warn(`    ! sorot tidak ditemukan: ${s.pilih}`);
      continue;
    }

    const b = await lokator.boundingBox();
    if (!b) continue;

    const x = ((b.x + b.width / 2 - kotak.x) / kotak.width) * 100;
    const y = ((b.y + b.height / 2 - kotak.y) / kotak.height) * 100;

    if (x < 0 || x > 100 || y < 0 || y > 100) {
      console.warn(`    ! sorot di luar area potret: ${s.pilih}`);
      continue;
    }

    hasil.push({
      label: s.label ?? i + 1,
      x: Math.round(x * 10) / 10,
      y: Math.round(y * 10) / 10,
      teks: s.teks ?? '',
    });
  }

  return hasil;
}

async function jalankanAdegan(browser, adegan, manifes) {
  const { ctx, page } = adegan.akun
    ? await konteksUntuk(browser, adegan.akun, adegan.peran)
    : await konteksTamu(browser, adegan.tema ?? 'light');

  // Satu adegan yang menggantung tidak boleh menyandera seluruh penangkapan.
  page.setDefaultTimeout(15_000);
  page.setDefaultNavigationTimeout(30_000);

  try {
    await page.goto(BASIS + (adegan.url ?? '/'), { waitUntil: 'networkidle' });

    if (adegan.siapkan) await adegan.siapkan(page);

    await siapkanHalaman(page);
    await pasangTopeng(page, adegan.topeng);

    const sasaran = adegan.bidik ? page.locator(adegan.bidik).first() : page;
    const kotak = adegan.bidik
      ? await sasaran.boundingBox()
      : { x: 0, y: 0, ...page.viewportSize() };

    if (!kotak) throw new Error(`bidik tidak ditemukan: ${adegan.bidik}`);

    const berkas = `${adegan.nama}.png`;
    const tujuan = path.join(adegan.regresi ? DIR_REGRESI : DIR_ASET, berkas);
    fs.mkdirSync(path.dirname(tujuan), { recursive: true });

    await sasaran.screenshot({
      path: tujuan,
      scale: 'css',
      ...(adegan.bidik ? {} : { fullPage: Boolean(adegan.penuh) }),
    });

    const sorot = await hitungSorot(page, adegan.sorot, kotak);

    if (adegan.regresi) {
      console.log(`  ✓ ${berkas} (regresi, ${Math.round(fs.statSync(tujuan).size / 1024)} KB)`);
      return;
    }

    manifes.gambar[berkas] = {
      lebar: Math.round(kotak.width),
      tinggi: Math.round(kotak.height),
      judul: adegan.judul ?? '',
      akun: adegan.akun ?? null,
      sorot,
    };

    const kb = Math.round(fs.statSync(tujuan).size / 1024);
    console.log(`  ✓ ${berkas} (${kb} KB, ${sorot.length} sorot)`);

    if (kb > 400) console.warn(`    ! ${berkas} ${kb} KB — di atas anggaran 400 KB`);
  } finally {
    await ctx.close();
  }
}

/** Bandingkan nama gambar yang dirujuk Markdown dengan isi manifes. */
function periksa() {
  const manifes = fs.existsSync(BERKAS_MANIFES)
    ? JSON.parse(fs.readFileSync(BERKAS_MANIFES, 'utf8'))
    : { gambar: {} };

  const dirDocs = path.join(path.dirname(DIR_ASET));
  const dirujuk = new Set();

  for (const berkas of fs.readdirSync(dirDocs).filter((f) => f.endsWith('.md'))) {
    const isi = fs.readFileSync(path.join(dirDocs, berkas), 'utf8');
    for (const m of isi.matchAll(/!\[[^\]]*\]\(aset\/([^)]+)\)/g)) dirujuk.add(m[1]);
  }

  const ada = new Set(Object.keys(manifes.gambar ?? {}));
  const hilang = [...dirujuk].filter((n) => !fs.existsSync(path.join(DIR_ASET, n)));
  const yatim = [...ada].filter((n) => !dirujuk.has(n));

  if (hilang.length) {
    console.error(`\nDirujuk Markdown tapi berkasnya tidak ada (${hilang.length}):`);
    for (const n of hilang) console.error(`  - ${n}`);
  }
  if (yatim.length) {
    console.warn(`\nAda di manifes tapi tidak dirujuk Markdown mana pun (${yatim.length}):`);
    for (const n of yatim) console.warn(`  - ${n}`);
  }
  if (!hilang.length && !yatim.length) console.log('\nManifes dan Markdown sinkron.');

  process.exit(hilang.length ? 1 : 0);
}

if (modePeriksa) periksa();

console.log(`Menangkap ${terpilih.length} adegan dari ${BASIS}\n`);

const manifesLama = fs.existsSync(BERKAS_MANIFES)
  ? JSON.parse(fs.readFileSync(BERKAS_MANIFES, 'utf8'))
  : { versi: 1, gambar: {} };

const manifes = { versi: 1, lebarTangkap: 1440, gambar: { ...manifesLama.gambar } };
const browser = await chromium.launch();
let gagal = 0;

const batasAdegan = (janji, nama) =>
  Promise.race([
    janji,
    new Promise((_, tolak) =>
      setTimeout(() => tolak(new Error(`melewati batas 90 detik`)), 90_000)),
  ]);

for (const adegan of terpilih) {
  try {
    await batasAdegan(jalankanAdegan(browser, adegan, manifes), adegan.nama);
  } catch (e) {
    gagal += 1;
    console.error(`  ✗ ${adegan.nama}: ${e.message.split('\n')[0]}`);
  }
}

await browser.close();

manifes.dibuat = new Date().toISOString();
fs.mkdirSync(DIR_ASET, { recursive: true });
fs.writeFileSync(BERKAS_MANIFES, JSON.stringify(manifes, null, 2) + '\n');

console.log(`\nSelesai. ${terpilih.length - gagal} berhasil, ${gagal} gagal.`);
process.exit(gagal ? 1 : 0);
