<?php

declare(strict_types=1);

namespace Synapse\MCP\Transport;

final class ProcessTransport implements TransportInterface
{
    /** @var resource */
    private mixed $process;
    /** @var resource */
    private mixed $stdin;
    /** @var resource */
    private mixed $stdout;

    public function __construct(string $command, array $args = [])
    {
        $cmd = implode(' ', array_map('escapeshellarg', [$command, ...$args]));
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'r'], 2 => ['pipe', 'w']];

        $this->process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($this->process)) {
            throw new \RuntimeException("Failed to start process: {$cmd}");
        }

        // Check if process exited immediately (command not found)
        usleep(50000); // 50ms
        $status = proc_get_status($this->process);
        if (!$status['running'] && $status['exitcode'] !== 0) {
            $stderr = isset($pipes[2]) ? stream_get_contents($pipes[2]) : '';
            proc_close($this->process);
            throw new \RuntimeException("Process exited immediately: {$cmd}" . ($stderr ? " ({$stderr})" : ''));
        }

        $this->stdin = $pipes[0];
        $this->stdout = $pipes[1];
    }

    public function send(array $message): void
    {
        fwrite($this->stdin, json_encode($message) . "\n");
        fflush($this->stdin);
    }

    public function receive(): ?array
    {
        $line = fgets($this->stdout);
        if ($line === false) {
            return null;
        }
        return json_decode(trim($line), true);
    }

    public function close(): void
    {
        fclose($this->stdin);
        fclose($this->stdout);
        proc_terminate($this->process);
        proc_close($this->process);
    }
}
