<?php

declare(strict_types=1);

namespace Synapse\Tests\Unit\Observability;

use PHPUnit\Framework\TestCase;
use Synapse\Observability\CostCalculator;

final class CostCalculatorTest extends TestCase
{
    public function testCalculatesGpt4oCost(): void
    {
        $calc = new CostCalculator();

        // 1000 input + 500 output for gpt-4o: (1000*2.5 + 500*10) / 1_000_000
        $cost = $calc->calculate('gpt-4o', 1000, 500);
        $this->assertEqualsWithDelta(0.0075, $cost, 0.0001);
    }

    public function testReturnsZeroForUnknownModel(): void
    {
        $calc = new CostCalculator();
        $this->assertSame(0.0, $calc->calculate('unknown-model', 1000, 1000));
    }
}
