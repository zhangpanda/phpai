<?php

declare(strict_types=1);

namespace PHPAI\RAG;

interface LoaderInterface
{
    /** @return list<Document> */
    public function load(string $source): array;
}
