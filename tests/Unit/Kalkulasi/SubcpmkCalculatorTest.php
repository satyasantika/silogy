<?php

use App\Modules\Kalkulasi\Models\HasilSubcpmk;
use App\Modules\Kalkulasi\Services\SubcpmkCalculator;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;
use Tests\Unit\Kalkulasi\Concerns\SetsUpKalkulasiFixtures;

uses(TestCase::class, RefreshDatabase::class, SetsUpKalkulasiFixtures::class);

beforeEach(function () {
    $this->seedKalkulasiBase();
    $this->calculator = app(SubcpmkCalculator::class);
});

it('menghitung nilai subcpmk tertimbang untuk semua komponen (kasus normal)', function () {
    $dasar = $this->createKelasPenilaianDasar();

    // Bobot pivot langsung berupa kontribusi nyata (skala sama dengan
    // bobot komponen) — satu-satunya Sub-CPMK pada masing-masing komponen,
    // jadi kontribusinya sama dengan bobot komponen itu sendiri (60, 40).
    $uts = $this->buatKomponenSkp($dasar['kelas'], $dasar['subcpmk'], $dasar['evaluasi'], 'UTS', 60, 60);
    $uas = $this->buatKomponenSkp($dasar['kelas'], $dasar['subcpmk'], $dasar['evaluasi'], 'UAS', 40, 40);

    $this->isiNilai($uts, $dasar['kmm'], 80);
    $this->isiNilai($uas, $dasar['kmm'], 90);

    $this->calculator->calculate($dasar['kelas']->id);

    $hasil = HasilSubcpmk::query()
        ->where('subcpmk_id', $dasar['subcpmk']->id)
        ->where('kelas_mk_mahasiswa_id', $dasar['kmm']->id)
        ->first();

    expect($hasil)->not->toBeNull()
        ->and((float) $hasil->nilai_akhir)->toBe(84.0)
        ->and($hasil->kelas_mk_id)->toBe($dasar['kelas']->id);
});

it('melewati insert bila tidak ada nilai sama sekali (kasus null)', function () {
    $dasar = $this->createKelasPenilaianDasar();

    $this->buatKomponenSkp($dasar['kelas'], $dasar['subcpmk'], $dasar['evaluasi'], 'UTS', 100, 100);

    $this->calculator->calculate($dasar['kelas']->id);

    expect(HasilSubcpmk::query()->count())->toBe(0);
});

it('menghitung ulang bobot hanya dari komponen yang punya nilai (kasus partial)', function () {
    $dasar = $this->createKelasPenilaianDasar();

    $uts = $this->buatKomponenSkp($dasar['kelas'], $dasar['subcpmk'], $dasar['evaluasi'], 'UTS', 60, 100);
    $this->buatKomponenSkp($dasar['kelas'], $dasar['subcpmk'], $dasar['evaluasi'], 'UAS', 40, 100);

    $this->isiNilai($uts, $dasar['kmm'], 75);

    $this->calculator->calculate($dasar['kelas']->id);

    $hasil = HasilSubcpmk::query()
        ->where('subcpmk_id', $dasar['subcpmk']->id)
        ->where('kelas_mk_mahasiswa_id', $dasar['kmm']->id)
        ->first();

    expect($hasil)->not->toBeNull()
        ->and((float) $hasil->nilai_akhir)->toBe(75.0);
});

it('meng-update baris hasil yang sudah ada via upsert', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $skp = $this->buatKomponenSkp($dasar['kelas'], $dasar['subcpmk'], $dasar['evaluasi'], 'UTS', 100, 100);

    $this->isiNilai($skp, $dasar['kmm'], 70);
    $this->calculator->calculate($dasar['kelas']->id);

    $this->isiNilai($skp, $dasar['kmm'], 95);
    $this->calculator->calculate($dasar['kelas']->id);

    expect(HasilSubcpmk::query()->count())->toBe(1)
        ->and((float) HasilSubcpmk::query()->first()->nilai_akhir)->toBe(95.0);
});

