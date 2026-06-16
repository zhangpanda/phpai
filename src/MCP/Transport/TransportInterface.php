<?php

declare(strict_types=1);

namespace PHPAI\MCP\Transport;

interface TransportInterface
{
    public function send(array $message): void;

    public function receive(): ?array;

    public function close(): void;
}
