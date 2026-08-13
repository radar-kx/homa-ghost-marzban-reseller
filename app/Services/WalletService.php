<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletJournal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class WalletService
{
    public function credit(int $resellerId, int $amount, string $key, string $sourceType, ?string $sourceId, string $description, array $metadata = []): WalletJournal
    {
        return $this->record($resellerId, $amount, 'credit', $key, $sourceType, $sourceId, $description, $metadata);
    }

    public function debit(int $resellerId, int $amount, string $key, string $sourceType, ?string $sourceId, string $description, array $metadata = []): WalletJournal
    {
        return $this->record($resellerId, $amount, 'debit', $key, $sourceType, $sourceId, $description, $metadata);
    }

    private function record(int $resellerId, int $amount, string $direction, string $key, string $sourceType, ?string $sourceId, string $description, array $metadata): WalletJournal
    {
        if ($amount <= 0) throw new RuntimeException('مبلغ تراکنش باید بیشتر از صفر باشد.');

        return DB::transaction(function () use ($resellerId, $amount, $direction, $key, $sourceType, $sourceId, $description, $metadata) {
            $existing = WalletJournal::query()->where('idempotency_key', $key)->first();
            if ($existing) return $existing;

            $reseller = User::query()->whereKey($resellerId)->lockForUpdate()->firstOrFail();
            $balance = (int) $reseller->wallet_balance_irr;
            if ($direction === 'debit' && $balance < $amount) throw new RuntimeException('موجودی کیف پول کافی نیست.');

            $newBalance = $direction === 'credit' ? $balance + $amount : $balance - $amount;
            $reseller->forceFill(['wallet_balance_irr' => $newBalance])->save();

            DB::table('ledger_accounts')->insertOrIgnore([
                'owner_type' => 'reseller', 'owner_id' => $resellerId, 'code' => 'wallet_liability', 'currency' => 'IRR',
                'type' => 'liability', 'updated_at' => now(), 'created_at' => now(),
            ]);
            $walletAccountId = (int) DB::table('ledger_accounts')->where(['owner_type' => 'reseller', 'owner_id' => $resellerId, 'code' => 'wallet_liability', 'currency' => 'IRR'])->value('id');
            $contraCode = $direction === 'credit' && $sourceType === 'operation_refund' ? 'service_revenue' : ($direction === 'credit' ? 'cash_clearing' : 'service_revenue');
            $contraType = $contraCode === 'cash_clearing' ? 'asset' : 'revenue';
            DB::table('ledger_accounts')->insertOrIgnore([
                'owner_type' => 'platform', 'owner_id' => 0, 'code' => $contraCode, 'currency' => 'IRR',
                'type' => $contraType, 'updated_at' => now(), 'created_at' => now(),
            ]);
            $contraId = (int) DB::table('ledger_accounts')->where(['owner_type' => 'platform', 'owner_id' => 0, 'code' => $contraCode, 'currency' => 'IRR'])->value('id');

            $transactionId = DB::table('ledger_transactions')->insertGetId([
                'public_id' => (string) Str::ulid(), 'idempotency_key' => $key, 'source_type' => $sourceType,
                'source_id' => $sourceId, 'description' => $description, 'created_at' => now(),
            ]);
            $entries = $direction === 'credit'
                ? [[$contraId, 'debit'], [$walletAccountId, 'credit']]
                : [[$walletAccountId, 'debit'], [$contraId, 'credit']];
            foreach ($entries as [$accountId, $side]) DB::table('ledger_entries')->insert([
                'ledger_transaction_id' => $transactionId, 'ledger_account_id' => $accountId, 'side' => $side,
                'amount_irr' => $amount, 'created_at' => now(),
            ]);

            return WalletJournal::query()->create([
                'reseller_id' => $resellerId, 'idempotency_key' => $key, 'direction' => $direction,
                'amount_irr' => $amount, 'balance_after_irr' => $newBalance, 'source_type' => $sourceType,
                'source_id' => $sourceId, 'description' => $description, 'metadata' => $metadata, 'created_at' => now(),
            ]);
        }, 3);
    }
}
