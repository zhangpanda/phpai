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
    private bool $closed = false;

    public function __construct(string $command, array $args = [], private readonly int $timeoutSeconds = 30)
    {
        $cmd = implode(' ', array_map('escapeshellarg', [$command, ...$args]));
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'r'], 2 => ['pipe', 'w']];

        $this->process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($this->process)) {
            throw new \RuntimeException("Failed to start process: {$cmd}");
        }

        usleep(50000);
        $status = proc_get_status($this->process);
        if (!$status['running'] && $status['exitcode'] !== 0) {
            $stderr = isset($pipes[2]) ? stream_get_contents($pipes[2]) : '';
            proc_close($this->process);
            throw new \RuntimeException("Process exited immediately: {$cmd}" . ($stderr ? " ({$stderr})" : ''));
        }

        $this->stdin = $pipes[0];
        $this->stdout = $pipes[1];
        stream_set_timeout($this->stdout, $this->timeoutSeconds);
    }

    public function send(array $message): void
    {
        $data = json_encode($message) . "\n";
        $written = @fwrite($this->stdin, $data);
        if ($written === false || $written < strlen($data)) {
            throw new \RuntimeException('Failed to write to MCP process stdin (process may have crashed)');
        }
        fflush($this->stdin);
    }

    public function receive(): ?array
    {
        $line = fgets($this->stdout);
        if ($line === false) {
            $meta = stream_get_meta_data($this->stdout);
            if ($meta['timed_out']) {
                throw new \RuntimeException("MCP process read timeout ({$this->timeoutSeconds}s)");
            }
            return null;
        }
        return json_decode(trim($line), true);
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        @fclose($this->stdin);
        @fclose($this->stdout);
        proc_terminate($this->process);
        proc_close($this->process);
    }
}
