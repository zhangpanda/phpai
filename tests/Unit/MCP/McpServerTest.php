<?php

declare(strict_types=1);

namespace PHPAI\Tests\Unit\MCP;

use PHPUnit\Framework\TestCase;
use PHPAI\MCP\McpResource;
use PHPAI\MCP\McpServer;
use PHPAI\MCP\McpTool;

class FakeMcpTool
{
    #[McpTool(description: 'Add two numbers')]
    public function add(int $a, int $b): string
    {
        return (string) ($a + $b);
    }

    #[McpTool(description: 'Say hello')]
    public function greet(string $name): string
    {
        return "Hello, {$name}!";
    }
}

#[McpResource(uri: 'test://info', description: 'Test resource')]
class FakeMcpResource
{
    public function read(): string
    {
        return json_encode(['status' => 'ok']);
    }
}

final class McpServerTest extends TestCase
{
    private McpServer $server;

    protected function setUp(): void
    {
        $this->server = McpServer::create('test-server', '1.0.0')
            ->addTool(new FakeMcpTool())
            ->addResource(new FakeMcpResource());
    }

    public function testHandleInitialize(): void
    {
        $response = $this->handle('initialize', []);

        $this->assertSame('2024-11-05', $response['result']['protocolVersion']);
        $this->assertSame('test-server', $response['result']['serverInfo']['name']);
    }

    public function testHandleToolsList(): void
    {
        $response = $this->handle('tools/list', []);

        $tools = $response['result']['tools'];
        $this->assertCount(2, $tools);
        $names = array_column($tools, 'name');
        $this->assertContains('add', $names);
        $this->assertContains('greet', $names);
    }

    public function testHandleToolsCall(): void
    {
        $response = $this->handle('tools/call', ['name' => 'add', 'arguments' => ['a' => 3, 'b' => 4]]);

        $this->assertSame('7', $response['result']['content'][0]['text']);
    }

    public function testHandleToolsCallGreet(): void
    {
        $response = $this->handle('tools/call', ['name' => 'greet', 'arguments' => ['name' => 'World']]);

        $this->assertSame('Hello, World!', $response['result']['content'][0]['text']);
    }

    public function testHandleResourcesList(): void
    {
        $response = $this->handle('resources/list', []);

        $resources = $response['result']['resources'];
        $this->assertCount(1, $resources);
        $this->assertSame('test://info', $resources[0]['uri']);
    }

    public function testHandleResourcesRead(): void
    {
        $response = $this->handle('resources/read', ['uri' => 'test://info']);

        $this->assertSame('test://info', $response['result']['contents'][0]['uri']);
        $this->assertSame('{"status":"ok"}', $response['result']['contents'][0]['text']);
    }

    public function testHandleUnknownMethod(): void
    {
        $response = $this->handle('unknown/method', []);

        $this->assertArrayHasKey('error', $response);
        $this->assertSame(-32601, $response['error']['code']);
    }

    /** Use reflection to call private handle() */
    private function handle(string $method, array $params): array
    {
        $ref = new \ReflectionMethod($this->server, 'handle');
        $ref->setAccessible(true);
        return $ref->invoke($this->server, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params,
        ]);
    }
}
