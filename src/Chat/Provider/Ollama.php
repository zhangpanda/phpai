<?php

declare(strict_types=1);

namespace Synapse\Chat\Provider;

use GuzzleHttp\ClientInterface;
use Synapse\Chat\ChatInterface;
use Synapse\Chat\Response;

use Synapse\Chat\StreamableInterface;

/**
 * Ollama provider — uses OpenAI-compatible API format.
 * Default connects to local Ollama at http://localhost:11434
 */
final class Ollama implements ChatInterface, StreamableInterface
{
    private readonly OpenAI $openai;

    public function __construct(
        private readonly string $model = 'llama3',
        private readonly string $baseUrl = 'http://localhost:11434/v1',
        ?ClientInterface $client = null,
    ) {
        $this->openai = new OpenAI(
            apiKey: 'ollama', // Ollama doesn't need a real key
            model: $this->model,
            baseUrl: $this->baseUrl,
            client: $client,
        );
    }

    public function send(array $messages, array $options = []): Response
    {
        return $this->openai->send($messages, $options);
    }

    /** @return \Generator<int, string> */
    public function streamRaw(array $messages, array $options = []): \Generator
    {
        return $this->openai->streamRaw($messages, $options);
    }
}
