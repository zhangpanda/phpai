<?php

declare(strict_types=1);

namespace Synapse\RAG;

interface VectorStoreInterface
{
    /** @param list<Chunk> $chunks */
    public function upsert(array $chunks): void;

    /** @return list<Chunk> */
    public function search(array $embedding, int $topK = 5): array;
}
