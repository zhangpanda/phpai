<?php

declare(strict_types=1);

namespace Synapse\MCP;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Synapse\MCP\Transport\StdioTransport;
use Synapse\MCP\Transport\TransportInterface;

final class McpServer
{
    /** @var array<string, array{instance: object, method: string, description: string, parameters: array}> */
    private array $tools = [];
    /** @var array<string, array{instance: object, uri: string, description: string, mimeType: string}> */
    private array $resources = [];

    private function __construct(
        private readonly string $name,
        private readonly string $version,
    ) {}

    public static function create(string $name, string $version = '1.0.0'): self
    {
        return new self($name, $version);
    }

    public function addTool(object $tool): self
    {
        $ref = new ReflectionClass($tool);
        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attrs = $method->getAttributes(McpTool::class);
            if ($attrs === []) {
                continue;
            }
            $attr = $attrs[0]->newInstance();
            $name = $attr->name ?? $method->getName();
            $this->tools[$name] = [
                'instance' => $tool,
                'method' => $method->getName(),
                'description' => $attr->description,
                'parameters' => $this->extractParameters($method),
            ];
        }
        return $this;
    }

    public function addResource(object $resource): self
    {
        $ref = new ReflectionClass($resource);
        $attrs = $ref->getAttributes(McpResource::class);
        if ($attrs === []) {
            return $this;
        }
        $attr = $attrs[0]->newInstance();
        $this->resources[$attr->uri] = [
            'instance' => $resource,
            'uri' => $attr->uri,
            'description' => $attr->description,
            'mimeType' => $attr->mimeType,
        ];
        return $this;
    }

    public function serveStdio(): never
    {
        $transport = new StdioTransport();
        while (true) {
            $request = $transport->receive();
            if ($request === null) {
                break;
            }
            $response = $this->handle($request);
            if ($response !== null) {
                $transport->send($response);
            }
        }
        exit(0);
    }

    private function handle(array $request): ?array
    {
        $id = $request['id'] ?? null;
        $method = $request['method'] ?? '';
        $params = $request['params'] ?? [];

        $result = match ($method) {
            'initialize' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => ['tools' => new \stdClass(), 'resources' => new \stdClass()],
                'serverInfo' => ['name' => $this->name, 'version' => $this->version],
            ],
            'tools/list' => ['tools' => array_values(array_map(fn($name, $t) => [
                'name' => $name,
                'description' => $t['description'],
                'inputSchema' => $t['parameters'],
            ], array_keys($this->tools), $this->tools))],
            'tools/call' => $this->callTool($params['name'] ?? '', $params['arguments'] ?? []),
            'resources/list' => ['resources' => array_values(array_map(fn($r) => [
                'uri' => $r['uri'],
                'description' => $r['description'],
                'mimeType' => $r['mimeType'],
            ], $this->resources))],
            'resources/read' => $this->readResource($params['uri'] ?? ''),
            'notifications/initialized' => null,
            default => ['error' => ['code' => -32601, 'message' => "Method not found: {$method}"]],
        };

        if ($result === null) {
            return null;
        }

        if (isset($result['error'])) {
            return ['jsonrpc' => '2.0', 'id' => $id, 'error' => $result['error']];
        }

        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    private function callTool(string $name, array $arguments): array
    {
        if (!isset($this->tools[$name])) {
            return ['error' => ['code' => -32602, 'message' => "Tool not found: {$name}"]];
        }

        $tool = $this->tools[$name];
        $method = new ReflectionMethod($tool['instance'], $tool['method']);
        $args = [];
        foreach ($method->getParameters() as $param) {
            $args[] = $arguments[$param->getName()] ?? ($param->isDefaultValueAvailable() ? $param->getDefaultValue() : null);
        }

        try {
            $result = $method->invoke($tool['instance'], ...$args);
            $text = is_string($result) ? $result : (string) json_encode($result);
        } catch (\Throwable $e) {
            return ['content' => [['type' => 'text', 'text' => "Tool execution failed: " . $e->getMessage()]], 'isError' => true];
        }

        return ['content' => [['type' => 'text', 'text' => $text]]];
    }

    private function readResource(string $uri): array
    {
        if (!isset($this->resources[$uri])) {
            return ['error' => ['code' => -32602, 'message' => "Resource not found: {$uri}"]];
        }

        $resource = $this->resources[$uri];
        $instance = $resource['instance'];

        try {
            if (method_exists($instance, 'read')) {
                $content = $instance->read();
            } else {
                $content = json_encode($instance);
            }
        } catch (\Throwable $e) {
            return ['error' => ['code' => -32603, 'message' => "Resource read failed: " . $e->getMessage()]];
        }

        return ['contents' => [['uri' => $uri, 'mimeType' => $resource['mimeType'], 'text' => $content]]];
    }

    private function extractParameters(ReflectionMethod $method): array
    {
        $properties = [];
        $required = [];

        foreach ($method->getParameters() as $param) {
            $type = $param->getType();
            $schema = match ($type instanceof ReflectionNamedType ? $type->getName() : 'string') {
                'int' => ['type' => 'integer'],
                'float' => ['type' => 'number'],
                'bool' => ['type' => 'boolean'],
                'array' => ['type' => 'array'],
                default => ['type' => 'string'],
            };
            $properties[$param->getName()] = $schema;
            if (!$param->isDefaultValueAvailable()) {
                $required[] = $param->getName();
            }
        }

        return array_filter([
            'type' => 'object',
            'properties' => $properties,
            'required' => $required ?: null,
        ]);
    }
}
