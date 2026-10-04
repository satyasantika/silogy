<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pusat Simulasi
    |--------------------------------------------------------------------------
    | Menyalakan menu "Simulasi" untuk Super Admin beserta perintah artisan
    | simulasi:*. Dimatikan berarti sandbox simulasi tidak bisa dibuat dari
    | antarmuka sama sekali.
    */
    'aktif' => (bool) env('SIMULASI_AKTIF', true),

    /*
    |--------------------------------------------------------------------------
    | Pemutus keras tombol "Coba sebagai ‹peran›"
    |--------------------------------------------------------------------------
    | Sakelar sebenarnya ada di basis data (tabel simulasi_pengaturan) dan
    | dipegang Super Admin lewat menu Simulasi. Nilai di sini hanya bisa
    | MELARANG, tidak pernah mengizinkan.
    |
    | Setel `false` bila sebuah instans tidak boleh membuka mode latihan sama
    | sekali, apa pun yang ditekan Super Admin.
    */
    'izinkan_coba_peran' => (bool) env('SIMULASI_IZINKAN_COBA_PERAN', true),

    /*
    | Batas percobaan masuk otomatis per menit per alamat IP.
    */
    'batas_coba_per_menit' => (int) env('SIMULASI_COBA_BATAS', 10),

    /*
    |--------------------------------------------------------------------------
    | Sandbox per pengunjung
    |--------------------------------------------------------------------------
    | Tiap pengunjung mendapat satu sandbox (satu paket data utuh) yang dibagi
    | oleh semua tab perannya. Sandbox siap-pakai disiapkan lebih dulu oleh
    | `simulasi:kolam` agar pengunjung tidak menunggu pembangunan.
    |
    |  maks_sandbox      : batas sandbox hidup (siap + terpakai) di satu instans
    |  kolam_siap_kosong : jumlah contoh kosong yang dijaga siap dipakai
    |  umur_menit        : sandbox tanpa aktivitas selama ini dihapus otomatis
    |
    | Contoh terisi TIDAK berkolam: ia satu salinan bersama yang hanya-baca untuk
    | semua pengunjung, dibangun sekali lalu dipakai terus.
    */
    'maks_sandbox' => (int) env('SIMULASI_MAKS_SANDBOX', 20),
    'kolam_siap_kosong' => (int) env('SIMULASI_KOLAM_SIAP_KOSONG', 3),

    // Banyaknya MK prodi pada contoh terisi (1-6). Contoh kosong hanya berisi satu
    // MK kosong: pengunjung menyusun sisanya.
    'jumlah_mk' => (int) env('SIMULASI_JUMLAH_MK', 6),
    'umur_menit' => (int) env('SIMULASI_UMUR_MENIT', 120),

    // Pembangunan dari menu Simulasi berjalan sebagai proses terpisah supaya
    // layar progres bisa dipantau dan tidak terpotong batas waktu permintaan
    // web. Setel false untuk membangun di dalam permintaan yang sama (dipakai
    // test dan lingkungan yang melarang proses latar belakang).
    'latar_belakang' => (bool) env('SIMULASI_LATAR_BELAKANG', true),
];
