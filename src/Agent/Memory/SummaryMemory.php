<?php

declare(strict_types=1);

namespace Synapse\Agent\Memory;

use Synapse\Chat\ChatInterface;
use Synapse\Chat\Message;

/**
 * When message count exceeds threshold, older messages are summarized
 * by an LLM into a compact summary message.
 */
final class SummaryMemory implements MemoryInterface
{
    /** @var list<Message> */
    private array $messages = [];
    private ?string $summary = null;

    public function __construct(
        private readonly ChatInterface $summarizer,
        private readonly int $threshold = 20,
        private readonly int $keepRecent = 6,
    ) {}

    public function load(): array
    {
        try {
            $result = [];
            if ($this->summary !== null) {
                $result[] = Message::system("Previous conversation summary:\n{$this->summary}");
            }
            return array_merge($result, $this->messages);
        } catch (\Throwable) {
            return [];
        }
    }

    public function save(array $messages): void
    {
        $this->messages = array_merge($this->messages, $messages);

        if (count($this->messages) > $this->threshold) {
            $this->compress();
        }
    }

    public function clear(): void
    {
        $this->messages = [];
        $this->summary = null;
    }

    private function compress(): void
    {
        $toSummarize = array_slice($this->messages, 0, -$this->keepRecent);
        if ($toSummarize === []) {
            return;
        }
        $kept = array_slice($this->messages, -$this->keepRecent);

        $text = '';
        foreach ($toSummarize as $msg) {
            $text .= "[{$msg->role->value}]: {$msg->content}\n";
        }

        $existing = $this->summary ? "Existing summary:\n{$this->summary}\n\nNew messages:\n" : '';

        try {
            $response = $this->summarizer->send([
                Message::system('Summarize the following conversation concisely, preserving key facts and context. Respond with the summary only.'),
                Message::user($existing . $text),
            ]);
            $this->summary = $response->content;
            $this->messages = $kept;
        } catch (\Throwable) {
            // On failure, restore messages — don't compress
        }
    }
}
