<?php

declare(strict_types=1);

namespace Synapse\RAG;

interface SplitterInterface
{
    /** @return list<Chunk> */
    public function split(Document $document): array;
}
