<?php

declare(strict_types=1);

namespace PHPAI\RAG;

final class RecursiveCharacterSplitter implements SplitterInterface
{
    public function __construct(
        private readonly int $chunkSize = 1000,
        private readonly int $overlap = 200,
    ) {
        if ($chunkSize < 1) {
            throw new \InvalidArgumentException('chunkSize must be at least 1');
        }
        if ($overlap < 0) {
            throw new \InvalidArgumentException('overlap must not be negative');
        }
        if ($overlap >= $chunkSize) {
            throw new \InvalidArgumentException("overlap ({$overlap}) must be less than chunkSize ({$chunkSize})");
        }
    }

    public function split(Document $document): array
    {
        $text = $document->content;
        if ($text === '') {
            return [];
        }
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
