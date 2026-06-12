<?php

declare(strict_types=1);

namespace Synapse\Tests\Unit\Tools;

use PHPUnit\Framework\TestCase;
use Synapse\Tools\AsTool;
use Synapse\Tools\Param;
use Synapse\Tools\ToolRegistry;

class FakeTool
{
    #[AsTool(description: 'Add two numbers')]
    public function add(
        #[Param(description: 'First number')] int $a,
        #[Param(description: 'Second number')] int $b,
    ): string {
        return (string) ($a + $b);
    }
}

final class ToolRegistryTest extends TestCase
{
    public function testRegistersAndExecutesTool(): void
    {
        $registry = new ToolRegistry();
        $registry->register(new FakeTool());

        $this->assertTrue($registry->has('add'));
        $this->assertSame('7', $registry->execute('add', ['a' => 3, 'b' => 4]));
    }

    public function testGeneratesOpenAIDefinitions(): void
    {
        $registry = new ToolRegistry();
        $registry->register(new FakeTool());

        $defs = $registry->getDefinitions();
        $this->assertCount(1, $defs);
        $this->assertSame('function', $defs[0]['type']);
        $this->assertSame('add', $defs[0]['function']['name']);
        $this->assertSame('Add two numbers', $defs[0]['function']['description']);
    }

    public function testReturnsErrorOnUnknownTool(): void
    {
        $registry = new ToolRegistry();

        $result = $registry->execute('nonexistent', []);
        $decoded = json_decode($result, true);
        $this->assertArrayHasKey('error', $decoded);
        $this->assertStringContainsString('nonexistent', $decoded['error']);
    }
}
