<?php

declare(strict_types=1);

namespace Synapse\Chat\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Synapse\Chat\ChatException;
use Synapse\Chat\ChatInterface;
use Synapse\Chat\Message;
use Synapse\Chat\Response;
use Synapse\Chat\RetryHandler;
use Synapse\Chat\Role;
use Synapse\Chat\ToolCall;
use Synapse\Chat\Usage;

final class OpenAI implements ChatInterface, \Synapse\Chat\StreamableInterface
{
    private ClientInterface $client;
    private RetryHandler $retry;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gpt-4o',
        private readonly string $baseUrl = 'https://api.openai.com/v1',
        ?ClientInterface $client = null,
        ?RetryHandler $retry = null,
    ) {
        $this->client = $client ?? new Client();
        $this->retry = $retry ?? new RetryHandler();
    }

    public function send(array $messages, array $options = []): Response
    {
        return $this->retry->execute(function () use ($messages, $options) {
            return $this->doSend($messages, $options);
        });
    }

    private function doSend(array $messages, array $options): Response
    {
        $body = array_filter([
            'model' => $options['model'] ?? $this->model,
            'messages' => array_map($this->formatMessage(...), $messages),
            'tools' => $options['tools'] ?? null,
            'response_format' => $options['response_format'] ?? null,
            'temperature' => $options['temperature'] ?? null,
            'max_tokens' => $options['max_tokens'] ?? null,
        ]);

        try {
            $httpResponse = $this->client->request('POST', $this->baseUrl . '/chat/completions', [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            throw ChatException::fromGuzzle($e, 'OpenAI');
        }

        $raw = $httpResponse->getBody()->getContents();
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            throw new ChatException("OpenAI API returned invalid JSON: " . substr($raw, 0, 200), provider: 'OpenAI');
        }

        if (isset($data['error'])) {
            $error = is_array($data['error']) ? ($data['error']['message'] ?? json_encode($data['error'])) : (string) $data['error'];
            throw new ChatException("OpenAI API error: {$error}", provider: 'OpenAI');
        }

        return $this->parseResponse($data);
    }

    /** @return \Generator<int, string> */
    public function streamRaw(array $messages, array $options = []): \Generator
    {
        $body = array_filter([
            'model' => $options['model'] ?? $this->model,
            'messages' => array_map($this->formatMessage(...), $messages),
            'stream' => true,
            'tools' => $options['tools'] ?? null,
            'temperature' => $options['temperature'] ?? null,
            'max_tokens' => $options['max_tokens'] ?? null,
        ]);

        try {
            $httpResponse = $this->client->request('POST', $this->baseUrl . '/chat/completions', [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
                'stream' => true,
            ]);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            throw ChatException::fromGuzzle($e, 'OpenAI');
        }

        $stream = $httpResponse->getBody();
        $buffer = '';

        while (!$stream->eof()) {
            try {
                $chunk = $stream->read(1024);
            } catch (\RuntimeException $e) {
                throw new ChatException("OpenAI stream connection dropped: {$e->getMessage()}", provider: 'OpenAI', previous: $e);
            }

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

                if ($json === '[DONE]') {
                    return;
                }

                $data = json_decode($json, true);

                if (!is_array($data)) {
                    continue;
                }

                $delta = $data['choices'][0]['delta']['content'] ?? '';

                if ($delta !== '') {
                    yield $delta;
                }
            }
        }
    }

    private function formatMessage(Message $message): array
    {
        $formatted = [
            'role' => $message->role->value,
        ];

        // Content: use null for assistant messages with tool_calls and no text
        if ($message->content !== '' && $message->content !== null) {
            $formatted['content'] = $message->content;
        } else {
            $formatted['content'] = $message->role === Role::Assistant && !empty($message->toolCalls) ? null : $message->content;
        }

        if ($message->toolCalls !== null && $message->toolCalls !== []) {
            $formatted['tool_calls'] = array_map(fn(ToolCall $tc) => [
                'id' => $tc->id,
                'type' => 'function',
                'function' => [
                    'name' => $tc->name,
                    'arguments' => json_encode($tc->arguments) ?: '{}',
                ],
            ], $message->toolCalls);
        }

        if ($message->toolCallId !== null) {
            $formatted['tool_call_id'] = $message->toolCallId;
        }

        return $formatted;
    }

    private function parseResponse(array $data): Response
    {
        $choices = $data['choices'] ?? [];

        if (!is_array($choices) || $choices === []) {
            $error = $data['error']['message'] ?? $data['message'] ?? json_encode($data);
            throw new ChatException("OpenAI API returned no choices: {$error}", provider: 'OpenAI');
        }

        $choice = $choices[0];

        if (!is_array($choice)) {
            throw new ChatException("OpenAI API returned invalid choice format", provider: 'OpenAI');
        }

        $assistantMessage = $choice['message'] ?? [];

        if (!is_array($assistantMessage)) {
            throw new ChatException("OpenAI API returned invalid message format", provider: 'OpenAI');
        }

        $toolCalls = [];
        $rawToolCalls = $assistantMessage['tool_calls'] ?? [];

        if (is_array($rawToolCalls)) {
            foreach ($rawToolCalls as $tc) {
                if (!is_array($tc)) {
                    continue;
                }

                $fn = $tc['function'] ?? [];
                $args = is_string($fn['arguments'] ?? null) ? (json_decode($fn['arguments'], true) ?? []) : ($fn['arguments'] ?? []);

                $toolCalls[] = new ToolCall(
                    id: $tc['id'] ?? ('call_' . bin2hex(random_bytes(12))),
                    name: $fn['name'] ?? 'unknown',
                    arguments: is_array($args) ? $args : [],
                );
            }
        }

        // Usage may be absent (Ollama, local models)
        $usage = $data['usage'] ?? [];
        if (!is_array($usage)) {
            $usage = [];
        }

        return new Response(
            content: $assistantMessage['content'] ?? '',
            toolCalls: $toolCalls,
            usage: new Usage(
                promptTokens: (int) ($usage['prompt_tokens'] ?? 0),
                completionTokens: (int) ($usage['completion_tokens'] ?? 0),
                totalTokens: (int) ($usage['total_tokens'] ?? ($usage['prompt_tokens'] ?? 0) + ($usage['completion_tokens'] ?? 0)),
            ),
            model: $data['model'] ?? $this->model ?? 'unknown',
            finishReason: $choice['finish_reason'] ?? 'stop',
        );
    }
}
