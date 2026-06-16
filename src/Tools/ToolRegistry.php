<?php

declare(strict_types=1);

namespace Synapse\Tools;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionMethod;
use ReflectionNamedType;

final class ToolRegistry
{
    /** @var array<string, ToolDefinition> */
    private array $tools = [];
    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function register(object ...$objects): self
    {
        foreach ($objects as $object) {
            $this->extractTools($object);
        }
        return $this;
    }

    /**
     * @return list<array<string, mixed>> OpenAI tools format
     */
    public function getDefinitions(): array
    {
        return array_values(array_map(
            fn(ToolDefinition $t) => $t->toOpenAIFormat(),
            $this->tools
        ));
    }

    public function execute(string $name, array $arguments): string
    {
        if (!isset($this->tools[$name])) {
            return json_encode(['error' => "Tool '{$name}' not found"]);
        }

        $tool = $this->tools[$name];

        try {
            $method = new ReflectionMethod($tool->instance, $tool->method);
        } catch (\Throwable $e) {
            return json_encode(['error' => "Tool reflection failed: " . $e->getMessage()]);
        }

        $args = [];
        foreach ($method->getParameters() as $param) {
            if (array_key_exists($param->getName(), $arguments)) {
                $args[] = $arguments[$param->getName()];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } elseif ($param->allowsNull()) {
                $args[] = null;
            } else {
                return json_encode(['error' => "Missing required parameter '{$param->getName()}' for tool '{$name}'"]);
            }
        }

        try {
            $result = $method->invoke($tool->instance, ...$args);
            return is_string($result) ? $result : (string) json_encode($result);
        } catch (\Throwable $e) {
            return json_encode(['error' => "Tool execution failed: " . $e->getMessage()]);
        }
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function isEmpty(): bool
    {
        return $this->tools === [];
    }

    private function extractTools(object $object): void
    {
        $reflection = new \ReflectionClass($object);
        $found = false;

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attrs = $method->getAttributes(AsTool::class);
            if ($attrs === []) {
                continue;
            }

            $found = true;
            $attr = $attrs[0]->newInstance();
            $name = $attr->name ?? $method->getName();

            if (isset($this->tools[$name])) {
                $existing = $this->tools[$name];
                throw new \RuntimeException("Duplicate tool name '{$name}': already registered by " . $existing->instance::class . "::{$existing->method}()");
            }

            $this->tools[$name] = new ToolDefinition(
                name: $name,
                description: $attr->description,
                parameters: $this->extractParameters($method),
                instance: $object,
                method: $method->getName(),
            );
        }

        if (!$found) {
            $this->logger->warning('Object of class {class} registered but has no #[AsTool] methods.', [
                'class' => $reflection->getName(),
            ]);
        }
    }

    private function extractParameters(ReflectionMethod $method): array
    {
        $properties = [];
        $required = [];

        foreach ($method->getParameters() as $param) {
            $paramAttr = $param->getAttributes(Param::class)[0] ?? null;
            $meta = $paramAttr?->newInstance();

            $schema = $this->parameterSchema($param);
            if ($meta?->description) {
                $schema['description'] = $meta->description;
            }
            if ($meta?->enum) {
                $schema['enum'] = $meta->enum;
            }

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

    private function parameterSchema(\ReflectionParameter $param): array
    {
        $type = $param->getType();
        if (!$type instanceof ReflectionNamedType) {
            return ['type' => 'string'];
        }

        return match ($type->getName()) {
            'int' => ['type' => 'integer'],
            'float' => ['type' => 'number'],
            'bool' => ['type' => 'boolean'],
            'array' => ['type' => 'array'],
            default => ['type' => 'string'],
        };
    }
}
