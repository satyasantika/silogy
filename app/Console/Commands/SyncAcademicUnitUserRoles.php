<?php

namespace App\Console\Commands;

use App\Modules\Institusi\Support\AcademicUnitUserRoleSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill: sinkronkan role global "Pimpinan"/"Tim Kurikulum"/"Validator Nilai"
 * untuk seluruh pengguna di academic_unit_users, kurikulum_validator, dan
 * pemegang role Validator Nilai saat ini.
 */
class SyncAcademicUnitUserRoles extends Command
{
    protected $signature = 'academic-unit:sync-roles';

    protected $description = 'Sinkronkan role Pimpinan/Tim Kurikulum/Validator Nilai berdasarkan penugasan unit, validator kurikulum, dan pemegang role yang sudah ada';

    public function handle(AcademicUnitUserRoleSync $roleSync): int
    {
        $userIds = $roleSync->userIdsTerkait();

        if ($userIds->isEmpty()) {
            $this->info('Tidak ada penugasan unit, validator kurikulum, maupun pemegang peran Validator Nilai — tidak ada yang disinkronkan.');

            return self::SUCCESS;
        }

        $usersProcessed = 0;
        $totalGranted = 0;
        $totalRevoked = 0;

        DB::transaction(function () use ($userIds, $roleSync, &$usersProcessed, &$totalGranted, &$totalRevoked): void {
            foreach ($userIds as $userId) {
                $tally = $roleSync->syncForUser($userId);

                $usersProcessed++;
                $totalGranted += $tally['granted'];
                $totalRevoked += $tally['revoked'];
            }
        });

        $this->info("Selesai. {$usersProcessed} pengguna diproses, {$totalGranted} role diberikan, {$totalRevoked} role dicabut.");

        return self::SUCCESS;
    }
}
