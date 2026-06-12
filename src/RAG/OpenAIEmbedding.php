<?php

declare(strict_types=1);

namespace Synapse\RAG;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

final class OpenAIEmbedding implements EmbeddingInterface
{
    private ClientInterface $client;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'text-embedding-3-small',
        private readonly string $baseUrl = 'https://api.openai.com/v1',
        ?ClientInterface $client = null,
    ) {
        $this->client = $client ?? new Client();
    }

    public function embed(string $text): array
    {
        return $this->embedBatch([$text])[0];
    }

    public function embedBatch(array $texts): array
    {
        try {
            $response = $this->client->request('POST', $this->baseUrl . '/embeddings', [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'input' => $texts,
                ],
            ]);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            throw new \RuntimeException("Embedding API request failed: " . $e->getMessage(), 0, $e);
        }

        $data = json_decode($response->getBody()->getContents(), true);

        if (!is_array($data) || !isset($data['data'])) {
            throw new \RuntimeException('OpenAI Embedding API returned unexpected response');
        }

        return array_map(fn($item) => $item['embedding'] ?? [], $data['data']);
    }
}
