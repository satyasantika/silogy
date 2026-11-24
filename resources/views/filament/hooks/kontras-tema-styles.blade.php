<style>
    /*
     * Kontras tema terang/gelap untuk view yang dulu memakai warna inline.
     * Inline `style` tak bisa ditimpa aturan `.dark` tanpa !important, jadi
     * warna dipindah ke kelas. Mode terang = tampilan sebelumnya; pasangan
     * `.dark` menjaga kontras >= 4.5:1 untuk teks biasa.
     * Badge angka biru memakai .silogy-badge + .silogy-tone-info
     * (status-badge-styles); teks redup memakai .silogy-teks-redup.
     */

    /* Permukaan kartu/tabel: latar + warna teks bawaan sel di dalamnya. */
    .silogy-permukaan {
        background: #ffffff;
        color: inherit;
    }

    .dark .silogy-permukaan {
        background: #18181b;
        color: #f4f4f5;
    }

    /* Permukaan lembut: wadah panel dan baris rincian bersarang. */
    .silogy-permukaan-lembut {
        background: rgba(248, 250, 252, .55);
    }

    .dark .silogy-permukaan-lembut {
        background: rgba(255, 255, 255, .03);
    }

    .silogy-permukaan-rincian {
        background: rgba(248, 250, 252, .9);
    }

    .dark .silogy-permukaan-rincian {
        background: rgba(255, 255, 255, .04);
    }

    /* Baris tabel yang sedang dibuka rinciannya. */
    .silogy-baris-terbuka {
        background: rgba(37, 99, 235, .04);
    }

    .dark .silogy-baris-terbuka {
        background: rgba(96, 165, 250, .12);
    }

    /* Kotak angka ringkas (Kelas / Mahasiswa). */
    .silogy-kotak-ringkas {
        background: rgba(15, 23, 42, .04);
    }

    .dark .silogy-kotak-ringkas {
        background: rgba(255, 255, 255, .06);
    }

    /* Teks utama (judul, angka menonjol). */
    .silogy-teks {
        color: rgb(15, 23, 42);
    }

    .dark .silogy-teks {
        color: #f4f4f5;
    }

    /* Tombol/tautan aksi di dalam tabel. */
    .silogy-tautan-aksi {
        color: rgb(51, 65, 85);
    }

    .dark .silogy-tautan-aksi {
        color: #e4e4e7;
    }

    .silogy-tautan-aksi-buka {
        color: rgb(29, 78, 216);
    }

    .dark .silogy-tautan-aksi-buka {
        color: #60a5fa;
    }

    /* Isian (select/input): latar, warna teks, dan garis tepi. */
    .silogy-isian {
        background: #ffffff;
        color: #111827;
        border: 1px solid rgba(128, 128, 128, .4);
    }

    .dark .silogy-isian {
        background: #27272a;
        color: #f4f4f5;
        border-color: rgba(161, 161, 170, .5);
        color-scheme: dark;
    }

    /*
     * Warna aksen tetap (sebelumnya inline `color:#...`). Mode terang = nilai
     * lama persis; pasangan `.dark` dipilih >= 4.5:1 di atas #18181b dan
     * tint tipis di atasnya.
     */
    .silogy-label-langkah { color: #0b3914; }
    .dark .silogy-label-langkah { color: #86efac; }

    .silogy-aksen-biru { color: #2563eb; }
    .dark .silogy-aksen-biru { color: #60a5fa; }

    .silogy-aksen-merah { color: #c1121f; }
    .dark .silogy-aksen-merah { color: #f87171; }

    .silogy-aksen-amber { color: #b45309; }
    .dark .silogy-aksen-amber { color: #fbbf24; }

    .silogy-aksen-hijau { color: #15803d; }
    .dark .silogy-aksen-hijau { color: #4ade80; }

    /* Pil "Ditangani oleh" pada kotak syarat hulu. */
    .silogy-chip-peran-saya {
        background: rgba(21, 128, 61, .15);
        color: #15803d;
    }

    .dark .silogy-chip-peran-saya {
        background: rgba(34, 197, 94, .18);
        color: #4ade80;
    }

    .silogy-chip-peran-lain {
        background: rgba(180, 83, 9, .18);
        color: #b45309;
    }

    .dark .silogy-chip-peran-lain {
        background: rgba(245, 158, 11, .18);
        color: #fbbf24;
    }

    /* Angka rata-rata ketercapaian (kartu laporan): hijau / merah tua. */
    .silogy-capaian-ok { color: #16a34a; }
    .dark .silogy-capaian-ok { color: #4ade80; }
    .silogy-capaian-kurang { color: #b91c1c; }
    .dark .silogy-capaian-kurang { color: #f87171; }

    /* Lencana peran di kolom pengguna. */
    .silogy-chip-tim-kurikulum {
        background: rgba(0, 112, 0, .14);
        color: #0b3914;
    }

    .dark .silogy-chip-tim-kurikulum {
        background: rgba(34, 197, 94, .18);
        color: #86efac;
    }

    .silogy-chip-pimpinan {
        background: rgba(37, 99, 235, .12);
        color: #1d4ed8;
    }

    .dark .silogy-chip-pimpinan {
        background: rgba(59, 130, 246, .18);
        color: #93c5fd;
    }

    /* Pemisah dekoratif (koma antar kode) dan teks kecil di kartu pastel. */
    .silogy-pemisah { opacity: .4; }
    .dark .silogy-pemisah { opacity: .7; }

    .silogy-teks-kartu-redup { opacity: .85; }
    .dark .silogy-teks-kartu-redup { opacity: 1; }

    /*
     * Lencana total bobot (Σ) matriks CPL x MK: warna latar datang dari
     * inline (PHP + Alpine) dengan teks putih. Di mode gelap latar diganti
     * yang lebih tua agar putih >= 4.5:1 (#15803d 5.02, #b45309 5.02,
     * #6b7280 4.83; merah #dc2626 sudah 4.83 dan dibiarkan).
     */
    .dark [data-silogy="rekap-bobot-cpl-mk"][data-status="pas"] { background: #15803d !important; }
    .dark [data-silogy="rekap-bobot-cpl-mk"][data-status="kurang"] { background: #b45309 !important; }
    .dark [data-silogy="rekap-bobot-cpl-mk"][data-status="kosong"] { background: #6b7280 !important; }

    /*
     * Kartu "sedang dikerjakan" (kurikulum / MK koordinator): tint #dcfce7
     * 35% di atas kartu gelap menjadi abu-hijau terang (#5d6863) sehingga
     * teks redup/putih turun ke < 4.5. Di mode gelap pakai tint hijau tipis.
     */
    .dark .silogy-kurikulum-cards .silogy-kurikulum-card-sedang-dikerjakan,
    .dark .silogy-mk-koordinator-cards .silogy-mk-card-sedang-dikerjakan {
        background-color: rgba(34, 197, 94, .14);
    }

    /*
     * Placeholder mode gelap: gray-500 Filament (#71717b) hanya 3.2:1 di atas
     * input #242427. #a1a1aa = 6.04:1 (input) / 6.91:1 (tabel #18181b).
     */
    .dark .fi-input::placeholder,
    .dark .fi-select-input-placeholder,
    .dark .fi-ta-placeholder {
        color: #a1a1aa;
    }
</style>
