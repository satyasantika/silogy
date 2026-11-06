<?php

use App\Modules\CPL\Models\Cpl;
use App\Modules\Institusi\Models\AcademicUnit;
use Database\Seeders\AcademicUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/*
 * Domain CPL lama (kognitif/afektif/psikomotorik) dipetakan ke label
 * Permendiktisaintek 39/2025: pengetahuan/sikap/keterampilan — dan bisa
 * dikembalikan.
 */

uses(RefreshDatabase::class);

function migrasiDomainPermen39(): object
{
    return require database_path('migrations/2026_11_06_000001_ubah_domain_cpl_ke_sikap_pengetahuan_keterampilan.php');
}

function domainMentah(string $id): mixed
{
    $raw = DB::table('cpl')->where('id', $id)->value('domain');

    return $raw === null ? null : json_decode((string) $raw, true);
}

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();

    $buat = function (?array $domain) use ($prodi): string {
        $cpl = Cpl::factory()->forAcademicUnit($prodi)->create();
        DB::table('cpl')->where('id', $cpl->id)->update([
            'domain' => $domain === null ? null : json_encode($domain),
        ]);

        return $cpl->id;
    };

    $this->ganda = $buat(['kognitif', 'afektif']);
    $this->tunggal = $buat(['psikomotorik']);
    $this->kosong = $buat(null);
    $this->larikKosong = $buat([]);
});

it('memetakan domain lama ke sikap, pengetahuan, keterampilan', function () {
    migrasiDomainPermen39()->up();

    expect(domainMentah($this->ganda))->toBe(['pengetahuan', 'sikap'])
        ->and(domainMentah($this->tunggal))->toBe(['keterampilan'])
        ->and(domainMentah($this->kosong))->toBeNull()
        ->and(domainMentah($this->larikKosong))->toBe([]);
});

it('rollback mengembalikan domain lama', function () {
    $migrasi = migrasiDomainPermen39();
    $migrasi->up();
    $migrasi->down();

    expect(domainMentah($this->ganda))->toBe(['kognitif', 'afektif'])
        ->and(domainMentah($this->tunggal))->toBe(['psikomotorik'])
        ->and(domainMentah($this->kosong))->toBeNull()
        ->and(domainMentah($this->larikKosong))->toBe([]);
});
