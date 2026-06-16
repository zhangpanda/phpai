<?php

declare(strict_types=1);

namespace Synapse\Chat\Provider;

use GuzzleHttp\ClientInterface;
use Synapse\Chat\ChatInterface;
use Synapse\Chat\Response;

use Synapse\Chat\StreamableInterface;

final class DeepSeek implements ChatInterface, StreamableInterface
{
    private readonly OpenAI $openai;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'deepseek-chat',
        ?ClientInterface $client = null,
    ) {
        $this->openai = new OpenAI(
            apiKey: $this->apiKey,
            model: $this->model,
            baseUrl: 'https://api.deepseek.com/v1',
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
