<?php

declare(strict_types=1);

namespace PHPAI\Agent\Memory;

use PHPAI\Chat\Message;

final class BufferMemory implements MemoryInterface
{
    /** @var list<Message> */
    private array $messages = [];

    public function __construct(private readonly int $maxMessages = 50)
    {
        if ($maxMessages < 1) {
            throw new \InvalidArgumentException('maxMessages must be at least 1');
        }
    }

    public function load(): array
    {
        return $this->messages;
    }

    public function save(array $messages): void
    {
        $this->messages = array_merge($this->messages, $messages);
        if (count($this->messages) > $this->maxMessages) {
            $this->messages = array_slice($this->messages, -$this->maxMessages);
        }
    }

    public function clear(): void
    {
        $this->messages = [];
    }
}
