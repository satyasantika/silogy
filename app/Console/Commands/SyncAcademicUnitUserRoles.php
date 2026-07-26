<?php

namespace App\Console\Commands;

use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Institusi\Support\AcademicUnitUserRoleSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill satu-kali: sinkronkan role global "Pimpinan"/"Tim Kurikulum"
 * untuk seluruh baris academic_unit_users yang sudah ada di database
 * sebelum AcademicUnitUserObserver dipasang.
 */
class SyncAcademicUnitUserRoles extends Command
{
    protected $signature = 'academic-unit:sync-roles';

    protected $description = 'Sinkronkan role Pimpinan/Tim Kurikulum berdasarkan penugasan unit akademik yang sudah ada';

    public function handle(AcademicUnitUserRoleSync $roleSync): int
    {
        $userIds = AcademicUnitUser::query()->distinct()->pluck('user_id');

        if ($userIds->isEmpty()) {
            $this->info('Tidak ada data academic_unit_users — tidak ada yang disinkronkan.');

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
