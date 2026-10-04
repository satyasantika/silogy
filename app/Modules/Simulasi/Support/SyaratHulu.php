<?php

namespace App\Modules\Simulasi\Support;

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\BoK\Filament\Resources\BokResource;
use App\Modules\CPL\Filament\Resources\CplResource;
use App\Modules\CPL\Models\Cpl;
use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\Kelas\Filament\Resources\KelasMkResource;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Filament\Resources\CpmkResource;
use App\Modules\MK\Filament\Resources\MataKuliahKoordinatorResource;
use App\Modules\MK\Filament\Resources\MkResource;
use App\Modules\MK\Filament\Resources\MkUnitResource;
use App\Modules\MK\Filament\Resources\SubcpmkResource;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\MK\Services\MataKuliahKoordinatorService;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\Penilaian\Filament\Pages\LaporanKoordinator;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource;
use App\Modules\Penilaian\Filament\Resources\PenilaianDosenResource;
use App\Modules\Penilaian\Filament\Resources\PesertaKelasResource;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Throwable;

/**
 * Keterangan "syarat yang belum terpenuhi" di halaman-halaman peran, khusus
 * ruang latihan (akun sandbox).
 *
 * Pada contoh kosong, tiap peran bekerja di atas hasil peran hulunya: Dosen
 * Pengampu menunggu Koordinator MK menyusun asesmen, Koordinator MK menunggu
 * Tim Kurikulum menetapkan namanya, dan seterusnya. Tanpa keterangan, halaman
 * hilir tampak kosong atau macet tanpa sebab yang bisa ditebak pengunjung.
 *
 * Seluruh syarat DIHITUNG dari data sandbox yang sedang dilihat, bukan dari
 * peran atau mode, sehingga keterangan hilang sendiri begitu hulunya selesai.
 * Contoh terisi hanya mendapat keterangan bila syaratnya benar-benar belum
 * dipenuhi (mis. Koordinator MK belum memilih MK). Akun inti tidak pernah
 * mendapat keterangan ini, dan kegagalan di sini tidak boleh merusak halaman.
 */
final class SyaratHulu
{
    /**
     * Kelas halaman (Resource atau Page) => kunci aturan.
     *
     * @var array<class-string, string>
     */
    public const PETA = [
        ProfilLulusanResource::class => 'profil',
        CplResource::class => 'cpl',
        BokResource::class => 'bok',
        MkResource::class => 'mk',
        MkUnitResource::class => 'penawaran',
        KelasMkResource::class => 'kelas',
        MataKuliahKoordinatorResource::class => 'koordinator',
        CpmkResource::class => 'cpmk',
        SubcpmkResource::class => 'subcpmk',
        KomponenPenilaianResource::class => 'asesmen',
        PesertaKelasResource::class => 'peserta',
        LaporanKoordinator::class => 'laporan',
        PenilaianDosenResource::class => 'dosen',
    ];

    private const TIM_KURIKULUM = ['nama' => 'Tim Kurikulum', 'peran' => 'Tim Kurikulum'];

    private const KOORDINATOR = ['nama' => 'Koordinator MK', 'peran' => 'Koordinator Mata Kuliah'];

    private const ADMIN_PRODI = ['nama' => 'Admin Program Studi', 'peran' => 'Admin'];

    private const DOSEN = ['nama' => 'Dosen Pengampu', 'peran' => 'Dosen Pengampu'];

    public static function pasangHook(): void
    {
        foreach (self::PETA as $kelas => $kunci) {
            FilamentView::registerRenderHook(
                PanelsRenderHook::PAGE_START,
                static fn (): string => self::render($kunci),
                scopes: $kelas,
            );
        }
    }

    public static function render(string $kunci): string
    {
        $user = auth()->user();

        if (! $user instanceof User || $user->getAttribute('sandbox_id') === null) {
            return '';
        }

        try {
            $butir = self::untuk($kunci, $user);

            if ($butir === []) {
                return '';
            }

            return view('filament.hooks.syarat-hulu', [
                'butir' => $butir,
                'peranAktif' => ActiveRole::currentFor($user),
            ])->render();
        } catch (Throwable $galat) {
            // Keterangan hanyalah pelengkap: halaman harus tetap terbuka.
            report($galat);

            return '';
        }
    }

