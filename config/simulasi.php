<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pusat Simulasi
    |--------------------------------------------------------------------------
    | Menyalakan menu "Simulasi" untuk Super Admin beserta perintah artisan
    | simulasi:*. Dimatikan berarti ruang simulasi tidak bisa dibuat dari
    | antarmuka sama sekali.
    */
    'aktif' => (bool) env('SIMULASI_AKTIF', true),

    /*
    |--------------------------------------------------------------------------
    | Pemutus keras mode latihan
    |--------------------------------------------------------------------------
    | Sakelar sebenarnya ada di basis data (tabel simulasi_pengaturan) dan
    | dipegang Super Admin lewat menu Simulasi. Nilai di sini hanya bisa
    | MELARANG, tidak pernah mengizinkan.
    */
    'izinkan_coba_peran' => (bool) env('SIMULASI_IZINKAN_COBA_PERAN', true),

    /*
    |--------------------------------------------------------------------------
    | Pembangunan di latar belakang
    |--------------------------------------------------------------------------
    | Setel false hanya untuk pengujian: pembangunan berjalan di dalam permintaan.
    */
    'latar_belakang' => (bool) env('SIMULASI_LATAR_BELAKANG', true),

    /*
    | Kapasitas ruang, batas percobaan masuk per IP, batas token salah, umur
    | ruang tanpa aktivitas, dan lama sewa peran TIDAK diatur di sini. Semuanya
    | diatur Super Admin dari menu Simulasi dan disimpan di tabel
    | simulasi_pengaturan (lihat App\Modules\Simulasi\Support\PengaturanSimulasi),
    | supaya bisa diubah tanpa akses ke server.
    */
];
