<?php

namespace Tests\Unit;

use App\Services\PanelUrlGuard;
use Tests\TestCase;

class PanelUrlGuardTest extends TestCase
{
    public function test_http_and_private_targets_are_rejected(): void
    {
        config(['services.marzban.allow_private_ips' => false]);
        $guard = app(PanelUrlGuard::class);
        foreach (['http://1.1.1.1', 'https://127.0.0.1', 'https://10.10.10.10'] as $url) {
            try { $guard->normalize($url); $this->fail("$url should be rejected"); }
            catch (\InvalidArgumentException) { $this->assertTrue(true); }
        }
        $this->assertSame('https://1.1.1.1:8443', $guard->normalize('https://1.1.1.1:8443/'));
    }
}
