# Tangkap layar manual SILOGY

Mengambil tangkapan layar **aplikasi yang sebenarnya** untuk panduan pengguna di
`docs/user-manual/`. Menggantikan 67 SVG tiruan antarmuka yang dulu digambar skrip
Python — gambar tiruan menyimpang diam-diam dari aplikasi, tangkapan layar tidak bisa.

## Menjalankan

```bash
# 1. Datanya harus ada lebih dulu, kalau tidak layar terpotret kosong.
docker compose exec app php artisan simulasi:buat

# 2. Peramban sudah ter-cache; perintah ini no-op bila versinya cocok.
npm install && npx playwright install chromium

# 3. Tangkap semuanya, atau sebagian saja.
npm run tangkap-layar   # bawaan http://localhost:8008; setel APP_URL bila instans di port/host lain
npm run tangkap-layar -- --adegan=tk-cpl-form,km-cpmk-list
npm run tangkap-layar -- --peran=sim-timkur

# 4. Pastikan manifes dan Markdown tidak saling meninggalkan.
npm run tangkap-layar -- --periksa
```

Biasakan menangkap **sebagian** (`--adegan=`) saat memperbaiki satu layar. Menangkap
semuanya menulis ulang ~76 berkas biner dan membuat diff git membengkak tanpa alasan.

## Berkas

| Berkas | Isi |
|---|---|
| `adegan.mjs` | **Satu-satunya tempat selector antarmuka hidup.** Daftar adegan. |
| `jalankan.mjs` | Runner: navigasi, potret, hitung koordinat, tulis manifes. |
| `konteks.mjs` | Masuk per akun, simpan sesi, penuhi prasyarat tiap peran. |
| `konfig.mjs` | Alamat, ukuran viewport, jalur keluaran, gaya deterministik. |

Keluaran: `docs/user-manual/aset/*.png` dan `docs/user-manual/aset/manifes.json`.
Sesi dan tangkapan regresi (`.state/`, `.regresi/`) tidak masuk git.

## Angka penunjuk tidak dibakar ke dalam gambar

Tiap adegan boleh mendaftarkan `sorot: [{ pilih, label, teks }]`. Saat memotret, runner
mencari elemen itu, menghitung titik pusatnya **sebagai persentase** terhadap area
potret, lalu menyimpannya di manifes. Halaman panduan menggambar badge merah sebagai
overlay CSS di atas gambar yang bersih.

Konsekuensinya penting: ketika sebuah tombol berpindah di antarmuka, penunjuknya ikut
berpindah sendiri pada penangkapan berikutnya. Tidak ada gambar petunjuk yang diam-diam
menunjuk tempat yang salah — dan karena persentase, badge tetap tepat pada lebar layar
berapa pun.

Selector yang tidak ditemukan atau jatuh di luar bingkai **dilewati dengan peringatan**,
bukan ditempatkan asal — lebih baik kehilangan satu penunjuk daripada menyesatkan.

## Kestabilan diff

Animasi, transisi, dan kursor kedip dimatikan; tema dipaksa terang; zona waktu dan locale
dikunci. Teks yang berubah tiap saat (mis. "2 hari yang lalu") diganti lewat `topeng`.
Dua penangkapan pada keadaan yang sama karena itu menghasilkan berkas yang identik.

## Menambah adegan

1. Tambahkan entri di `adegan.mjs`.
2. `npm run tangkap-layar -- --adegan=nama-baru`
3. Rujuk dari Markdown: `![Keterangan](aset/nama-baru.png)`
4. `npm run tangkap-layar -- --periksa`

## Audit kontras mode gelap

`scripts/audit-kontras-gelap.mjs` (`npm run audit-kontras -- <opsi>`) memakai ulang masuk/sesi
di sini, mengaktifkan mode gelap, lalu menghitung rasio kontras WCAG AA (4,5:1 teks normal,
3:1 teks besar) setiap teks yang tampak, per (peran, halaman). Skrip hanya membaca: tidak
mengirim formulir; modal dibuka lalu ditutup tanpa konfirmasi.

Prasyarat: aplikasi berjalan dan berisi data dari `php artisan simulasi:buat` (akun `sim-*`).
`APP_URL` bawaan `http://localhost:8008`; setel untuk port lain.

```bash
APP_URL=http://localhost:PORT npm run audit-kontras -- --peran=sim-validator
APP_URL=http://localhost:PORT npm run audit-kontras -- --peran=sim-validator --halaman=slug
npm run audit-kontras -- --autotest      # uji fungsi audit pada fixture, tanpa aplikasi
```

- Jalankan SATU `--peran` per proses; satu putaran penuh semua peran memakan > 10 menit.
- `--halaman=SLUG` membatasi ke satu halaman; `--keluaran=DIR` mengganti folder keluaran.
- Kode keluar: `0` bersih (atau autotest lulus); `1` ada temuan; `2` galat (termasuk
  `--peran`/`--halaman` tak cocok); `3` tanpa temuan tetapi ada halaman tak tercapai, jadi
  belum boleh disebut bersih.
- Keluaran: `scripts/tangkap-layar/.audit-kontras/{temuan.json,laporan.md}` (tidak masuk git).

Keterbatasan metode:

- Teks di kanvas / Chart.js tidak diaudit.
- Tidak ditangani: `clip` / `clip-path` / `font-size:0`, `mix-blend-mode`, `filter`,
  `backdrop-filter`, dan teks di belakang tirai modal.
- Latar gradien dilaporkan sebagai tak tentu (indeterminate), bukan lulus/gagal; latar
  bergambar didaftar untuk cek visual.
- Halaman yang tak tercapai didaftar di laporan, tidak dihitung bersih.
