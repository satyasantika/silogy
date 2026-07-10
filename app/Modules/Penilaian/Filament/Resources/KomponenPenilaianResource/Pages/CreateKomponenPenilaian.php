<?php

namespace App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages;

use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\Concerns\ValidatesBobotKomponenSama100;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Policies\KomponenPenilaianPolicy;
use App\Modules\Penilaian\Services\KomponenPenilaianMassalService;
use App\Support\Filament\Pages\BaseCreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class CreateKomponenPenilaian extends BaseCreateRecord
{
    use HasKoordinatorMkScope;
    use ValidatesBobotKomponenSama100;

    protected static string $resource = KomponenPenilaianResource::class;

    protected function beforeCreate(): void
    {
        $data = $this->form->getState();
        $user = auth()->user();

        if (! $user || ! app(KomponenPenilaianPolicy::class)->create($user)) {
            throw new AuthorizationException;
        }

        if (KomponenPenilaianMassalService::resolveUntukPembuatan() === null) {
            $kelasMk = KelasMk::query()->find($data['kelas_mk_id'] ?? null);

            if (! $kelasMk instanceof KelasMk) {
                throw new AuthorizationException;
            }

            $komponen = new KomponenPenilaian(['kelas_mk_id' => $kelasMk->id]);
            $komponen->setRelation('kelasMk', $kelasMk);

            if (! app(KomponenPenilaianPolicy::class)->update($user, $komponen)) {
                throw new AuthorizationException;
            }
        }

        $this->validateBobotKomponenSama100($data);
    }

    /**
     * Asesmen berlaku untuk semua kelas pada mata kuliah + semester
     * terpilih; buat/perbarui (berdasar kode) komponen di setiap kelasnya.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $massal = KomponenPenilaianMassalService::resolveUntukPembuatan();

        if ($massal === null) {
            return static::getModel()::create($data);
        }

        $payload = collect($data)->only(['kode', 'evaluasi_id', 'nama', 'bobot'])->all();

        return KomponenPenilaianMassalService::buatUntukSemuaKelas($payload, $massal);
    }
}
