<?php

declare(strict_types=1);

namespace Synapse\Chat\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Synapse\Chat\ChatException;
use Synapse\Chat\ChatInterface;
use Synapse\Chat\Message;
use Synapse\Chat\Response;
use Synapse\Chat\Role;
use Synapse\Chat\ToolCall;
use Synapse\Chat\Usage;

final class Anthropic implements ChatInterface, \Synapse\Chat\StreamableInterface
{
    private ClientInterface $client;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-sonnet-4-20250514',
        private readonly int $maxTokens = 4096,
        private readonly string $baseUrl = 'https://api.anthropic.com/v1',
        ?ClientInterface $client = null,
    ) {
        $this->client = $client ?? new Client();
    }

    public function send(array $messages, array $options = []): Response
    {
        $systemParts = [];
        $filtered = [];

        foreach ($messages as $message) {
            if ($message->role === Role::System) {
                $systemParts[] = $message->content;
            } else {
                $filtered[] = $this->formatMessage($message);
            }
        }

        $system = $systemParts !== [] ? implode("\n\n", $systemParts) : null;

        $body = array_filter([
            'model' => $options['model'] ?? $this->model,
            'max_tokens' => $options['max_tokens'] ?? $this->maxTokens,
            'system' => $system,
            'messages' => $filtered,
            'tools' => isset($options['tools']) ? $this->formatTools($options['tools']) : null,
            'temperature' => $options['temperature'] ?? null,
        ]);

        try {
            $httpResponse = $this->client->request('POST', $this->baseUrl . '/messages', [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            throw ChatException::fromGuzzle($e, 'Anthropic');
        }

        $raw = $httpResponse->getBody()->getContents();
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            throw new ChatException("Anthropic API returned invalid JSON: " . substr($raw, 0, 200), provider: 'Anthropic');
        }

        // Handle error response type
        if (($data['type'] ?? '') === 'error' || isset($data['error'])) {
            $error = is_array($data['error'] ?? null) ? ($data['error']['message'] ?? json_encode($data['error'])) : ($data['error'] ?? 'Unknown error');
            throw new ChatException("Anthropic API error: {$error}", provider: 'Anthropic');
        }

        return $this->parseResponse($data);
    }

    /**
     * Convert OpenAI tools format to Anthropic format.
     * OpenAI:     [{type: "function", function: {name, description, parameters}}]
     * Anthropic:  [{name, description, input_schema}]
     */
    private function formatTools(array $tools): array
    {
        return array_map(function (array $tool) {
            if (isset($tool['function'])) {
                $fn = $tool['function'];
                return [
                    'name' => $fn['name'] ?? 'unknown',
                    'description' => $fn['description'] ?? '',
                    'input_schema' => $fn['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()],
                ];
            }
            // Already in Anthropic format or passthrough
            return $tool;
        }, $tools);
    }

    private function formatMessage(Message $message): array
    {
        // Tool results must be sent as user messages with tool_result content blocks
        if ($message->role === Role::Tool) {
            return [
                'role' => 'user',
                'content' => [[
                    'type' => 'tool_result',
                    'tool_use_id' => $message->toolCallId ?? '',
                    'content' => $message->content ?? '',
                ]],
            ];
        }

        $content = [];

        if ($message->content !== null && $message->content !== '') {
            $content[] = ['type' => 'text', 'text' => $message->content];
        }

        if ($message->toolCalls !== null && $message->toolCalls !== []) {
            foreach ($message->toolCalls as $tc) {
                $content[] = [
                    'type' => 'tool_use',
                    'id' => $tc->id,
                    'name' => $tc->name,
                    'input' => $tc->arguments ?: new \stdClass(),
                ];
            }
        }

        return [
            'role' => $message->role === Role::Assistant ? 'assistant' : 'user',
            'content' => $content !== [] ? $content : $message->content,
        ];
    }

    /** @return \Generator<int, string> */
    public function streamRaw(array $messages, array $options = []): \Generator
    {
        $systemParts = [];
        $filtered = [];

        foreach ($messages as $message) {
            if ($message->role === Role::System) {
                $systemParts[] = $message->content;
            } else {
                $filtered[] = $this->formatMessage($message);
            }
        }

        $system = $systemParts !== [] ? implode("\n\n", $systemParts) : null;

        $body = array_filter([
            'model' => $options['model'] ?? $this->model,
            'max_tokens' => $options['max_tokens'] ?? $this->maxTokens,
            'system' => $system,
            'messages' => $filtered,
            'stream' => true,
            'temperature' => $options['temperature'] ?? null,
        ]);

        try {
            $httpResponse = $this->client->request('POST', $this->baseUrl . '/messages', [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
                'stream' => true,
            ]);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            throw ChatException::fromGuzzle($e, 'Anthropic');
        }

        $stream = $httpResponse->getBody();
        $buffer = '';

        while (!$stream->eof()) {
            $chunk = $stream->read(1024);
            if ($chunk === '') {
                continue;
            }

            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                $line = trim($line);

                if ($line === '' || !str_starts_with($line, 'data: ')) {
                    continue;
                }

                $json = substr($line, 6);
                $data = json_decode($json, true);

                if (!is_array($data)) {
                    continue;
                }

                $type = $data['type'] ?? '';

                if ($type === 'content_block_delta') {
                    $text = $data['delta']['text'] ?? '';
                    if ($text !== '') {
                        yield $text;
                    }
                } elseif ($type === 'message_stop') {
                    return;
                }
            }
        }
    }

    private function parseResponse(array $data): Response
    {
        $contentBlocks = $data['content'] ?? null;

        if (!is_array($contentBlocks)) {
            $error = $data['message'] ?? json_encode($data);
            throw new ChatException("Anthropic API returned unexpected response (no content array): {$error}", provider: 'Anthropic');
        }

        $content = '';
        $toolCalls = [];

        foreach ($contentBlocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? '';

            if ($type === 'text') {
                $content .= $block['text'] ?? '';
            } elseif ($type === 'tool_use') {
                $input = $block['input'] ?? [];
                $toolCalls[] = new ToolCall(
                    id: $block['id'] ?? ('toolu_' . bin2hex(random_bytes(12))),
                    name: $block['name'] ?? 'unknown',
                    arguments: is_array($input) ? $input : [],
                );
            }
        }

        // Usage may be absent
        $usage = $data['usage'] ?? [];
        if (!is_array($usage)) {
            $usage = [];
        }

        $inputTokens = (int) ($usage['input_tokens'] ?? 0);
        $outputTokens = (int) ($usage['output_tokens'] ?? 0);

        return new Response(
            content: $content,
            toolCalls: $toolCalls,
            usage: new Usage(
                promptTokens: $inputTokens,
                completionTokens: $outputTokens,
                totalTokens: $inputTokens + $outputTokens,
            ),
            model: $data['model'] ?? $this->model ?? 'unknown',
            finishReason: $data['stop_reason'] ?? 'end_turn',
        );
    }
}
