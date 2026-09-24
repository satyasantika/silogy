<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pusat Simulasi
    |--------------------------------------------------------------------------
    | Menyalakan menu "Simulasi" untuk Super Admin beserta perintah artisan
    | simulasi:*. Dimatikan berarti data simulasi tidak bisa dibuat dari
    | antarmuka sama sekali.
    */
    'aktif' => (bool) env('SIMULASI_AKTIF', true),

    /*
    |--------------------------------------------------------------------------
    | Pemutus keras tombol "Coba sebagai ‹peran›"
    |--------------------------------------------------------------------------
    | Sakelar sebenarnya ada di basis data dan dipegang Super Admin lewat menu
    | Simulasi — lihat kolom `simulasi_jalan.coba_peran`. Nilai di sini hanya
    | bisa MELARANG, tidak pernah mengizinkan.
    |
    | Setel `false` bila sebuah instans tidak boleh membuka mode latihan sama
    | sekali, apa pun yang ditekan Super Admin. Biarkan `true` (bawaan) bila
    | keputusannya memang ingin diserahkan kepada Super Admin.
    |
    | Kenapa dipisah begini: pada banyak pemasangan, orang yang berwenang
    | memutuskan boleh-tidaknya mode latihan dibuka justru tidak punya akses
    | menyunting .env di peladen.
    */
    'izinkan_coba_peran' => (bool) env('SIMULASI_IZINKAN_COBA_PERAN', true),

    /*
    | Batas percobaan masuk otomatis per menit per alamat IP.
    */
    'batas_coba_per_menit' => (int) env('SIMULASI_COBA_BATAS', 10),
];
