<?php

declare(strict_types=1);

namespace Synapse\Prompt;

final class Template
{
    private array $variables = [];

    private function __construct(private readonly string $template) {}

    public static function from(string $template): self
    {
        return new self($template);
    }

    public static function load(string $path): self
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Cannot read file: {$path}");
        }
        return new self($content);
    }

    public function with(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->variables[$key] = $value;
        return $clone;
    }

    public function withIf(bool $condition, string $key, mixed $value): self
    {
        if (!$condition) {
            return $this;
        }
        return $this->with($key, $value);
    }

    public function render(): string
    {
        $result = $this->template;

        // Handle conditionals: {{#if key}}content{{/if}} — process innermost first (loop for nesting)
        $maxDepth = 10;
        while ($maxDepth-- > 0 && str_contains($result, '{{#if')) {
            $prev = $result;
            $result = (string) preg_replace_callback(
                '/\{\{#if\s+(\w+)\}\}((?:(?!\{\{#if\b)(?!\{\{\/if\}\}).)*)?\{\{\/if\}\}/s',
                function (array $matches) {
                    $key = $matches[1];
                    return isset($this->variables[$key]) && $this->variables[$key] ? ($matches[2] ?? '') : '';
                },
                $result,
            );
            if ($result === $prev) {
                break;
            }
        }

        // Handle variables: {{key}}
        foreach ($this->variables as $key => $value) {
            $result = str_replace("{{" . $key . "}}", (string) $value, $result);
        }

        return $result;
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
