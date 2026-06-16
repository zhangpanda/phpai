<?php

declare(strict_types=1);

namespace PHPAI\Chat;

interface ChatInterface
{
    /**
     * @param list<Message> $messages
     * @param array<string, mixed> $options
     */
    public function send(array $messages, array $options = []): Response;
}
