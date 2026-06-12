<?php

declare(strict_types=1);

namespace Synapse\RAG;

final class InMemoryStore implements VectorStoreInterface
{
    /** @var list<Chunk> */
    private array $chunks = [];

    public function upsert(array $chunks): void
    {
        foreach ($chunks as $chunk) {
            $this->chunks[] = $chunk;
        }
    }

    public function search(array $embedding, int $topK = 5): array
    {
        if ($this->chunks === [] || $embedding === []) {
            return [];
        }

        $scored = [];
        foreach ($this->chunks as $chunk) {
            if ($chunk->embedding === []) {
                continue;
            }
            $scored[] = ['chunk' => $chunk, 'score' => $this->cosineSimilarity($embedding, $chunk->embedding)];
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_map(fn($s) => $s['chunk'], array_slice($scored, 0, $topK));
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $len = min(count($a), count($b));

        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $denom = sqrt($normA) * sqrt($normB);
        return $denom > 0.0 ? $dot / $denom : 0.0;
    }

    public function count(): int
    {
        return count($this->chunks);
    }
}
