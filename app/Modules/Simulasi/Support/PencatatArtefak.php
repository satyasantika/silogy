<?php

namespace App\Modules\Simulasi\Support;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\BoK\Models\BokKodeOverride;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplKodeOverride;
use App\Modules\CPL\Models\CplMk;
use App\Modules\CPL\Models\CplProfilLulusan;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilIndikator;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Kurikulum\Models\StateTransition;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\CpmkSemester;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Models\PerubahanCpmkRequest;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\MK\Models\SubcpmkSemester;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\KomponenPenilaianSemester;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use App\Modules\Simulasi\Models\SimulasiArtefak;
use App\Modules\Simulasi\Models\SimulasiJalan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

/**
 * Mencatat setiap baris yang BENAR-BENAR dibuat selama satu jalan simulasi.
 *
 * Bekerja lewat listener Eloquent bertanda bintang, bukan dengan menyunting
 * ~60 pemanggilan firstOrCreate di SimulasiAkademikBuilder. Konsekuensinya
 * penting dan disengaja: firstOrCreate yang MENEMUKAN baris lama tidak
 * memancarkan event `created`, sehingga baris itu tidak pernah tercatat dan
 * tidak akan pernah ikut terhapus. Di situlah jaminan "tidak menyentuh data
 * nyata" berada.
 */
class PencatatArtefak
{
    /**
     * Model yang boleh dimiliki simulasi. Daftar IZIN, bukan daftar tolak:
     * apa pun yang lahir di luar daftar ini dilaporkan sebagai peringatan
     * supaya tidak ada baris yatim yang lolos diam-diam.
     *
     * @var list<class-string<Model>>
     */
    public const MODEL_DIMILIKI = [
        AcademicUnit::class,
        AcademicUnitUser::class,
        User::class,
        Mahasiswa::class,
        Kurikulum::class,
        ProfilLulusan::class,
        ProfilIndikator::class,
        StateTransition::class,
        Cpl::class,
        CplProfilLulusan::class,
        CplBok::class,
        CplMk::class,
        CplKodeOverride::class,
        Bok::class,
        BokKodeOverride::class,
        Mk::class,
        MkUnit::class,
        Cpmk::class,
        CpmkSemester::class,
        MkCpmk::class,
        Subcpmk::class,
        SubcpmkSemester::class,
        KelasMk::class,
        KomponenPenilaian::class,
        KomponenPenilaianSemester::class,
        SubcpmkKomponenPenilaian::class,
        PerubahanCpmkRequest::class,
    ];

    /**
     * Baris turunan: TIDAK dicatat karena dijamin ikut terhapus lewat CASCADE
     * dari baris yang sudah dicatat (seluruhnya diperiksa di information_schema):
     *
     *  - kelas_mk_mahasiswa → CASCADE dari kelas_mk DAN dari mahasiswas
     *  - nilai_mahasiswas   → CASCADE dari kelas_mk_mahasiswa
     *  - hasil_cpmk/_subcpmk→ CASCADE dari kelas_mk & kelas_mk_mahasiswa
     *  - hasil_cpl_mk       → CASCADE dari cpl, mk_units, kelas_mk_mahasiswa
     *  - hasil_cpl_unit/_mk_unit → CASCADE dari academic_units, cpl, mk
     *
     * Ada alasan kedua yang memaksa keputusan ini: SimulasiAkademikBuilder
     * membungkus pembuatan peserta dan nilai dengan NilaiMahasiswa::withoutEvents(),
     * dan withoutEvents mematikan dispatcher untuk SELURUH model, bukan cuma
     * NilaiMahasiswa. Baris-baris itu memang tak akan pernah terlihat listener.
     * Mencatatnya pun mubazir: ribuan penghapusan satu per satu untuk pekerjaan
     * yang sudah dikerjakan mesin basis data dalam satu langkah.
     *
     * Cacahnya tetap dilaporkan di halaman Simulasi, dihitung dari akar yang
     * tercatat — lihat SimulasiService::cacahTurunan().
     *
     * @var list<string>
     */
    public const MODEL_TURUNAN = [
        KelasMkMahasiswa::class,
        NilaiMahasiswa::class,
        'App\Modules\Kalkulasi\Models\HasilCpmk',
        'App\Modules\Kalkulasi\Models\HasilSubcpmk',
        'App\Modules\Kalkulasi\Models\HasilCplMk',
        'App\Modules\Kalkulasi\Models\HasilCplUnit',
        'App\Modules\Kalkulasi\Models\HasilCplMkUnit',
        'Spatie\Activitylog\Models\Activity',
    ];

