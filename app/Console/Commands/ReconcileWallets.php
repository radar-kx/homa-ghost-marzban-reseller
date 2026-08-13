<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileWallets extends Command
{
    protected $signature = 'homa:reconcile-wallets {--fix : بازسازی کش موجودی از دفترکل}';
    protected $description = 'کنترل توازن دفترکل و تطبیق موجودی نمایندگان';

    public function handle(): int
    {
        $debits = (int) DB::table('ledger_entries')->where('side', 'debit')->sum('amount_irr');
        $credits = (int) DB::table('ledger_entries')->where('side', 'credit')->sum('amount_irr');
        if ($debits !== $credits) { $this->error("دفترکل نامتوازن است: debit=$debits credit=$credits"); return self::FAILURE; }

        $mismatches = 0;
        User::query()->where('role', 'reseller')->orderBy('id')->chunkById(100, function ($users) use (&$mismatches) {
            foreach ($users as $user) {
                $accountId = DB::table('ledger_accounts')->where(['owner_type' => 'reseller', 'owner_id' => $user->id, 'code' => 'wallet_liability', 'currency' => 'IRR'])->value('id');
                $credit = $accountId ? (int) DB::table('ledger_entries')->where('ledger_account_id', $accountId)->where('side', 'credit')->sum('amount_irr') : 0;
                $debit = $accountId ? (int) DB::table('ledger_entries')->where('ledger_account_id', $accountId)->where('side', 'debit')->sum('amount_irr') : 0;
                $ledgerBalance = $credit - $debit;
                if ($ledgerBalance !== (int) $user->wallet_balance_irr) {
                    $mismatches++;
                    $this->warn("نماینده {$user->id}: cache={$user->wallet_balance_irr}, ledger={$ledgerBalance}");
                    if ($this->option('fix')) $user->forceFill(['wallet_balance_irr' => max(0, $ledgerBalance)])->save();
                }
            }
        });
        $this->info($mismatches === 0 ? 'دفترکل و همه موجودی‌ها تطبیق دارند.' : "تعداد مغایرت: $mismatches");
        return $mismatches > 0 && ! $this->option('fix') ? self::FAILURE : self::SUCCESS;
    }
}
