<?php

use Illuminate\Support\Facades\File;

function renderPenawaran(array $penawaran, ?string $terbuka = null): string
{
    return view('filament.modules.mk.partials.mk-edit-penawaran', [
        'penawaran' => $penawaran,
        'total_kelas' => 3,
        'total_mahasiswa' => 40,
        'penawaranDetailId' => $terbuka,
    ])->render();
}

function barisPenawaran(array $perSemester): array
{
    return [[
        'id' => 'p-1',
        'kode' => 'PMT101',
        'unit' => 'Prodi A',
        'kurikulum' => 'Kurikulum 2026',
        'semester_ke' => 3,
        'jumlah_kelas' => 2,
        'jumlah_mahasiswa' => 40,
        'is_active' => true,
        'per_semester' => $perSemester,
    ]];
}

it('penawaran memakai kelas tema, bukan latar putih inline, saat baris tertutup', function () {
    $html = renderPenawaran(barisPenawaran([]));

    expect($html)
        ->not->toContain('background:#fff')
        ->not->toContain('color:rgb(15,23,42)')
        ->not->toContain('color:rgb(51,65,85)')
        ->toContain('silogy-permukaan')
        ->toContain('silogy-tone-info');
});

it('penawaran baris terbuka dengan rincian semester memakai kelas, bukan warna inline', function () {
    $html = renderPenawaran(barisPenawaran([
        ['nama' => '2026/2027 Ganjil', 'kode' => '20261', 'jumlah_kelas' => 2, 'jumlah_mahasiswa' => 40],
    ]), 'p-1');

    expect($html)
        ->not->toContain('background:#fff')
        ->not->toContain('rgba(37,99,235,.04)')
        ->not->toContain('rgba(248,250,252')
        ->not->toContain('rgb(15,23,42)')
        ->not->toContain('rgb(51,65,85)')
        ->not->toContain('rgb(29,78,216)')
        ->toContain('Rincian semester')
        ->toContain('Tutup')
        ->toContain('20261');
    expect(substr_count($html, 'silogy-permukaan'))->toBeGreaterThanOrEqual(2);
});

it('penawaran baris terbuka tanpa kelas kontrak memakai teks redup berkelas', function () {
    $html = renderPenawaran(barisPenawaran([]), 'p-1');

    expect($html)
        ->toContain('Belum ada kelas kontrak')
        ->toContain('silogy-teks-redup')
        ->not->toContain('rgba(248,250,252');
});

it('penawaran kosong tidak memakai warna inline tetap', function () {
    $html = renderPenawaran([]);

    expect($html)
        ->toContain('Belum ada penawaran')
        ->not->toContain('rgba(248,250,252')
        ->not->toContain('rgb(15,23,42)');
});

it('hook kontras-tema mendefinisikan pasangan gelap tiap kelas', function () {
    $css = view('filament.hooks.kontras-tema-styles')->render();

    foreach (['.silogy-permukaan', '.silogy-teks', '.silogy-isian'] as $kelas) {
        expect($css)->toContain('.dark '.$kelas);
    }
});

it('hook kontras-tema terdaftar pada STYLES_AFTER panel admin', function () {
    $sumber = File::get(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($sumber)->toContain("view('filament.hooks.kontras-tema-styles')");
});

it('select halaman penilaian memakai silogy-isian tanpa latar putih inline', function () {
    foreach ([
        'validasi-nilai.blade.php',
        'laporan-koordinator.blade.php',
    ] as $berkas) {
        $sumber = File::get(resource_path('views/filament/modules/penilaian/pages/'.$berkas));

        expect($sumber)
            ->toContain('silogy-isian')
            ->not->toContain('background:#fff;font-size:13px');
    }
});
