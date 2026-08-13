<?php

namespace App\Http\Controllers;

use App\Models\DepositRequest;
use App\Models\ServiceAccount;
use App\Models\WalletJournal;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        if ($request->user()->isAdmin()) return view('admin.dashboard', [
            'resellerCount' => \App\Models\User::query()->where('role', 'reseller')->count(),
            'activeServiceCount' => ServiceAccount::query()->where('status', 'active')->count(),
            'pendingDepositCount' => DepositRequest::query()->where('status', 'pending')->count(),
            'totalWallets' => \App\Models\User::query()->where('role', 'reseller')->sum('wallet_balance_irr'),
            'unknownOperationCount' => \App\Models\ProvisionOperation::query()->where('status', 'unknown')->count(),
        ]);

        $user = $request->user();
        return view('dashboard', [
            'activeServices' => $user->services()->where('status', 'active')->count(),
            'allServices' => $user->services()->count(),
            'pendingDeposits' => $user->deposits()->where('status', 'pending')->count(),
            'recentServices' => $user->services()->with('plan')->latest()->limit(5)->get(),
            'recentJournals' => WalletJournal::query()->where('reseller_id', $user->id)->latest('created_at')->limit(5)->get(),
        ]);
    }
}
