<?php

namespace App\Modules\Auth\Support;

use RuntimeException;

/**
 * Masuk dengan Google ditolak dengan alasan yang aman ditampilkan ke pengguna
 * (domain salah, surel belum terverifikasi, akun menunggu peran, dsb.).
 */
class MasukGoogleDitolak extends RuntimeException {}
