<?php

declare(strict_types=1);

namespace PHPAI\Tools;

final readonly class ToolDefinition
{
    public function __construct(
        public string $name,
        public string $description,
        public array $parameters,
        public object $instance,
        public string $method,
    ) {}

    public function toOpenAIFormat(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->parameters,
            ],
        ];
    }
}
