<?php

namespace Tests\Feature;

use App\Models\PanelConnection;
use App\Models\Plan;
use App\Models\User;
use App\Services\ProvisioningService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_is_created_and_wallet_is_debited_once(): void
    {
        $reseller = User::query()->create(['name' => 'R', 'email' => 'r@example.com', 'password' => 'password12345', 'role' => 'reseller', 'is_active' => true, 'reseller_prefix' => 'res']);
        $panel = PanelConnection::query()->create(['name' => 'P', 'base_url' => 'https://1.1.1.1', 'admin_username' => 'admin', 'admin_password' => 'secret', 'verify_tls' => true, 'is_active' => true]);
        $plan = Plan::query()->create(['panel_connection_id' => $panel->id, 'name' => '10GB', 'data_limit_gb' => 10, 'duration_days' => 30, 'price_irr' => 500000, 'proxies' => ['vless' => []], 'inbounds' => ['vless' => ['VLESS']], 'is_active' => true]);
        app(WalletService::class)->credit($reseller->id, 1000000, 'initial-credit', 'deposit_approval', '1', 'شارژ');
        Http::fake(function (Request $request) {
            if ($request->url() === 'https://1.1.1.1/api/admin/token') return Http::response(['access_token' => 'token'], 200);
            if ($request->method() === 'GET') return Http::response(['detail' => 'User not found'], 404);
            return Http::response(['username' => 'res_client', 'status' => 'active', 'expire' => now()->addDays(30)->timestamp, 'data_limit' => 10737418240, 'subscription_url' => 'https://sub.example/s/test'], 200);
        });

        $requestId = (string) Str::ulid();
        $service = app(ProvisioningService::class)->create($reseller, $plan->load('panel'), $requestId, 'client', 'مشتری');
        $same = app(ProvisioningService::class)->create($reseller->fresh(), $plan->load('panel'), $requestId, 'client', 'مشتری');
        $this->assertSame($service->id, $same->id);
        $this->assertSame(500000, $reseller->fresh()->wallet_balance_irr);
        $this->assertSame('active', $service->fresh()->status);
    }
}
