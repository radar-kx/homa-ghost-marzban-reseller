<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DepositRequest;
use App\Models\ServiceAccount;
use App\Models\User;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'resellerCount' => User::query()->where('role', 'reseller')->count(),
            'activeServiceCount' => ServiceAccount::query()->where('status', 'active')->count(),
            'pendingDepositCount' => DepositRequest::query()->where('status', 'pending')->count(),
            'totalWallets' => User::query()->where('role', 'reseller')->sum('wallet_balance_irr'),
            'unknownOperationCount' => \App\Models\ProvisionOperation::query()->where('status', 'unknown')->count(),
        ]);
    }
}