it('mengembalikan null dari hitungNilaiAkhir bila penyebut nol', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $skp = $this->buatKomponenSkp($dasar['kelas'], $dasar['subcpmk'], $dasar['evaluasi'], 'UTS', 100, 100);

    $komponens = SubcpmkKomponenPenilaian::query()
        ->whereKey($skp->id)
        ->with(['komponenPenilaian', 'nilaiMahasiswas'])
        ->get();

    expect($this->calculator->hitungNilaiAkhir($komponens, $dasar['kmm']->id))->toBeNull();
});

it('tidak melakukan apa pun bila kelas tanpa mahasiswa', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $dasar['kmm']->delete();

    $this->buatKomponenSkp($dasar['kelas'], $dasar['subcpmk'], $dasar['evaluasi'], 'UTS', 100, 100);

    $this->calculator->calculate($dasar['kelas']->id);

    expect(HasilSubcpmk::query()->count())->toBe(0);
});

it('menghitung bobot gabungan komponen dan subcpmk pada hitungNilaiAkhir', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $skp = $this->buatKomponenSkp($dasar['kelas'], $dasar['subcpmk'], $dasar['evaluasi'], 'TGS', 50, 80);
    $this->isiNilai($skp, $dasar['kmm'], 100);

    $komponens = SubcpmkKomponenPenilaian::query()
        ->where('subcpmk_id', $dasar['subcpmk']->id)
        ->with(['komponenPenilaian', 'nilaiMahasiswas'])
        ->get();

    $nilai = $this->calculator->hitungNilaiAkhir($komponens, $dasar['kmm']->id);

    expect($nilai)->toBe(100.0);
});

it('mengelompokkan beberapa subcpmk dalam satu kelas', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $subcpmk2 = $this->buatSubcpmkKedua($dasar['subcpmk']);

    $skp1 = $this->buatKomponenSkp($dasar['kelas'], $dasar['subcpmk'], $dasar['evaluasi'], 'UTS1', 100, 100);
    $skp2 = $this->buatKomponenSkp($dasar['kelas'], $subcpmk2, $dasar['evaluasi'], 'UTS2', 100, 100);

    $this->isiNilai($skp1, $dasar['kmm'], 60);
    $this->isiNilai($skp2, $dasar['kmm'], 80);

    $this->calculator->calculate($dasar['kelas']->id);

    expect(HasilSubcpmk::query()->count())->toBe(2);
});

it('mengabaikan komponen penilaian kelas lain', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $lain = $this->createKelasPenilaianDasar('IF-KALK-B');

    $skp = $this->buatKomponenSkp($dasar['kelas'], $dasar['subcpmk'], $dasar['evaluasi'], 'UTS', 100, 100);
    $this->isiNilai($skp, $dasar['kmm'], 88);

    $skpLain = $this->buatKomponenSkp($lain['kelas'], $lain['subcpmk'], $lain['evaluasi'], 'UTS', 100, 100);
    $this->isiNilai($skpLain, $lain['kmm'], 10);

    $this->calculator->calculate($dasar['kelas']->id);

    expect((float) HasilSubcpmk::query()->first()->nilai_akhir)->toBe(88.0);
});

it('menghitung nilai dengan bobot subcpmk komponen di bawah 100 persen', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $skp = $this->buatKomponenSkp($dasar['kelas'], $dasar['subcpmk'], $dasar['evaluasi'], 'QUIZ', 100, 50);
    $this->isiNilai($skp, $dasar['kmm'], 80);

    $komponens = SubcpmkKomponenPenilaian::query()
        ->where('subcpmk_id', $dasar['subcpmk']->id)
        ->with(['komponenPenilaian', 'nilaiMahasiswas'])
        ->get();

    expect($this->calculator->hitungNilaiAkhir($komponens, $dasar['kmm']->id))->toBe(80.0);
});

it('mengembalikan null bila koleksi komponen kosong pada hitungNilaiAkhir', function () {
    expect($this->calculator->hitungNilaiAkhir(new Collection, 'kmm'))->toBeNull();
});
