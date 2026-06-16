<?php

declare(strict_types=1);

namespace PHPAI\RAG;

interface EmbeddingInterface
{
    /** @return list<float> */
    public function embed(string $text): array;

    /** @return list<list<float>> */
    public function embedBatch(array $texts): array;
}
