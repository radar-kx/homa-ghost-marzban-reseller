<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseller_cannot_open_admin_routes(): void
    {
        $user = User::query()->create(['name' => 'R', 'email' => 'r@example.com', 'password' => 'password12345', 'role' => 'reseller', 'is_active' => true, 'reseller_prefix' => 'res']);
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::query()->create(['name' => 'R', 'email' => 'off@example.com', 'password' => 'password12345', 'role' => 'reseller', 'is_active' => false, 'reseller_prefix' => 'off']);
        $this->post('/login', ['email' => 'off@example.com', 'password' => 'password12345'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
