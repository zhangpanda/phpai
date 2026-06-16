<?php

declare(strict_types=1);

namespace PHPAI\Chat;

interface StreamableInterface
{
    /**
     * @param list<Message> $messages
     * @param array<string, mixed> $options
     * @return \Generator<int, string>
     */
    public function streamRaw(array $messages, array $options = []): \Generator;
}
