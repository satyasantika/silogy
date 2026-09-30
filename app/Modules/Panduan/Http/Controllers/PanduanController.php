<?php

namespace App\Modules\Panduan\Http\Controllers;

use App\Modules\Panduan\Services\PerenderPanduan;
use App\Modules\Panduan\Support\PeranPanduan;
use App\Modules\Simulasi\Services\SimulasiService;
use App\Modules\Simulasi\Support\AkunSimulasi;
use Illuminate\Contracts\View\View;

class PanduanController
{
    public function __construct(
        protected PerenderPanduan $perender,
        protected SimulasiService $simulasi,
    ) {}

    public function indeks(): View
    {
        return view('panduan.indeks', [
            'peran' => PeranPanduan::semua(),
            'simulasiAda' => $this->simulasi->aktif() !== null,
            'cobaAktif' => $this->simulasi->cobaPeranTerbuka(),
        ]);
    }

    public function peran(string $peran): View
    {
        $halaman = $this->perender->render($peran);

        abort_if($halaman === null, 404);

        return view('panduan.peran', [
            'halaman' => $halaman,
            'definisi' => PeranPanduan::definisi($peran),
            'peran' => PeranPanduan::semua(),
            'akunLatihan' => PeranPanduan::akunUntuk($peran),
            'sandiLatihan' => AkunSimulasi::SANDI,
            'simulasiAda' => $this->simulasi->aktif() !== null,
            'cobaAktif' => $this->simulasi->cobaPeranTerbuka(),
        ]);
    }
}
