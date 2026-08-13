<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProvisionOperation;
use App\Services\ProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OperationController extends Controller
{
    public function index(): View
    {
        return view('admin.operations.index', [
            'operations' => ProvisionOperation::query()->with(['reseller', 'service'])->latest()->paginate(25),
        ]);
    }

    public function reconcile(ProvisionOperation $operation, ProvisioningService $provisioning): RedirectResponse
    {
        if ($operation->status !== 'unknown') return back()->withErrors(['operation' => 'فقط عملیات با نتیجه نامشخص قابل تطبیق است.']);
        return $provisioning->reconcileUnknown($operation->load('service.panel'))
            ? back()->with('success', 'عملیات با مرزبان تطبیق و تعیین تکلیف شد.')
            : back()->withErrors(['operation' => 'مرزبان هنوز پاسخ قطعی نداد؛ عملیات بدون تغییر باقی ماند.']);
    }
}
