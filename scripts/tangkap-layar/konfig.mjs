import path from 'node:path';
import { fileURLToPath } from 'node:url';

const DIR = path.dirname(fileURLToPath(import.meta.url));

export const AKAR = path.resolve(DIR, '../..');
export const BASIS = (process.env.APP_URL ?? 'http://localhost:8017').replace(/\/$/, '');
export const DIR_ASET = path.join(AKAR, 'docs/user-manual/aset');
export const BERKAS_MANIFES = path.join(DIR_ASET, 'manifes.json');
export const DIR_SESI = path.join(DIR, '.state');
export const DIR_REGRESI = path.join(DIR, '.regresi');

/**
 * Lebar 1440 CSS px dengan deviceScaleFactor 1 — sama seperti tangkapan layar
 * asli yang sudah ada di manual (login.png, superadmin-dasbor.png). Skala 2
 * akan melipatgandakan ukuran repositori tanpa menambah keterbacaan.
 */
export const VIEWPORT = { width: 1440, height: 900 };
export const SKALA = 1;

export const SANDI = 'siliwangi';

/**
 * Animasi, transisi, dan kursor kedip dimatikan supaya dua kali penangkapan
 * pada keadaan yang sama menghasilkan berkas yang identik — tanpa ini setiap
 * penangkapan ulang menghasilkan diff biner meski tak ada yang berubah.
 */
export const GAYA_DETERMINISTIK = `
  *, *::before, *::after {
    animation: none !important;
    transition: none !important;
    scroll-behavior: auto !important;
  }
  * { caret-color: transparent !important; }
  ::-webkit-scrollbar { width: 0 !important; height: 0 !important; }
`;
