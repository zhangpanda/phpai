<?php

declare(strict_types=1);

namespace Synapse\RAG;

final class RecursiveCharacterSplitter implements SplitterInterface
{
    public function __construct(
        private readonly int $chunkSize = 1000,
        private readonly int $overlap = 200,
    ) {}

    public function split(Document $document): array
    {
        if ($this->chunkSize <= 0) {
            return [];
        }

        $text = $document->content;
        $chunks = [];
        $start = 0;
        $len = strlen($text);
        $step = max(1, $this->chunkSize - $this->overlap);

        while ($start < $len) {
            $end = min($start + $this->chunkSize, $len);
            $chunk = substr($text, $start, $end - $start);

            $chunks[] = new Chunk(
                content: $chunk,
                metadata: array_merge($document->metadata, ['chunk_index' => count($chunks)]),
                id: md5($chunk),
            );

            $start += $step;
        }

        return $chunks;
    }
}