    /**
     * Infrastruktur bersama yang simulasi PINJAM, tidak pernah miliki.
     *
     * Peran, izin, master evaluasi, dan semester dipakai bersama data nyata.
     * Seluruh foreign key ke `semesters` dan `evaluasi` pun bersifat RESTRICT,
     * jadi menghapusnya bukan sekadar tidak sopan — memang tidak mungkin.
     *
     * @var list<string>
     */
    public const MODEL_DIPINJAM = [
        Role::class,
        Permission::class,
        Evaluasi::class,
        Semester::class,
    ];

    protected ?SimulasiJalan $jalan = null;

    protected bool $terpasang = false;

    /** @var list<array{simulasi_jalan_id: string, model_type: string, model_uuid: string, created_at: string}> */
    protected array $penyangga = [];

    /** @var array<string, true> */
    protected array $sudahDicatat = [];

    /** @var array<string, int> */
    protected array $takDikenal = [];

    /**
     * Jalankan $bangun sambil merekam seluruh baris baru ke dalam $jalan.
     *
     * @template T
     *
     * @param  callable(): T  $bangun
     * @return T
     */
    public function rekam(SimulasiJalan $jalan, callable $bangun): mixed
    {
        $this->pasangSekali();

        $this->jalan = $jalan;
        $this->penyangga = [];
        $this->sudahDicatat = [];
        $this->takDikenal = [];

        try {
            return $bangun();
        } finally {
            $this->siram();
            $this->jalan = null;
        }
    }

    /**
     * Catat satu model secara eksplisit. Diperlukan untuk baris yang dibuat
     * di dalam withoutEvents(), yang menurut definisinya tak terlihat listener.
     */
    public function catat(Model $model): void
    {
        if ($this->jalan === null) {
            return;
        }

        $kelas = $model->getMorphClass();
        $kunci = $kelas.':'.$model->getKey();

        if (isset($this->sudahDicatat[$kunci])) {
            return;
        }

        if (in_array($kelas, self::MODEL_TURUNAN, true)
            || in_array($kelas, self::MODEL_DIPINJAM, true)) {
            return;
        }

        if (! in_array($kelas, self::MODEL_DIMILIKI, true)) {
            $this->takDikenal[$kelas] = ($this->takDikenal[$kelas] ?? 0) + 1;

            return;
        }

        $this->sudahDicatat[$kunci] = true;
        $this->penyangga[] = [
            'simulasi_jalan_id' => $this->jalan->getKey(),
            'model_type' => $kelas,
            'model_uuid' => (string) $model->getKey(),
            'created_at' => now()->toDateTimeString(),
        ];

        if (count($this->penyangga) >= 500) {
            $this->siram();
        }
    }

    /**
     * Model yang lahir tanpa masuk daftar izin, untuk ditampilkan sebagai
     * peringatan di halaman Simulasi.
     *
     * @return array<string, int>
     */
    public function takDikenal(): array
    {
        return $this->takDikenal;
    }

    protected function siram(): void
    {
        if ($this->penyangga === []) {
            return;
        }

        SimulasiArtefak::query()->insert($this->penyangga);
        $this->penyangga = [];
    }

    /**
     * Listener dipasang sekali lalu dibiarkan hidup; aktif-tidaknya ditentukan
     * oleh $this->jalan. Sengaja tidak memakai Event::forget('eloquent.created: *')
     * yang akan ikut mencabut listener milik pihak lain pada pola yang sama.
     */
    protected function pasangSekali(): void
    {
        if ($this->terpasang) {
            return;
        }

        Event::listen('eloquent.created: *', function (string $peristiwa, array $muatan): void {
            if ($this->jalan === null) {
                return;
            }

            $model = $muatan[0] ?? null;

            if ($model instanceof Model) {
                $this->catat($model);
            }
        });

        $this->terpasang = true;
    }
}
