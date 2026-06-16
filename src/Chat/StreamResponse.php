<?php

declare(strict_types=1);

namespace Synapse\Chat;

final class StreamResponse implements \IteratorAggregate
{
    private \Generator $generator;
    private string $fullContent = '';
    private bool $consumed = false;
    private bool $iterating = false;

    public function __construct(\Generator $generator)
    {
        $this->generator = $generator;
    }

    /** @return \Generator<int, string> */
    public function getIterator(): \Generator
    {
        if ($this->consumed || $this->iterating) {
            throw new \LogicException('StreamResponse can only be iterated once.');
        }

        $this->iterating = true;
        foreach ($this->generator as $chunk) {
            $this->fullContent .= $chunk;
            yield $chunk;
        }
        $this->consumed = true;
        $this->iterating = false;
    }

    public function getFullContent(): string
    {
        if (!$this->consumed) {
            if ($this->iterating) {
                throw new \LogicException('Cannot get full content while iteration is in progress.');
            }
            foreach ($this->generator as $chunk) {
                $this->fullContent .= $chunk;
            }
            $this->consumed = true;
        }
        return $this->fullContent;
    }
}
