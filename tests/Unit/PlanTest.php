<?php

namespace Tests\Unit;

use App\Models\Plan;
use PHPUnit\Framework\TestCase;

class PlanTest extends TestCase
{
    public function test_gigabytes_are_converted_to_bytes_without_float_money(): void
    {
        $plan = new Plan(['data_limit_gb' => '12.50', 'price_irr' => 1250000]);
        $this->assertSame(13421772800, $plan->dataLimitBytes());
        $this->assertSame(1250000, $plan->price_irr);
    }
}
