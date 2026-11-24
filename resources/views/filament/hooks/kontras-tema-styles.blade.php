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
</style>