    /**
     * Keterangan untuk layar 403 akun sandbox. Halaman yang ditolak policy
     * tidak pernah dirender, jadi hook PAGE_START tidak berjalan; tanpa ini
     * pengunjung hanya melihat "Akses ditolak" tanpa tahu langkah hulu mana
     * yang harus diselesaikan lebih dulu.
     *
     * Selalu menghasilkan keterangan untuk akun sandbox (ada penjelasan umum
     * bila halamannya tak dikenal) dan string kosong untuk akun data inti.
     */
    public static function renderUntukPenolakan(string $path): string
    {
        $user = auth()->user();

        if (! $user instanceof User || $user->getAttribute('sandbox_id') === null) {
            return '';
        }

        try {
            $peran = ActiveRole::currentFor($user);
            $kunci = self::kunciDariPath($path);

            // Halaman milik Tim Kurikulum yang dibuka Koordinator MK: bila Koordinator
            // belum punya MK, jelaskan dari sudut pandangnya (namanya belum ditetapkan);
            // bila sudah punya MK, penyebabnya wewenang, bukan urutan pengisian.
            if ($peran === 'Koordinator Mata Kuliah' && in_array($kunci, ['profil', 'cpl', 'bok', 'mk', 'penawaran'], true)) {
                $punyaMk = ! MataKuliahKoordinatorResource::scopedKoordinatorMkIds($user)->isEmpty();

                $butir = $punyaMk ? [self::butir(
                    'Halaman ini milik Tim Kurikulum, bukan Koordinator MK',
                    'Koordinator MK bekerja pada mata kuliah yang sudah ditetapkan baginya: CPMK, Sub-CPMK, asesmen, peserta, dan laporan.',
                    self::KOORDINATOR,
                    'Gunakan menu Mata Kuliah (daftar MK Anda), lalu klik MK yang akan dikerjakan.',
                )] : self::untuk('koordinator', $user);
            } else {
                $butir = $kunci === null ? [] : self::untuk($kunci, $user);
            }

            if ($butir === []) {
                $butir = [self::butir(
                    'Halaman ini belum bisa dibuka oleh peran Anda saat ini',
                    'Di ruang latihan sebagian halaman terkunci sampai peran hulu menyelesaikan langkahnya, atau memang bukan wewenang peran ini. '
                        .'Di contoh kosong, tiap peran bekerja di atas hasil peran sebelumnya: Tim Kurikulum → Koordinator MK → Admin Program Studi → Dosen Pengampu.',
                    ['nama' => 'Peran hulu', 'peran' => '-'],
                    'Buka halaman panduan lalu pilih peran hulunya di tab baru, atau pilih “Lihat contoh terisi” untuk melihat hasil akhirnya.',
                )];
            }

            return view('filament.hooks.syarat-hulu', ['butir' => $butir, 'peranAktif' => $peran])->render();
        } catch (Throwable $galat) {
            report($galat);

            return '';
        }
    }

