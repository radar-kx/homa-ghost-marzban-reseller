<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WalletJournal;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_and_debit_are_idempotent(): void
    {
        $user = User::query()->create(['name' => 'R', 'email' => 'r@example.com', 'password' => 'password12345', 'role' => 'reseller', 'is_active' => true, 'reseller_prefix' => 'res']);
        $wallet = app(WalletService::class);
        $wallet->credit($user->id, 1000000, 'credit-one', 'test', '1', 'شارژ تست');
        $wallet->credit($user->id, 1000000, 'credit-one', 'test', '1', 'شارژ تست');
        $wallet->debit($user->id, 250000, 'debit-one', 'test', '2', 'خرید تست');
        $this->assertSame(750000, $user->fresh()->wallet_balance_irr);
        $this->assertSame(2, WalletJournal::query()->count());
        $this->assertDatabaseCount('ledger_transactions', 2);
        $this->assertDatabaseCount('ledger_entries', 4);
        $this->assertSame(
            (int) \Illuminate\Support\Facades\DB::table('ledger_entries')->where('side', 'debit')->sum('amount_irr'),
            (int) \Illuminate\Support\Facades\DB::table('ledger_entries')->where('side', 'credit')->sum('amount_irr')
        );
    }

    public function test_insufficient_balance_does_not_create_a_journal(): void
    {
        $user = User::query()->create(['name' => 'R', 'email' => 'r2@example.com', 'password' => 'password12345', 'role' => 'reseller', 'is_active' => true, 'reseller_prefix' => 'rs2']);
        $this->expectException(RuntimeException::class);
        try { app(WalletService::class)->debit($user->id, 1, 'debit-fail', 'test', null, 'خرید'); }
        finally { $this->assertDatabaseCount('wallet_journals', 0); }
    }
}
