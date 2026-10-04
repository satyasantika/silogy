<?php

namespace App\Modules\Panduan\Http\Controllers;

use App\Modules\Panduan\Services\PerenderPanduan;
use App\Modules\Panduan\Support\PeranPanduan;
use App\Modules\Simulasi\Services\SimulasiService;
use App\Modules\Simulasi\Support\AkunSimulasi;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PanduanController
{
    public function __construct(
        protected PerenderPanduan $perender,
        protected SimulasiService $simulasi,
    ) {}

    public function indeks(): View
    {
        return view('panduan.indeks', [
            'level' => AkunSimulasi::LEVEL,
            'cobaAktif' => $this->simulasi->cobaPeranTerbuka(),
        ]);
    }

    public function level(string $level): View
    {
        abort_unless(array_key_exists($level, AkunSimulasi::LEVEL), 404);

        return view('panduan.level', [
            'level' => $level,
            'labelLevel' => AkunSimulasi::LEVEL[$level],
            'semuaLevel' => AkunSimulasi::LEVEL,
            'peran' => PeranPanduan::bisaDicoba(),
            'cobaAktif' => $this->simulasi->cobaPeranTerbuka(),
            'levelBisaDicoba' => PeranPanduan::levelBisaDicoba($level),
        ]);
    }

    public function peran(Request $request, string $peran): View
    {
        $halaman = $this->perender->render($peran);

        abort_if($halaman === null, 404);

        $level = (string) $request->query('level', 'prodi');

        if (! array_key_exists($level, AkunSimulasi::LEVEL)) {
            $level = 'prodi';
        }

        return view('panduan.peran', [
            'halaman' => $halaman,
            'definisi' => PeranPanduan::definisi($peran),
            'peran' => PeranPanduan::semua(),
            'bisaDicoba' => PeranPanduan::akunUntuk($peran) !== null,
            'levelBisaDicoba' => PeranPanduan::levelBisaDicoba($level),
            'punyaPilihanMode' => PeranPanduan::punyaPilihanMode($peran),
            'level' => $level,
            'semuaLevel' => AkunSimulasi::LEVEL,
            'cobaAktif' => $this->simulasi->cobaPeranTerbuka(),
        ]);
    }
}
