<?php

declare(strict_types=1);

namespace Synapse\MCP;

use Synapse\MCP\Transport\ProcessTransport;
use Synapse\MCP\Transport\TransportInterface;

final class McpClient
{
    private int $nextId = 1;

    private function __construct(
        private readonly TransportInterface $transport,
    ) {}

    public static function connectStdio(string $command, array $args = []): self
    {
        return new self(new ProcessTransport($command, $args));
    }

    public function initialize(): void
    {
        $this->request('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => new \stdClass(),
            'clientInfo' => ['name' => 'synapse-mcp-client', 'version' => '1.0.0'],
        ]);
        $this->transport->send(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);
    }

    public function listTools(): array
    {
        return $this->request('tools/list')['tools'] ?? [];
    }

    public function callTool(string $name, array $args): string
    {
        $result = $this->request('tools/call', ['name' => $name, 'arguments' => $args]);
        return $result['content'][0]['text'] ?? '';
    }

    public function listResources(): array
    {
        return $this->request('resources/list')['resources'] ?? [];
    }

    public function readResource(string $uri): string
    {
        $result = $this->request('resources/read', ['uri' => $uri]);
        return $result['contents'][0]['text'] ?? '';
    }

    public function close(): void
    {
        $this->transport->close();
    }

    private function request(string $method, array $params = []): array
    {
        $id = $this->nextId++;
        $this->transport->send([
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => $method,
            'params' => $params ?: new \stdClass(),
        ]);

        $response = $this->transport->receive();
        if ($response === null) {
            throw new \RuntimeException('No response from server');
        }
        if (isset($response['error'])) {
            throw new \RuntimeException($response['error']['message'] ?? 'Unknown error');
        }

        return $response['result'] ?? [];
    }
}
