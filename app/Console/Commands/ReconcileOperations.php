<?php

namespace App\Console\Commands;

use App\Models\ProvisionOperation;
use App\Services\ProvisioningService;
use Illuminate\Console\Command;

class ReconcileOperations extends Command
{
    protected $signature = 'homa:reconcile-operations';
    protected $description = 'تطبیق عملیات نامشخص ساخت و تمدید با مرزبان';

    public function handle(ProvisioningService $provisioning): int
    {
        $checked = 0; $resolved = 0;
        ProvisionOperation::query()->where('status', 'unknown')->where('updated_at', '<=', now()->subMinute())->with(['service.panel'])->oldest()->limit(50)->get()
            ->each(function (ProvisionOperation $operation) use ($provisioning, &$checked, &$resolved) {
                $checked++; if ($provisioning->reconcileUnknown($operation)) $resolved++;
            });
        $this->info("بررسی: $checked | تعیین تکلیف: $resolved");
        return self::SUCCESS;
    }
}
