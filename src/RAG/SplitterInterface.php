<?php

declare(strict_types=1);

namespace PHPAI\RAG;

interface SplitterInterface
{
    /** @return list<Chunk> */
    public function split(Document $document): array;
}
