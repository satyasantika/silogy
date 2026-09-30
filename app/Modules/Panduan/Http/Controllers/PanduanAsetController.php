<?php

namespace App\Modules\Panduan\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Menyajikan gambar manual dari docs/user-manual/aset.
 *
 * Gambar sengaja tinggal di satu tempat saja, di dalam docs/, supaya berkas
 * .md tetap tampil benar ketika dibaca langsung di GitHub. Melayaninya lewat
 * rute juga membuat tautannya ikut benar pada pemasangan sub-path seperti
 * /demo-silogy, yang tidak akan terjadi bila memakai path literal.
 */
class PanduanAsetController
{
    public function __invoke(Request $request, string $berkas): BinaryFileResponse
    {
        // Pola rute sudah membatasi nama berkas, basename() menutup sisa celah
        // penelusuran direktori.
        $jalur = base_path('docs/user-manual/aset/'.basename($berkas));

        abort_unless(is_file($jalur), 404);

        return response()
            ->file($jalur, [
                'Cache-Control' => 'public, max-age=86400',
            ])
            ->setAutoEtag();
    }
}
