<?php

declare(strict_types=1);

namespace PHPAI\MCP\Transport;

final class StdioTransport implements TransportInterface
{
    public function send(array $message): void
    {
        fwrite(STDOUT, json_encode($message) . "\n");
    }

    public function receive(): ?array
    {
        $line = fgets(STDIN);
        if ($line === false) {
            return null;
        }
        return json_decode(trim($line), true);
    }

    public function close(): void
    {
        // No-op for stdio
    }
}
