/**
 * Manifes adegan tangkapan layar.
 *
 * Berkas ini satu-satunya tempat selector antarmuka hidup. Ketika UI berubah,
 * yang perlu diperbaiki cuma di sini — koordinat angka merah pada tiap gambar
 * dihitung ULANG SENDIRI oleh jalankan.mjs dari selector `sorot`, bukan
 * digambar tangan. Itulah bedanya dengan 67 SVG tiruan yang dulu dipakai
 * manual ini: gambar tiruan menyimpang diam-diam dari aplikasi sebenarnya,
 * tangkapan layar tidak bisa.
 *
 * Bentuk satu adegan:
 *   nama     — nama berkas tanpa .png, sekaligus kunci di manifes
 *   akun     — username simulasi; kosongkan untuk halaman publik
 *   peran    — peran yang dipilih bila akun punya lebih dari satu
 *   tema     — 'light' | 'dark' untuk halaman publik
 *   url      — path relatif terhadap APP_URL
 *   siapkan  — async (page) untuk membuka modal, mengisi form, dsb
 *   bidik    — selector elemen yang dipotret; tanpa ini seluruh viewport
 *   penuh    — true untuk memotret seluruh halaman (hanya tanpa `bidik`)
 *   judul    — keterangan gambar di manual
 *   sorot    — [{ pilih, label, teks }] penunjuk bernomor
 *   topeng   — [{ pilih, jadi }] mengganti teks yang berubah tiap saat
 */

const SIDEBAR = '.fi-sidebar';
// Banner hijau "yang sedang dikerjakan". Kelasnya digenerate inline, jadi
// kaitnya memakai atribut data yang memang sudah dipakai CSS panel. Tim
// Kurikulum memilih KURIKULUM, Koordinator memilih MATA KULIAH — dua kait
// berbeda untuk komponen yang tampak serupa.
const BANNER_KURIKULUM = '[data-silogy="banner-kurikulum-header-panel"]';
const BANNER_MK = '[data-silogy="banner-mk-header-panel"]';

const TOPENG_WAKTU = [{ pilih: '[data-waktu-relatif]', jadi: '2 hari yang lalu' }];

/** Buka menu titik-tiga di kepala halaman. */
const bukaMenuKepala = async (page) => {
  const pemicu = page.locator('.fi-header .fi-dropdown-trigger').last();
  if (await pemicu.count()) {
    await pemicu.click();
    await page.waitForTimeout(350);
  }
};

/** Klik satu tombol bila ada; diamkan bila tidak, supaya adegan tidak gagal total. */
const klikBila = async (page, teks) => {
  const t = page.getByRole('button', { name: teks }).first();
  if (await t.count()) {
    await t.click();
    await page.waitForTimeout(500);
  }
};

