<?php

declare(strict_types=1);

namespace Synapse\Agent\Memory;

use Synapse\Chat\Message;

interface MemoryInterface
{
    /** @return list<Message> */
    public function load(): array;

    /** @param list<Message> $messages */
    public function save(array $messages): void;

    public function clear(): void;
}
