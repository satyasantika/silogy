<?php

namespace App\Modules\Penilaian\Services;

use App\Models\User;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Penilaian\Models\CatatanRtlKelas;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Blok F laporan kelas: "Analisis & Rencana Tindak Lanjut". Hanya dosen
 * pengampu kelas yang boleh mengisi; koordinator MK membaca saja.
 *
 * Catatan untuk CPL yang kemudian tercapai TIDAK dihapus — hanya tidak
 * ditampilkan, dan muncul lagi bila CPL itu kembali di bawah target.
 */
class CatatanRtlService
{
    /**
     * @param  list<array{cpl_id: string, cpl_kode: string, cpl_deskripsi: string, rata_rata: float|null, tercapai: bool}>  $ketercapaianCpl  keluaran EvaluasiCplService::ketercapaianCplPerKelas()
     * @return array{
     *     baris: list<array{cpl_id: string, kode: string, deskripsi: string, rata_rata: float, analisis: string|null, rencana: string|null}>,
     *     umum: string|null,
     *     semua_tercapai: bool,
     *     terakhir: array{nama: string, waktu: string}|null,
     * }
     */
    public function blok(KelasMk $kelasMk, array $ketercapaianCpl): array
    {
        $catatan = CatatanRtlKelas::query()
            ->where('kelas_mk_id', $kelasMk->id)
            ->with('diubahOleh')
            ->get();

        $perCpl = $catatan->whereNotNull('cpl_id')->keyBy('cpl_id');

        $baris = collect($ketercapaianCpl)
            ->filter(fn (array $cpl): bool => $cpl['rata_rata'] !== null && ! $cpl['tercapai'])
            ->map(fn (array $cpl): array => [
                'cpl_id' => $cpl['cpl_id'],
                'kode' => $cpl['cpl_kode'],
                'deskripsi' => $cpl['cpl_deskripsi'],
                'rata_rata' => (float) $cpl['rata_rata'],
                'analisis' => $perCpl->get($cpl['cpl_id'])?->analisis,
                'rencana' => $perCpl->get($cpl['cpl_id'])?->rencana_tindak_lanjut,
            ])
            ->values()
            ->all();

        $terakhir = $catatan->sortByDesc('updated_at')->first();

        return [
            'baris' => $baris,
            'umum' => $catatan->firstWhere('cpl_id', null)?->analisis,
            'semua_tercapai' => $baris === [] && collect($ketercapaianCpl)->contains(fn (array $cpl): bool => $cpl['rata_rata'] !== null),
            'terakhir' => $terakhir === null ? null : [
                'nama' => $terakhir->diubahOleh?->namaDenganGelar() ?? '—',
                'waktu' => $terakhir->updated_at?->translatedFormat('j F Y H:i') ?? '',
            ],
        ];
    }

    /**
     * @param  array{cpl?: array<string, array{analisis?: string|null, rencana?: string|null}>, umum?: string|null}  $isian
     *
     * @throws AuthorizationException
     */
    public function simpan(KelasMk $kelasMk, User $user, array $isian): void
    {
        if ((string) $kelasMk->dosen_pengampu_id !== (string) $user->getKey()) {
            throw new AuthorizationException('Hanya dosen pengampu kelas ini yang dapat mengisi catatan evaluasi.');
        }

        $cplMk = CplMk::query()
            ->where('mk_id', $kelasMk->mkUnit?->mk_id)
            ->with('cplBok')
            ->get()
            ->pluck('cplBok.cpl_id')
            ->filter()
            ->map(fn ($id): string => (string) $id)
            ->all();

        DB::transaction(function () use ($kelasMk, $user, $isian, $cplMk): void {
            foreach ($isian['cpl'] ?? [] as $cplId => $baris) {
                if (! in_array((string) $cplId, $cplMk, true)) {
                    continue;
                }

                CatatanRtlKelas::query()->updateOrCreate(
                    ['kelas_mk_id' => $kelasMk->id, 'cpl_id' => $cplId],
                    [
                        'analisis' => $this->bersihkan($baris['analisis'] ?? null),
                        'rencana_tindak_lanjut' => $this->bersihkan($baris['rencana'] ?? null),
                        'diubah_oleh_id' => $user->getKey(),
                    ],
                );
            }

            if (array_key_exists('umum', $isian)) {
                // updateOrCreate dengan cpl_id null tidak andal di semua
                // driver (NULL = NULL), jadi dicari eksplisit lewat whereNull.
                $umum = CatatanRtlKelas::query()
                    ->where('kelas_mk_id', $kelasMk->id)
                    ->whereNull('cpl_id')
                    ->first() ?? new CatatanRtlKelas(['kelas_mk_id' => $kelasMk->id, 'cpl_id' => null]);

                $umum->fill([
                    'analisis' => $this->bersihkan($isian['umum']),
                    'diubah_oleh_id' => $user->getKey(),
                ])->save();
            }
        });
    }

    private function bersihkan(?string $teks): ?string
    {
        $teks = trim((string) $teks);

        return $teks === '' ? null : $teks;
    }
}