export const ADEGAN = [
  // ══ Halaman publik ═══════════════════════════════════════════════
  // regresi: bukan gambar manual, melainkan pembanding untuk membuktikan
  // beranda tidak berubah saat layout publik disentuh. Ditulis ke direktori
  // terpisah yang tidak masuk git — dua tangkapan penuh ini ~2 MB.
  { nama: 'beranda-light', tema: 'light', url: '/', penuh: true, regresi: true, judul: 'Beranda SILOGY (mode terang)' },
  { nama: 'beranda-dark', tema: 'dark', url: '/', penuh: true, regresi: true, judul: 'Beranda SILOGY (mode gelap)' },
  {
    nama: 'login',
    tema: 'light',
    url: '/login',
    judul: 'Halaman masuk SILOGY',
    sorot: [
      { pilih: '[wire\\:model="data.login"]', label: 1, teks: 'Ketik <b>username</b>, email, NIDN, NIP, atau NUPTK.' },
      { pilih: '[wire\\:model="data.password"]', label: 2, teks: 'Ketik kata sandi.' },
      { pilih: 'button[type="submit"]', label: 3, teks: 'Klik <b>Masuk ke SILOGY</b>.' },
    ],
  },

  // ══ Super Admin ══════════════════════════════════════════════════
  { nama: 'superadmin-dasbor', akun: 'sim-superadmin', url: '/dashboard', judul: 'Dasbor Super Admin', topeng: TOPENG_WAKTU },
  { nama: 'sidebar-super-admin', akun: 'sim-superadmin', url: '/dashboard', bidik: SIDEBAR, judul: 'Menu Super Admin' },
  {
    nama: 'superadmin-unit',
    akun: 'sim-superadmin',
    url: '/academic-units',
    judul: 'Hirarki unit akademik',
    sorot: [{ pilih: 'table tbody tr:first-child', label: 1, teks: 'Unit tersusun berjenjang: universitas → fakultas → jurusan → program studi.' }],
  },
  { nama: 'superadmin-pengguna', akun: 'sim-superadmin', url: '/users', judul: 'Daftar pengguna' },
  {
    nama: 'tk-semester',
    akun: 'sim-superadmin',
    url: '/semesters',
    judul: 'Semester aktif diatur Super Admin',
    sorot: [{ pilih: 'table tbody tr:first-child', label: 1, teks: 'Hanya satu semester boleh berstatus <b>aktif</b>; seluruh kelas dan nilai mengikutinya.' }],
  },
  { nama: 'superadmin-simulasi', akun: 'sim-superadmin', url: '/simulasi', judul: 'Pusat Simulasi', topeng: TOPENG_WAKTU },
  { nama: 'superadmin-log', akun: 'sim-superadmin', url: '/activity-logs', judul: 'Log aktivitas', topeng: TOPENG_WAKTU },
  { nama: 'sidebar-admin', akun: 'sim-adminprodi', peran: 'Admin', url: '/dashboard', bidik: SIDEBAR, judul: 'Menu Admin Unit' },
  { nama: 'admin-dasbor', akun: 'sim-adminprodi', peran: 'Admin', url: '/dashboard', judul: 'Dasbor Admin Program Studi', topeng: TOPENG_WAKTU },
  { nama: 'tk-kelas-form', akun: 'sim-adminprodi', peran: 'Admin', url: '/kelas-mks/create', judul: 'Formulir kelas MK' },
  { nama: 'admin-kelas', akun: 'sim-adminprodi', peran: 'Admin', url: '/kelas-mks', judul: 'Daftar kelas MK' },

  // ══ Tim Kurikulum ════════════════════════════════════════════════
  { nama: 'sidebar-tim-kurikulum', akun: 'sim-timkur', url: '/dashboard', bidik: SIDEBAR, judul: 'Menu Tim Kurikulum' },
  { nama: 'timkur-dasbor', akun: 'sim-timkur', url: '/dashboard', judul: 'Dasbor Tim Kurikulum', topeng: TOPENG_WAKTU },
  {
    nama: 'tk-unit',
    akun: 'sim-timkur',
    url: '/cpls',
    bidik: BANNER_KURIKULUM,
    judul: 'Cakupan Tim Kurikulum mengikuti unit penugasan',
  },
  {
    nama: 'tk-banner-kurikulum',
    akun: 'sim-timkur',
    url: '/cpls',
    bidik: BANNER_KURIKULUM,
    judul: 'Banner kurikulum yang sedang dikerjakan',
    sorot: [{ pilih: 'button:has-text("Ganti"), a:has-text("Ganti")', label: 1, teks: 'Klik <b>Ganti</b> untuk berpindah ke kurikulum lain.' }],
  },
  {
    nama: 'tk-kurikulum-kartu',
    akun: 'sim-timkur',
    url: '/kurikulums',
    judul: 'Kartu kurikulum',
    sorot: [{ pilih: 'button:has-text("Sedang dikerjakan"), a:has-text("Sedang dikerjakan")', label: 1, teks: 'Kartu bertanda <b>Sedang dikerjakan</b> adalah kurikulum yang sedang Anda susun.' }],
  },
  {
    nama: 'tk-kurikulum-buat',
    akun: 'sim-timkur',
    url: '/kurikulums',
    judul: 'Tombol buat kurikulum',
    sorot: [{ pilih: 'a:has-text("Buat kurikulum"), button:has-text("Buat kurikulum")', label: 1, teks: 'Klik <b>Buat kurikulum</b>.' }],
  },
  {
    nama: 'tk-kurikulum-form',
    akun: 'sim-timkur',
    url: '/kurikulums/create',
    judul: 'Formulir kurikulum',
    sorot: [
      { pilih: '[wire\\:model="data.nama"]', label: 1, teks: 'Isi <b>Nama kurikulum</b>, mis. "Kurikulum OBE 2025".' },
      { pilih: '[wire\\:model="data.kode"]', label: 2, teks: 'Isi <b>Kode</b> yang unik di unit Anda.' },
      { pilih: '[wire\\:model="data.tahun"]', label: 3, teks: 'Isi <b>Tahun berlaku</b>.' },
      { pilih: 'button[type="submit"]', label: 4, teks: 'Klik <b>Simpan</b>.' },
    ],
  },
  { nama: 'tk-kurikulum-target', akun: 'sim-timkur', url: '/kurikulums/create', judul: 'Penggeser target capaian lulusan' },
  { nama: 'tk-kurikulum-aktif', akun: 'sim-timkur', url: '/kurikulums/create', judul: 'Sakelar kurikulum aktif' },
  { nama: 'tk-profil-buat', akun: 'sim-timkur', url: '/profil-lulusan', judul: 'Daftar profil lulusan' },
  { nama: 'tk-profil-daftar', akun: 'sim-timkur', url: '/profil-lulusan', judul: 'Profil lulusan yang sudah tersimpan' },
  {
    nama: 'tk-profil-form',
    akun: 'sim-timkur',
    url: '/profil-lulusan/create',
    judul: 'Formulir profil lulusan',
    sorot: [
      { pilih: '[wire\\:model="data.kode"]', label: 1, teks: 'Isi <b>Kode</b> profil, mis. PL-01.' },
      { pilih: '[wire\\:model="data.nama"]', label: 2, teks: 'Tulis <b>nama profil lulusan</b>.' },
      { pilih: 'button[type="submit"]', label: 3, teks: 'Klik <b>Simpan</b>.' },
    ],
  },
  {
    nama: 'tk-profil-indikator',
    akun: 'sim-timkur',
    url: '/profil-lulusan/create',
    judul: 'Indikator profil lulusan',
    sorot: [
      { pilih: '.fi-fo-repeater', label: 1, teks: 'Tiap profil dirinci menjadi beberapa <b>indikator</b> yang terukur.' },
    ],
  },
  {
    nama: 'tk-cpl-form',
    akun: 'sim-timkur',
    url: '/cpls/create',
    judul: 'Formulir CPL',
    sorot: [
      { pilih: '[wire\\:model="data.kode"]', label: 1, teks: 'Isi <b>Kode CPL</b>, mis. CPL-01.' },
      { pilih: 'button[type="submit"]', label: 2, teks: 'Klik <b>Simpan</b>.' },
    ],
  },
  { nama: 'tk-cpl-alias', akun: 'sim-timkur', url: '/cpls', judul: 'CPL adaptasi dari unit induk' },
  { nama: 'tk-bok-form', akun: 'sim-timkur', url: '/boks/create', judul: 'Formulir bahan kajian (BoK)' },
  { nama: 'tk-bok-adaptasi', akun: 'sim-timkur', url: '/boks', judul: 'BoK adaptasi dari unit induk' },
  { nama: 'tk-mk-form', akun: 'sim-timkur', url: '/mks/create', judul: 'Formulir mata kuliah' },
  { nama: 'tk-mk-koordinator', akun: 'sim-timkur', url: '/mks/create', judul: 'Memilih Koordinator MK' },
  { nama: 'tk-mk-simpan', akun: 'sim-timkur', url: '/mks', judul: 'Daftar mata kuliah' },
  { nama: 'tk-penawaran-buat', akun: 'sim-timkur', url: '/mk-units', judul: 'Daftar penawaran MK' },
  { nama: 'tk-penawaran-form', akun: 'sim-timkur', url: '/mk-units/create', judul: 'Formulir penawaran MK' },
  { nama: 'tk-penawaran-unik', akun: 'sim-timkur', url: '/mk-units/create', judul: 'Satu MK hanya boleh ditawarkan sekali per kurikulum' },
  { nama: 'tk-matriks-profil-cpl', akun: 'sim-timkur', url: '/interaksi/profil-cpl', judul: 'Matriks Profil Lulusan × CPL' },
  { nama: 'tk-matriks-cpl-bok', akun: 'sim-timkur', url: '/interaksi/cpl-bok', judul: 'Matriks CPL × BoK' },
  { nama: 'matriks-cpl-mk', akun: 'sim-timkur', url: '/interaksi/cpl-mk', judul: 'Matriks CPL × Mata Kuliah' },
  { nama: 'tk-analisis', akun: 'sim-timkur', url: '/laporan/analisis-mk-prodi', judul: 'Analisis MK program studi' },
  {
    nama: 'tk-impor',
    akun: 'sim-timkur',
    url: '/cpls',
    siapkan: (page) => klikBila(page, /Impor massal/i),
    judul: 'Impor massal CPL',
  },
  { nama: 'usulan-cpmk', akun: 'sim-timkur', url: '/usulan-perubahan-cpmk', judul: 'Kotak masuk usulan perubahan CPMK', topeng: TOPENG_WAKTU },
  {
    nama: 'tk-usulan-setujui',
    akun: 'sim-timkur',
    url: '/usulan-perubahan-cpmk',
    judul: 'Menyetujui usulan perubahan CPMK',
    topeng: TOPENG_WAKTU,
    sorot: [
      { pilih: 'button:has-text("Setujui")', label: 1, teks: 'Klik <b>Setujui</b> untuk membuka kunci CPMK bagi Koordinator.' },
      { pilih: 'button:has-text("Tolak")', label: 2, teks: 'Klik <b>Tolak</b> bila usulan belum beralasan cukup.' },
    ],
  },
  {
    nama: 'tk-usulan-tolak',
    akun: 'sim-timkur',
    url: '/usulan-perubahan-cpmk',
    siapkan: async (page) => {
      const tolak = page.getByRole('button', { name: /^Tolak$/ }).first();
      if (await tolak.count()) { await tolak.click(); await page.waitForTimeout(700); }
    },
    bidik: '.fi-modal-window',
    judul: 'Menolak usulan wajib disertai alasan',
    topeng: TOPENG_WAKTU,
  },
  {
    nama: 'tk-usulan-detail',
    akun: 'sim-timkur',
    url: '/usulan-perubahan-cpmk',
    siapkan: async (page) => {
      const baris = page.locator('table tbody tr a, table tbody tr').first();
      if (await baris.count()) { await baris.click(); await page.waitForLoadState('networkidle'); }
    },
    judul: 'Detail usulan dan potret CPMK berjalan',
    topeng: TOPENG_WAKTU,
  },

  // ══ Koordinator MK ═══════════════════════════════════════════════
  { nama: 'sidebar-koordinator', akun: 'sim-korma', url: '/dashboard', bidik: SIDEBAR, judul: 'Menu Koordinator MK' },
  { nama: 'korma-dasbor', akun: 'sim-korma', url: '/dashboard', judul: 'Dasbor Koordinator MK', topeng: TOPENG_WAKTU },
  {
    nama: 'km-siapa',
    akun: 'sim-korma',
    url: '/mata-kuliah-koordinator',
    judul: 'Mata kuliah yang Anda koordinasikan',
    sorot: [{ pilih: 'a:has-text("Kerjakan"), button:has-text("Kerjakan")', label: 1, teks: 'Klik <b>Kerjakan</b> pada MK yang ingin Anda susun.' }],
  },
  { nama: 'korma-pilih-mk', akun: 'sim-korma', url: '/mata-kuliah-koordinator', judul: 'Memilih mata kuliah' },
  { nama: 'km-banner-mk', akun: 'sim-korma', url: '/cpmk', bidik: BANNER_MK, judul: 'Banner MK dan semester terpilih' },
  { nama: 'km-pilih-semester', akun: 'sim-korma', url: '/cpmk', bidik: BANNER_MK, judul: 'Semester yang sedang dikerjakan' },
  { nama: 'km-cpmk-list', akun: 'sim-korma', url: '/cpmk', judul: 'Daftar CPMK semester berjalan' },
  {
    nama: 'km-cpmk-form',
    akun: 'sim-korma',
    url: '/cpmk/create',
    judul: 'Formulir CPMK',
    sorot: [
      { pilih: '[wire\\:model="data.kode"]', label: 1, teks: 'Isi <b>Kode CPMK</b>.' },
      { pilih: 'button[type="submit"]', label: 2, teks: 'Klik <b>Simpan</b>.' },
    ],
  },
  { nama: 'km-sub-list', akun: 'sim-korma', url: '/subcpmk', judul: 'Daftar Sub-CPMK' },
  {
    nama: 'km-sub-form',
    akun: 'sim-korma',
    url: '/subcpmk/create',
    judul: 'Formulir Sub-CPMK',
    sorot: [
      { pilih: '[wire\\:model="data.kode"]', label: 1, teks: 'Isi <b>Kode Sub-CPMK</b>.' },
      { pilih: 'button[type="submit"]', label: 2, teks: 'Klik <b>Simpan</b>.' },
    ],
  },
  { nama: 'korma-asesmen', akun: 'sim-korma', url: '/komponen-penilaian', judul: 'Daftar komponen asesmen' },
  {
    nama: 'km-asesmen-form',
    akun: 'sim-korma',
    url: '/komponen-penilaian/create',
    judul: 'Formulir komponen asesmen',
    sorot: [
      { pilih: '[wire\\:model="data.kode"]', label: 1, teks: 'Isi <b>Kode komponen</b>, mis. UTS.' },
      { pilih: 'button[type="submit"]', label: 2, teks: 'Klik <b>Simpan</b>.' },
    ],
  },
  { nama: 'km-asesmen-100', akun: 'sim-korma', url: '/komponen-penilaian', judul: 'Total bobot asesmen harus tepat 100%' },
  { nama: 'km-matriks-cpl-cpmk', akun: 'sim-korma', url: '/interaksi/cpl-cpmk', judul: 'Matriks CPL × CPMK' },
  { nama: 'km-matriks-sub-asesmen', akun: 'sim-korma', url: '/interaksi/subcpmk-asesmen', judul: 'Matriks Sub-CPMK × Asesmen' },
  { nama: 'km-mahasiswa', akun: 'sim-korma', url: '/peserta-kelas', judul: 'Peserta kelas' },
  { nama: 'km-usulan-status', akun: 'sim-korma', url: '/usulan-perubahan-cpmk', judul: 'Status usulan perubahan CPMK', topeng: TOPENG_WAKTU },
  {
    nama: 'km-reset',
    akun: 'sim-korma',
    url: '/cpmk',
    siapkan: async (page) => { await bukaMenuKepala(page); },
    judul: 'Menu reset satu semester',
  },

  // ══ Dosen Pengampu ═══════════════════════════════════════════════
  { nama: 'sidebar-dosen', akun: 'sim-dosen', url: '/dashboard', bidik: SIDEBAR, judul: 'Menu Dosen Pengampu' },
  { nama: 'dosen-dasbor', akun: 'sim-dosen', url: '/dashboard', judul: 'Dasbor Dosen Pengampu', topeng: TOPENG_WAKTU },
  { nama: 'dosen-pengampu', akun: 'sim-dosen', url: '/penilaian', judul: 'Kelas yang Anda ampu' },
  {
    nama: 'dosen-input-nilai',
    akun: 'sim-dosen',
    url: '/penilaian/input-nilai',
    judul: 'Halaman input nilai',
    sorot: [
      { pilih: 'table', label: 1, teks: 'Tiap baris satu mahasiswa; tiap kolom satu <b>komponen penilaian</b>.' },
    ],
  },

  // ══ Pimpinan ═════════════════════════════════════════════════════
  { nama: 'sidebar-pimpinan', akun: 'sim-kaprodi', url: '/dashboard', bidik: SIDEBAR, judul: 'Menu Pimpinan' },
  { nama: 'pimpinan-dasbor', akun: 'sim-kaprodi', url: '/dashboard', judul: 'Dasbor Pimpinan', topeng: TOPENG_WAKTU },
  { nama: 'pimpinan-kurikulum', akun: 'sim-kaprodi', url: '/laporan/kurikulums', judul: 'Daftar kurikulum untuk Pimpinan' },
  { nama: 'pimpinan-hasil-cpl', akun: 'sim-kaprodi', url: '/laporan/hasil-analisis-cpl', judul: 'Hasil analisis CPL' },
  { nama: 'pimpinan-grafik-cpl', akun: 'sim-kaprodi', url: '/laporan/grafik-cpl', judul: 'Grafik capaian CPL' },
  { nama: 'pimpinan-mahasiswa', akun: 'sim-kaprodi', url: '/laporan/analisis-cpl-mahasiswa', judul: 'Analisis CPL per mahasiswa' },

  // ══ Auditor Mutu ═════════════════════════════════════════════════
  { nama: 'sidebar-auditor', akun: 'sim-auditor', url: '/dashboard', bidik: SIDEBAR, judul: 'Menu Auditor Mutu' },
  { nama: 'auditor-dasbor', akun: 'sim-auditor', url: '/dashboard', judul: 'Dasbor Auditor Mutu', topeng: TOPENG_WAKTU },
  {
    nama: 'auditor-log',
    akun: 'sim-auditor',
    url: '/activity-logs',
    judul: 'Log aktivitas sebagai bukti audit',
    topeng: TOPENG_WAKTU,
    sorot: [{ pilih: 'table tbody tr:first-child', label: 1, teks: 'Tiap baris mencatat <b>siapa</b> mengubah <b>apa</b> dan <b>kapan</b>.' }],
  },
  { nama: 'auditor-analisis', akun: 'sim-auditor', url: '/ai-analisis', judul: 'Riwayat analisis AI', topeng: TOPENG_WAKTU },
];
