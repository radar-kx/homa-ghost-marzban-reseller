<?php

namespace Tests\Feature;

use App\Models\DepositRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DepositApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_is_idempotent(): void
    {
        $admin = User::query()->create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'password12345', 'role' => 'admin', 'is_active' => true]);
        $reseller = User::query()->create(['name' => 'R', 'email' => 'r@example.com', 'password' => 'password12345', 'role' => 'reseller', 'is_active' => true, 'reseller_prefix' => 'res']);
        $deposit = DepositRequest::query()->create(['public_id' => (string) Str::ulid(), 'reseller_id' => $reseller->id, 'amount_irr' => 2000000, 'tracking_code' => '123', 'status' => 'pending']);

        $this->actingAs($admin)->post("/admin/deposits/{$deposit->id}/approve")->assertRedirect();
        $this->actingAs($admin)->post("/admin/deposits/{$deposit->id}/approve")->assertRedirect();
        $this->assertSame(2000000, $reseller->fresh()->wallet_balance_irr);
        $this->assertDatabaseCount('ledger_transactions', 1);
    }
}