    /** Kunci aturan untuk path halaman; null bila halaman tidak dikenal. */
    public static function kunciDariPath(string $path): ?string
    {
        $path = '/'.trim((string) preg_replace('#^/?s/[^/]+/#', '/', '/'.ltrim($path, '/')), '/');

        $kandidat = [];

        foreach (self::PETA as $kelas => $kunci) {
            try {
                $kandidat['/'.trim((string) $kelas::getSlug(), '/')] = $kunci;
            } catch (Throwable) {
                continue;
            }
        }

        // Slug terpanjang menang: penilaian/laporan-koordinator mengalahkan penilaian.
        uksort($kandidat, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($kandidat as $slug => $kunci) {
            if ($path === $slug || str_starts_with($path, $slug.'/')) {
                return $kunci;
            }
        }

        return null;
    }

    /**
     * @return list<array{judul: string, rincian: string, nama: string, peran: string, langkah: string}>
     */
    public static function untuk(string $kunci, User $user): array
    {
        return match ($kunci) {
            'profil' => self::kurikulumDulu('Profil Lulusan disusun di bawah sebuah kurikulum.'),
            'cpl' => self::kurikulumDulu('CPL dikelompokkan per kurikulum; urutan OBE dimulai dari kurikulum.'),
            'bok' => self::cplDulu('BoK dipetakan ke CPL; urutan OBE: Profil → CPL → BoK → MK.'),
            'mk' => [
                ...self::cplDulu('Mata kuliah ditulis setelah CPL ada (urutan OBE: Profil → CPL → BoK → MK).'),
                ...self::mkTanpaKoordinator(),
            ],
            'penawaran' => self::penawaran(),
            'kelas' => self::kelas(),
            'koordinator' => self::konteksKoordinator($user)[1],
            'cpmk' => self::konteksKoordinator($user)[1],
            'subcpmk' => self::subcpmk($user),
            'asesmen' => self::asesmen($user),
            'peserta' => self::peserta($user),
            'laporan' => self::laporan($user),
            'dosen' => self::dosen($user),
            default => [],
        };
    }

    // ── Tim Kurikulum ────────────────────────────────────────────────────

    /** @return list<array<string, string>> */
    private static function kurikulumDulu(string $alasan): array
    {
        if (Kurikulum::query()->exists()) {
            return [];
        }

        return [self::butir(
            'Kurikulum belum dibuat',
            $alasan,
            self::TIM_KURIKULUM,
            'Buka menu Kurikulum → buat kurikulum, lalu kembali ke halaman ini.',
        )];
    }

    /** @return list<array<string, string>> */
    private static function cplDulu(string $alasan): array
    {
        if (Cpl::query()->exists()) {
            return [];
        }

        return [self::butir('CPL belum ada', $alasan, self::TIM_KURIKULUM, 'Buka menu CPL → buat CPL terlebih dahulu.')];
    }

    /** @return list<array<string, string>> */
    private static function mkTanpaKoordinator(): array
    {
        $jumlah = Mk::query()->whereNull('koordinator_mk_id')->count();

        if ($jumlah === 0) {
            return [];
        }

        return [self::butir(
            "{$jumlah} mata kuliah belum punya Koordinator MK",
            'Peran Koordinator MK baru melihat sebuah MK setelah namanya diisi pada kolom Koordinator MK di MK itu. Tanpa itu, langkah CPMK, Sub-CPMK, dan asesmen tidak bisa dimulai.',
            self::TIM_KURIKULUM,
            'Edit mata kuliah → isi Koordinator MK.',
        )];
    }

    /** @return list<array<string, string>> */
    private static function penawaran(): array
    {
        $unitIds = array_keys(MkUnitResource::timKurikulumUnitOptions());

        $adaMk = collect($unitIds)->contains(
            fn (string $id): bool => MkUnitResource::adaptableMkOptions($id) !== [],
        );

        if ($adaMk) {
            return [];
        }

        return [self::butir(
            'Belum ada mata kuliah untuk ditawarkan',
            'Penawaran memilih mata kuliah milik unit sendiri atau unit induk. Mata kuliah harus ada lebih dulu.',
            self::TIM_KURIKULUM,
            'Buka menu Mata Kuliah → buat mata kuliah, lalu kembali ke Penawaran MK.',
        )];
    }

    // ── Admin Program Studi ──────────────────────────────────────────────

    /** @return list<array<string, string>> */
    private static function kelas(): array
    {
        if (KelasMkResource::mkUnitOptions() === []) {
            return [self::butir(
                'Belum ada mata kuliah yang ditawarkan di unit ini',
                'Kelas hanya bisa dibuat untuk mata kuliah yang sudah ditawarkan (Penawaran MK).',
                self::TIM_KURIKULUM,
                'Buat mata kuliah, lalu tawarkan lewat menu Penawaran MK.',
            )];
        }

        $daftar = KelasMk::query()->with('mkUnit.mk')->get();

        if ($daftar->isEmpty()) {
            return [self::butir(
                'Belum ada kelas',
                'Kelas menghubungkan mata kuliah, semester, Dosen Pengampu, dan peserta. Dosen baru melihat pekerjaan setelah ada kelas yang ia ampu.',
                self::ADMIN_PRODI,
                'Kelas MK → New: pilih MK, semester, kode kelas, dan Dosen Pengampu; lalu tambahkan peserta.',
            )];
        }

        $butir = [];

        $tanpaDosen = $daftar->whereNull('dosen_pengampu_id')->count();

        if ($tanpaDosen > 0) {
            $butir[] = self::butir(
                "{$tanpaDosen} kelas belum punya Dosen Pengampu",
                'Dosen hanya melihat kelas yang ia ampu.',
                self::ADMIN_PRODI,
                'Edit kelas → isi Dosen Pengampu.',
            );
        }

        $belumSiap = $daftar->filter(fn (KelasMk $k): bool => ! $k->penugasanSelesai())
            ->map(fn (KelasMk $k): string => (string) ($k->mkUnit?->mk->nama ?? '—'))
            ->unique()
            ->values();

        if ($belumSiap->isNotEmpty()) {
            $butir[] = self::butir(
                'Kelas belum bisa dinilai karena Koordinator MK belum menentukan tagihan penilaian',
                'Menunggu asesmen (total bobot 100%, terpetakan ke Sub-CPMK) untuk: '.$belumSiap->take(5)->implode(', ')
                    .($belumSiap->count() > 5 ? ', dan lainnya' : '').'.',
                self::KOORDINATOR,
                'Pilih MK → CPMK → Sub-CPMK → Asesmen.',
            );
        }

        return $butir;
    }

    // ── Koordinator MK ───────────────────────────────────────────────────

    /**
     * @return array{0: ?Mk, 1: list<array<string, string>>}
     */
    private static function konteksKoordinator(User $user): array
    {
        if (MataKuliahKoordinatorResource::scopedKoordinatorMkIds($user)->isEmpty()) {
            if (Mk::query()->exists()) {
                return [null, [self::butir(
                    'Anda belum ditetapkan sebagai Koordinator MK',
                    'Daftar mata kuliah Anda terisi dari kolom Koordinator MK pada tiap MK.',
                    self::TIM_KURIKULUM,
                    'Menu Mata Kuliah → edit MK → isi Koordinator MK dengan akun Koordinator.',
                )]];
            }

            return [null, [self::butir(
                'Belum ada mata kuliah',
                'Koordinator MK bekerja di atas mata kuliah yang disusun Tim Kurikulum.',
                self::TIM_KURIKULUM,
                'Menu Mata Kuliah → buat MK, lalu isi Koordinator MK.',
            )]];
        }

        $mk = MkTerpilih::current();

        if (! $mk instanceof Mk) {
            return [null, [self::butir(
                'Mata kuliah belum dipilih',
                'CPMK, Sub-CPMK, asesmen, dan peserta selalu terikat pada satu MK terpilih.',
                self::KOORDINATOR,
                'Buka menu Mata Kuliah → klik MK yang akan dikerjakan.',
            )]];
        }

        return [$mk, []];
    }

    private static function semesterKoordinator(Mk $mk, User $user): ?string
    {
        return SemesterTerpilih::berlakuUntukUser($user) ? SemesterTerpilih::currentId($mk->id) : null;
    }

    /** @return list<array<string, string>> */
    private static function subcpmk(User $user): array
    {
        [$mk, $butir] = self::konteksKoordinator($user);

        if ($mk === null) {
            return $butir;
        }

        $ada = MataKuliahKoordinatorService::ketersediaanPenilaian($mk, $user, self::semesterKoordinator($mk, $user));

        if (! $ada['cpmk']) {
            return [self::butir(
                'CPMK belum ada untuk MK dan semester ini',
                'Sub-CPMK dibuat di bawah CPMK, jadi CPMK harus ada lebih dulu.',
                self::KOORDINATOR,
                'Langkah 1 — buka menu CPMK dan buat CPMK.',
            )];
        }

        return [];
    }

    /** @return list<array<string, string>> */
    private static function asesmen(User $user): array
    {
        [$mk, $butir] = self::konteksKoordinator($user);

        if ($mk === null) {
            return $butir;
        }

        $semesterId = self::semesterKoordinator($mk, $user);
        $ada = MataKuliahKoordinatorService::ketersediaanPenilaian($mk, $user, $semesterId);

        if (! $ada['subcpmk']) {
            return [self::butir(
                'Sub-CPMK belum ada',
                'Setiap asesmen (tagihan penilaian) dipetakan ke Sub-CPMK, dan nilai dosen dihitung lewat pemetaan itu.',
                self::KOORDINATOR,
                $ada['cpmk']
                    ? 'Langkah 2 — buka menu Sub-CPMK dan buat Sub-CPMK.'
                    : 'Mulai dari langkah 1 (CPMK), lalu langkah 2 (Sub-CPMK).',
            )];
        }

        if ($ada['asesmen'] && filled($semesterId)) {
            $alasan = self::alasanAsesmenBelumSiap((string) $mk->id, (string) $semesterId);

            if ($alasan !== null) {
                return [self::butir(
                    'Asesmen belum siap dipakai Dosen Pengampu',
                    "Saat ini: {$alasan}. Dosen baru bisa menilai bila total bobot 100% dan tiap asesmen terpetakan ke Sub-CPMK.",
                    self::KOORDINATOR,
                    'Atur bobot asesmen hingga total 100%, lalu petakan tiap asesmen ke Sub-CPMK.',
                )];
            }
        }

        return [];
    }

    /** @return list<array<string, string>> */
    private static function peserta(User $user): array
    {
        [$mk, $butir] = self::konteksKoordinator($user);

        if ($mk === null) {
            return $butir;
        }

        $kelas = KelasMk::query()->whereHas('mkUnit', fn ($q) => $q->where('mk_id', $mk->id))->get();

        if ($kelas->isEmpty()) {
            return [self::butir(
                'Belum ada kelas untuk mata kuliah ini',
                'Halaman ini menampilkan peserta per kelas. Kelas dibuat oleh Admin Program Studi, bukan oleh Koordinator MK.',
                self::ADMIN_PRODI,
                'Kelas MK → New: pilih MK ini, semester, kode kelas, dan Dosen Pengampu.',
            )];
        }

        if (! $kelas->contains(fn (KelasMk $k): bool => $k->mahasiswas()->exists())) {
            return [self::butir(
                'Kelas belum punya peserta',
                'Nilai hanya bisa diisi untuk mahasiswa yang terdaftar di kelas.',
                self::ADMIN_PRODI,
                'Buka kelas → tambahkan mahasiswa sebagai peserta.',
            )];
        }

        return [];
    }

    /** @return list<array<string, string>> */
    private static function laporan(User $user): array
    {
        [$mk, $butir] = self::konteksKoordinator($user);

        if ($mk === null) {
            return $butir;
        }

        $kelas = KelasMk::query()->whereHas('mkUnit', fn ($q) => $q->where('mk_id', $mk->id))->get();

        if ($kelas->isEmpty()) {
            return [self::butir(
                'Laporan kosong: belum ada kelas',
                'Laporan dihitung dari nilai mahasiswa di kelas mata kuliah ini.',
                self::ADMIN_PRODI,
                'Kelas MK → New: buat kelas dan tambahkan peserta.',
            )];
        }

        if ($kelas->contains(fn (KelasMk $k): bool => ! $k->penugasanSelesai())) {
            $alasan = self::alasanAsesmenBelumSiap((string) $mk->id, (string) $kelas->first()->semester_id);

            return [self::butir(
                'Laporan kosong: asesmen belum siap',
                'Dosen belum bisa menilai sebelum asesmen selesai'.($alasan !== null ? " (saat ini: {$alasan})" : '').'.',
                self::KOORDINATOR,
                'Selesaikan CPMK → Sub-CPMK → Asesmen (total bobot 100%).',
            )];
        }

        $idPeserta = KelasMkMahasiswa::query()->whereIn('kelas_mk_id', $kelas->pluck('id'))->pluck('id');

        if ($idPeserta->isEmpty() || ! NilaiMahasiswa::query()->whereIn('kelas_mk_mahasiswa_id', $idPeserta)->exists()) {
            return [self::butir(
                'Laporan kosong: nilai belum diisi',
                'Laporan baru berisi setelah Dosen Pengampu menyimpan nilai mahasiswa.',
                self::DOSEN,
                'Buka Input Nilai → pilih kelas → isi nilai → Simpan.',
            )];
        }

        return [];
    }

    // ── Dosen Pengampu ───────────────────────────────────────────────────

    /** @return list<array<string, string>> */
    private static function dosen(User $user): array
    {
        $kelasSaya = KelasMk::query()
            ->where('dosen_pengampu_id', $user->id)
            ->with('mkUnit.mk')
            ->get();

        if ($kelasSaya->isEmpty()) {
            if (! MkUnit::query()->exists()) {
                return [self::butir(
                    'Belum ada mata kuliah yang ditawarkan',
                    'Kelas dibuat untuk mata kuliah yang sudah ditawarkan, dan kelas itulah yang muncul di halaman ini.',
                    self::TIM_KURIKULUM,
                    'Buat mata kuliah, lalu tawarkan lewat Penawaran MK. Setelah itu Admin Program Studi membuat kelas.',
                )];
            }

            return [self::butir(
                'Anda belum ditetapkan sebagai Dosen Pengampu di kelas mana pun',
                'Halaman ini hanya menampilkan kelas yang Anda ampu. Kelas dibuat dan pengampunya ditetapkan Admin Program Studi.',
                self::ADMIN_PRODI,
                'Kelas MK → New (atau edit kelas) → isi Dosen Pengampu dengan akun Dosen.',
            )];
        }

        $butir = [];
        $siapTanpaPeserta = [];

        foreach ($kelasSaya->groupBy(fn (KelasMk $k): string => $k->mkUnit?->mk_id.'|'.$k->semester_id) as $grup) {
            /** @var KelasMk $kelas */
            $kelas = $grup->first();
            $mk = $kelas->mkUnit?->mk;

            if (! $mk instanceof Mk) {
                continue;
            }

            if (! $kelas->penugasanSelesai()) {
                $alasan = self::alasanAsesmenBelumSiap((string) $mk->id, (string) $kelas->semester_id)
                    ?? 'asesmen belum lengkap';

                $butir[] = self::butir(
                    "Koordinator MK belum menentukan tagihan penilaian {$mk->nama}",
                    "Dosen baru bisa menilai setelah Koordinator MK menyelesaikan CPMK, Sub-CPMK, dan asesmen (total bobot 100%). Saat ini: {$alasan}.",
                    self::KOORDINATOR,
                    'Koordinator MK: pilih MK → CPMK → Sub-CPMK → Asesmen. Menu Input Nilai muncul otomatis begitu ada kelas yang siap.',
                );

                continue;
            }

            if (! $kelas->mahasiswas()->exists()) {
                $siapTanpaPeserta[] = (string) $mk->nama;
            }
        }

        if ($siapTanpaPeserta !== []) {
            $butir[] = self::butir(
                'Kelas yang siap dinilai belum punya peserta: '.implode(', ', array_unique($siapTanpaPeserta)),
                'Nilai hanya bisa diisi untuk mahasiswa yang terdaftar di kelas.',
                self::ADMIN_PRODI,
                'Buka kelas → tambahkan mahasiswa sebagai peserta.',
            );
        }

        return $butir;
    }

    // ── Pembantu ─────────────────────────────────────────────────────────

    /**
     * Alasan tunggal paling awal mengapa MK pada semester itu belum bisa
     * dinilai, atau null bila CPMK, Sub-CPMK, dan asesmennya sudah lengkap.
     */
    private static function alasanAsesmenBelumSiap(string $mkId, string $semesterId): ?string
    {
        if (blank($semesterId)) {
            return 'semester kelas belum ditetapkan';
        }

        if (! Cpmk::query()->where('mk_id', $mkId)->untukSemester($semesterId)->exists()) {
            return 'CPMK belum ditetapkan';
        }

        $adaSubcpmk = Subcpmk::query()
            ->untukSemester($semesterId)
            ->whereHas('mkCpmk.cpmk', fn ($q) => $q->where('mk_id', $mkId))
            ->exists();

        if (! $adaSubcpmk) {
            return 'Sub-CPMK belum dibuat';
        }

        $komponen = KomponenPenilaian::query()
            ->where('mk_id', $mkId)
            ->untukSemester($semesterId)
            ->withCount(['subcpmkKomponens' => fn ($q) => $q->where('semester_id', $semesterId)])
            ->get();

        if ($komponen->isEmpty()) {
            return 'asesmen (tagihan penilaian) belum disusun';
        }

        $total = (float) $komponen->sum(fn (KomponenPenilaian $k): float => $k->bobotUntukSemester($semesterId));

        if (abs($total - 100.0) > 0.005) {
            return 'total bobot asesmen '.rtrim(rtrim(number_format($total, 2, ',', ''), '0'), ',').'% (harus 100%)';
        }

        $belumDipetakan = $komponen->filter(fn (KomponenPenilaian $k): bool => (int) $k->subcpmk_komponens_count === 0)
            ->pluck('kode');

        if ($belumDipetakan->isNotEmpty()) {
            return 'asesmen '.$belumDipetakan->implode(', ').' belum dipetakan ke Sub-CPMK';
        }

        return null;
    }

    /**
     * @param  array{nama: string, peran: string}  $penangan
     * @return array{judul: string, rincian: string, nama: string, peran: string, langkah: string}
     */
    private static function butir(string $judul, string $rincian, array $penangan, string $langkah): array
    {
        return [
            'judul' => $judul,
            'rincian' => $rincian,
            'nama' => $penangan['nama'],
            'peran' => $penangan['peran'],
            'langkah' => $langkah,
        ];
    }
}
