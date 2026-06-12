<?php

declare(strict_types=1);

namespace Synapse\Chat;

final class StreamResponse implements \IteratorAggregate
{
    private \Generator $generator;
    private string $fullContent = '';
    private bool $consumed = false;

    public function __construct(\Generator $generator)
    {
        $this->generator = $generator;
    }

    /** @return \Generator<int, string> */
    public function getIterator(): \Generator
    {
        foreach ($this->generator as $chunk) {
            $this->fullContent .= $chunk;
            yield $chunk;
        }
        $this->consumed = true;
    }

    public function getFullContent(): string
    {
        if (!$this->consumed) {
            foreach ($this->generator as $chunk) {
                $this->fullContent .= $chunk;
            }
            $this->consumed = true;
        }
        return $this->fullContent;
    }
}
