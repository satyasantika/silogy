<?php

namespace App\Modules\Kalkulasi\Jobs;

use App\Modules\Kalkulasi\Services\CplMkCalculator;
use App\Modules\Kalkulasi\Services\CplMkUnitCalculator;
use App\Modules\Kalkulasi\Services\CplUnitAggregator;
use App\Modules\Kalkulasi\Services\CpmkCalculator;
use App\Modules\Kalkulasi\Services\SubcpmkCalculator;
use App\Modules\Kalkulasi\Support\DashboardCplCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalkulasiCplJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(
        public string $kelasMkId,
        public string $academicUnitId,
        public string $semesterId,
    ) {
        $this->onQueue('cpl-calculation');
    }

    public function handle(
        SubcpmkCalculator $subcpmkCalculator,
        CpmkCalculator $cpmkCalculator,
        CplMkCalculator $cplMkCalculator,
        CplMkUnitCalculator $cplMkUnitCalculator,
        CplUnitAggregator $cplUnitAggregator,
    ): void {
        $subcpmkCalculator->calculate($this->kelasMkId);
        $cpmkCalculator->calculate($this->kelasMkId);
        $cplMkCalculator->calculate($this->kelasMkId, $this->semesterId);
        $cplMkUnitCalculator->calculate($this->academicUnitId, $this->semesterId);
        $cplUnitAggregator->aggregate($this->academicUnitId, $this->semesterId);

        DashboardCplCache::invalidate($this->academicUnitId, $this->semesterId);
    }
}
