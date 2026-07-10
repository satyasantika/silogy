<?php

namespace App\Modules\Penilaian\Services;

use App\Models\User;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Models\Mk;
use App\Modules\Penilaian\Filament\Pages\InputNilai;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class PenilaianDosenService
{
    /**
     * Kelas MK milik dosen untuk sebuah MK, pada semester tertentu (bila diisi).
     *
     * @return Collection<int, KelasMk>
     */
    public static function kelasUntukMk(Mk $mk, User $dosen, ?string $semesterId): Collection
    {
        return KelasMk::query()
            ->where('dosen_pengampu_id', $dosen->id)
            ->whereHas('mkUnit', fn ($query) => $query->where('mk_id', $mk->id))
            ->when(
                filled($semesterId),
                fn ($query) => $query->where('semester_id', $semesterId),
            )
            ->withCount('kelasMkMahasiswas')
            ->orderBy('kode_kelas')
            ->get();
    }

    /**
     * @return array{kode_kelas: string, jumlah_mahasiswa: int, rata_rata: float|null, sudah_dinilai: bool}
     */
    public static function ringkasanKelas(KelasMk $kelas): array
    {
        $jumlahMahasiswa = $kelas->kelas_mk_mahasiswas_count
            ?? $kelas->kelasMkMahasiswas()->count();

        $rataRata = NilaiMahasiswa::query()
            ->whereHas(
                'kelasMkMahasiswa',
                fn ($query) => $query->where('kelas_mk_id', $kelas->id),
            )
            ->avg('nilai');

        return [
            'kode_kelas' => $kelas->kode_kelas,
            'jumlah_mahasiswa' => $jumlahMahasiswa,
            'rata_rata' => $rataRata !== null ? round((float) $rataRata, 2) : null,
            'sudah_dinilai' => $rataRata !== null,
        ];
    }

    /**
     * Ringkasan gabungan (total mahasiswa & rata-rata nilai) seluruh kelas
     * milik dosen untuk sebuah MK, pada semester tertentu (bila diisi).
     *
     * @return array{jumlah_mahasiswa: int, rata_rata: float|null, sudah_dinilai: bool}
     */
    public static function ringkasanSeluruhKelas(Mk $mk, User $dosen, ?string $semesterId): array
    {
        $kelasList = static::kelasUntukMk($mk, $dosen, $semesterId);

        if ($kelasList->isEmpty()) {
            return ['jumlah_mahasiswa' => 0, 'rata_rata' => null, 'sudah_dinilai' => false];
        }

        $jumlahMahasiswa = (int) $kelasList->sum(
            fn (KelasMk $kelas): int => $kelas->kelas_mk_mahasiswas_count ?? $kelas->kelasMkMahasiswas()->count(),
        );

        $rataRata = NilaiMahasiswa::query()
            ->whereHas(
                'kelasMkMahasiswa',
                fn ($query) => $query->whereIn('kelas_mk_id', $kelasList->pluck('id')),
            )
            ->avg('nilai');

        return [
            'jumlah_mahasiswa' => $jumlahMahasiswa,
            'rata_rata' => $rataRata !== null ? round((float) $rataRata, 2) : null,
            'sudah_dinilai' => $rataRata !== null,
        ];
    }

    public static function ringkasanKelasHtml(Mk $mk, User $dosen, ?string $semesterId): HtmlString
    {
        $kelasList = static::kelasUntukMk($mk, $dosen, $semesterId);

        if ($kelasList->isEmpty()) {
            return new HtmlString(
                '<div style="margin-top:4px;padding-top:6px;font-size:12px;color:#6b7280;">'
                .'Tidak ada kelas pada semester ini.'
                .'</div>'
            );
        }

        $badges = $kelasList
            ->map(function (KelasMk $kelas): string {
                $ringkasan = static::ringkasanKelas($kelas);

                $url = InputNilai::getUrl(['kelas_mk_id' => $kelas->id]);

                $sudahDinilai = $ringkasan['sudah_dinilai'];

                $background = $sudahDinilai ? '#dcfce7' : '#fef3c7';
                $color = $sudahDinilai ? '#166534' : '#92400e';
                $border = $sudahDinilai ? '#86efac' : '#fcd34d';

                $keterangan = $sudahDinilai
                    ? sprintf('%d mhs · rata-rata %s', $ringkasan['jumlah_mahasiswa'], $ringkasan['rata_rata'])
                    : sprintf('%d mhs · Belum dinilai', $ringkasan['jumlah_mahasiswa']);

                return '<a href="'.e($url).'" '
                    .'style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;'
                    .'border-radius:6px;font-size:11px;font-weight:600;line-height:1.4;text-decoration:none;'
                    .'background:'.$background.';color:'.$color.';border:1px solid '.$border.';">'
                    .'Kelas '.e($ringkasan['kode_kelas']).' · '.e($keterangan)
                    .'</a>';
            })
            ->implode('');

        return new HtmlString(
            '<div style="display:flex;flex-wrap:wrap;align-items:center;gap:4px;margin-top:4px;padding-top:6px;">'
            .$badges
            .'</div>'
        );
    }
}
