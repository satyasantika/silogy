<?php

namespace App\Modules\MK\Services;

use App\Models\User;
use App\Modules\Kurikulum\Models\StateTransition;
use App\Modules\MK\Enums\StatusPerubahanCpmk;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\PerubahanCpmkRequest;
use App\Modules\MK\Support\GerbangPerubahanCpmk;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Siklus hidup usulan perubahan CPMK.
 *
 * Riwayat keputusannya ditulis ke state_transitions — tabel itu sudah
 * polimorfik (model_type/model_id/from_state/to_state/actor_id) walau
 * lahir untuk Kurikulum, jadi jejak audit didapat tanpa perlu memasang
 * state machine untuk empat nilai.
 */
class PerubahanCpmkService
{
    /**
     * @param  list<array<string, mixed>>|null  $ringkasan
     */
    public function ajukan(Mk $mk, string $semesterId, User $pengusul, string $alasan, ?array $ringkasan = null): PerubahanCpmkRequest
    {
        $terbuka = GerbangPerubahanCpmk::usulanTerbuka($mk, $semesterId);

        if ($terbuka !== null) {
            throw ValidationException::withMessages([
                'alasan' => 'Sudah ada usulan perubahan CPMK yang menunggu keputusan untuk mata kuliah dan semester ini.',
            ]);
        }

        $mk->loadMissing('academicUnit');

        return DB::transaction(function () use ($mk, $semesterId, $pengusul, $alasan, $ringkasan): PerubahanCpmkRequest {
            $usulan = PerubahanCpmkRequest::query()->create([
                'mk_id' => $mk->id,
                'semester_id' => $semesterId,
                'academic_unit_id' => $mk->academic_unit_id,
                'diajukan_oleh_id' => $pengusul->id,
                'status' => StatusPerubahanCpmk::Diajukan,
                'alasan' => $alasan,
                'ringkasan_usulan' => $ringkasan ?? $this->ringkasanCpmkBerjalan($mk, $semesterId),
            ]);

            $this->catatTransisi($usulan, null, StatusPerubahanCpmk::Diajukan, $pengusul);

            return $usulan;
        });
    }

    public function setujui(PerubahanCpmkRequest $usulan, User $peninjau, ?string $catatan = null): void
    {
        $this->putuskan($usulan, StatusPerubahanCpmk::Disetujui, $peninjau, $catatan);
    }

    public function tolak(PerubahanCpmkRequest $usulan, User $peninjau, string $catatan): void
    {
        $this->putuskan($usulan, StatusPerubahanCpmk::Ditolak, $peninjau, $catatan);
    }

    public function batalkan(PerubahanCpmkRequest $usulan, User $pengusul): void
    {
        $this->putuskan($usulan, StatusPerubahanCpmk::Dibatalkan, $pengusul, null);
    }

    private function putuskan(
        PerubahanCpmkRequest $usulan,
        StatusPerubahanCpmk $status,
        User $aktor,
        ?string $catatan,
    ): void {
        if (! $usulan->menunggu()) {
            throw ValidationException::withMessages([
                'status' => 'Usulan ini sudah diputuskan sebelumnya.',
            ]);
        }

        /** @var StatusPerubahanCpmk $sebelum */
        $sebelum = $usulan->status;

        DB::transaction(function () use ($usulan, $status, $aktor, $catatan, $sebelum): void {
            $usulan->update([
                'status' => $status,
                'ditinjau_oleh_id' => $aktor->id,
                'ditinjau_pada' => now(),
                'catatan_peninjau' => $catatan,
            ]);

            $this->catatTransisi($usulan, $sebelum, $status, $aktor);
        });
    }

    private function catatTransisi(
        PerubahanCpmkRequest $usulan,
        ?StatusPerubahanCpmk $dari,
        StatusPerubahanCpmk $ke,
        User $aktor,
    ): void {
        StateTransition::query()->create([
            'model_type' => PerubahanCpmkRequest::class,
            'model_id' => $usulan->id,
            'from_state' => $dari?->value,
            'to_state' => $ke->value,
            'actor_id' => $aktor->id,
        ]);
    }

    /**
     * Potret CPMK yang sedang berlaku, disimpan bersama usulan supaya Tim
     * Kurikulum tahu apa yang hendak diganti — barisnya sendiri baru berubah
     * setelah usulan disetujui.
     *
     * @return list<array{kode: string, deskripsi: string}>
     */
    private function ringkasanCpmkBerjalan(Mk $mk, string $semesterId): array
    {
        return Cpmk::query()
            ->where('mk_id', $mk->id)
            ->untukSemester($semesterId)
            ->orderBy('kode')
            ->get()
            ->map(fn (Cpmk $cpmk): array => [
                'kode' => (string) $cpmk->kode,
                'deskripsi' => (string) $cpmk->deskripsi,
            ])
            ->all();
    }
}
