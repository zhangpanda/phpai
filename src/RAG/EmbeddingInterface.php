<?php

declare(strict_types=1);

namespace Synapse\RAG;

interface EmbeddingInterface
{
    /** @return list<float> */
    public function embed(string $text): array;

    /** @return list<list<float>> */
    public function embedBatch(array $texts): array;
}
