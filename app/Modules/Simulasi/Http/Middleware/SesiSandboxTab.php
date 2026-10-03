<?php

namespace App\Modules\Simulasi\Http\Middleware;

use App\Modules\Simulasi\Support\SesiTab;
use Closure;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memberi setiap tab simulasi sesinya sendiri.
 *
 * URL tab berbentuk `/s/<token>/<path biasa>`. Segmen `/s/<token>` diperlakukan
 * sebagai base URL (seperti aplikasi yang dipasang di sub-folder), bukan
 * ditulis ulang. Akibatnya, dengan satu mekanisme:
 *
 *  - routing berjalan seperti biasa pada sisa path;
 *  - URL yang dibangkitkan Laravel ikut berawalan tab;
 *  - validasi signed URL (upload Livewire/Filament) tetap cocok, karena
 *    Request::url() dan root URL sama-sama memuat awalan tab;
 *  - cookie sesi dibatasi ke path tab, sehingga tab lain tidak mengirimnya.
 *
 * Dua penyesuaian karena awalan tab bukan bagian dari aset statis:
 *
 *  - asset() dikembalikan ke origin asli. Nginx tidak mengenal /s/<token>.
 *  - RouteUrlGenerator membuang base URL request dari URL relatif, sehingga
 *    alamat AJAX Livewire kehilangan awalan tab. Alamat itu dipulihkan pada
 *    HTML keluaran. Tanpa ini AJAX tab mengirim cookie tab lain.
 *
 * Token yang tidak dikenal (kedaluwarsa atau ngawur) ditolak 404 SEBELUM sesi
 * dimulai, jadi tidak ada cookie yatim yang tercipta dari tebakan token.
 *
 * Harus berjalan sebelum StartSession; karena itu didaftarkan sebagai
 * middleware global yang paling awal.
 */
class SesiSandboxTab
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! preg_match(SesiTab::POLA, $request->getPathInfo(), $cocok)) {
            return $next($request);
        }

        $token = $cocok[1];
        $catatan = SesiTab::cari($token);

        abort_if($catatan === null, 404);

        $asal = $this->asalAplikasi($request);
        $awalan = '/s/'.$token;

        $this->jadikanBaseUrl($request, $awalan);
        $this->pisahkanSesi($token, $asal, $awalan);

        SesiTab::sentuh($token, $catatan);

        $request->attributes->set('sandbox_tab', ['token' => $token] + $catatan);

        // Livewire menyuntikkan skripnya di listener RequestHandled, SETELAH
        // middleware selesai, jadi alamat AJAX-nya baru ada di HTML pada saat
        // itu. Listener ini didaftarkan belakangan sehingga berjalan sesudah
        // milik Livewire, dan hanya bertindak untuk request tab ini.
        Event::listen(RequestHandled::class, function (RequestHandled $peristiwa) use ($token, $asal, $awalan): void {
            if (($peristiwa->request->attributes->get('sandbox_tab')['token'] ?? null) === $token) {
                $this->pulihkanAlamatLivewire($peristiwa->response, $asal['path'].$awalan);
            }
        });

        return $next($request);
    }

    /**
     * Skema+host dan sub-path aplikasi (dari APP_URL bila ada, jika tidak
     * dari request), sebelum awalan tab ditambahkan.
     *
     * @return array{origin: string, path: string}
     */
    private function asalAplikasi(Request $request): array
    {
        $subPath = rtrim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');

        if ($subPath !== '') {
            return [
                'origin' => rtrim((string) config('app.url'), '/'),
                'path' => $subPath,
            ];
        }

        return ['origin' => $request->getSchemeAndHttpHost(), 'path' => ''];
    }

    private function jadikanBaseUrl(Request $request, string $awalan): void
    {
        $server = $request->server->all();
        $server['SCRIPT_NAME'] = $awalan.'/index.php';
        $server['PHP_SELF'] = $awalan.'/index.php';
        $server['SCRIPT_FILENAME'] = dirname((string) ($server['SCRIPT_FILENAME'] ?? '')).'/index.php';

        $request->initialize(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $request->files->all(),
            $server,
            $request->getContent(),
        );
    }

    /**
     * @param  array{origin: string, path: string}  $asal
     */
    private function pisahkanSesi(string $token, array $asal, string $awalan): void
    {
        config([
            'session.cookie' => 'silogy_tab_'.$token,
            'session.path' => $asal['path'].$awalan,
        ]);

        // Driver sesi di-cache per proses; tanpa ini nama cookie baru tak
        // terpakai pada request kedua dalam proses yang sama (test, Octane).
        app('session')->forgetDrivers();

        URL::forceRootUrl($asal['origin'].$awalan);
        URL::useAssetOrigin($asal['origin']);

        $this->arahkanSkripLivewire($asal['origin']);
    }

    /**
     * Skrip Livewire yang sudah dipublikasikan dibentuk lewat url(), sehingga
     * ikut berawalan tab padahal berkasnya statis di public/. Dikembalikan ke
     * origin asli; bila belum dipublikasikan, Livewire memakai rutenya sendiri.
     */
    private function arahkanSkripLivewire(string $origin): void
    {
        $manifest = public_path('vendor/livewire/manifest.json');

        if (! is_file($manifest)) {
            return;
        }

        $versi = json_decode((string) file_get_contents($manifest), true)['/livewire.js'] ?? 'dev';
        $berkas = config('app.debug') ? '/livewire.js' : '/livewire.min.js';

        config(['livewire.asset_url' => $origin.'/vendor/livewire'.$berkas.'?id='.$versi]);
    }

    private function pulihkanAlamatLivewire(Response $respons, string $awalanLengkap): void
    {
        // Header Content-Type baru dipasang saat respons disiapkan untuk dikirim,
        // jadi pada titik ini kosong untuk halaman HTML biasa. Respons JSON
        // (permintaan AJAX Livewire) sudah membawanya dan dilewati.
        $tipe = (string) $respons->headers->get('Content-Type');

        if ($tipe !== '' && ! str_contains($tipe, 'text/html')) {
            return;
        }

        $uri = app('livewire')->getUpdateUri();

        if (str_starts_with($uri, $awalanLengkap)) {
            return;
        }

        $benar = $awalanLengkap.$uri;
        $isi = (string) $respons->getContent();

        $isi = str_replace(
            ['data-update-uri="'.$uri.'"', '"uri":'.json_encode($uri)],
            ['data-update-uri="'.$benar.'"', '"uri":'.json_encode($benar)],
            $isi,
        );

        $respons->setContent($isi);
    }
}
